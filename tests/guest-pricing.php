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
 $nativeFields=$event->getBookingFields();foreach($nativeFields as &$field){if(($field['name']??'')==='guests'){$field['enabled']=true;$field['limit']=3;}}unset($field);$event->setBookingFields($nativeFields);
 $options=Options::defaults();$options['enabled']=true;$options['name_mode']='hidden';$options['email_mode']='optional';
 $options['fields']=[['id'=>'category','label'=>'Tarif','type'=>'radio','required'=>true,'choices'=>['Adulte','Enfant'],'pricing'=>'replace','prices'=>[5000,2500]], ['id'=>'extra','label'=>'Atelier supplémentaire','type'=>'checkbox','required'=>false,'choices'=>[],'pricing'=>'add','prices'=>[1000]]];
 $store->save('calendar_event',2,['guest_options'=>$options],$before['revision']);
 $event->updateMeta('payment_settings',['enabled'=>'yes','driver'=>'native','offline_enabled'=>'yes','items'=>[['title'=>'Réservation','value'=>50]]]);
 $input=['email'=>'fba-holder@example.invalid','first_name'=>'Holder','last_name'=>'Test','start_time'=>'2031-01-03 14:00:00','end_time'=>'2031-01-03 14:30:00','person_time_zone'=>'UTC','source'=>'web','status'=>'scheduled','payment_method'=>'offline','_fba_extras'=>json_encode([['email'=>'','name'=>'','fields'=>['category'=>'Adulte','extra'=>'1']],['email'=>'','name'=>'','fields'=>['category'=>'Enfant','extra'=>'']]])];
 $booking=BookingService::createBooking($input,$event,['payment_method'=>'offline']);
 check(!is_wp_error($booking),is_wp_error($booking)?$booking->get_error_message():'anonymous guests accepted');
 check(Booking::count()-$countBefore===3,'two guests without emails consume two native seats plus holder');
 check(Booking::where('parent_id',$booking->id)->where('email','')->count()===2,'no fabricated guest emails');
 $order=(new FluentBookingPro\App\Services\OrderHelper())->processDraftOrder($booking,$event,['quantity'=>1]);
 check((int)$order->total_amount===13500,'50 holder + 50 adult + 10 extra + 25 child = 135');
 check((int)$order->items()->first()->item_total===13500,'order lines agree with custom total');
 $error=BookingService::createBooking($input,$event,['payment_method'=>'offline']);check(is_wp_error($error),'second party rejected at insufficient capacity');
 $bad=$input;$bad['start_time']='2031-01-04 14:00:00';$bad['end_time']='2031-01-04 14:30:00';$bad['_fba_extras']=json_encode([['email'=>'bad','name'=>'','fields'=>['category'=>'Adulte']]]);
 check(is_wp_error(BookingService::createBooking($bad,$event,['payment_method'=>'offline'])),'optional email validated when supplied');
 $booking->status='cancelled';$booking->save();
 check(Booking::where('parent_id',$booking->id)->where('status','cancelled')->count()===2,'cancellation releases every attached seat');
 $booking->status='scheduled';try {$booking->save();throw new RuntimeException('Reactivation accepted');} catch(RuntimeException $e) {check(str_contains($e->getMessage(),'réactivation'),'reactivation still guarded');}
 $export=(new WaasKit\FluentBooking\Infrastructure\Privacy())->export('fba-holder@example.invalid');
 check(str_contains(json_encode($export),'Enfant'),'holder privacy export covers unnamed guests');
 (new WaasKit\FluentBooking\Infrastructure\Privacy())->erase('fba-holder@example.invalid');
 $children=Booking::where('parent_id',$booking->id)->get();
 check($children[0]->getMeta(BookingAdapter::META,[])['guests'][0]['fields']===[],'holder erasure also covers attached seat copies');
 $htmlVars=apply_filters('fluent_booking/public_event_vars',['form_fields'=>$event->getBookingFields()],$event);
 check(count(array_filter($htmlVars['form_fields'],static fn($f)=>($f['name']??'')==='guests' && !empty($f['enabled'])))===0,'native identity form replaced in attached mode');
 $booking->delete();check(Booking::where('parent_id',$booking->id)->count()===0,'deleting holder removes attached seats');
 $options['name_mode']='required';$options['email_mode']='required';
 $current=$store->read('calendar_event',2);$store->save('calendar_event',2,['guest_options'=>$options],$current['revision']);
 $input['additional_guests']=[['name'=>'Adult','email'=>'adult@example.invalid'],['name'=>'Child','email'=>'child@example.invalid']];
 $input['_fba_extras']=json_encode([['email'=>'adult@example.invalid','fields'=>['category'=>'Adulte','extra'=>'1']],['email'=>'child@example.invalid','fields'=>['category'=>'Enfant','extra'=>'']]]);
 $native=BookingService::createBooking($input,$event,['payment_method'=>'offline']);
 check(!is_wp_error($native),'priced choices also work with mandatory native identities');
 $nativeOrder=(new FluentBookingPro\App\Services\OrderHelper())->processDraftOrder($native,$event,['quantity'=>3]);
 check((int)$nativeOrder->total_amount===13500 && (int)$native->getMeta('quantity',0)===1,'native guest quantity cannot multiply priced choices twice');
 (new WaasKit\FluentBooking\Infrastructure\Privacy())->erase('fba-holder@example.invalid');
 check(Booking::where('parent_id',$native->id)->where('first_name','Invité')->count()===2,'erasure removes guest names from native seat records too');
 $badOptions=$options;$badOptions['fields'][1]['pricing']='replace';
 try {Options::validate($badOptions);throw new RuntimeException('Two replacements accepted');} catch(InvalidArgumentException $e) {check(true,'conflicting replacement fields rejected');}
} finally {$wpdb->query('ROLLBACK');wp_cache_flush();}
check($store->read('calendar_event',2)===$before,'user settings preserved');check(Booking::count()===$countBefore,'no fixture booking retained');
