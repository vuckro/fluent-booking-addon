<?php
$path=getenv('WAASKIT_WP_PATH');if(!$path){throw new RuntimeException('Local WordPress required.');}require $path.'/wp-load.php';
if(!in_array(parse_url(home_url(),PHP_URL_HOST),['localhost','127.0.0.1'],true)){throw new RuntimeException('Local only.');}
use WaasKit\FluentBooking\Guests\Options;
use WaasKit\FluentBooking\Guests\NativeTariffs;
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
 $event->max_book_per_slot=5;$event->save();
 $nativeFields=$event->getBookingFields();foreach($nativeFields as &$field){if(($field['name']??'')==='guests'){$field['enabled']=true;$field['limit']=10;}}unset($field);$event->setBookingFields($nativeFields);
 $options=Options::defaults();$options['enabled']=true;$options['native_tariffs']=true;$options['name_mode']='required';$options['email_mode']='hidden';
 $options['fields']=[['id'=>'age','label'=>'Âge','type'=>'number','required'=>true,'choices'=>[],'pricing'=>'none','prices'=>[]]];
 $store->save('calendar_event',2,['guest_options'=>$options],$before['revision']);
 $event->updateMeta('payment_settings',['enabled'=>'yes','driver'=>'native','offline_enabled'=>'yes','items'=>[['title'=>'Adulte','value'=>70],['title'=>'Enfant','value'=>55]]]);
 $catalogue=NativeTariffs::catalogue($event);$adult=$catalogue[0]['id'];$child=$catalogue[1]['id'];
 $base=['email'=>'fba-holder@example.invalid','first_name'=>'Holder','last_name'=>'Test','start_time'=>'2032-01-03 14:00:00','end_time'=>'2032-01-03 14:30:00','person_time_zone'=>'UTC','source'=>'web','status'=>'scheduled','payment_method'=>'offline'];
 foreach ([[$adult,[],7000],[$child,[],5500],[$adult,[['name'=>'Enfant','tariff'=>$child,'fields'=>['age'=>'8']]],12500]] as $i=>[$holder,$guests,$expected]) {
  $input=$base;$input['start_time']='2032-01-'.(10+$i).' 14:00:00';$input['end_time']='2032-01-'.(10+$i).' 14:30:00';
  $input['_fba_extras']=wp_json_encode(['holder_tariff'=>$holder,'guests'=>$guests]);
  $booking=BookingService::createBooking($input,$event,['payment_method'=>'offline']);
  check(!is_wp_error($booking),is_wp_error($booking)?$booking->get_error_message():'exclusive native tariffs accepted');
  $order=(new FluentBookingPro\App\Services\OrderHelper())->processDraftOrder($booking,$event,['quantity'=>999]);
  check((int)$order->total_amount===$expected,'authoritative total '.$expected.' cents ignores client quantity');
  check((int)$order->items()->sum('item_total')===$expected,'order lines match total');
  check(Booking::where('parent_id',$booking->id)->count()===count($guests),'one native seat per guest');
  $snapshot=$booking->getMeta(BookingAdapter::META,[]);check($snapshot['holder_tariff']['id']===$holder,'holder tariff frozen');
 }
 $bad=$base;$bad['_fba_extras']=wp_json_encode(['holder_tariff'=>'forged','guests'=>[]]);
 check(is_wp_error(BookingService::createBooking($bad,$event,['payment_method'=>'offline'])),'forged holder choice rejected');
 $guest=['name'=>'Test','tariff'=>$child,'fields'=>['age'=>'8']];
 $bad['_fba_extras']=wp_json_encode(['holder_tariff'=>$adult,'guests'=>[array_replace($guest,['tariff'=>'invalid'])]]);
 check(is_wp_error(BookingService::createBooking($bad,$event,['payment_method'=>'offline'])),'forged guest choice rejected');
 $bad['_fba_extras']=wp_json_encode(['holder_tariff'=>$adult,'guests'=>[array_replace($guest,['fields'=>['age'=>'bad']])]]);
 check(is_wp_error(BookingService::createBooking($bad,$event,['payment_method'=>'offline'])),'custom number validated server-side');
 $bad['_fba_extras']=wp_json_encode(['holder_tariff'=>$adult,'guests'=>array_fill(0,5,$guest)]);
 check(is_wp_error(BookingService::createBooking($bad,$event,['payment_method'=>'offline'])),'six people rejected despite native guest limit ten');
 $bad['_fba_extras']=wp_json_encode(['holder_tariff'=>$adult,'guests'=>array_fill(0,4,$guest)]);
 $full=BookingService::createBooking($bad,$event,['payment_method'=>'offline']);check(!is_wp_error($full),is_wp_error($full)?$full->get_error_message():'five people fill five seats');
 check(is_wp_error(BookingService::createBooking($bad,$event,['payment_method'=>'offline'])),'occupied slot refuses another party');
 $full->status='cancelled';$full->save();check(Booking::where('parent_id',$full->id)->where('status','cancelled')->count()===4,'cancellation releases all four guest seats');
 $event->updateMeta('payment_settings',['enabled'=>'yes','driver'=>'native','offline_enabled'=>'yes','items'=>[['title'=>'Adulte','value'=>80],['title'=>'Enfant','value'=>55]]]);
 $bad['start_time']='2032-02-03 14:00:00';$bad['end_time']='2032-02-03 14:30:00';
 check(is_wp_error(BookingService::createBooking($bad,$event,['payment_method'=>'offline'])),'stale tariff rejected after price change');
 check((int)$order->fresh()->total_amount===12500,'existing order unchanged after tariff change');
} finally {$wpdb->query('ROLLBACK');wp_cache_flush();}
check($store->read('calendar_event',2)===$before,'user settings preserved');check(Booking::count()===$countBefore,'no fixture booking retained');
