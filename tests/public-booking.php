<?php
/** Exercise the real public AJAX handler, including WordPress request slashing. */
define('DOING_AJAX', true);
$path = getenv('WAASKIT_WP_PATH');
if (!$path) {throw new RuntimeException('Local WordPress required.');}
require $path.'/wp-load.php';
set_exception_handler(static function (Throwable $error) {fwrite(STDERR, $error->getMessage()."\n".$error->getTraceAsString()."\n"); exit(1);});
if (!in_array(parse_url(home_url(), PHP_URL_HOST), ['localhost', '127.0.0.1'], true)) {throw new RuntimeException('Local only.');}

use FluentBooking\App\Models\Booking;
use FluentBooking\App\Models\CalendarSlot;
use FluentBooking\App\Hooks\Handlers\TimeSlotServiceHandler;
use WaasKit\FluentBooking\Guests\BookingAdapter;
use WaasKit\FluentBooking\Guests\NativeTariffs;
use WaasKit\FluentBooking\Guests\Options;
use WaasKit\FluentBooking\Integrations\FluentBooking\ConfigurationStore;

// Error (not Exception) escapes the native handler's catch after wp_send_json.
final class PublicResponse extends Error {}
add_filter('wp_die_ajax_handler', static fn() => static function () {throw new PublicResponse();});
add_filter('pre_wp_mail', '__return_true');
add_filter('pre_http_request', static fn() => new WP_Error('blocked', 'Test'), 999);
foreach (['scheduled', 'pending', 'cancelled', 'completed'] as $status) {
    remove_all_actions('fluent_booking/pre_after_booking_'.$status);
    remove_all_actions('fluent_booking/after_booking_'.$status);
}
add_filter('fluent_booking/public_ajax_ratelimit', static fn($args) => ['limit'=>1000, 'window'=>60]);
function check($ok, $message) {if (!$ok) {throw new RuntimeException($message);} echo "PASS $message\n";}

$store = new ConfigurationStore();
$before = $store->read('calendar_event', 2);
$countBefore = Booking::count();
$requestBefore = $_REQUEST;
// Buffer progress too: headers must remain available to the native AJAX handler.
ob_start();
$wpdb->query('START TRANSACTION');
try {
    $event = CalendarSlot::findOrFail(2);
    $fields = $event->getBookingFields();
    foreach ($fields as &$field) {if (($field['name'] ?? '') === 'guests') {$field['enabled']=true; $field['limit']=10;}}
    unset($field);
    $event->setBookingFields($fields);
    // Keep the capacity scenario independent of the current admin settings.
    $event->max_book_per_slot=5;
    $event->type='paid'; $event->save();
    $event->updateMeta('payment_settings', ['enabled'=>'yes', 'driver'=>'native', 'offline_enabled'=>'yes', 'items'=>[['title'=>'Adulte','value'=>70], ['title'=>'Enfant','value'=>55]]]);
    $options=Options::defaults();
    $options['enabled']=true; $options['allow_nonparticipating']=true; $options['customize_guests']=true; $options['email_mode']='hidden';
    $options['fields']=[['id'=>'age','label'=>'Âge','type'=>'number','required'=>true,'choices'=>[],'min'=>'8','max'=>'99']];
    $save=static function () use ($store, &$options) {$current=$store->read('calendar_event',2); $store->save('calendar_event',2,['guest_options'=>$options],$current['revision']);};
    $save();
    $catalogue=NativeTariffs::catalogue($event);
    $service=TimeSlotServiceHandler::initService($event->calendar,$event);
    $spots=$service->getAvailableSpots(gmdate('Y-m-d',strtotime('+1 day')), 'UTC');
    $start=null;
    foreach ($spots as $day) {foreach ($day as $spot) {if ((int)($spot['remaining']??0)>=5) {$start=$spot['start']; break 2;}}}
    if (!$start) {throw new RuntimeException('Fixture needs an available group slot with five places.');}
    $guest=['name'=>'Élodie O\'Neil "Junior" \\ test','tariff'=>$catalogue[1]['id'],'fields'=>['age'=>'8']];
    // The admin/REST Request already unslashes: the shared hook must leave it alone.
    $json=wp_json_encode(['holder_participates'=>true,'holder_tariff'=>$catalogue[0]['id'],'guests'=>[$guest]]);
    $request=new FluentBooking\Framework\Http\Request\Request(FluentBooking\App\App::getInstance(),[],wp_slash(['fba_extra_2'=>$json]));
    $initialized=apply_filters('fluent_booking/initialize_booking_data',[],$request->all(),$event);
    check($initialized['_fba_extras']===$json,'already-clean native Request is not unslashed twice');
    $send=static function ($payload, $authenticated=false) use ($start) {
        $_REQUEST=wp_slash(['action'=>'fluent_cal_schedule_meeting','event_id'=>2,'name'=>'Contact test','email'=>'fba-ajax@example.invalid','timezone'=>'UTC','start_date'=>$start,'message'=>'Test local','payment_method'=>'offline','fba_extra_2'=>is_string($payload)?$payload:wp_json_encode($payload)]);
        ob_start();
        try {do_action($authenticated?'wp_ajax_fluent_cal_schedule_meeting':'wp_ajax_nopriv_fluent_cal_schedule_meeting');}
        catch (PublicResponse $response) {}
        finally {$body=ob_get_clean();}
        return [http_response_code(), json_decode($body,true,512,JSON_THROW_ON_ERROR)];
    };
    foreach ([true,false] as $attends) {
        [$status,$response]=$send(['holder_participates'=>$attends,'holder_tariff'=>$catalogue[0]['id'],'guests'=>[$guest]],$attends);
        check($status===200, 'public AJAX accepts '.($attends?'attending':'nonparticipating').' contact: '.($response['message']??''));
        $booking=Booking::where('hash',$response['booking_hash'])->firstOrFail();
        $snapshot=$booking->getMeta(BookingAdapter::META,[]);
        $google=apply_filters('fluent_booking/google_event_data',['summary'=>'Group'], $booking, []);
        check(str_contains($google['description']??'', 'Voir les détails de la réservation') && !str_contains($google['description'],$guest['name']),'registered Google hook describes real booking without personal data');
        $outlook=apply_filters('fluent_booking/outlook_event_data',['subject'=>'Group'], $booking);
        check(str_contains($outlook['body']['content']??'', 'booking_id='.$booking->id),'registered Outlook hook provides protected organizer access');
        check($snapshot['guests'][0]['name']===$guest['name'],'accents, apostrophes, quotes and backslashes survive request and storage');
        check($snapshot['count']===($attends?2:1) && 1+Booking::where('parent_id',$booking->id)->count()===($attends?2:1),'public reservation uses only participant seats');
        $order=(new FluentBookingPro\App\Services\OrderHelper())->processDraftOrder($booking,$event,['quantity'=>999]);
        check((int)$order->total_amount===($attends?12500:5500),'stored order matches chosen tariffs');
        $view=clone $booking;
        do_action_ref_array('fluent_booking/format_booking_schedule',[&$view]);
        $admin=wp_json_encode($view->custom_form_data,JSON_UNESCAPED_UNICODE);
        check(str_contains($admin,'Âge : 8') && str_contains($admin,$attends?'— participe':'ne participe pas'),'native admin includes participation and guest answers');
        check($view->first_name===$booking->first_name && $view->email===$booking->email,'presentation preserves contact identity');
        $html=FluentBooking\App\Services\BookingService::getBookingConfirmationHtml($booking);
        check(!str_contains($html,'fba-booked-guests') && str_contains($html,'Réservation et participants') && str_contains($html,'Âge : 8'),'confirmation uses native sections with guest answers');
        check(str_contains($html,'Informations de facturation'),'confirmation uses billing heading');
        if (!$attends) {check(str_contains($html,'ne participe pas au rendez-vous') && !str_contains($html,'La personne qui a réservé participe également.'),'confirmation never presents nonparticipant as attending');}
        if ($attends) {
            $seat=Booking::where('parent_id',$booking->id)->firstOrFail();
            do_action_ref_array('fluent_booking/format_booking_schedule',[&$seat]);
            check(str_contains(wp_json_encode($seat->custom_form_data,JSON_UNESCAPED_UNICODE),'Âge : 8'),'attached child exposes its own answers');
        }
        $booking->status='cancelled'; $booking->save();
        check(Booking::where('parent_id',$booking->id)->where('status','scheduled')->count()===0,'cancellation releases attached seats');
    }
    $valid=['holder_participates'=>true,'holder_tariff'=>$catalogue[0]['id'],'guests'=>[$guest]];
    foreach ([
        'required name'=>array_replace($guest,['name'=>'']),
        'minimum age'=>array_replace($guest,['fields'=>['age'=>'7']]),
        'maximum age'=>array_replace($guest,['fields'=>['age'=>'100']]),
        'unknown tariff'=>array_replace($guest,['tariff'=>'forged']),
    ] as $reason=>$invalidGuest) {
        $count=Booking::count();
        [$status]=$send(array_replace($valid,['guests'=>[$invalidGuest]]));
        check($status===422 && Booking::count()===$count,'public validation rejects '.$reason.' without creating a booking');
    }
    [$status]=$send(array_replace($valid,['guests'=>array_fill(0,5,$guest)]));
    check($status===422,'six participants cannot reserve five available places');
    [$status]=$send(array_replace($valid,['holder_participates'=>false,'guests'=>[]]));
    check($status===422,'nonparticipating contact cannot reserve with zero participants');
    [$status,$response]=$send('{broken JSON');
    check($status===422 && !str_contains($response['message'],'Syntax error'),'malformed payload returns a readable validation error');
    $options['enabled']=false; $options['allow_nonparticipating']=false; $save();
    [$status,$response]=$send([$guest]);
    check($status===200,'information-only array payload survives public AJAX');
    $booking=Booking::where('hash',$response['booking_hash'])->firstOrFail();
    check($booking->getMeta(BookingAdapter::META,[])['guests'][0]['name']===$guest['name'],'information-only identity is not double-unescaped');
} finally {
    $wpdb->query('ROLLBACK'); wp_cache_flush(); $_REQUEST=$requestBefore;
    ob_end_flush();
}
check($store->read('calendar_event',2)===$before,'settings restored');
check(Booking::count()===$countBefore,'no test reservations retained');
