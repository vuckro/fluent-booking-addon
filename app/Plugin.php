<?php
namespace WaasKit\FluentBooking;

use WaasKit\FluentBooking\Admin\SettingsPage;
use WaasKit\FluentBooking\Integrations\FluentBooking\ConfigurationStore;

final class Plugin
{
    public const VERSION = '4.0.0-alpha.40';
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
        (new SettingsPage($store))->register();
        (new \WaasKit\FluentBooking\Emails\Appearance())->register();
        if (ConfigurationStore::migrationRequired()) {
            $message='Fluent Booking Addon : migration des anciens réglages requise. Consultez docs/migration.md avant de rouvrir les réservations.';
            add_action('admin_notices', static function () use ($message) {echo '<div class="notice notice-error"><p>'.esc_html($message).'</p></div>';});
            add_filter('fluent_booking/booking_data',static fn()=>new \WP_Error('fba_migration_required',$message,['status'=>503]));
            \FluentBooking\App\Models\Booking::creating(static function () use ($message) {throw new \RuntimeException($message);});
            return;
        }
        (new \WaasKit\FluentBooking\Infrastructure\Privacy())->register();
        (new \WaasKit\FluentBooking\Integrations\FluentBooking\RetiredProfiles())->register();
        (new \WaasKit\FluentBooking\Guests\BookingAdapter($store))->register();

    }
}
