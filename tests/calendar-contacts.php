<?php
$path=getenv('WAASKIT_WP_PATH');if(!$path){throw new RuntimeException('Local WordPress required.');}require $path.'/wp-load.php';
if(!in_array(parse_url(home_url(),PHP_URL_HOST),['localhost','127.0.0.1'],true)){throw new RuntimeException('Local only.');}
use WaasKit\FluentBooking\Guests\CalendarContacts;
use FluentBooking\App\Models\Booking;
function check($ok,$message){if(!$ok){throw new RuntimeException($message);}echo "PASS $message\n";}
final class ContactFixture {
    public function __construct(private array $meta){}
    public function getMeta($key,$default=[]){return $this->meta;}
}
$guard=new CalendarContacts();
$seat=new ContactFixture(['attached_seat'=>true]);
$holder=new ContactFixture(['attached'=>true,'holder_participates'=>false]);
$native=new ContactFixture([]);
$collection=(new Booking())->newCollection([$holder,$seat,$native]);
$called=0;$received=[];
$spy=static function(...$args)use(&$called,&$received){$called++;$received=$args;return 'native-result';};
check($guard->dispatch($spy,[[], $seat])===null && $called===0,'attached seats cannot trigger provider operations');
check($guard->dispatch($spy,[[], $holder])==='native-result' && $received[1]===$holder,'nonparticipating contact remains the calendar contact');
$guard->dispatch($spy,[[], $holder,$collection,false],true);
check($received[2]->count()===2 && $received[2]->contains(static fn($item)=>$item===$native) && !$received[2]->contains(static fn($item)=>$item===$seat),'group sync includes contacts only, including unrelated native bookings');
check($collection->count()===3,'stock collection unchanged');
$guard->dispatch($spy,[[], $native]);check($received[1]===$native,'unrelated native booking unchanged');
$requests=0;
add_filter('pre_http_request',static function()use(&$requests){$requests++;return new WP_Error('blocked','Test');},999);
add_filter('pre_wp_mail','__return_true');
global $wp_filter;
foreach(['google','outlook','apple_calendar','next_cloud_calendar'] as $driver){
 foreach(['create_remote_calendar_event_','refresh_remote_calendar_group_members_','cancel_remote_calendar_event_','patch_remote_calendar_event_','delete_remote_calendar_event_'] as $action){
  $hook='fluent_booking/'.$action.$driver;
  $entries=$wp_filter[$hook]->callbacks[10]??[];
  check(count($entries)===1 && reset($entries)['function'] instanceof Closure,'native provider wrapped: '.$action.$driver);
  // An unwrapped native method would reject this fixture or contact the provider.
  do_action($hook,[], $seat, $collection, false);
 }
}
check($requests===0,'native child lifecycle makes no remote requests');
$before=count($wp_filter['fluent_booking/create_remote_calendar_event_google']->callbacks[10]);
$guard->protectProviders();
check(count($wp_filter['fluent_booking/create_remote_calendar_event_google']->callbacks[10])===$before,'provider protection is idempotent');
$notifications=new WaasKit\FluentBooking\Guests\SeatNotifications();
$called=0;
$notifications->dispatch($spy,[$seat]);check($called===0,'attached seat does not notify guest or host');
$notifications->dispatch($spy,[$holder]);check($called===1 && $received[0]===$holder,'main contact notifications preserved');
foreach(['after_booking_scheduled','after_booking_scheduled_async','after_booking_pending','after_booking_pending_async','booking_schedule_reminder','after_booking_rescheduled','booking_schedule_cancelled','booking_schedule_rejected','after_patch_booking_email'] as $suffix){
 $entries=$wp_filter['fluent_booking/'.$suffix]->callbacks[10]??[];
 $protected=false;
 foreach($entries as $entry){if($entry['function'] instanceof Closure){$reflection=new ReflectionFunction($entry['function']);if($reflection->getClosureThis() instanceof WaasKit\FluentBooking\Guests\SeatNotifications){$protected=true;call_user_func($entry['function'],$seat,null,null);}}}
 check($protected,'native notification protected: '.$suffix);
}
$wpdb->query('START TRANSACTION');
try {
 $event=FluentBooking\App\Models\CalendarSlot::find(2);
 if(!$event){throw new RuntimeException('Local event 2 is required.');}
 $row=Booking::create(['calendar_id'=>$event->calendar_id,'event_id'=>$event->id,'host_user_id'=>$event->user_id,'first_name'=>'Test','email'=>'test@example.invalid','status'=>'scheduled','start_time'=>'2033-01-01 09:00:00','end_time'=>'2033-01-01 09:30:00','slot_minutes'=>30,'person_time_zone'=>'UTC','event_type'=>'group']);
 FluentBooking\App\Services\Helper::updateBookingMeta($row->id,WaasKit\FluentBooking\Guests\BookingAdapter::META,['attached_seat'=>true]);
 $called=0;$notifications->dispatch($spy,[$row->id]);check($called===0,'previously queued child notification ID is suppressed');
} finally {$wpdb->query('ROLLBACK');}
