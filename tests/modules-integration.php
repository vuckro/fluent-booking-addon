<?php
$path = getenv('WAASKIT_WP_PATH');
if (!$path || !is_file($path . '/wp-load.php')) { throw new RuntimeException('Local WordPress required.'); }
require $path . '/wp-load.php';
if (!in_array(parse_url(home_url(), PHP_URL_HOST), ['localhost','127.0.0.1'], true)) { throw new RuntimeException('Local tests only.'); }
use WaasKit\FluentBooking\Domain\BookingProfile;
use WaasKit\FluentBooking\Infrastructure\CapacityStore;
use WaasKit\FluentBooking\Integrations\FluentBooking\ConfigurationStore;
use WaasKit\FluentBooking\Integrations\FluentBooking\BookingModules;
use FluentBooking\App\Models\Booking;
use FluentBooking\App\Models\CalendarSlot;
use FluentBooking\App\Services\BookingService;
function check($ok,$label) { if (!$ok) { throw new RuntimeException($label); } echo "PASS $label\n"; }
add_filter('pre_wp_mail', '__return_true');
add_filter('pre_http_request', static fn()=>new WP_Error('test_network_blocked','Network disabled in tests'), 999);
foreach (['scheduled','pending','cancelled','completed'] as $status) {
    remove_all_actions('fluent_booking/pre_after_booking_' . $status);
    remove_all_actions('fluent_booking/after_booking_' . $status);
}
$capacity = new CapacityStore(); $capacity->install();
$store = new ConfigurationStore();
$event = null;
foreach (CalendarSlot::all() as $candidate) { if ($candidate->isMultiGuestEvent()) { $event=$candidate; break; } }
if (!$event) { throw new RuntimeException('Group event fixture required.'); }
$wpdb->query('START TRANSACTION');
try {
    delete_option(ConfigurationStore::KEY);
    $wpdb->delete($wpdb->prefix . 'fcal_meta', ['key'=>ConfigurationStore::KEY]);
    $profile = BookingProfile::defaults(); $profile['enabled']=true; $profile['capacity']=3;
    $store->save('calendar_event', (int)$event->id, ['booking_profile'=>$profile], 0);
    $rows = [['type'=>'participant','name'=>'Test A'],['type'=>'participant','name'=>'Test B']];
    $input = ['email'=>'fba-test@example.invalid','first_name'=>'Test','last_name'=>'Fixture','start_time'=>'2030-01-01 14:00:00','end_time'=>'2030-01-01 14:30:00','person_time_zone'=>'UTC','source'=>'test','status'=>'scheduled','_fba_party'=>json_encode($rows)];
    $booking = BookingService::createBooking($input, $event);
    check(!is_wp_error($booking) && $booking->id > 0, 'native service creates one booking for entire party');
    check($booking->getMeta(BookingModules::META, [])['count']===2, 'participant snapshot persisted');
    check($capacity->remaining('event:'.$event->id, [(int)$event->id], $input['start_time'], $input['end_time'],3)===1,'two participants consume two places');
    $rejected = BookingService::createBooking($input,$event);
    check(is_wp_error($rejected), 'second party refused at capacity');
    check($capacity->orphanCount()===0,'normal rejection creates no orphan hold');
    $booking->status='cancelled'; $booking->save();
    check($capacity->remaining('event:'.$event->id, [(int)$event->id], $input['start_time'], $input['end_time'],3)===3,'cancellation releases entire party');
    $blocked=false;
    try {$booking->status='scheduled';$booking->save();} catch (RuntimeException $e) {$blocked=true;}
    check($blocked,'reactivation cannot bypass capacity');
    $booking->status='cancelled';
    $blocked=false;
    try {$booking->start_time='2030-01-02 14:00:00';$booking->save();} catch (RuntimeException $e) {$blocked=true;}
    check($blocked,'unsupported reschedule is blocked');
    $missing=$input;unset($missing['_fba_party']);
    check(is_wp_error(BookingService::createBooking($missing,$event)), 'missing party refused server-side');
    $blocked=false;
    try {Booking::create(['event_id'=>$event->id,'calendar_id'=>$event->calendar_id,'email'=>'test@example.invalid','start_time'=>$input['start_time'],'end_time'=>$input['end_time']]);} catch (RuntimeException $e) {$blocked=true;}
    check($blocked,'direct model creation cannot bypass participant validation');
    $profile['pricing']=true; $profile['types'][0]['price']=1429;
    $store->save('calendar_event',(int)$event->id,['booking_profile'=>$profile],1);
    $event->updateMeta('payment_settings',['enabled'=>'yes','driver'=>'native','offline_enabled'=>'yes','items'=>[['title'=>'Original price','value'=>1]]]);
    $input['source']='web'; $input['payment_method']='offline';
    $paid = BookingService::createBooking($input,$event,['payment_method'=>'offline']);
    check(!is_wp_error($paid),'priced native booking accepted');
    $order=(new \FluentBookingPro\App\Services\OrderHelper())->processDraftOrder($paid,$event,[]);
    check((int)$order->total_amount===2858 && (int)$order->subtotal===2858,'native order uses server quote not original event price');
    $items=$order->items()->get();
    check((int)$items[0]->item_total===2858,'native order lines agree with total');
    $stripe=new \FluentBookingPro\App\Services\Integrations\PaymentMethods\Stripe\Stripe();
    check((int)$stripe->getPayableAmount($event->getPaymentItems(), false)===2858,'Stripe payable helper sees same authoritative amount');
    foreach ($GLOBALS['wp_filter']['fluent_booking/payment/pay_order_with_stripe']->callbacks[1] as $callback) { call_user_func($callback['function'],$paid); }
    $args=apply_filters('fluent-booking/payment/stripe_checkout_session_args',['client_reference_id'=>$paid->hash,'line_items'=>[]]);
    check($args['line_items'][0]['amount']===2858,'Stripe Checkout receives exact integer cents');
    $poolChange=$profile; $poolChange['pool']='new-pool'; $blocked=false;
    try {$store->save('calendar_event',(int)$event->id,['booking_profile'=>$poolChange],2);} catch (RuntimeException $e) {$blocked=true;}
    check($blocked,'changing a live capacity pool is refused');
    
    $profile['types'][0]['price']=9999;
    $store->save('calendar_event',(int)$event->id,['booking_profile'=>$profile],2);
    check($paid->getMeta(BookingModules::META,[])['subtotal']===2858,'existing booking keeps immutable price after configuration edit');
    $export=(new \WaasKit\FluentBooking\Infrastructure\Privacy())->export('fba-test@example.invalid');
    check(count($export['data'])===2,'WordPress privacy export contains holder participant records');
    (new \WaasKit\FluentBooking\Infrastructure\Privacy())->erase('fba-test@example.invalid');
    $erased=$paid->getMeta(BookingModules::META,[]);
    check($erased['participants'][0]['name']==='' && $erased['subtotal']===2858,'privacy erasure preserves payment and capacity facts');
    $raw=$input;$raw['coupon_codes']=['PROMO'];
    check(is_wp_error(BookingService::createBooking($raw,$event,['payment_method'=>'offline'])),'unsupported coupon rejected before booking or payment');
} finally {
    $capacity->unlock();$wpdb->query('ROLLBACK');wp_cache_flush();
}
