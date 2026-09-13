<?php
/** Data is retained by default. Explicit opt-in is required for irreversible removal. */
defined('WP_UNINSTALL_PLUGIN') || exit;
if (!defined('FBA_DELETE_DATA_ON_UNINSTALL') || FBA_DELETE_DATA_ON_UNINSTALL !== true) { return; }
$remove = static function (): void {
    global $wpdb;
    $wpdb->query('DROP TABLE IF EXISTS `' . esc_sql($wpdb->prefix . 'fba_capacity') . '`');
    delete_option('fba_db_version');
    delete_option('waaskit_fb_email_border_color');
    delete_option('waaskit_fluent_booking_config');
    delete_option('fba_native_settings_migrated');
    // Native booking records and historical payment data remain untouched.
    foreach (['fcal_meta'=>['key'=>'waaskit_fluent_booking_config'], 'fcal_booking_meta'=>['meta_key'=>'fba_party_v1']] as $suffix=>$where) {
        $table = $wpdb->prefix . $suffix;
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table) { $wpdb->delete($table,$where); if ($suffix === 'fcal_booking_meta') { $wpdb->delete($table, ['meta_key'=>'fba_guests_v1']); } }
    }
};
if (is_multisite()) {
    $offset=0;
    do {
        $ids=get_sites(['fields'=>'ids','number'=>100,'offset'=>$offset]);
        foreach($ids as $id) {switch_to_blog($id);try {$remove();} finally {restore_current_blog();}}
        $offset+=count($ids);
    } while(count($ids)===100);
} else {$remove();}
