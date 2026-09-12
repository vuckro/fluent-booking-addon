<?php
namespace WaasKit\FluentBooking;

use WaasKit\FluentBooking\Admin\SettingsPage;
use WaasKit\FluentBooking\Integrations\FluentBooking\ConfigurationStore;
use WaasKit\FluentBooking\Rules\Registry;
use WaasKit\FluentBooking\Rules\ParticipantLimit;

final class Plugin
{
    public const VERSION = '4.0.0-alpha.16';
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
                    echo '<div class="notice notice-error"><p>' . esc_html__('Fluent Booking Addon nécessite FluentBooking 2.4.x. Vérifiez les événements dépendants avant de poursuivre.', 'waaskit-fluent-booking') . '</p></div>';
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
        (new \WaasKit\FluentBooking\Infrastructure\Privacy())->register();
        (new \WaasKit\FluentBooking\Integrations\FluentBooking\ConfigurationApi($store))->register();
        (new \WaasKit\FluentBooking\Integrations\FluentBooking\RetiredProfiles($store))->register();
        (new \WaasKit\FluentBooking\Integrations\FluentBooking\ParticipantLimitAdapter($store, $registry))->register();
    }
}
