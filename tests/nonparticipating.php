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
 $nativeFields=$event->getBookingFields();foreach($nativeFields as &$field){if(($field['name']??'')==='guests'){$field['enabled']=true;$field['limit']=10;}}unset($field);$event->setBookingFields($nativeFields);
 $options=Options::defaults();$options['enabled']=true;$options['allow_nonparticipating']=true;$options['customize_guests']=true;$options['email_mode']='hidden';
 $options['fields']=[['id'=>'age','label'=>'Age','type'=>'number','required'=>true,'choices'=>[],'min'=>'1','max'=>'99']];
 $save=static function() use (&$options,$store) {$current=$store->read('calendar_event',2);$store->save('calendar_event',2,['guest_options'=>$options],$current['revision']);};$save();
 $event->updateMeta('payment_settings',['enabled'=>'yes','driver'=>'native','offline_enabled'=>'yes','items'=>[['title'=>'Option 1','value'=>70],['title'=>'Option 2','value'=>55]]]);
 $catalogue=NativeTariffs::catalogue($event);$adult=$catalogue[0]['id'];$child=$catalogue[1]['id'];
 $base=['email'=>'fba-contact@example.invalid','first_name'=>'Contact','last_name'=>'Test','start_time'=>'2033-01-03 14:00:00','end_time'=>'2033-01-03 14:30:00','person_time_zone'=>'UTC','source'=>'web','status'=>'scheduled','payment_method'=>'offline'];
 $guest=['name'=>'Participant','tariff'=>$child,'fields'=>['age'=>'8']];
 $create=static function($day,$attends,$guests) use ($base,$adult,$event) {
     $input=$base;$input['start_time']='2033-01-'.$day.' 14:00:00';$input['end_time']='2033-01-'.$day.' 14:30:00';
     $input['_fba_extras']=wp_json_encode(['holder_participates'=>$attends,'holder_tariff'=>$adult,'guests'=>$guests]);
     return BookingService::createBooking($input,$event,['payment_method'=>'offline']);
 };
 foreach([[false,[$guest],5500,1],[false,[$guest,$guest],11000,2],[true,[$guest],12500,2],[false,[array_replace($guest,['tariff'=>$adult]),$guest],12500,2]] as $i=>[$attends,$guests,$amount,$seats]) {
     $booking=$create(10+$i,$attends,$guests);
     check(!is_wp_error($booking),is_wp_error($booking)?$booking->get_error_message():'participation scenario '.$i.' accepted');
     $snapshot=$booking->getMeta(BookingAdapter::META,[]);
     check($snapshot['holder_participates']===$attends && $snapshot['count']===$seats,'only actual participants counted');
     check(1+Booking::where('parent_id',$booking->id)->count()===$seats,'native rows equal participant count');
     check($booking->email==='fba-contact@example.invalid' && $booking->first_name==='Contact','contact remains native payer and recipient');
     $order=(new FluentBookingPro\App\Services\OrderHelper())->processDraftOrder($booking,$event,['quantity'=>999]);
     check((int)$order->total_amount===$amount && (int)$order->items()->sum('item_total')===$amount,'order charges participants exactly once');
     check(count($snapshot['guests'])===count($guests),'first participant retained in snapshot');
 }
 ob_start();(new BookingAdapter($store))->summary($booking);$html=ob_get_clean();check(str_contains($html,'ne participe pas') && str_contains($html,'Participant'),'booking detail distinguishes contact and participants');
 check(is_wp_error($create(20,false,[])),'zero participants rejected');
 check(is_wp_error($create(20,'false',[$guest])),'non-boolean participation rejected');
 check(is_wp_error($create(20,false,[array_replace($guest,['fields'=>['age'=>'0']])])),'first participant field validated');
 check(is_wp_error($create(20,false,[array_replace($guest,['tariff'=>'invalid'])])),'first participant tariff validated');
 $options['allow_nonparticipating']=false;$save();check(is_wp_error($create(20,false,[$guest])),'opt-out forbidden when event option disabled');
 $options['allow_nonparticipating']=true;$save();
 check(is_wp_error($create(20,false,array_fill(0,6,$guest))),'six participants exceed capacity five');
 $full=$create(20,false,array_fill(0,5,$guest));check(!is_wp_error($full),'five guests without attending contact accepted');
 check(Booking::where('parent_id',$full->id)->count()===4,'contact adds no sixth native seat');
 check(is_wp_error($create(20,false,[$guest])),'full native slot rejects next reservation');
 $full->status='cancelled';$full->save();check(Booking::where('parent_id',$full->id)->where('status','cancelled')->count()===4,'cancellation releases all attached participants');
 $again=$create(20,false,[$guest]);check(!is_wp_error($again),'cancelled slot can be booked again');
 try {$full->status='scheduled';$full->save();check(false,'reactivation guard');} catch(RuntimeException $e) {check(str_contains($e->getMessage(),'réactivation'),'reactivation still guarded');}
 $id=$booking->id;$booking->delete();check(Booking::where('parent_id',$id)->count()===0,'deleting reservation removes remaining participants');
 $export=(new WaasKit\FluentBooking\Infrastructure\Privacy())->export('fba-contact@example.invalid');check(str_contains(wp_json_encode($export),'Participant'),'contact privacy export includes participants');
 (new WaasKit\FluentBooking\Infrastructure\Privacy())->erase('fba-contact@example.invalid');check($again->getMeta(BookingAdapter::META,[])['guests'][0]['name']==='','first participant can be erased');
 $options['enabled']=false;$save();$flat=$create(22,false,[$guest]);check(!is_wp_error($flat),'nonparticipating contact works without custom price');
 $flatOrder=(new FluentBookingPro\App\Services\OrderHelper())->processDraftOrder($flat,$event,['quantity'=>1]);check((int)$flatOrder->total_amount===12500,'native flat total retained when per-person pricing disabled');
 $event->type='free';$event->save();$event->updateMeta('payment_settings',['enabled'=>'no']);$free=$create(23,false,[$guest]);check(!is_wp_error($free),'free event accepts nonparticipating contact');
 check($free->getMeta(BookingAdapter::META,[])['seats']===1,'free event counts one participant');
 $options['enabled']=true;$save();$freeTariff=$create(24,false,[$guest]);check(!is_wp_error($freeTariff),is_wp_error($freeTariff)?$freeTariff->get_error_message():'free event also works with per-person switch enabled');
 foreach($nativeFields as &$field){if(($field['name']??'')==='guests'){$field['enabled']=false;}}unset($field);$event->setBookingFields($nativeFields);
 check(is_wp_error($create(25,false,[$guest])),'native disabled guests cannot be bypassed by opt-out');
 $options['enabled']=false;$save();check(!is_wp_error($create(26,true,[])),'holder alone still works when native guests disable the participation toggle');

 $options['enabled']=true;$options['native_tariffs']=false;
 try {Options::validate($options);check(false,'legacy pricing rejected');} catch(InvalidArgumentException $e) {check(true,'legacy pricing explicitly excluded');}
} finally {$wpdb->query('ROLLBACK');wp_cache_flush();}
check($store->read('calendar_event',2)===$before,'user settings preserved');check(Booking::count()===$countBefore,'no fixture booking retained');
