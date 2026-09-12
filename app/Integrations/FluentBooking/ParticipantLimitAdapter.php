<?php
namespace WaasKit\FluentBooking\Integrations\FluentBooking;

use WaasKit\FluentBooking\Plugin;
use WaasKit\FluentBooking\Rules\Registry;

/** Native BookingService input is already normalized, including multi-guest emails. */
final class ParticipantLimitAdapter
{
    public function __construct(private ConfigurationStore $store, private Registry $registry) {}

    public function register(): void
    {
        add_filter('fluent_booking/booking_data', [$this, 'validate'], 100, 4);
    }

    public function validate($data, $event, $fields, $input)
    {
        if (is_wp_error($data)) { return $data; }
        try {
            $effective = $this->store->effective('calendar_event', (int) $event->id);
            $settings = array_map(static fn($entry) => $entry['value'], $effective);
            if (!empty($settings['booking_profile']['enabled'])) {
                return new \WP_Error('fba_retired_profile', 'Cet événement utilise un ancien module retiré. Contactez son gestionnaire.', ['status' => 503]);
            }
            if (!$settings['enabled']) { return $data; }
            if (!Plugin::compatible()) {
                return new \WP_Error('waaskit_incompatible', 'Réservation indisponible : compatibilité à vérifier.', ['status' => 503]);
            }
            $count = is_array($data['email'] ?? null) ? count($data['email']) : 1 + count((array) ($input['additional_guests'] ?? []));
            $error = $this->registry->validate(['participant_count' => $count], $settings);
            return $error === null ? $data : new \WP_Error('waaskit_rule_refused', $error, ['status' => 422]);
        } catch (\Throwable $error) {
            return new \WP_Error('waaskit_configuration_error', 'Configuration de réservation à vérifier.', ['status' => 503]);
        }
    }
}
