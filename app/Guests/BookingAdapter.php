<?php
namespace WaasKit\FluentBooking\Guests;

use FluentBooking\App\Models\Booking;
use FluentBooking\App\Services\Helper;
use WaasKit\FluentBooking\Integrations\FluentBooking\ConfigurationStore;
use WaasKit\FluentBooking\Plugin;

/** Use native bookings for seats and native orders for prices. No parallel stock table. */
final class BookingAdapter
{
    public const META='fba_guests_v1';
    private array $pending=[];
    private array $locks=[];
    private ?array $paymentContext=null;
    public function __construct(private ConfigurationStore $store) {}
    public function options(int $id): array {return Options::validate($this->store->read('calendar_event',$id)['values']['guest_options']??[]);}
    public function register(): void
    {
        add_filter('fluent_booking/booking_data', function($data) {$this->paymentContext=null;return $data;},1);
        foreach(['stripe','offline'] as $method) {
            add_action('fluent_booking/payment/pay_order_with_'.$method,function($booking){
                $snapshot=$booking->getMeta(self::META,[]);
                if(isset($snapshot['currency']) && $snapshot['currency']!==\FluentBooking\App\Services\CurrenciesHelper::getGlobalCurrency()) {throw new \RuntimeException('La devise a changé depuis la réservation.');}
                $this->paymentContext=isset($snapshot['items'])?['event_id'=>(int)$booking->event_id,'items'=>$snapshot['items']]:null;
            },1);
        }
        add_filter('fluent_booking/get_event_payment_settings',function($settings,$event){
            if($this->paymentContext && $this->paymentContext['event_id']===(int)$event->id) {
                $settings['items']=array_map(static fn($item)=>['title'=>$item['title'],'value'=>$item['cents']/100],$this->paymentContext['items']);
            }
            return $settings;
        },100,2);
        add_filter('fluent_booking/public_event_vars',[$this,'publicVars'],110,2);
        add_filter('fluent_booking/initialize_booking_data',function($data,$posted,$event){$data['_fba_extras']=$posted['fba_extra_'.$event->id]??'[]';return $data;},110,3);
        add_filter('fluent_booking/booking_data',[$this,'validate'],200,4);
        add_action('fluent_booking/after_booking_meta_update',[$this,'persist'],1,4);
        add_filter('fluent_booking/create_draft_order',[$this,'order'],100,4);
        add_action('fluent_booking/after_order_items_created',[$this,'orderItems'],100,4);
        add_action('fluent_booking/booking_details_header', [$this,'summary']);
        register_shutdown_function([$this,'unlock']);
        Booking::updated([AttachedSeats::class,'sync']);
        Booking::deleting([AttachedSeats::class,'remove']);
        Booking::updating(static function($booking){
            if(!$booking->getMeta(self::META,[])) {return;}
            foreach(['start_time','end_time','calendar_id','event_id'] as $key) {
                if($booking->isDirty($key)) {throw new \RuntimeException('Le report de cette réservation avec invités doit être traité manuellement.');}
            }
            if($booking->isDirty('status') && in_array($booking->status,['pending','scheduled'],true) && !in_array($booking->getOriginal('status'),['pending','scheduled'],true)) {throw new \RuntimeException('La réactivation nécessite une vérification des places.');}
        });
    }
    public function publicVars(array $vars,$event): array
    {
        $options=$this->options((int)$event->id);
        if(!$options['enabled']) {return $vars;}
        $attached=Identity::attached($options);
        $limit=10;
        foreach($vars['form_fields'] as &$field) {if(($field['name']??'')==='guests') {$limit=empty($field['enabled'])?1:(int)($field['limit']??10); if($attached) {$field['enabled']=false;$field['required']=false;}}} unset($field);
        $vars['form_fields'][]=['name'=>'fba_extra_'.$event->id,'type'=>'text','label'=>'','required'=>false,'enabled'=>true,'system_defined'=>true];
        $price=0;
        foreach($event->getPaymentItems() as $item) {$price+=(float)$item['value'];}
        $config=['attached'=>$attached,'limit'=>$limit,'nameMode'=>$options['name_mode'],'emailMode'=>$options['email_mode'],'fields'=>$options['fields'],'seats'=>$options['per_person_seats'],'price'=>$options['per_person_price'], 'unit'=>$price,'currency'=>\FluentBooking\App\Services\CurrenciesHelper::getGlobalCurrency()];
        $entry=dirname(__DIR__,2).'/wk-fluent-multireservation.php';
        wp_enqueue_script('fba-guests',plugins_url('assets/public/guests.js',$entry),[],Plugin::VERSION,true);
        wp_enqueue_style('fba-guests',plugins_url('assets/public/guests.css',$entry),[],Plugin::VERSION);
        wp_add_inline_script('fba-guests','window.fbaGuestForms=window.fbaGuestForms||{};window.fbaGuestForms['.(int)$event->id.']='.wp_json_encode($config).';','before');
        return $vars;
    }
    public function validate($data,$event,$custom,$input)
    {
        if(is_wp_error($data)) {return $data;}
        try {
            $options=$this->options((int)$event->id);
            if(!$options['enabled']) {return $data;}
            if(!Plugin::compatible() || !$event->isMultiGuestEvent() || $event->isRecurringEvent() || !is_string($data['start_time']??null)) {throw new \RuntimeException('Ces options nécessitent un événement de groupe sur un créneau unique, avec FluentBooking 2.4.x.');}
            if(\FluentBooking\App\Services\CurrenciesHelper::isZeroDecimal(\FluentBooking\App\Services\CurrenciesHelper::getGlobalCurrency())) {throw new \RuntimeException('Ce mode utilise pour le moment les devises à deux décimales.');}
            if(($event->getPaymentSettings()['multi_payment_enabled']??'no')==='yes') {throw new \RuntimeException('Utilisez un tarif de base unique pour ces options invités.');}
            if(($event->getPaymentSettings()['driver']??'native')!=='native') {throw new \RuntimeException('Ces options utilisent les paiements natifs FluentBooking, pas WooCommerce.');}
            if($event->isPaymentEnabled($data['slot_minutes']??null) && !in_array($data['payment_method']??'', ['stripe','offline'],true)) {throw new \RuntimeException('Ce mode prend en charge Stripe et le paiement hors ligne.');}
            if(!empty($input['coupon_codes']) || !empty($data['applied_coupons'])) {throw new \RuntimeException('Les coupons ne sont pas encore pris en charge avec ces options invités.');}
            $attached=Identity::attached($options);
            $attachedRows=$attached?Identity::rows($input['_fba_extras']??'[]',$options):[];
            if($attached && (is_array($data['email']) || !empty($input['additional_guests']))) {throw new \RuntimeException('Utilisez le formulaire d’invités de cette réservation.');}
            if(array_filter($options['fields'],static fn($f)=>($f['pricing']??'none')!=='none') && !$event->isPaymentEnabled($data['slot_minutes']??null)) {throw new \RuntimeException('Activez un tarif de réservation et un paiement natif avant d’utiliser des choix payants.');}
            if(array_filter($options['fields'],static fn($f)=>($f['pricing']??'none')!=='none') && count($event->getPaymentItems())!==1) {throw new \RuntimeException('Les choix tarifaires nécessitent un seul tarif de base FluentBooking.');}
            $emails=(array)$data['email'];$names=(array)$data['first_name'];
            if(count($emails)!==count($names) || !$emails) {throw new \RuntimeException('Identités des invités invalides.');}
            $count=count($emails);$holder=array_key_last($emails);$guests=[];
            foreach($emails as $i=>$email) {
                if(!is_string($email) || !is_email($email) || !is_string($names[$i]) || trim($names[$i])==='') {throw new \RuntimeException('Chaque personne doit avoir un nom et un e-mail valide.');}
                if($i!==$holder) {$guests[]=['email'=>$email,'name'=>$names[$i]];}
            }
            if(count(array_unique(array_map('strtolower',$emails)))!==$count) {throw new \RuntimeException('Utilisez un e-mail distinct pour chaque personne.');}
            if((int)($input['_fba_requested_count']??$count)>$count) {throw new \RuntimeException('Des invités dépassent la limite ou les places restantes. Aucun invité ne doit être retiré silencieusement.');}
            $raw=$input['_fba_extras']??'[]';
            if(!is_string($raw) || strlen($raw)>30000) {throw new \RuntimeException('Informations invités invalides.');}
            $rows=json_decode($raw,true,16,JSON_THROW_ON_ERROR);
            if(!is_array($rows)) {throw new \RuntimeException('Informations invités invalides.');}
            if(!$attached && $raw!=='[]' && count($rows)!==count($guests)) {throw new \RuntimeException('Vérifiez les informations de chaque invité.');}
            if(!$options['fields']) {$rows=array_map(static fn($g)=>['email'=>$g['email'],'fields'=>[]],$guests);}
            $answers=$attached?[]:Options::answers($rows,$guests,$options['fields']);
            if($attached) {
                $guests=$attachedRows; $count=count($guests)+1;
                $answers=Options::answers($attachedRows,$guests,$options['fields']);
                $effective=$this->store->effective('calendar_event',(int)$event->id);
                $maximum=$effective['enabled']['value']?$effective['max_participants']['value']:0;
                if($maximum>0 && $count>$maximum) {throw new \RuntimeException('La limite de personnes par réservation est dépassée.');}
                $nativeLimit=1;
                foreach($event->getBookingFields() as $field) {if(($field['name']??'')==='guests' && !empty($field['enabled'])) {$nativeLimit=(int)($field['limit']??10);}}
                if($count>$nativeLimit) {throw new \RuntimeException('Activez les invités supplémentaires et vérifiez leur limite dans les questions FluentBooking.');}
            }
            $quantity=$options['per_person_price']?$count:1;
            // Serialize admission on this event and exact native slot. Native records remain stock.
            global $wpdb;
            $lock='fba_'.substr(hash('sha256',DB_NAME.$wpdb->prefix.$event->id.$data['start_time']),0,48);
            if(!isset($this->locks[$lock])) {
                if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,5)',$lock))!==1) {throw new \RuntimeException('Une réservation est en cours. Réessayez.');}
                $this->locks[$lock]=true;
            }
            $used=Booking::where('event_id',$event->id)->where('start_time',$data['start_time'])->whereIn('status',['pending','scheduled','approved','completed'])->count();
            $seats=$count;
            if(($options['per_person_seats'] || $attached) && $used+$seats>(int)$event->getMaxBookingPerSlot()) {throw new \RuntimeException('Il ne reste pas assez de places pour cette réservation.');}
            $items=[];
            foreach($event->getPaymentItems() as $item) {$items[]=['title'=>$item['title'],'cents'=>(int)round((float)$item['value']*100)];}
            $quote=Pricing::quote(array_sum(array_column($items,'cents')),$options,$answers);
            $priced=(bool)array_filter($options['fields'],static fn($f)=>($f['pricing']??'none')!=='none');
            if($priced) {$items=[['title'=>'Réservation — '.$count.' personne(s)','cents'=>$quote['total']]];$quantity=1;}
            $token=wp_generate_uuid4();
            $this->pending[$token]=['attached'=>$attached,'guest_amounts'=>$quote['guests'],'holder'=>$emails[$holder],'count'=>$count,'quantity'=>$quantity,'seats'=>$seats,'guests'=>$answers,'fields'=>$options['fields'],'currency'=>\FluentBooking\App\Services\CurrenciesHelper::getGlobalCurrency(),'items'=>$items,'lock'=>$lock];
            $data['_fba_token']=$token;
            $data['quantity']=$quantity;
            return $data;
        } catch(\Throwable $error) { $this->unlock();return new \WP_Error('fba_guests_refused',$error->getMessage(),['status'=>422]); }
    }
    public function persist($booking,$data,$custom,$event): void
    {
        $token=$data['_fba_token']??'';
        if(!isset($this->pending[$token])) {return;}
        $snapshot=$this->pending[$token];
        // Mark all child seats too, so unsupported reschedules cannot desynchronize a party.
        if(strtolower($booking->email)!==strtolower($snapshot['holder'])) {
            $own=array_values(array_filter($snapshot['guests'],static fn($g)=>strtolower($g['email'])===strtolower($booking->email)));
            Helper::updateBookingMeta($booking->id,self::META,['seat'=>true,'guests'=>$own,'fields'=>$snapshot['fields']]);return;
        }
        if(!empty($snapshot['attached'])) {AttachedSeats::create($booking,$snapshot);}
        unset($snapshot['holder'],$snapshot['lock']);
        Helper::updateBookingMeta($booking->id,self::META,$snapshot);
        Helper::updateBookingMeta($booking->id,'quantity',$snapshot['quantity']);
        if($booking->getMeta(self::META,[])!==$snapshot) {throw new \RuntimeException('Les informations des invités n’ont pas pu être enregistrées.');}
        $lock=$this->pending[$token]['lock'];unset($this->pending[$token]);$this->unlock($lock);
    }
    public function order(array $order,$booking,$event,$data): array
    {
        $snapshot=$booking->getMeta(self::META,[]);
        if(!isset($snapshot['quantity'])) {return $order;}
        if($order['currency']!==$snapshot['currency']) {throw new \RuntimeException('La devise de cette réservation a changé. Vérification nécessaire.');}
        $order['subtotal']=array_sum(array_column($snapshot['items'],'cents'))*$snapshot['quantity'];
        $order['total_amount']=$order['subtotal'];$order['discount_total']=0;
        return $order;
    }
    public function orderItems($order,$booking,$event,$data): void
    {
        $snapshot=$booking->getMeta(self::META,[]);
        if(!isset($snapshot['quantity'])) {return;}
        $items=$order->items()->orderBy('id')->get();
        if(count($items)!==count($snapshot['items'])) {throw new \RuntimeException('Les lignes de paiement ont changé. Vérification nécessaire.');}
        foreach($items as $i=>$item) {$item->item_name=$snapshot['items'][$i]['title'];$item->item_price=$snapshot['items'][$i]['cents'];$item->quantity=$snapshot['quantity'];$item->item_total=$item->item_price*$snapshot['quantity'];$item->save();}
    }
    public function summary($booking): void
    {
        $snapshot=$booking->getMeta(self::META,[]);
        if(empty($snapshot['guests'])) {return;}
        echo '<section class="fba-booked-guests"><h3>Invités</h3><ul>';
        $labels=array_column($snapshot['fields'],'label','id');
        foreach($snapshot['guests'] as $guest) {
            echo '<li>'.esc_html($guest['name']);
            foreach($guest['fields'] as $id=>$value) {if($value!=='') {echo ' · '.esc_html(($labels[$id]??$id).' : '.$value);}}
            echo '</li>';
        }
        echo '</ul></section>';
    }

    public function unlock(?string $one=null): void
    {
        global $wpdb;
        foreach(array_keys($this->locks) as $lock) {if($one!==null && $one!==$lock){continue;}$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));unset($this->locks[$lock]);}
    }
}
