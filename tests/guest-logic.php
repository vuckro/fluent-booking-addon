<?php
$path = getenv('WAASKIT_WP_PATH');
if (!$path || !is_file($path . '/wp-load.php')) { throw new RuntimeException('Local WordPress required.'); }
require $path . '/wp-load.php';
if (!in_array(parse_url(home_url(), PHP_URL_HOST), ['localhost','127.0.0.1'], true)) { throw new RuntimeException('Local only.'); }
use WaasKit\FluentBooking\Integrations\FluentBooking\ConfigurationStore;
use FluentBooking\App\Models\CalendarSlot;
use FluentBooking\App\Models\Booking;
use FluentBooking\App\Services\BookingService;
use FluentBooking\App\Hooks\Handlers\FrontEndHandler;
function check($ok,$label) { if (!$ok) {throw new RuntimeException($label);} echo "PASS $label\n"; }
add_filter('pre_wp_mail','__return_true');
add_filter('pre_http_request',static fn()=>new WP_Error('test_network_blocked','Blocked'),999);
foreach(['scheduled','pending','cancelled','completed'] as $status) {
 remove_all_actions('fluent_booking/pre_after_booking_'.$status);
 remove_all_actions('fluent_booking/after_booking_'.$status);
}
$store=new ConfigurationStore();$event=CalendarSlot::find(2);
$before=$store->read('calendar_event',2);
$bookingCount=Booking::count();
$wpdb->query('START TRANSACTION');
try {
 $store->save('calendar_event',2,['enabled'=>true,'max_participants'=>2],$before['revision']);
 $vars=(new FrontEndHandler())->getCalendarEventVars($event->calendar,$event);
 $guests=array_values(array_filter($vars['form_fields'],static fn($f)=>$f['name']==='guests'))[0];
 check((int)$guests['limit']===2,'public native component allows one guest plus holder');
 $native=array_values(array_filter($event->getMeta('booking_fields',[]),static fn($f)=>$f['name']==='guests'))[0];
 check((int)$native['limit']===10,'native questions configuration remains unchanged');
 $adapter = new WaasKit\FluentBooking\Integrations\FluentBooking\GuestFields($store);
 check($adapter->validatePosted(['guests'=>[['name'=>'Guest','email'=>'guest@example.test']],'email'=>'holder@example.test'],$event)===null,'raw public submission accepts one valid guest');
 check(is_wp_error($adapter->validatePosted(['guests'=>[[],[]]],$event)),'raw public submission refuses excess before controller slicing');
 check(is_wp_error($adapter->validatePosted(['guests'=>[['name'=>'Guest','email'=>'']]],$event)),'incomplete guests are refused instead of disappearing');
 $input=['email'=>'fba-test@example.invalid','first_name'=>'Holder','last_name'=>'Test','start_time'=>'2030-01-02 14:00:00','end_time'=>'2030-01-02 14:30:00','person_time_zone'=>'UTC','source'=>'test','status'=>'scheduled','additional_guests'=>[['email'=>'fba-guest@example.invalid','name'=>'Guest']]];
 $booking=BookingService::createBooking($input,$event);
 check(!is_wp_error($booking),'holder and guest accepted at maximum two');
 $rows=Booking::where('event_id',2)->where('start_time',$input['start_time'])->where('group_id',$booking->group_id)->get();
 check(count($rows)===2,'native group creates two places, not one');
 check((int)$event->getMaxBookingPerSlot()-count($rows)===3,'five-place session has three places after two people');
 $input['additional_guests'][]=['email'=>'fba-extra@example.invalid','name'=>'Extra'];
 check(is_wp_error(BookingService::createBooking($input,$event)),'third person rejected');
 $truncated=$input;array_pop($truncated['additional_guests']);$truncated['_fba_requested_count']=3;
 check(is_wp_error(BookingService::createBooking($truncated,$event)),'controller cannot silently truncate three people into two');
 $duplicate=$truncated;unset($duplicate['_fba_requested_count']);$duplicate['additional_guests'][0]['email']=$duplicate['email'];
 check(is_wp_error(BookingService::createBooking($duplicate,$event)),'duplicate email cannot collapse guest place');
 // Exercise native per-person pricing locally, without Stripe or external notifications.
 $event->updateMeta('payment_settings',['enabled'=>'yes','driver'=>'native','offline_enabled'=>'yes','items'=>[['title'=>'Prix par personne','value'=>20]]]);
 $priced=$input;array_pop($priced['additional_guests']);$priced['payment_method']='offline';
 $paid=BookingService::createBooking($priced,$event,['payment_method'=>'offline']);
 check(!is_wp_error($paid) && (int)$paid->getMeta('quantity',1)===2,'native priced booking records quantity two');
 $order=(new FluentBookingPro\App\Services\OrderHelper())->processDraftOrder($paid,$event,['quantity'=>(int)$paid->getMeta('quantity',1)]);
 check((int)$order->total_amount===4000,'native price 20 times two participants produces order 40');
 $saved=$store->read('calendar_event',2);$store->save('calendar_event',2,['enabled'=>true,'max_participants'=>1],$saved['revision']);
 $vars=(new FrontEndHandler())->getCalendarEventVars($event->calendar,$event);
 $guests=array_values(array_filter($vars['form_fields'],static fn($f)=>$f['name']==='guests'))[0];
 check((int)$guests['limit']===1 && !$guests['required'],'one-person maximum removes ability to add a guest');
} finally {
 $wpdb->query('ROLLBACK');wp_cache_flush();
}
check($store->read('calendar_event',2)===$before,'user configuration restored after test');
check(Booking::count()===$bookingCount,'no test bookings retained');
