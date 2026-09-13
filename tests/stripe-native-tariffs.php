<?php
/**
 * Exercises the Stripe order preparation without contacting Stripe.
 * The booking uses source=cli: FluentBooking still runs the same early
 * pre_after_booking_pending hook, while the provider intentionally does not
 * start a checkout session.
 */
$path=getenv('WAASKIT_WP_PATH');
if(!$path){throw new RuntimeException('Local WordPress required.');}
require $path.'/wp-load.php';
if(!in_array(parse_url(home_url(),PHP_URL_HOST),['localhost','127.0.0.1'],true)){throw new RuntimeException('Local only.');}

use FluentBooking\App\Models\Booking;
use FluentBooking\App\Models\CalendarSlot;
use FluentBooking\App\Services\BookingService;
use FluentBooking\App\Services\EditorShortCodeParser;
use FluentBookingPro\App\Services\OrderHelper;
use WaasKit\FluentBooking\Guests\NativeTariffs;
use WaasKit\FluentBooking\Guests\Options;
use WaasKit\FluentBooking\Integrations\FluentBooking\ConfigurationStore;

function check($ok,$message){if(!$ok){throw new RuntimeException($message);}echo "PASS {$message}\n";}
add_filter('pre_wp_mail','__return_true');
add_filter('pre_http_request',static fn()=>new WP_Error('blocked','Test'),999);

$store=new ConfigurationStore();
$before=$store->read('calendar_event',2);
$countBefore=Booking::count();
$wpdb->query('START TRANSACTION');
try {
    $event=CalendarSlot::findOrFail(2);
    $fields=$event->getBookingFields();
    foreach($fields as &$field){if(($field['name']??'')==='guests'){$field['enabled']=true;$field['limit']=10;}}
    unset($field);
    $event->setBookingFields($fields);
    $event->type='paid';$event->save();
    $event->updateMeta('payment_settings',['enabled'=>'yes','driver'=>'native','items'=>[['title'=>'Adulte','value'=>70],['title'=>'Enfant','value'=>55]]]);
    $options=Options::defaults();
    $options['enabled']=true;$options['native_tariffs']=true;$options['name_mode']='required';$options['email_mode']='hidden';
    $options['fields']=[['id'=>'age','label'=>'Âge','type'=>'number','required'=>true,'choices'=>[],'pricing'=>'none','prices'=>[]]];
    $store->save('calendar_event',2,['guest_options'=>$options],$before['revision']);
    $catalogue=NativeTariffs::catalogue($event);
    $adult=$catalogue[0]['id'];
    $vars=apply_filters('fluent_booking/public_event_vars',['form_fields'=>$event->getBookingFields()],$event);
    check(count($vars['payment_items']??[])===1 && (int)round($vars['payment_items'][0]['value']*100)===7000,'Stripe public widget starts at the first 70 euro tariff');
    check((int)round(($vars['slot']['total_payment']??0)*100)===7000,'Stripe public total does not expose the 125 euro catalogue sum');
    $booking=BookingService::createBooking([
        'email'=>'stripe-test@example.invalid','first_name'=>'Camille','last_name'=>'Test',
        'start_time'=>'2032-03-14 09:00:00','end_time'=>'2032-03-14 09:30:00',
        'person_time_zone'=>'UTC','source'=>'cli','status'=>'pending','payment_method'=>'stripe',
        '_fba_extras'=>wp_json_encode(['holder_tariff'=>$adult,'guests'=>[]]),
    ],$event,['payment_method'=>'stripe']);
    check(!is_wp_error($booking),is_wp_error($booking)?$booking->get_error_message():'Stripe fixture booking accepted');
    $items=$event->getPaymentItems($booking->slot_minutes);
    check(count($items)===1 && (int)round($items[0]['value']*100)===7000,'draft order sees only the selected 70 euro tariff');
    $order=(new OrderHelper())->processDraftOrder($booking,$event,['quantity'=>1]);
    check((int)$order->total_amount===7000,'Stripe draft total is 70 euro, not the 125 euro catalogue total');
    check((int)$order->items()->sum('item_total')===7000,'Stripe draft line matches the selected tariff');
    $email=EditorShortCodeParser::parse('{{booking.custom.fba_participants_email}}',$booking);
    check(str_contains($email,'Contact de réservation') && str_contains($email,'Camille Test') && str_contains($email,'Participants'),'participant summary is available to native email templates');
} finally {
    $wpdb->query('ROLLBACK');wp_cache_flush();
}
check($store->read('calendar_event',2)===$before,'settings restored');
check(Booking::count()===$countBefore,'no Stripe fixture booking retained');
