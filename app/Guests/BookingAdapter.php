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
    public function options(int $id): array {return Options::effective($this->store->read('calendar_event',$id)['values']['guest_options']??[]);}
    public function register(): void
    {
        add_filter('fluent_booking/booking_data', function($data) {$this->paymentContext=null;return $data;},1);

        /*
         * FluentBooking builds the draft order before invoking the payment
         * provider. Prepare the frozen participant lines here so that both the
         * draft order and Stripe receive the selected tariff(s), never the
         * complete native catalogue.
         */
        add_action('fluent_booking/pre_after_booking_pending', function($booking) {
            $this->preparePaymentContext($booking);
        },1);
        foreach(['stripe','offline'] as $method) {
            add_action('fluent_booking/payment/pay_order_with_'.$method,function($booking){
                // Covers payment retries, which do not always re-enter the
                // pending-booking lifecycle.
                $this->preparePaymentContext($booking);
            },1);
        }
        add_filter('fluent_booking/get_event_payment_settings',function($settings,$event){
            if($this->paymentContext && $this->paymentContext['event_id']===(int)$event->id) {
                $settings['items']=array_map(static fn($item)=>['title'=>$item['title'],'value'=>$item['cents']/100],$this->paymentContext['items']);
            }
            return $settings;
        },100,2);
        add_action('fluent_booking/author_landing_head', static function () { wp_print_styles(['fba-guests']); });
        add_action('fluent_booking/author_landing_footer', static function () { wp_print_scripts(['fba-guests']); });
        add_filter('fluent_booking/public_event_vars',[$this,'publicVars'],110,2);
        add_filter('fluent_booking/initialize_booking_data',function($data,$posted,$event){
            $extras=$posted['fba_extra_'.$event->id]??'[]';
            // The public AJAX handler passes raw, WP-slashed $_REQUEST. The REST
            // controller already cleans its request. Unslash only the public boundary,
            // exactly once, so JSON escapes in names and answers remain intact.
            if (doing_action('wp_ajax_fluent_cal_schedule_meeting') || doing_action('wp_ajax_nopriv_fluent_cal_schedule_meeting')) {
                $extras=wp_unslash($extras);
            }
            $data['_fba_extras']=$extras;
            $data['_fba_requested_count']=1+count((array)($posted['guests']??[]));
            return $data;
        },110,3);
        add_filter('fluent_booking/schedule_validation_rules_data', function($rules,$posted,$event) {if($this->options((int)$event->id)['enabled']) {unset($rules['rules']['guests']);} return $rules;},100,3);
        add_filter('fluent_booking/booking_data',[$this,'validate'],200,4);
        add_action('fluent_booking/after_booking_meta_update',[$this,'persist'],1,4);
        add_filter('fluent_booking/create_draft_order',[$this,'order'],100,4);
        add_action('fluent_booking/after_order_items_created',[$this,'orderItems'],100,4);
        (new BookingPresentation())->register();
        (new CalendarPresentation())->register();
        (new CalendarContacts())->register();
        (new SeatNotifications())->register();
        (new BookingProtection())->register();
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

    /** Restrict native payment items to the immutable items selected at booking. */
    private function preparePaymentContext($booking): void
    {
        $this->paymentContext=null;
        $snapshot=$booking->getMeta(self::META,[]);
        if (!is_array($snapshot) || !empty($snapshot['preserve_payments'])) {return;}
        if (isset($snapshot['currency']) && $snapshot['currency']!==\FluentBooking\App\Services\CurrenciesHelper::getGlobalCurrency()) {
            throw new \RuntimeException('La devise a changé depuis la réservation.');
        }
        if (!isset($snapshot['items']) || !is_array($snapshot['items'])) {return;}
        $this->paymentContext=['event_id'=>(int)$booking->event_id,'items'=>$snapshot['items']];
    }
    public function publicVars(array $vars,$event): array
    {
        $options=$this->options((int)$event->id);
        if(!$options['enabled']) {return $vars;}
        foreach($vars['form_fields'] as &$field) {if(($field['name']??'')==='guests') {$field['enabled']=false;$field['required']=false;}} unset($field);
        $vars['form_fields'][]=['name'=>'fba_extra_'.$event->id,'type'=>'text','label'=>'','required'=>false,'enabled'=>true,'system_defined'=>true];
        $catalogue=null;$configurationError='';
        try {$catalogue=$options['native_tariffs']?NativeTariffs::catalogue($event):null;}
        catch (\InvalidArgumentException $error) {$configurationError=$error->getMessage();}
        if ($catalogue) {
            // FluentBooking's Stripe component reads both payment_items and
            // the payment field. Keep its initial Payment Element aligned
            // with the first selectable tariff instead of the full catalogue.
            $initialItem=['title'=>$catalogue[0]['title'],'value'=>$catalogue[0]['cents']/100];
            $vars['slot']['total_payment']=$initialItem['value'];
            $vars['payment_items']=[$initialItem];
            foreach ($vars['form_fields'] as &$field) {
                if (($field['type']??'')==='payment') {$field['payment_items']=[$initialItem];}
            } unset($field);
        }
        $price=0;
        foreach($event->getPaymentItems() as $item) {$price+=(float)$item['value'];}
        $config=['structuredPayload'=>$options['native_tariffs'] || $options['allow_nonparticipating'],'allowNonparticipating'=>$options['allow_nonparticipating'] && $this->guestsAllowed($event),'preservePayments'=>!$options['pricing_enabled'],'error'=>$configurationError,'tariffs'=>$catalogue,'limit'=>$this->guestLimit($event),'nameMode'=>$options['name_mode'],'emailMode'=>$options['email_mode'],'fields'=>$options['fields'],'price'=>$options['per_person_price'], 'unit'=>$price,'currency'=>\FluentBooking\App\Services\CurrenciesHelper::getGlobalCurrency()];
        $entry=dirname(__DIR__,2).'/wk-fluent-multireservation.php';
        wp_enqueue_script('fba-guests',plugins_url('assets/public/guests.js',$entry),[],Plugin::VERSION.'.'.filemtime(dirname(__DIR__,2).'/assets/public/guests.js'),true);
        wp_enqueue_style('fba-guests',plugins_url('assets/public/guests.css',$entry),[],Plugin::VERSION.'.'.filemtime(dirname(__DIR__,2).'/assets/public/guests.css'));
        wp_add_inline_script('fba-guests','window.fbaGuestForms=window.fbaGuestForms||{};window.fbaGuestForms['.(int)$event->id.']='.wp_json_encode($config).';','before');
        return $vars;
    }
    private function guestsAllowed($event): bool
    {
        foreach ($event->getBookingFields() as $field) {
            if (($field['name']??'')==='guests') {return !empty($field['enabled']);}
        }
        return false;
    }
    private function guestLimit($event): int
    {
        $limit=1;
        foreach($event->getBookingFields() as $field) {
            if(($field['name']??'')==='guests' && !empty($field['enabled'])) {$limit=max(1,(int)($field['limit']??10));}
        }
        return min($limit,(int)$event->getMaxBookingPerSlot());
    }
    public function validate($data,$event,$custom,$input)
    {
        if(is_wp_error($data)) {return $data;}
        try {
            $options=$this->options((int)$event->id);
            if(!$options['enabled']) {return $data;}
            if(!Plugin::compatible() || !$event->isMultiGuestEvent() || $event->isRecurringEvent() || !is_string($data['start_time']??null)) {throw new \RuntimeException('Ces options nécessitent un événement de groupe sur un créneau unique, avec FluentBooking 2.4.x ou 2.5.x.');}
            if(\FluentBooking\App\Services\CurrenciesHelper::isZeroDecimal(\FluentBooking\App\Services\CurrenciesHelper::getGlobalCurrency())) {throw new \RuntimeException('Ce mode utilise pour le moment les devises à deux décimales.');}
            if(($event->getPaymentSettings()['multi_payment_enabled']??'no')==='yes') {throw new \RuntimeException('Utilisez un tarif de base unique pour ces options invités.');}
            if(($event->getPaymentSettings()['driver']??'native')!=='native') {throw new \RuntimeException('Ces options utilisent les paiements natifs FluentBooking, pas WooCommerce.');}
            if($event->isPaymentEnabled($data['slot_minutes']??null) && !in_array($data['payment_method']??'', ['stripe','offline'],true)) {throw new \RuntimeException('Ce mode prend en charge Stripe et le paiement hors ligne.');}
            if(!empty($input['coupon_codes']) || !empty($data['applied_coupons'])) {throw new \RuntimeException('Les coupons ne sont pas encore pris en charge avec ces options invités.');}
            $priced=(bool)array_filter($options['fields'],static fn($field)=>($field['pricing']??'none')!=='none');
            $paymentItems=$event->getPaymentItems();
            if($priced && (!$event->isPaymentEnabled($data['slot_minutes']??null) || count($paymentItems)!==1)) {
                throw new \RuntimeException('Les choix payants nécessitent un tarif de base unique et un paiement natif activé.');
            }
            $catalogue=$options['native_tariffs']?NativeTariffs::catalogue($event):null;
            $holderTariff='';$holderParticipates=true;
            if ($options['native_tariffs'] || $options['allow_nonparticipating']) {
                $raw=$input['_fba_extras']??'';
                if (!is_string($raw) || strlen($raw)>30000) {throw new \InvalidArgumentException('Informations de réservation invalides.');}
                $payload=json_decode($raw,true,16,JSON_THROW_ON_ERROR);
                if (!is_array($payload) || !is_array($payload['guests']??null) || !array_is_list($payload['guests'])) {throw new \InvalidArgumentException('Complétez les participants.');}
                $holderParticipates=Participation::requested($payload,$options['allow_nonparticipating'] && $this->guestsAllowed($event));
                $holderTariff=$payload['holder_tariff']??'';
                $input['_fba_extras']=wp_json_encode($payload['guests']);
            }
            $rows=Identity::request($data,$input,$options);
            if(!is_string($data['email']) || !is_email($data['email']) || !is_string($data['first_name']) || trim($data['first_name'])==='') {
                throw new \RuntimeException('La personne qui réserve doit indiquer un nom et un courriel valide.');
            }
            $answers=Options::answers($rows,$rows,$options['fields']);
            $count=Participation::count(count($answers),$holderParticipates);
            if($count>$this->guestLimit($event)) {
                throw new \RuntimeException('Le nombre de personnes dépasse la limite de cette réservation. Vérifiez les invités supplémentaires et le maximum autorisé.');
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
            if($used+$seats>(int)$event->getMaxBookingPerSlot()) {throw new \RuntimeException('Il ne reste pas assez de places pour cette réservation.');}
            $items=[];
            foreach($paymentItems as $item) {$items[]=['title'=>$item['title'],'cents'=>(int)round((float)$item['value']*100)];}
            $tariffQuote=null;
            if ($options['native_tariffs'] && $catalogue) {
                foreach ($rows as $i=>$row) {$rows[$i]['tariff']=$row['tariff']??'';}
                $tariffQuote=NativeTariffs::quote($catalogue,$holderTariff,$rows,$holderParticipates);
                foreach ($answers as $i=>&$answer) {$answer['tariff']=$tariffQuote['people'][$i+($holderParticipates?1:0)];} unset($answer);
            }
            if ($options['native_tariffs']) {
                $quantity=1;
                $quote=['total'=>$tariffQuote['total']??0,'guests'=>array_column(array_slice($tariffQuote['people']??[],$holderParticipates?1:0),'cents')];
                $items=array_map(static fn($person,$index)=>['title'=>($holderParticipates && $index===0?'Contact principal':'Participant '.($index+($holderParticipates?0:1))).' — '.$person['title'],'cents'=>$person['cents']],$tariffQuote['people']??[],array_keys($tariffQuote['people']??[]));
            }
            else {
                $quote=Pricing::quote(array_sum(array_column($items,'cents')),$options,$answers);
                if($priced) {$items=[['title'=>'Réservation — '.$count.' personne(s)','cents'=>$quote['total']]];$quantity=1;}
            }
            $token=wp_generate_uuid4();
            $this->pending[$token]=['holder_participates'=>$holderParticipates,'preserve_payments'=>!$options['pricing_enabled'],'holder_tariff'=>$holderParticipates?($tariffQuote['people'][0]??null):null,'attached'=>true,'guest_amounts'=>$quote['guests'],'count'=>$count,'quantity'=>$quantity,'seats'=>$seats,'guests'=>$answers,'fields'=>$options['fields'],'currency'=>\FluentBooking\App\Services\CurrenciesHelper::getGlobalCurrency(),'items'=>$items,'lock'=>$lock];
            $data['_fba_token']=$token;
            $data['quantity']=$quantity;
            return $data;
        } catch(\Throwable $error) {
            $this->unlock();
            $message=$error instanceof \JsonException ? 'Les informations des participants sont invalides. Rechargez la page puis réessayez.' : $error->getMessage();
            return new \WP_Error('fba_guests_refused',$message,['status'=>422]);
        }
    }
    public function persist($booking,$data,$custom,$event): void
    {
        $token=$data['_fba_token']??'';
        if(!isset($this->pending[$token])) {return;}
        $snapshot=$this->pending[$token];
        AttachedSeats::create($booking,$snapshot);
        unset($snapshot['lock']);
        Helper::updateBookingMeta($booking->id,self::META,$snapshot);
        Helper::updateBookingMeta($booking->id,'quantity',$snapshot['quantity']);
        // Native e-mail templates resolve booking.custom.* from this metadata.
        // Keep the summary separate from user-facing form fields and never add
        // it to shared calendar payloads.
        $customFields=(array)$booking->getMeta('custom_fields_data',[]);
        $customFields['fba_participants_email']=(new BookingPresentation())->emailSummary($booking);
        Helper::updateBookingMeta($booking->id,'custom_fields_data',$customFields);
        if($booking->getMeta(self::META,[])!==$snapshot) {throw new \RuntimeException('Les informations des invités n’ont pas pu être enregistrées.');}
        $lock=$this->pending[$token]['lock'];unset($this->pending[$token]);$this->unlock($lock);
    }
    public function order(array $order,$booking,$event,$data): array
    {
        $snapshot=$booking->getMeta(self::META,[]);
        if(!isset($snapshot['quantity']) || !empty($snapshot['preserve_payments'])) {return $order;}
        if($order['currency']!==$snapshot['currency']) {throw new \RuntimeException('La devise de cette réservation a changé. Vérification nécessaire.');}
        $order['subtotal']=array_sum(array_column($snapshot['items'],'cents'))*$snapshot['quantity'];
        $order['total_amount']=$order['subtotal'];$order['discount_total']=0;
        return $order;
    }
    public function orderItems($order,$booking,$event,$data): void
    {
        $snapshot=$booking->getMeta(self::META,[]);
        if(!isset($snapshot['quantity']) || !empty($snapshot['preserve_payments'])) {return;}
        $items=$order->items()->orderBy('id')->get();
        // Replace draft lines before the native transaction is created. One frozen line per person.
        foreach ($items as $item) {$item->delete();}
        foreach ($snapshot['items'] as $item) {
            $order->items()->create(['booking_id'=>$booking->id,'item_name'=>$item['title'],'item_price'=>$item['cents'],'quantity'=>$snapshot['quantity'],'item_total'=>$item['cents']*$snapshot['quantity'],'rate'=>1,'type'=>'single','line_meta'=>wp_json_encode($item)]);
        }
    }
    public function unlock(?string $one=null): void
    {
        global $wpdb;
        foreach(array_keys($this->locks) as $lock) {if($one!==null && $one!==$lock){continue;}$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));unset($this->locks[$lock]);}
    }
}
