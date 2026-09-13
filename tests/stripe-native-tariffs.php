<?php
/**
 * Exercises native web booking -> order -> Stripe PaymentIntent preparation.
 * All outgoing HTTP and email are intercepted; fixtures are rolled back.
 */
define('DOING_AJAX', true);
ob_start();
$path=getenv('WAASKIT_WP_PATH');
if(!$path){throw new RuntimeException('Local WordPress required.');}
require $path.'/wp-load.php';
if(!in_array(parse_url(home_url(),PHP_URL_HOST),['localhost','127.0.0.1'],true)){throw new RuntimeException('Local only.');}

use FluentBooking\App\Models\Booking;
use FluentBooking\App\Models\CalendarSlot;
use FluentBooking\App\Services\BookingService;
use WaasKit\FluentBooking\Guests\NativeTariffs;
use WaasKit\FluentBooking\Guests\Options;
use WaasKit\FluentBooking\Integrations\FluentBooking\ConfigurationStore;

function check($ok,$message){if(!$ok){throw new RuntimeException($message);}echo "PASS {$message}\n";}
add_filter('pre_wp_mail','__return_true');
final class StripeTestResponse extends Error {}
add_filter('wp_die_ajax_handler', static fn() => static function () {throw new StripeTestResponse();});
$sent = [];
$intents=[]; $cancelCalls=0; $failCancel=false;
add_filter('pre_http_request', static function ($pre, $args, $url) use (&$sent, &$intents, &$cancelCalls, &$failCancel) {
    $reply=static fn($body,$code=200)=>['headers'=>[], 'response'=>['code'=>$code,'message'=>'Fixture'], 'body'=>wp_json_encode($body)];
    if ($url === 'https://api.stripe.com/v1/payment_intents') {
        parse_str($args['body'], $body); $sent[]=$body;
        $id='pi_localfixture'.$body['metadata']['booking_id'];
        $intents[$id]=['id'=>$id,'client_secret'=>$id.'_secret_fixture','amount'=>(int)$body['amount'],
            'currency'=>strtolower($body['currency']),'status'=>'requires_payment_method','amount_received'=>0,
            'metadata'=>$body['metadata']];
        return $reply($intents[$id]);
    }
    if (preg_match('~^https://api.stripe.com/v1/payment_intents/(pi_[A-Za-z0-9]+)(/cancel)?$~', $url, $match) && isset($intents[$match[1]])) {
        if (!empty($match[2])) {
            $cancelCalls++;
            if ($failCancel) return $reply(['error'=>['message'=>'Simulated conflict']],409);
            $intents[$match[1]]['status']='canceled';
        }
        return $reply($intents[$match[1]]);
    }
    return new WP_Error('blocked', 'No network in tests');
}, 999, 3);
add_filter('pre_option_fluent_booking_payment_settings_stripe', static fn() => [
    'is_active'=>'yes','provider'=>'api_keys','payment_mode'=>'test','checkout_mode'=>'onsite',
    'test_publishable_key'=>'pk_test_local_fixture','test_secret_key'=>''
]);

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
    $options['allow_nonparticipating']=true;
    $store->save('calendar_event',2,['guest_options'=>$options],$store->read('calendar_event',2)['revision']);
    foreach ([[true,[],7000], [true,[0],14000], [true,[1],12500], [true,[1,0],19500], [false,[1],5500]] as $case => [$attends,$choices,$expected]) {
        $guests=array_map(static fn($choice) => ['name'=>'Participant test','tariff'=>$catalogue[$choice]['id'],'fields'=>['age'=>'8']],$choices);
        ob_start();
        try { BookingService::createBooking([
            'email'=>'stripe-test@example.invalid','first_name'=>'Camille','last_name'=>'Test',
            'start_time'=>'2032-03-'.(14+$case).' 09:00:00','end_time'=>'2032-03-'.(14+$case).' 09:30:00',
            'person_time_zone'=>'UTC','source'=>'web','status'=>'pending','payment_method'=>'stripe',
            '_fba_extras'=>wp_json_encode(['holder_participates'=>$attends,'holder_tariff'=>$adult,'guests'=>$guests]),
        ],$event,['payment_method'=>'stripe','quantity'=>999]); }
        catch (StripeTestResponse $response) {}
        finally {$json=ob_get_clean();}
        $response=json_decode($json,true,512,JSON_THROW_ON_ERROR);
        check(!empty($response['success']), 'native web booking returns a Stripe checkout response');
        $booking=Booking::findOrFail($response['data']['data']['id']);
        $order=FluentBookingPro\App\Models\Order::where('parent_id',$booking->id)->firstOrFail();
        check((int)$order->total_amount===$expected && (int)$order->items()->sum('item_total')===$expected, "order and lines = {$expected} cents, forged quantity ignored");
        check((int)end($sent)['amount']===$expected, "actual native PaymentIntent request = {$expected} cents");
        check((int)$response['data']['data']['payment_args']['amount']===$expected && (int)$response['data']['intent']['amount']===$expected, 'response, intent and stored order agree');
        $edit=new WaasKit\FluentBooking\Payments\EditCheckout();
        $intentId=$response['data']['intent']['id'];
        $secret=$response['data']['intent']['client_secret'];
        $cancel=static fn($hash=null,$clientSecret=null)=>$edit->cancel($booking->id,$intentId,$clientSecret??$secret,$hash??$booking->hash);
        $refuse=static function($action,$label) use($booking) {
            $rejected=false;try{$action();}catch(RuntimeException $e){$rejected=true;}
            check($rejected && Booking::find($booking->id)->status==='pending',$label);
        };
        if ($case===0) {
            $refuse(static fn()=>$cancel('invalid'), 'wrong booking capability cannot cancel');
            $refuse(static fn()=>$cancel(null,$intentId.'_secret_wrong'), 'wrong Stripe secret cannot cancel');
            $intents[$intentId]['metadata']['ref_id']='another-booking';
            $refuse($cancel,'Stripe intent must belong to this booking');
            $intents[$intentId]['metadata']['ref_id']=$booking->hash;
            foreach (['processing','succeeded','requires_capture'] as $status) {
                $intents[$intentId]['status']=$status;
                $refuse($cancel,'cannot edit Stripe status '.$status);
            }
            $intents[$intentId]['status']='requires_payment_method';
            $failCancel=true;
            $refuse($cancel,'Stripe cancellation conflict keeps reservation and seats');
            $failCancel=false;
        }
        $cancel();
        check(Booking::find($booking->id)->status==='cancelled', 'unpaid attempt cancelled after Stripe confirmation');
        check(Booking::where('parent_id',$booking->id)->where('status','!=','cancelled')->count()===0,'all attached seats released');
        check(FluentBookingPro\App\Models\Order::find($order->id)->status==='cancelled','draft order closed');
        $calls=$cancelCalls; $cancel();
        check($cancelCalls===$calls,'retry after lost response is idempotent');

    }

} finally {
    $wpdb->query('ROLLBACK');wp_cache_flush();
}
check($store->read('calendar_event',2)===$before,'settings restored');
check(Booking::count()===$countBefore,'no Stripe fixture booking retained');

ob_end_flush();
