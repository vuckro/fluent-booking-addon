<?php
$path=getenv('WAASKIT_WP_PATH');if(!$path){throw new RuntimeException('Local WordPress required.');}require $path.'/wp-load.php';
if(!in_array(parse_url(home_url(),PHP_URL_HOST),['localhost','127.0.0.1'],true)){throw new RuntimeException('Local only.');}
use WaasKit\FluentBooking\Guests\Options;
use WaasKit\FluentBooking\Guests\BookingAdapter;
use WaasKit\FluentBooking\Integrations\FluentBooking\ConfigurationStore;
use FluentBooking\App\Models\CalendarSlot;
use FluentBooking\App\Models\Booking;
use FluentBooking\App\Services\BookingService;
function check($ok,$s){if(!$ok){throw new RuntimeException($s);}echo "PASS $s\n";}
add_filter('pre_wp_mail','__return_true');add_filter('pre_http_request',static fn()=>new WP_Error('blocked','Test'),999);
foreach(['scheduled','pending','cancelled','completed'] as $s){remove_all_actions('fluent_booking/pre_after_booking_'.$s);remove_all_actions('fluent_booking/after_booking_'.$s);}
$store=new ConfigurationStore();$event=CalendarSlot::find(2);$before=$store->read('calendar_event',2);$countBefore=Booking::count();
$wpdb->query('START TRANSACTION');
try {
 $options=Options::defaults();$options['enabled']=true;$options['fields']=[['id'=>'category','label'=>'Catégorie','type'=>'select','required'=>true,'choices'=>['Adulte','Enfant']],['id'=>'note','label'=>'Précision','type'=>'text','required'=>false,'choices'=>[]]];
 $store->save('calendar_event',2,['enabled'=>true,'max_participants'=>3,'guest_options'=>$options],$before['revision']);
 $event->updateMeta('payment_settings',['enabled'=>'yes','driver'=>'native','offline_enabled'=>'yes','items'=>[['title'=>'Prix par personne','value'=>100]]]);
 $input=['email'=>'fba-test@example.invalid','first_name'=>'Holder','last_name'=>'Test','start_time'=>'2030-01-03 14:00:00','end_time'=>'2030-01-03 14:30:00','person_time_zone'=>'UTC','source'=>'web','status'=>'scheduled','payment_method'=>'offline','additional_guests'=>[['email'=>'guest1@example.invalid','name'=>'Guest 1'],['email'=>'guest2@example.invalid','name'=>'Guest 2']], '_fba_extras'=>json_encode([['email'=>'guest1@example.invalid','fields'=>['category'=>'Adulte','note'=>'']],['email'=>'guest2@example.invalid','fields'=>['category'=>'Enfant','note'=>'Allergie']]])];
 $booking=BookingService::createBooking($input,$event,['payment_method'=>'offline']);
 check(!is_wp_error($booking),is_wp_error($booking)?$booking->get_error_message():'three people accepted');
 check(Booking::count()-$countBefore===3,'three native seats created');
 $snapshot=$booking->getMeta(BookingAdapter::META,[]);
 check($snapshot['count']===3 && $snapshot['quantity']===3,'snapshot distinguishes people and payable quantity');
 check($snapshot['guests'][1]['fields']['category']==='Enfant','guest category stored');
 check($snapshot['guests'][1]['fields']['note']==='Allergie','free text stored');
 $order=(new FluentBookingPro\App\Services\OrderHelper())->processDraftOrder($booking,$event,['quantity'=>3]);
 check((int)$order->total_amount===30000,'100 times three produces 300');
 check((int)$order->items()->first()->quantity===3,'order lines quantity agrees');
 $rejected=BookingService::createBooking($input,$event,['payment_method'=>'offline']);
 check(is_wp_error($rejected),'three cannot fit in remaining two places');
 $invalid=$input;$invalid['start_time']='2030-01-04 14:00:00';$invalid['end_time']='2030-01-04 14:30:00';$invalid['_fba_extras']='[]';
 check(is_wp_error(BookingService::createBooking($invalid,$event,['payment_method'=>'offline'])),'missing required guest answers rejected');
 $options['per_person_price']=false;$current=$store->read('calendar_event',2);$store->save('calendar_event',2,['enabled'=>true,'max_participants'=>3,'guest_options'=>$options],$current['revision']);
 $input['start_time']='2030-01-05 14:00:00';$input['end_time']='2030-01-05 14:30:00';
 $flat=BookingService::createBooking($input,$event,['payment_method'=>'offline']);
 check(!is_wp_error($flat),'flat price booking accepted');
 $flatOrder=(new FluentBookingPro\App\Services\OrderHelper())->processDraftOrder($flat,$event,['quantity'=>3]);
 check((int)$flatOrder->total_amount===10000,'disabled multiplier charges 100 for group');
 check((int)$flat->getMeta('quantity',0)===1 && (int)$flatOrder->items()->first()->quantity===1,'payment metadata and lines use quantity one');
 $event->updateMeta('payment_settings',['enabled'=>'yes','driver'=>'native','offline_enabled'=>'yes','items'=>[['title'=>'Nouveau tarif','value'=>14.29]]]);
 $oldOrder=(new FluentBookingPro\App\Services\OrderHelper())->processDraftOrder($flat,$event,['quantity'=>3]);
 check((int)$oldOrder->total_amount===10000,'stored price survives later event price change');
 check((int)$oldOrder->items()->first()->item_price===10000,'stored price also survives in order line');
 $export=(new WaasKit\FluentBooking\Infrastructure\Privacy())->export('fba-test@example.invalid');
 check(str_contains(json_encode($export),'Allergie'),'privacy export contains guest answers');
 (new WaasKit\FluentBooking\Infrastructure\Privacy())->erase('fba-test@example.invalid');
 $erased=$flat->getMeta(BookingAdapter::META,[]);
 check($erased['guests'][1]['fields']===[] && $erased['quantity']===1,'privacy erases answers and preserves quantity');
} finally {$wpdb->query('ROLLBACK');wp_cache_flush();}
check($store->read('calendar_event',2)===$before,'user settings preserved');check(Booking::count()===$countBefore,'no fixture booking retained');
