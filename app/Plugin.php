<?php
namespace WaasKit\FluentBooking;

use WaasKit\FluentBooking\Admin\SettingsPage;
use WaasKit\FluentBooking\Integrations\FluentBooking\ConfigurationStore;
use WaasKit\FluentBooking\Rules\Registry;
use WaasKit\FluentBooking\Rules\ParticipantLimit;

final class Plugin
{
    public const VERSION = '4.0.0-alpha.2';
    public static function compatible(): bool
    {
        return defined('FLUENT_BOOKING_VERSION') && version_compare(FLUENT_BOOKING_VERSION, '2.4.0', '>=')
            && version_compare(FLUENT_BOOKING_VERSION, '2.5.0', '<')
            && class_exists('FluentBooking\\App\\Services\\PermissionManager');
    }
    public function register(): void
    {
        add_action('init', static function () {
            load_plugin_textdomain('waaskit-fluent-booking', false, 'fluent-booking-addon/languages');
        });
        if (!self::compatible()) {
            add_action('admin_notices', static function () {
                if (current_user_can('manage_options')) {
                    echo '<div class="notice notice-error"><p>' . esc_html__('WaasKit nécessite FluentBooking 2.4.x. Vérifiez les événements dépendants avant de poursuivre.', 'waaskit-fluent-booking') . '</p></div>';
                }
            });
            // Fail closed when the native service is available, only for configured events.
        }
        if (!class_exists('FluentBooking\\App\\Models\\CalendarSlot')) { return; }
        $store = new ConfigurationStore();
        $registry = new Registry();
        $registry->add(new ParticipantLimit());
        do_action('waaskit_fluent_booking/register_rules', $registry);
        (new SettingsPage($store))->register();
        add_filter('fluent_booking/booking_data', static function ($data, $event, $fields, $input) use ($store, $registry) {
            if (is_wp_error($data)) { return $data; }
            try {
                $effective = $store->effective('calendar_event', (int) $event->id);
                $settings = array_map(static fn($entry) => $entry['value'], $effective);
                if (!$settings['enabled']) { return $data; }
                if (!self::compatible()) {
                    return new \WP_Error('waaskit_incompatible', 'Réservation indisponible : compatibilité à vérifier.', ['status' => 503]);
                }
                $count = is_array($data['email'] ?? null) ? count($data['email']) : 1 + count((array) ($input['additional_guests'] ?? []));
                $error = $registry->validate(['participant_count' => $count], $settings);
                return $error === null ? $data : new \WP_Error('waaskit_rule_refused', $error, ['status' => 422]);
            } catch (\Throwable $error) {
                return new \WP_Error('waaskit_configuration_error', 'Configuration de réservation à vérifier.', ['status' => 503]);
            }
        }, 100, 4);
    }
}
