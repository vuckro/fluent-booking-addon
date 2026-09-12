<?php
// Explicit local-only test runner. All database changes are rolled back.
$path = getenv('WAASKIT_WP_PATH');
if (!$path || !is_file($path . '/wp-load.php')) { fwrite(STDERR, "Set WAASKIT_WP_PATH to the disposable WordPress test installation.\n"); exit(1); }
require $path . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
if (!in_array(parse_url(home_url(), PHP_URL_HOST), ['localhost', '127.0.0.1'], true)) { throw new RuntimeException('Local test site required.'); }
require_once dirname(__DIR__) . '/autoload.php';
use WaasKit\FluentBooking\Configuration\Schema;
use WaasKit\FluentBooking\Integrations\FluentBooking\ConfigurationStore;
use WaasKit\FluentBooking\Admin\SettingsPage;
use WaasKit\FluentBooking\Plugin;
use FluentBooking\App\Models\CalendarSlot;
function check($ok, $name) { if (!$ok) { throw new RuntimeException($name); } echo "PASS $name\n"; }
$store = new ConfigurationStore();
$event = CalendarSlot::first();
if (!$event) { throw new RuntimeException('Create a native event fixture first.'); }
$admin = get_users(['role' => 'administrator', 'number' => 1])[0];
$wpdb->query('START TRANSACTION');
try {
    // Reset only test configuration within the transaction, never booking data.
    delete_option(ConfigurationStore::KEY);
    $wpdb->delete($wpdb->prefix . 'fcal_meta', ['key' => ConfigurationStore::KEY]);
    wp_set_current_user($admin->ID);
    check(SettingsPage::allowed('site', 0), 'admin can configure site');
    check(SettingsPage::allowed('calendar_event', (int) $event->id), 'admin can configure event');
    check(!SettingsPage::allowed('calendar_event', 99999999), 'missing event rejected');
    wp_set_current_user(0);
    check(!SettingsPage::allowed('site', 0), 'anonymous cannot configure site');
    check(!SettingsPage::allowed('calendar_event', (int) $event->id), 'anonymous cannot configure event');
    wp_set_current_user($admin->ID);
    $store->save('site', 0, ['enabled' => true, 'max_participants' => 6], 0);
    $store->save('calendar', (int) $event->calendar_id, ['max_participants' => 4], 0);
    $store->save('calendar_event', (int) $event->id, ['enabled' => false, 'max_participants' => 0], 0);
    $effective = $store->effective('calendar_event', (int) $event->id);
    check($effective['enabled']['value'] === false && $effective['max_participants']['value'] === 0, 'native storage preserves false and zero');
    $stale = false;
    try { $store->save('site', 0, [], 0); } catch (RuntimeException $e) { $stale = true; }
    check($stale, 'stale revision rejected');
    check($store->read('site')['values']['max_participants'] === 6, 'stale save did not overwrite');
    $store->save('calendar_event', (int) $event->id, [], 1);
    check($store->effective('calendar_event', (int) $event->id)['max_participants'] === ['value' => 4, 'source' => 'agenda'], 'return to inheritance');
    if (!has_filter('fluent_booking/booking_data')) { throw new RuntimeException('Native hooks missing.'); }
    $store->save('site', 0, ['enabled' => false], 1);
    $data = ['email' => ['one@example.test', 'two@example.test', 'three@example.test']];
    check(apply_filters('fluent_booking/booking_data', $data, $event, [], []) === $data, 'disabled module is neutral');
    $store->save('site', 0, ['enabled' => true], 2);
    $store->save('calendar', (int) $event->calendar_id, ['max_participants' => 2], 1);
    check(is_wp_error(apply_filters('fluent_booking/booking_data', $data, $event, [], [])), 'real hook refuses excess participants');
    $nativeError = new WP_Error('existing', 'Existing refusal');
    // Call only our callback: third-party native filters do not all accept WP_Error.
    foreach ($GLOBALS['wp_filter']['fluent_booking/booking_data']->callbacks[100] as $callback) {
        check(call_user_func($callback['function'], $nativeError, $event, [], []) === $nativeError, 'existing error preserved');
    }
    ob_start(); $_GET = ['scope' => 'calendar_event', 'object_id' => (string) $event->id];
    (new SettingsPage($store))->render(); $html = ob_get_clean();
    check(str_contains($html, 'Valeur effective') && str_contains($html, 'waaskit_fb_save') && str_contains($html, 'Modules et réglages'), 'contextual admin renders configuration');
    ob_start(); $_GET['tab'] = 'diagnostics'; (new SettingsPage($store))->render(); $diagnostics = ob_get_clean();
    check(str_contains($diagnostics, 'simulation uniquement') && !str_contains($diagnostics, 'name="action"'), 'diagnostics tab separates maintenance from settings');
    check(Plugin::compatible(), 'native version contract passes');
    $before = $store->read('site');
    $external = $before; $external['revision']++;
    $wpdb->update($wpdb->options, ['option_value' => maybe_serialize($external)], ['option_name' => ConfigurationStore::KEY]);
    $staleCache = false;
    try { $store->save('site', 0, [], $before['revision']); } catch (RuntimeException $e) { $staleCache = true; }
    check($staleCache, 'cached options cannot hide another writer');
    $native = new \WaasKit\FluentBooking\Integrations\FluentBooking\NativeSettings($store);
    $menu = $native->menu([]);
    check($menu['waaskit_addon']['route']['name'] === 'configure-integrations', 'native menu reuses existing Vue route');
    check($native->fields()['fields']['enabled']['type'] === 'select', 'native form schema exposes supported controls');
    $nativeState = $native->settings();
    $native->save(['_revision' => $nativeState['_revision'], 'enabled' => 'off', 'max_participants' => '0']);
    check($store->read('site')['values'] === ['enabled' => false, 'max_participants' => 0], 'native settings preserve false and zero');
    $rejected = false;
    try { $native->save($nativeState); } catch (RuntimeException $e) { $rejected = true; }
    check($rejected, 'native form rejects stale revisions');
    $nativeState = $native->settings();
    $rejected = false;
    try { $native->save(array_merge($nativeState, ['max_participants' => '-1'])); } catch (InvalidArgumentException $e) { $rejected = true; }
    check($rejected, 'native form validates participant limit');
    $native->save(array_merge($nativeState, ['enabled' => 'inherit', 'max_participants' => '']));
    check($store->read('site')['values'] === [], 'native blank value restores inheritance');
    wp_set_current_user(0);
    check($native->menu([]) === [], 'native menu hidden without global permission');
    $rejected = false;
    try { $native->settings(); } catch (RuntimeException $e) { $rejected = true; }
    check($rejected, 'native settings authorize reads');
    $rejected = false;
    try { $native->save($nativeState); } catch (RuntimeException $e) { $rejected = true; }
    check($rejected, 'native settings authorize writes');

} finally {
    $wpdb->query('ROLLBACK');
    wp_cache_flush();
}
