<?php
namespace WaasKit\FluentBooking\Integrations\FluentBooking;

use WaasKit\FluentBooking\Domain\BookingProfile;
use WaasKit\FluentBooking\Domain\Party;
use WaasKit\FluentBooking\Infrastructure\CapacityStore;
use WaasKit\FluentBooking\Plugin;
use FluentBooking\App\Models\Booking;
use FluentBooking\App\Models\CalendarSlot;
use FluentBooking\App\Services\CurrenciesHelper;
use FluentBooking\App\Services\Helper;

final class BookingModules
{
    public const META = 'fba_party_v1';
    private array $quotes = [];
    private array $pending = [];
    private array $payment = [];
    public function __construct(private ConfigurationStore $config, private CapacityStore $capacity) {}
    private function quoteKey(int $id): string { return get_current_blog_id() . ':' . $id; }
    public function profile(int $id): array { return BookingProfile::validate($this->config->effective('calendar_event', $id)['booking_profile']['value']); }
    public function register(): void
    {
        add_action('admin_init', function () { if (current_user_can('manage_options')) { $this->capacity->install(); } });
        add_filter('fluent_booking/available_slots_for_view', [$this, 'availability'], 100, 5);
        add_filter('fluent_booking/booking_fields', [$this, 'fields'], 30, 2);
        add_filter('fluent_booking/initialize_booking_data', [$this, 'initialize'], 100, 3);
        add_filter('fluent_booking/booking_data', function ($data, $event) {
            unset($this->quotes[$this->quoteKey((int) $event->id)]);
            return $data;
        }, 1, 2);
        add_filter('fluent_booking/booking_data', [$this, 'validate'], 200, 4);
        add_action('fluent_booking/after_booking_meta_update', [$this, 'persist'], 1, 4);
        add_filter('fluent_booking/get_event_payment_settings', [$this, 'paymentSettings'], 100, 2);
        foreach (['stripe','offline'] as $method) {
            add_action('fluent_booking/payment/pay_order_with_' . $method, [$this, 'primePayment'], 1);
        }
        add_filter('fluent_booking/create_draft_order', function ($order, $booking) {
            $quote = $booking->getMeta(self::META, []);
            if (!empty($quote['pricing'])) { $order['subtotal'] = $quote['subtotal']; $order['total_amount'] = $quote['subtotal']; $order['discount_total'] = 0; }
            return $order;
        }, 100, 2);
        add_action('fluent_booking/after_order_items_created', function ($order, $booking) {
            $quote = $booking->getMeta(self::META, []);
            if (empty($quote['pricing'])) { return; }
            $items = $order->items()->where('type', 'single')->orderBy('id')->get();
            if (count($items) !== count($quote['lines'])) { throw new \RuntimeException('Lignes de paiement incohérentes.'); }
            foreach ($items as $i=>$item) {
                $item->item_price = $quote['lines'][$i]['total']; $item->item_total = $quote['lines'][$i]['total']; $item->quantity = 1; $item->save();
            }
        }, 100, 2);
        add_filter('fluent-booking/payment/stripe_checkout_session_args', [$this, 'checkout'], 100);
        add_action('fluent_booking/booking_details_header', [$this, 'summary']);
        add_action('rest_api_init', [$this, 'routes']);
        Booking::creating(function ($booking) {
            if (!$this->profile((int) $booking->event_id)['enabled']) { return; }
            foreach ($this->pending as &$pending) {
                if ($pending['blog_id'] === get_current_blog_id() && $pending['event_id'] === (int) $booking->event_id && !$pending['claimed']) { $pending['claimed'] = true; return; }
            }
            throw new \RuntimeException('Ce parcours doit fournir une liste validée de participants.', 422);
        });
        // Changing a paid group's time/quantity needs a dedicated amendment workflow.
        Booking::updating(static function ($booking) {
            if (!$booking->getMeta(self::META, [])) { return; }
            foreach (['start_time','end_time','event_id','calendar_id'] as $key) {
                if ($booking->isDirty($key)) { throw new \RuntimeException('Le report des réservations avec participants n’est pas encore disponible.', 422); }
            }
            if ($booking->isDirty('status') && in_array($booking->status, ['pending','scheduled'], true) && !in_array($booking->getOriginal('status'), ['pending','scheduled'], true)) {
                throw new \RuntimeException('Une réservation libérée ne peut pas être réactivée automatiquement.', 422);
            }
        });
    }
    public function initialize($data, $posted, $event)
    {
        $key = 'fba_party_' . $event->id;
        $custom = is_array($posted['custom_fields'] ?? null) ? $posted['custom_fields'] : [];
        $data['_fba_party'] = $posted[$key] ?? ($custom[$key] ?? null);
        return $data;
    }
    public function validate($data, $event, $custom, $input)
    {
        if (is_wp_error($data)) { return $data; }
        try {
            $this->capacity->lock();
            wp_cache_delete(ConfigurationStore::KEY, 'options');
            wp_cache_delete('notoptions', 'options');
            $profile = $this->profile((int) $event->id);
            if (!$profile['enabled']) { $this->capacity->unlock(); return $data; }
            if (!Plugin::compatible()) { throw new \RuntimeException('Version FluentBooking non prise en charge.'); }
            if (!$event->isMultiGuestEvent() || is_array($data['start_time']) || is_array($data['email']) || !empty($input['additional_guests']) || !empty($input['recurring_count'])) { throw new \InvalidArgumentException('Ce module nécessite un événement de groupe, un seul créneau et le formulaire Participants.'); }
            $raw = $input['_fba_party'] ?? null;
            if (!is_string($raw) || strlen($raw) > 100000) { throw new \InvalidArgumentException('La liste des participants est requise.'); }
            $rows = json_decode($raw, true, 20, JSON_THROW_ON_ERROR);
            if (!is_array($rows)) { throw new \InvalidArgumentException('Liste des participants invalide.'); }
            $start = $data['start_time']; $end = $data['end_time'];
            if (!is_string($start) || !is_string($end) || $start >= $end || $start >= '2038-01-19 03:14:07' || $end >= '2038-01-19 03:14:07') { throw new \InvalidArgumentException('Créneau hors limites du stockage natif.'); }
            $date = new \DateTimeImmutable($start, new \DateTimeZone('UTC'));
            if ($date->format('Y-m-d H:i:s') !== $start) { throw new \InvalidArgumentException('Date invalide.'); }
            // Age is evaluated on the event day in the calendar's timezone.
            $date = $date->setTimezone(new \DateTimeZone($event->calendar->author_timezone ?: 'UTC'));
            $quote = Party::evaluate($rows, $profile, $date);
            $legacy = $this->config->effective('calendar_event', (int) $event->id);
            if ($legacy['enabled']['value'] && $legacy['max_participants']['value'] > 0 && $quote['count'] > $legacy['max_participants']['value']) { throw new \InvalidArgumentException('Maximum de participants dépassé.'); }
            $quote['pricing'] = $profile['pricing'];
            $quote['currency'] = CurrenciesHelper::getGlobalCurrency();
            if ($profile['pricing']) {
                $native = $event->getPaymentSettings();
                if (($native['driver'] ?? 'native') !== 'native' || !defined('FLUENT_BOOKING_PRO_VERSION') || version_compare(FLUENT_BOOKING_PRO_VERSION, '2.5.0', '>=')) { throw new \InvalidArgumentException('Tarification disponible avec les paiements natifs FluentBooking Pro 2.4.x uniquement.'); }
                if (CurrenciesHelper::isZeroDecimal($quote['currency'])) { throw new \InvalidArgumentException('La tarification nécessite une devise à deux décimales.'); }
                if (!empty($data['coupon_codes'])) { throw new \InvalidArgumentException('Les coupons ne sont pas encore disponibles avec les tarifs par type.'); }
                if ($quote['subtotal'] > 0 && (!in_array($data['payment_method'] ?? '', ['stripe','offline'], true) || ($native['enabled'] ?? 'no') !== 'yes' || ($native[($data['payment_method'] ?? '') . '_enabled'] ?? 'no') !== 'yes')) { throw new \InvalidArgumentException('Activez Stripe ou le paiement hors ligne pour cet événement.'); }
                if ($quote['subtotal'] === 0) { $data['payment_method'] = ''; $data['payment_status'] = ''; $data['status'] = $event->isConfirmationRequired($start) ? 'pending' : 'scheduled'; }
                $this->quotes[$this->quoteKey((int) $event->id)] = $quote;
            }
            [$pool, $ids, $limit] = $this->pool($event, $profile);
            $token = $this->capacity->hold((int) $event->id, $pool, $ids, $start, $end, $quote['units'], $limit);
            $key = wp_generate_uuid4();
            $this->pending[$key] = ['quote'=>$quote, 'token'=>$token, 'event_id'=>(int) $event->id, 'claimed'=>false, 'blog_id'=>get_current_blog_id()];
            $data['_fba_key'] = $key;
            return $data;
        } catch (\Throwable $error) {
            $this->capacity->unlock(); unset($this->quotes[$this->quoteKey((int) $event->id)]);
            return new \WP_Error('fba_booking_refused', $error->getMessage(), ['status'=>422]);
        }
    }
    public function availability(array $days, $event, $calendar, $timezone, $duration): array
    {
        try {
            $profile = $this->profile((int) $event->id);
            if (!$profile['enabled'] || !$days) { return $days; }
            [$pool,$ids,$limit] = $this->pool($event,$profile);
            $utc = new \DateTimeZone('UTC'); $zone = new \DateTimeZone($timezone); $bounds=[];
            foreach ($days as $day=>$slots) {
                foreach ($slots as $i=>$slot) {
                    $start=(new \DateTimeImmutable($slot['start'],$zone))->setTimezone($utc)->format('Y-m-d H:i:s');
                    $end=(new \DateTimeImmutable($slot['end'],$zone))->setTimezone($utc)->format('Y-m-d H:i:s');
                    $bounds[$day][$i]=[$start,$end];
                }
            }
            $starts=[]; $ends=[];
            foreach($bounds as $slots) {foreach($slots as [$a,$b]) {$starts[]=$a;$ends[]=$b;}}
            if(!$starts) {return [];}
            $rows=$this->capacity->intervals($pool,$ids,min($starts),max($ends));
            foreach($bounds as $day=>$slots) {
                foreach($slots as $i=>[$start,$end]) {
                    $remaining=max(0,$limit-\WaasKit\FluentBooking\Domain\Capacity::peak($rows,$start,$end));
                    if(!$remaining) {unset($days[$day][$i]);}
                    else {$days[$day][$i]['remaining']=min((int)($days[$day][$i]['remaining'] ?: $limit),$remaining);}
                }
                $days[$day]=array_values($days[$day]);
            }
            return array_filter($days);
        } catch (\Throwable $error) { return []; }
    }
    public function pool($event, array $profile): array
    {
        $ids = [(int) $event->id];
        $limit = $profile['capacity'] ?: (int) $event->getMaxBookingPerSlot();
        if ($limit < 1) { throw new \InvalidArgumentException('Définissez une capacité positive.'); }
        if ($profile['pool'] !== '') {
            if (!$profile['capacity']) { throw new \InvalidArgumentException('Une jauge partagée nécessite une capacité explicite.'); }
            $ids = [];
            foreach (CalendarSlot::all() as $candidate) {
                $p = $this->profile((int) $candidate->id);
                if ($p['pool'] !== $profile['pool']) { continue; }
                if (!$p['enabled'] || $p['capacity'] !== $limit) { throw new \InvalidArgumentException('Les événements d’une jauge partagée doivent être activés avec la même capacité.'); }
                $ids[] = (int) $candidate->id;
            }
        }
        return [$profile['pool'] === '' ? 'event:' . $event->id : 'pool:' . $profile['pool'], $ids, $limit];
    }
    public function persist($booking, $data, $custom, $event): void
    {
        $key = $data['_fba_key'] ?? '';
        if (!isset($this->pending[$key])) { return; }
        $pending = $this->pending[$key];
        Helper::updateBookingMeta($booking->id, self::META, $pending['quote']);
        if ($booking->getMeta(self::META, []) !== $pending['quote']) { throw new \RuntimeException('Enregistrement des participants à vérifier.'); }
        $this->capacity->attach($pending['token'], (int) $booking->id);
        unset($this->pending[$key]);
        do_action('waaskit_fluent_booking/party_created', $booking, $pending['quote']);
    }
    public function primePayment($booking): void
    {
        $snapshot = $booking->getMeta(self::META, []);
        if (!empty($snapshot['pricing'])) {
            if ($snapshot['currency'] !== CurrenciesHelper::getGlobalCurrency()) { throw new \RuntimeException('La devise a changé depuis la réservation.'); }
            $this->quotes[$this->quoteKey((int) $booking->event_id)] = $snapshot;
            $this->payment = ['hash'=>$booking->hash, 'quote'=>$snapshot];
        }
    }
    public function checkout(array $args): array
    {
        if (($args['client_reference_id'] ?? '') !== ($this->payment['hash'] ?? null)) { return $args; }
        $q = $this->payment['quote'];
        $args['line_items'] = array_map(static fn($line)=>[
            'amount'=>$line['total'], 'currency'=>strtolower($q['currency']),
            'name'=>$line['quantity'] . ' × ' . $line['label'], 'quantity'=>1
        ], $q['lines']);
        return $args;
    }
    public function paymentSettings(array $settings, $event): array
    {
        $quote = $this->quotes[$this->quoteKey((int) $event->id)] ?? null;
        if (!$quote || !$quote['pricing']) { return $settings; }
        $settings['multi_payment_enabled'] = 'no';
        $settings['items'] = array_map(static fn($line) => ['title'=>$line['quantity'] . ' × ' . $line['label'], 'value'=>$line['total'] / 100], $quote['lines']);
        return $settings;
    }
    public function fields(array $fields, $event): array
    {
        $profile = $this->profile((int) $event->id);
        if (!$profile['enabled']) { return $fields; }
        $fields = array_values(array_filter($fields, static fn($field) => $field['name'] !== 'guests'));
        $fields[] = ['name'=>'fba_party_' . $event->id, 'type'=>'text', 'label'=>__('Participants', 'waaskit-fluent-booking'), 'required'=>true, 'enabled'=>true, 'disabled'=>false, 'system_defined'=>true, 'placeholder'=>'', 'help_text'=>''];
        if (!is_admin()) {
            $base = dirname(__DIR__, 3) . '/wk-fluent-multireservation.php';
            wp_enqueue_script('fba-party', plugins_url('assets/public/party.js', $base), ['wp-i18n'], Plugin::VERSION, true);
            wp_set_script_translations('fba-party', 'waaskit-fluent-booking');
            wp_enqueue_style('fba-party', plugins_url('assets/public/party.css', $base), [], Plugin::VERSION);
            $vars = ['profile'=>$profile, 'currency'=>CurrenciesHelper::getGlobalCurrency(), 'quote_url'=>rest_url('fluent-booking-addon/v1/quote/' . $event->id)];
            wp_add_inline_script('fba-party', 'window.fbaProfiles=window.fbaProfiles||{};window.fbaProfiles[' . (int) $event->id . ']=' . wp_json_encode($vars, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';', 'before');
        }
        return $fields;
    }
    public function summary($booking): void
    {
        $quote = $booking->getMeta(self::META, []);
        if (!$quote) { return; }
        echo '<section class="fba-party-summary"><h3>' . esc_html__('Participants', 'waaskit-fluent-booking') . '</h3><ul>';
        foreach ($quote['participants'] as $person) { echo '<li>' . esc_html(($person['name'] ?: 'Participant') . ' — ' . $person['type']) . '</li>'; }
        echo '</ul></section>';
    }
    public function routes(): void
    {
        register_rest_route('fluent-booking-addon/v1', '/quote/(?P<id>\d+)', [
            'methods'=>'POST', 'permission_callback'=>'__return_true', 'callback'=>function ($request) {
                try {
                    $event = CalendarSlot::find((int) $request['id']);
                    if (!$event || $event->status !== 'active') { return new \WP_Error('not_found', 'Événement introuvable.', ['status'=>404]); }
                    $profile = $this->profile((int) $event->id);
                    if (!$profile['enabled']) { throw new \InvalidArgumentException('Module désactivé.'); }
                    $rows = $request->get_param('participants');
                    if (!is_array($rows)) { throw new \InvalidArgumentException('Participants requis.'); }
                    if (strlen($request->get_body()) > 100000) { throw new \InvalidArgumentException('Requête trop volumineuse.'); }
                    $start = $request->get_param('start_time');
                    if (!is_string($start) || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/D', $start)) { throw new \InvalidArgumentException('Heure de séance UTC requise.'); }
                    $at = new \DateTimeImmutable($start, new \DateTimeZone('UTC'));
                    if ($at->format('Y-m-d H:i:s') !== $start) { throw new \InvalidArgumentException('Date invalide.'); }
                    $at = $at->setTimezone(new \DateTimeZone($event->calendar->author_timezone ?: 'UTC'));
                    $quote = Party::evaluate($rows, $profile, $at);
                    // A quote does not reserve capacity. Booking validates again.
                    return ['lines'=>$quote['lines'], 'subtotal'=>$quote['subtotal'], 'units'=>$quote['units'], 'currency'=>CurrenciesHelper::getGlobalCurrency(), 'provisional'=>true];
                } catch (\Throwable $e) { return new \WP_Error('fba_invalid', $e->getMessage(), ['status'=>422]); }
            }
        ]);
        register_rest_route('fluent-booking-addon/v1', '/bookings/(?P<id>\d+)/participants', [
            'methods'=>'GET', 'permission_callback'=>static function ($r) {
                $b = Booking::find((int) $r['id']);
                return $b && \WaasKit\FluentBooking\Admin\SettingsPage::allowed('calendar_event', (int) $b->event_id);
            }, 'callback'=>static function ($r) { return Booking::find((int) $r['id'])->getMeta(self::META, []); }
        ]);
    }
}
