<?php
namespace WaasKit\FluentBooking\Guests;

use FluentBooking\App\Models\Booking;
use FluentBooking\App\Services\Helper;
use WaasKit\FluentBooking\Integrations\FluentBooking\ConfigurationStore;
use WaasKit\FluentBooking\Plugin;

/** Use native bookings for seats and native orders for prices. No parallel stock table. */
final class BookingAdapter
{
    public const META = 'fba_guests_v1';

    private array $pending = [];
    private SlotLock $slotLock;
    private RescheduleCoordinator $reschedule;
    private ?array $paymentContext = null;
    private bool $appAssetsEnqueued = false;

    public function __construct(private ConfigurationStore $store)
    {
        $this->slotLock = new SlotLock();
        $this->reschedule = new RescheduleCoordinator(
            $this->slotLock,
            fn(int $id): bool => $this->options($id)['enabled']
        );
    }

    public function options(int $id): array
    {
        return Options::effective($this->store->read('calendar_event', $id)['values']['guest_options'] ?? []);
    }

    public function register(): void
    {
        add_filter('fluent_booking/asset_listed_slugs', static function ($slugs) {
            $slugs = is_array($slugs) ? $slugs : [];
            $slugs[] = '\/' . preg_quote(basename(dirname(__DIR__, 2)), '/') . '\/';
            return array_values(array_unique($slugs));
        });
        $this->reschedule->register();
        add_filter('fluent_booking/booking_data', function ($data) {
            $this->paymentContext = null;
            return $data;
        }, 1);

        /*
         * FluentBooking builds the draft order before invoking the payment
         * provider. Prepare the frozen participant lines here so that both the
         * draft order and Stripe receive the selected tariff(s), never the
         * complete native catalogue.
         */
        add_action('fluent_booking/pre_after_booking_pending', function ($booking) {
            $this->preparePaymentContext($booking);
        }, 1);
        foreach (['stripe', 'offline'] as $method) {
            add_action('fluent_booking/payment/pay_order_with_' . $method, function ($booking) {
                // Covers payment retries, which do not always re-enter the
                // pending-booking lifecycle.
                $this->preparePaymentContext($booking);
            }, 1);
        }
        add_filter('fluent_booking/get_event_payment_settings', function ($settings, $event) {
            if ($this->paymentContext && $this->paymentContext['event_id'] === (int) $event->id) {
                $settings['items'] = array_map(
                    static fn($item) => ['title' => $item['title'], 'value' => $item['cents'] / 100],
                    $this->paymentContext['items']
                );
            }
            return $settings;
        }, 100, 2);

        foreach (['fluent_booking/author_landing_head', 'fluent_booking/main_landing'] as $styleHook) {
            add_action($styleHook, static function () {
                wp_print_styles(['fba-guests']);
            });
        }
        foreach (['fluent_booking/author_landing_footer', 'fluent_booking/main_landing_footer'] as $scriptHook) {
            add_action($scriptHook, static function () {
                wp_print_scripts(['fba-guests']);
            });
        }
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_action('fluent_booking_loading_app', [$this, 'enqueueAppAssets']);
        add_filter('fluent_booking/public_event_vars', [$this, 'publicVars'], 110, 2);
        add_filter('rest_request_before_callbacks', [$this, 'beforeRestCallbacks'], 10, 3);
        add_filter('fluent_booking/initialize_booking_data', function ($data, $posted, $event) {
            if (!empty($posted['rescheduling_hash'])) {
                return $this->reschedule->normalizePublicRescheduleData($data, $posted, $event);
            }
            $extras = $posted['fba_extra_' . $event->id] ?? ($posted['custom_fields']['fba_extra_' . $event->id] ?? '[]');
            // The public AJAX handler passes raw, WP-slashed $_REQUEST. The REST
            // controller already cleans its request. Unslash only the public boundary,
            // exactly once, so JSON escapes in names and answers remain intact.
            if (doing_action('wp_ajax_fluent_cal_schedule_meeting') || doing_action('wp_ajax_nopriv_fluent_cal_schedule_meeting')) {
                $extras = wp_unslash($extras);
            }
            $data['_fba_extras'] = $extras;
            $data['_fba_requested_count'] = 1 + count((array) ($posted['guests'] ?? []));
            $data['_fba_ignore_availability'] = ($posted['source'] ?? '') === 'admin'
                && filter_var($posted['ignore_availability'] ?? false, FILTER_VALIDATE_BOOLEAN);
            return $data;
        }, 110, 3);
        add_filter('fluent_booking/schedule_validation_rules_data', function ($rules, $posted, $event) {
            if ($this->options((int) $event->id)['enabled']) {
                unset($rules['rules']['guests']);
            }
            return $rules;
        }, 100, 3);
        add_filter('fluent_booking/booking_data', [$this, 'validate'], 200, 4);
        add_action('fluent_booking/after_creating_schedule', static function ($booking) {
            if (is_wp_error($booking)) {
                throw new \Exception($booking->get_error_message(), 422);
            }
        }, 1);
        add_action('fluent_booking/after_booking_meta_update', [$this, 'persist'], 1, 4);
        add_filter('fluent_booking/create_draft_order', [$this, 'order'], 100, 4);
        add_action('fluent_booking/after_order_items_created', [$this, 'orderItems'], 100, 4);
        (new BookingPresentation())->register();
        (new CalendarPresentation())->register();
        (new CalendarContacts())->register();
        (new SeatNotifications())->register();
        (new BookingProtection())->register();
        register_shutdown_function([$this, 'unlock']);
        Booking::updated([AttachedSeats::class, 'sync']);
        Booking::deleting([AttachedSeats::class, 'remove']);
        Booking::updating(function ($booking) {
            $snapshot = $booking->getMeta(self::META, []);
            if (!$snapshot) {
                return;
            }
            foreach (['calendar_id', 'event_id'] as $key) {
                if ($booking->isDirty($key)) {
                    throw new \RuntimeException('Le changement d’événement n’est pas pris en charge pour cette réservation.', 422);
                }
            }
            if (!empty($snapshot['attached_seat'])) {
                return;
            }
            if ($booking->isDirty('start_time') && (string) $booking->start_time !== (string) $booking->getOriginal('start_time')) {
                $event = $booking->calendar_event ?: \FluentBooking\App\Models\CalendarSlot::find($booking->event_id);
                if (!$event) {
                    throw new \RuntimeException('Événement introuvable pour ce report.', 422);
                }
                $ignore = $this->reschedule->isIgnoringAvailability((int) $booking->id);
                $this->reschedule->guardCapacity($booking, $event, (string) $booking->start_time, $ignore);
            }
            if ($booking->isDirty('status') && in_array($booking->status, ['pending', 'scheduled'], true) && !in_array($booking->getOriginal('status'), ['pending', 'scheduled', 'approved', 'rescheduled'], true)) {
                throw new \RuntimeException('La réactivation nécessite une vérification des places.', 422);
            }
        });
    }

    public function beforeRestCallbacks($response, array $handler, $request)
    {
        if (is_wp_error($response) || !is_object($request) || !method_exists($request, 'get_route')) {
            return $response;
        }
        $route = (string) $request->get_route();
        if (preg_match('#^/fluent-booking/v2/bookings/create/(?P<event_id>\d+)$#', $route, $matches) === 1) {
            $event = \FluentBooking\App\Models\CalendarSlot::find((int) $matches['event_id']);
            if ($event && $this->options((int) $event->id)['enabled']) {
                $splitField = $this->splitContactLastNameField($event);
                if ($splitField !== null) {
                    $customFields = (array) ($request->get_param('custom_fields') ?? []);
                    if (trim((string) ($customFields[$splitField] ?? '')) === '') {
                        $fullName = trim((string) ($request->get_param('name') ?? ''));
                        if ($fullName !== '') {
                            $parts = preg_split('/\s+/u', $fullName, 2);
                            $customFields[$splitField] = trim((string) ($parts[1] ?? $parts[0]));
                            $request->set_param('custom_fields', $customFields);
                        }
                    }
                }
            }
            return $response;
        }
        $method = method_exists($request, 'get_method') ? strtoupper((string) $request->get_method()) : 'POST';
        $matched = RescheduleCoordinator::matchRestRescheduleRoute($method, $route);
        if (!$matched) {
            return $response;
        }
        if ($matched['action'] === 'slots') {
            $this->reschedule->beginRestRescheduleSlots($matched['booking_id']);
            return $response;
        }
        $booking = Booking::find($matched['booking_id']);
        $event = \FluentBooking\App\Models\CalendarSlot::find($matched['event_id']);
        $error = $this->reschedule->beforeRestReschedule($booking, $event, $request);
        return $error ?? $response;
    }

    private function splitContactLastNameField($event): ?string
    {
        if (!method_exists($event, 'getBookingFields')) {
            return null;
        }
        $fields = $event->getBookingFields();
        $firstNameSystemField = false;
        foreach ($fields as $field) {
            if (($field['name'] ?? '') === 'name') {
                $label = mb_strtolower(trim((string) ($field['label'] ?? '')), 'UTF-8');
                $firstNameSystemField = str_contains($label, 'prénom') || str_contains($label, 'prenom') || str_contains($label, 'first name');
                break;
            }
        }
        if (!$firstNameSystemField) {
            return null;
        }
        foreach ($fields as $field) {
            if (!empty($field['system_defined']) || empty($field['enabled']) || ($field['type'] ?? '') !== 'text') {
                continue;
            }
            $name = (string) ($field['name'] ?? '');
            $label = trim((string) ($field['label'] ?? ''));
            if (in_array($name, ['custom_votre_nom', 'custom_nom', 'custom_last_name'], true)
                || preg_match('/^(votre\s+)?nom(\s+de\s+famille)?$/iu', $label) === 1
                || preg_match('/^(your\s+)?last\s+name$/iu', $label) === 1) {
                return $name;
            }
        }
        return null;
    }

    /** Restrict native payment items to the immutable items selected at booking. */
    private function preparePaymentContext($booking): void
    {
        $this->paymentContext = null;
        $snapshot = $booking->getMeta(self::META, []);
        if (!is_array($snapshot) || !empty($snapshot['preserve_payments'])) {
            return;
        }
        if (isset($snapshot['currency']) && $snapshot['currency'] !== \FluentBooking\App\Services\CurrenciesHelper::getGlobalCurrency()) {
            throw new \RuntimeException('La devise a changé depuis la réservation.');
        }
        if (!isset($snapshot['items']) || !is_array($snapshot['items'])) {
            return;
        }
        $this->paymentContext = ['event_id' => (int) $booking->event_id, 'items' => $snapshot['items']];
    }

    private function clientConfig($event, array $options, ?array &$catalogueOut = null): array
    {
        $catalogue = null;
        $configurationError = '';
        try {
            $catalogue = $options['native_tariffs'] ? NativeTariffs::catalogue($event) : null;
        } catch (\InvalidArgumentException $error) {
            $configurationError = $error->getMessage();
        }
        $catalogueOut = $catalogue;
        $price = 0;
        foreach ($event->getPaymentItems() as $item) {
            $price += (float) $item['value'];
        }
        return [
            'structuredPayload'     => $options['native_tariffs'] || $options['allow_nonparticipating'],
            'allowNonparticipating' => $options['allow_nonparticipating'] && $this->guestsAllowed($event),
            'preservePayments'      => !$options['pricing_enabled'],
            'error'                 => $configurationError,
            'tariffs'               => $catalogue,
            'limit'                 => $this->guestLimit($event),
            'nameMode'              => $options['name_mode'],
            'emailMode'             => $options['email_mode'],
            'splitName'             => !empty($options['split_name']),
            'fields'                => $options['fields'],
            'price'                 => $options['per_person_price'],
            'unit'                  => $price,
            'currency'              => \FluentBooking\App\Services\CurrenciesHelper::getGlobalCurrency(),
        ];
    }

    private function enqueueGuestAssets(?string $inlineScript = null): void
    {
        $root = dirname(__DIR__, 2);
        $entry = $root . '/wk-fluent-multireservation.php';
        // Include 'fluent_booking=1' in the style URL so ?embedded=1 iframes retain our CSS.
        $styleUrl = add_query_arg('fluent_booking', '1', plugins_url('assets/public/guests.css', $entry));
        wp_enqueue_style('fba-guests', $styleUrl, [], Plugin::VERSION . '.' . filemtime($root . '/assets/public/guests.css'));
        if ($inlineScript !== null) {
            wp_enqueue_script('fba-guests', plugins_url('assets/public/guests.js', $entry), [], Plugin::VERSION . '.' . filemtime($root . '/assets/public/guests.js'), true);
            wp_add_inline_script('fba-guests', $inlineScript, 'before');
        }
    }

    public function enqueueAdminAssets(): void
    {
        if (($_GET['page'] ?? '') !== 'fluent-booking') {
            return;
        }
        $this->enqueueAppAssets();
    }

    public function enqueueAppAssets(): void
    {
        if ($this->appAssetsEnqueued || !class_exists('FluentBooking\\App\\Models\\CalendarSlot')) {
            return;
        }
        $configs = [];
        foreach (\FluentBooking\App\Models\CalendarSlot::query()->get() as $event) {
            $options = $this->options((int) $event->id);
            if ($options['enabled']) {
                $configs[(int) $event->id] = $this->clientConfig($event, $options);
            }
        }
        if (!$configs) {
            return;
        }
        $this->appAssetsEnqueued = true;
        $this->enqueueGuestAssets('window.fbaGuestForms=Object.assign(window.fbaGuestForms||{},' . wp_json_encode($configs) . ');');
    }

    public static function normalizeReschedulePublicVars(array $vars, bool $isPaidEvent, string $confirmLabel, string $noPaymentHelpText): array
    {
        unset(
            $vars['payment_items'],
            $vars['payment_methods'],
            $vars['multi_payment_items'],
            $vars['multi_payment_woo_ids'],
            $vars['multi_payment_cart_ids']
        );
        if (isset($vars['slot']) && is_array($vars['slot'])) {
            $vars['slot']['total_payment'] = '';
            $vars['slot']['settings'] = is_array($vars['slot']['settings'] ?? null) ? $vars['slot']['settings'] : [];
            $vars['slot']['settings']['submit_button_text'] = $confirmLabel;
        }
        $vars['i18n'] = is_array($vars['i18n'] ?? null) ? $vars['i18n'] : [];
        $vars['i18'] = is_array($vars['i18'] ?? null) ? $vars['i18'] : [];
        foreach (['Schedule_Meeting', 'Continue_to_Payments', 'Confirm_Payment'] as $key) {
            $vars['i18n'][$key] = $confirmLabel;
            $vars['i18'][$key] = $confirmLabel;
        }
        if ($isPaidEvent && isset($vars['form_fields']) && is_array($vars['form_fields'])) {
            foreach ($vars['form_fields'] as &$field) {
                if (is_array($field) && ($field['name'] ?? '') === 'rescheduling_reason' && trim((string) ($field['help_text'] ?? '')) === '') {
                    $field['help_text'] = $noPaymentHelpText;
                }
            }
            unset($field);
        }
        return $vars;
    }

    public function publicVars(array $vars, $event): array
    {
        if (!empty($vars['rescheduling']) || (($_REQUEST['type'] ?? '') === 'reschedule' && !empty($_REQUEST['meeting_hash']))) {
            $isPaid = is_object($event) && (($event->type ?? '') === 'paid');
            $vars = self::normalizeReschedulePublicVars(
                $vars,
                $isPaid,
                __('Confirm Reschedule', 'fluent-booking'),
                __('Report sans nouveau paiement : votre règlement initial est conservé.', 'waaskit-fluent-booking')
            );
            if ($this->options((int) $event->id)['enabled']) {
                $this->enqueueGuestAssets();
            }
            return $vars;
        }
        $options = $this->options((int) $event->id);
        if (!$options['enabled']) {
            return $vars;
        }
        foreach ($vars['form_fields'] as &$field) {
            if (($field['name'] ?? '') === 'guests') {
                $field['enabled'] = false;
                $field['required'] = false;
            }
        }
        unset($field);
        $isRest = defined('REST_REQUEST') && REST_REQUEST;
        if ($isRest) {
            $splitField = $this->splitContactLastNameField($event);
            if ($splitField !== null) {
                $vars['form_fields'] = array_values(array_filter(
                    $vars['form_fields'],
                    static fn($field) => ($field['name'] ?? '') !== $splitField
                ));
            }
        }
        $vars['form_fields'][] = [
            'name'           => 'fba_extra_' . $event->id,
            'type'           => 'text',
            'label'          => '',
            'placeholder'    => 'fba_extra_' . $event->id,
            'required'       => false,
            'enabled'        => true,
            'system_defined' => !$isRest,
        ];
        $catalogue = null;
        $config = $this->clientConfig($event, $options, $catalogue);
        if ($catalogue) {
            // FluentBooking's Stripe component reads both payment_items and
            // the payment field. Keep its initial Payment Element aligned
            // with the first selectable tariff instead of the full catalogue.
            $initialItem = ['title' => $catalogue[0]['title'], 'value' => $catalogue[0]['cents'] / 100];
            $vars['slot']['total_payment'] = $initialItem['value'];
            $vars['payment_items'] = [$initialItem];
            foreach ($vars['form_fields'] as &$field) {
                if (($field['type'] ?? '') === 'payment') {
                    $field['payment_items'] = [$initialItem];
                }
            }
            unset($field);
        }
        $this->enqueueGuestAssets('window.fbaGuestForms=window.fbaGuestForms||{};window.fbaGuestForms[' . (int) $event->id . ']=' . wp_json_encode($config) . ';');
        return $vars;
    }

    private function guestsAllowed($event): bool
    {
        foreach ($event->getBookingFields() as $field) {
            if (($field['name'] ?? '') === 'guests') {
                return !empty($field['enabled']);
            }
        }
        return false;
    }

    private function guestLimit($event): int
    {
        $limit = 1;
        foreach ($event->getBookingFields() as $field) {
            if (($field['name'] ?? '') === 'guests' && !empty($field['enabled'])) {
                $limit = max(1, (int) ($field['limit'] ?? 10));
            }
        }
        return min($limit, (int) $event->getMaxBookingPerSlot());
    }

    public function validate($data, $event, $custom, $input)
    {
        if (is_wp_error($data)) {
            return $data;
        }
        try {
            $options = $this->options((int) $event->id);
            if (!$options['enabled']) {
                return $data;
            }
            if (!Plugin::compatible() || !$event->isMultiGuestEvent() || $event->isRecurringEvent() || !is_string($data['start_time'] ?? null)) {
                throw new \RuntimeException('Ces options nécessitent un événement de groupe sur un créneau unique, avec FluentBooking 2.4.x à 2.6.x.');
            }
            if (\FluentBooking\App\Services\CurrenciesHelper::isZeroDecimal(\FluentBooking\App\Services\CurrenciesHelper::getGlobalCurrency())) {
                throw new \RuntimeException('Ce mode utilise pour le moment les devises à deux décimales.');
            }
            if (($event->getPaymentSettings()['multi_payment_enabled'] ?? 'no') === 'yes') {
                throw new \RuntimeException('Utilisez un tarif de base unique pour ces options invités.');
            }
            if (($event->getPaymentSettings()['driver'] ?? 'native') !== 'native') {
                throw new \RuntimeException('Ces options utilisent les paiements natifs FluentBooking, pas WooCommerce.');
            }
            $isAdmin = ($data['source'] ?? '') === 'admin';
            if (!$isAdmin && $event->isPaymentEnabled($data['slot_minutes'] ?? null) && !in_array($data['payment_method'] ?? '', ['stripe', 'offline'], true)) {
                throw new \RuntimeException('Ce mode prend en charge Stripe et le paiement hors ligne.');
            }
            if (!empty($input['coupon_codes']) || !empty($data['applied_coupons'])) {
                throw new \RuntimeException('Les coupons ne sont pas encore pris en charge avec ces options invités.');
            }
            $priced = (bool) array_filter($options['fields'], static fn($field) => ($field['pricing'] ?? 'none') !== 'none');
            $paymentItems = $event->getPaymentItems();
            if ($priced && (!$event->isPaymentEnabled($data['slot_minutes'] ?? null) || count($paymentItems) !== 1)) {
                throw new \RuntimeException('Les choix payants nécessitent un tarif de base unique et un paiement natif activé.');
            }
            $catalogue = $options['native_tariffs'] ? NativeTariffs::catalogue($event) : null;
            $holderTariff = '';
            $holderParticipates = true;
            if ($options['native_tariffs'] || $options['allow_nonparticipating']) {
                $raw = $input['_fba_extras'] ?? '';
                if ($isAdmin && $raw === '[]') {
                    $raw = wp_json_encode(['holder_participates' => true, 'holder_tariff' => $catalogue[0]['id'] ?? '', 'guests' => []]);
                }
                if (!is_string($raw) || strlen($raw) > 30000) {
                    throw new \InvalidArgumentException('Informations de réservation invalides.');
                }
                $payload = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
                if (!is_array($payload) || !is_array($payload['guests'] ?? null) || !array_is_list($payload['guests'])) {
                    throw new \InvalidArgumentException('Complétez les participants.');
                }
                $holderParticipates = Participation::requested($payload, $options['allow_nonparticipating'] && $this->guestsAllowed($event));
                $holderTariff = $payload['holder_tariff'] ?? '';
                $input['_fba_extras'] = wp_json_encode($payload['guests']);
            }
            $splitField = $this->splitContactLastNameField($event);
            if ($splitField !== null && trim((string) ($data['last_name'] ?? '')) === '' && is_array($custom) && trim((string) ($custom[$splitField] ?? '')) !== '') {
                $data['last_name'] = sanitize_text_field(trim((string) $custom[$splitField]));
            }
            $rows = Identity::request($data, $input, $options);
            if (!is_string($data['email']) || !is_email($data['email']) || !is_string($data['first_name']) || trim($data['first_name']) === '') {
                throw new \RuntimeException('La personne qui réserve doit indiquer un nom et un courriel valide.');
            }
            $answers = Options::answers($rows, $rows, $options['fields']);
            $count = Participation::count(count($answers), $holderParticipates);
            if ($count > $this->guestLimit($event)) {
                throw new \RuntimeException('Le nombre de personnes dépasse la limite de cette réservation. Vérifiez les invités supplémentaires et le maximum autorisé.');
            }
            $quantity = $options['per_person_price'] ? $count : 1;
            // Serialize admission on this event and exact native slot. Native records remain stock.
            $lock = $this->slotLock->acquire((int) $event->id, (string) $data['start_time']);
            $used = Booking::where('event_id', $event->id)->where('start_time', $data['start_time'])->whereIn('status', ['pending', 'scheduled', 'approved', 'completed'])->count();
            $seats = $count;
            if (!($isAdmin && !empty($input['_fba_ignore_availability'])) && $used + $seats > (int) $event->getMaxBookingPerSlot()) {
                throw new \RuntimeException('Il ne reste pas assez de places pour cette réservation.');
            }
            $items = [];
            foreach ($paymentItems as $item) {
                $items[] = ['title' => $item['title'], 'cents' => (int) round((float) $item['value'] * 100)];
            }
            $tariffQuote = null;
            if ($options['native_tariffs'] && $catalogue) {
                foreach ($rows as $i => $row) {
                    $rows[$i]['tariff'] = $row['tariff'] ?? '';
                }
                $tariffQuote = NativeTariffs::quote($catalogue, $holderTariff, $rows, $holderParticipates);
                foreach ($answers as $i => &$answer) {
                    $answer['tariff'] = $tariffQuote['people'][$i + ($holderParticipates ? 1 : 0)];
                }
                unset($answer);
            }
            if ($options['native_tariffs']) {
                $quantity = 1;
                $quote = ['total' => $tariffQuote['total'] ?? 0, 'guests' => array_column(array_slice($tariffQuote['people'] ?? [], $holderParticipates ? 1 : 0), 'cents')];
                $items = array_map(
                    static fn($person, $index) => [
                        'title' => ($holderParticipates && $index === 0 ? 'Contact principal' : 'Participant ' . ($index + ($holderParticipates ? 0 : 1))) . ' — ' . $person['title'],
                        'cents' => $person['cents'],
                    ],
                    $tariffQuote['people'] ?? [],
                    array_keys($tariffQuote['people'] ?? [])
                );
            } else {
                $quote = Pricing::quote(array_sum(array_column($items, 'cents')), $options, $answers);
                if ($priced) {
                    $items = [['title' => 'Réservation — ' . $count . ' personne(s)', 'cents' => $quote['total']]];
                    $quantity = 1;
                }
            }
            $token = wp_generate_uuid4();
            $this->pending[$token] = [
                'holder_participates' => $holderParticipates,
                'preserve_payments'   => !$options['pricing_enabled'],
                'holder_tariff'       => $holderParticipates ? ($tariffQuote['people'][0] ?? null) : null,
                'attached'            => true,
                'guest_amounts'       => $quote['guests'],
                'count'               => $count,
                'quantity'            => $quantity,
                'seats'               => $seats,
                'guests'              => $answers,
                'fields'              => $options['fields'],
                'currency'            => \FluentBooking\App\Services\CurrenciesHelper::getGlobalCurrency(),
                'items'               => $items,
                'lock'                => $lock,
            ];
            $data['_fba_token'] = $token;
            $data['quantity'] = $quantity;
            return $data;
        } catch (\Throwable $error) {
            $this->unlock();
            $message = $error instanceof \JsonException
                ? 'Les informations des participants sont invalides. Rechargez la page puis réessayez.'
                : $error->getMessage();
            return new \WP_Error('fba_guests_refused', $message, ['status' => 422]);
        }
    }

    public function persist($booking, $data, $custom, $event): void
    {
        $token = $data['_fba_token'] ?? '';
        if (!isset($this->pending[$token])) {
            return;
        }
        $snapshot = $this->pending[$token];
        AttachedSeats::create($booking, $snapshot);
        unset($snapshot['lock']);
        Helper::updateBookingMeta($booking->id, self::META, $snapshot);
        Helper::updateBookingMeta($booking->id, 'quantity', $snapshot['quantity']);
        // Native e-mail templates resolve booking.custom.* from this metadata.
        // Keep the summary separate from user-facing form fields and never add
        // it to shared calendar payloads.
        $customFields = (array) $booking->getMeta('custom_fields_data', []);
        $customFields['fba_participants_email'] = (new BookingPresentation())->emailSummary($booking);
        Helper::updateBookingMeta($booking->id, 'custom_fields_data', $customFields);
        if ($booking->getMeta(self::META, []) !== $snapshot) {
            throw new \RuntimeException('Les informations des invités n’ont pas pu être enregistrées.');
        }
        $lock = $this->pending[$token]['lock'];
        unset($this->pending[$token]);
        $this->unlock($lock);
    }

    public function order(array $order, $booking, $event, $data): array
    {
        $snapshot = $booking->getMeta(self::META, []);
        if (!isset($snapshot['quantity']) || !empty($snapshot['preserve_payments'])) {
            return $order;
        }
        if ($order['currency'] !== $snapshot['currency']) {
            throw new \RuntimeException('La devise de cette réservation a changé. Vérification nécessaire.');
        }
        $order['subtotal'] = array_sum(array_column($snapshot['items'], 'cents')) * $snapshot['quantity'];
        $order['total_amount'] = $order['subtotal'];
        $order['discount_total'] = 0;
        return $order;
    }

    public function orderItems($order, $booking, $event, $data): void
    {
        $snapshot = $booking->getMeta(self::META, []);
        if (!isset($snapshot['quantity']) || !empty($snapshot['preserve_payments'])) {
            return;
        }
        $items = $order->items()->orderBy('id')->get();
        // Replace draft lines before the native transaction is created. One frozen line per person.
        foreach ($items as $item) {
            $item->delete();
        }
        foreach ($snapshot['items'] as $item) {
            $order->items()->create([
                'booking_id' => $booking->id,
                'item_name'  => $item['title'],
                'item_price' => $item['cents'],
                'quantity'   => $snapshot['quantity'],
                'item_total' => $item['cents'] * $snapshot['quantity'],
                'rate'       => 1,
                'type'       => 'single',
                'line_meta'  => wp_json_encode($item),
            ]);
        }
    }

    public function unlock(?string $one = null): void
    {
        if ($one === null) {
            $this->reschedule->rollbackStagedDurations();
        }
        $this->slotLock->release($one);
    }
}
