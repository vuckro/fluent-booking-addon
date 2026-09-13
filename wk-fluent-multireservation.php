<?php
/**
 * Plugin Name: Fluent Booking Addon
 * Plugin URI: https://github.com/vuckro/fluent-booking-addon
 * Description: Invités supplémentaires, places et choix d’un tarif FluentBooking par personne.
 * Version: 4.0.0-alpha.34
 * Author: WaasKit
 * Author URI: https://waaskit.com
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Requires Plugins: fluent-booking
 * Text Domain: waaskit-fluent-booking
 * License: GPL-2.0-or-later
 * Update URI: https://github.com/vuckro/fluent-booking-addon
 */
defined('ABSPATH') || exit;
require_once __DIR__ . '/autoload.php';
add_filter('plugin_action_links_' . plugin_basename(__FILE__), static function (array $links): array {
    if (current_user_can('read')) {
        array_unshift($links, '<a href="' . esc_url(admin_url('admin.php?page=waaskit-fluent-booking')) . '">' . esc_html__('Réglages', 'waaskit-fluent-booking') . '</a>');
    }
    return $links;
});

add_action('plugins_loaded', static function () {
    (new \WaasKit\FluentBooking\Plugin())->register();
}, 30);
