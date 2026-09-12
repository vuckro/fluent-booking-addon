<?php
// Local-only native-service regression. Roll back all bookings; block network and mail.
$path = getenv('WAASKIT_WP_PATH');
if (!$path || !is_file($path . '/wp-load.php')) { throw new RuntimeException('Local WordPress required.'); }
require $path . '/wp-load.php';
if (!in_array(parse_url(home_url(), PHP_URL_HOST), ['localhost', '127.0.0.1'], true)) { throw new RuntimeException('Local tests only.'); }
use WaasKit\FluentBooking\Integrations\FluentBooking\ConfigurationStore;
use FluentBooking\App\Models\Booking;
use FluentBooking\App\Models\CalendarSlot;
use FluentBooking\App\Services\BookingService;
function check($ok, $label) { if (!$ok) { throw new RuntimeException($label); } echo "PASS $label\n"; }
add_filter('pre_wp_mail', '__return_true');
add_filter('pre_http_request', static fn() => new WP_Error('test_network_blocked', 'Network disabled in tests'), 999);
foreach (['scheduled', 'pending', 'cancelled', 'completed'] as $status) {
    remove_all_actions('fluent_booking/pre_after_booking_' . $status);
    remove_all_actions('fluent_booking/after_booking_' . $status);
}
$store = new ConfigurationStore();
$wpdb->query('START TRANSACTION');
try {
    delete_option(ConfigurationStore::KEY);
    $wpdb->delete($wpdb->prefix . 'fcal_meta', ['key' => ConfigurationStore::KEY]);
    $store->save('site', 0, ['enabled' => true, 'max_participants' => 2], 0);
    $events = CalendarSlot::all();
    check(count($events) >= 2, 'native individual and group fixtures available');
    foreach ($events as $event) {
        $input = ['email' => 'fba-test@example.invalid', 'first_name' => 'Fixture', 'last_name' => 'Test',
            'start_time' => '2030-01-01 14:00:00', 'end_time' => '2030-01-01 14:30:00',
            'person_time_zone' => 'UTC', 'source' => 'test', 'status' => 'scheduled',
            'additional_guests' => [['email' => 'guest@example.invalid', 'name' => 'Guest']]];
        $booking = BookingService::createBooking($input, $event);
        check(!is_wp_error($booking) && $booking->id > 0, 'native service accepts holder plus one guest for event ' . $event->id);
        $before = Booking::count();
        $input['additional_guests'][] = ['email' => 'extra@example.invalid', 'name' => 'Extra'];
        $refused = BookingService::createBooking($input, $event);
        check(is_wp_error($refused) && $refused->get_error_code() === 'waaskit_rule_refused', 'native service rejects third person for event ' . $event->id);
        check(Booking::count() === $before, 'refusal inserts no booking for event ' . $event->id);
    }
} finally {
    $wpdb->query('ROLLBACK');
    wp_cache_flush();
}
