<?php
/**
 * Plugin Name: Fluent Booking Addon
 * Plugin URI: https://github.com/vuckro/fluent-booking-addon
 * Description: Fondation modulaire : configuration contextuelle, héritage et diagnostics FluentBooking.
 * Version: 4.0.0-alpha.8
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
add_action('plugins_loaded', static function () {
    (new \WaasKit\FluentBooking\Plugin())->register();
}, 30);
