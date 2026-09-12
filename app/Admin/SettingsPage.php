<?php
namespace WaasKit\FluentBooking\Admin;

use WaasKit\FluentBooking\Configuration\Schema;
use WaasKit\FluentBooking\Integrations\FluentBooking\ConfigurationStore;
use WaasKit\FluentBooking\Plugin;
use FluentBooking\App\Models\Calendar;
use FluentBooking\App\Models\CalendarSlot;
use FluentBooking\App\Services\PermissionManager;

final class SettingsPage
{
    public function __construct(private ConfigurationStore $store) {}
    public function register(): void
    {
        add_action('admin_menu', function () {
            add_submenu_page('fluent-booking', 'WaasKit', 'WaasKit', 'read', 'waaskit-fluent-booking', [$this, 'render']);
        }, 30);
        add_action('admin_post_waaskit_fb_save', [$this, 'save']);
    }
    public static function allowed(string $scope, int $id): bool
    {
        if ($scope === 'site') { return $id === 0 && current_user_can('manage_options'); }
        if ($id <= 0) { return false; }
        if ($scope === 'calendar') {
            return (bool) Calendar::find($id) && (current_user_can('manage_options') || PermissionManager::canWriteCalendar($id));
        }
        return $scope === 'calendar_event' && (bool) CalendarSlot::find($id)
            && (current_user_can('manage_options') || PermissionManager::canUpdateCalendarEvent($id));
    }
    private function url(string $scope = 'site', int $id = 0): string
    {
        return add_query_arg(['page' => 'waaskit-fluent-booking', 'scope' => $scope, 'object_id' => $id], admin_url('admin.php'));
    }
    public function save(): void
    {
        $scope = isset($_POST['scope']) && is_string($_POST['scope']) ? sanitize_key(wp_unslash($_POST['scope'])) : '';
        $id = isset($_POST['object_id']) && is_scalar($_POST['object_id']) ? absint($_POST['object_id']) : 0;
        if (!self::allowed($scope, $id)) { wp_die('Accès refusé.', '', ['response' => 403]); }
        check_admin_referer('waaskit_fb_save_' . $scope . '_' . $id);
        try {
            if (!Plugin::compatible()) { throw new \RuntimeException('Version FluentBooking non prise en charge.'); }
            $modes = isset($_POST['modes']) && is_array($_POST['modes']) ? wp_unslash($_POST['modes']) : [];
            $input = isset($_POST['values']) && is_array($_POST['values']) ? wp_unslash($_POST['values']) : [];
            $values = [];
            foreach (Schema::fields() as $key => $field) {
                $mode = $modes[$key] ?? null;
                if (!in_array($mode, ['inherit', 'override'], true)) { throw new \InvalidArgumentException('Mode de réglage invalide.'); }
                if ($mode === 'inherit') { continue; }
                $raw = $input[$key] ?? null;
                if (!is_string($raw)) { throw new \InvalidArgumentException('Valeur invalide.'); }
                if ($field['type'] === 'bool') {
                    if (!in_array($raw, ['0', '1'], true)) { throw new \InvalidArgumentException('Valeur booléenne invalide.'); }
                    $values[$key] = $raw === '1';
                } else {
                    if (!preg_match('/^(0|[1-9][0-9]{0,3})$/D', $raw)) { throw new \InvalidArgumentException('Nombre entier requis.'); }
                    $values[$key] = (int) $raw;
                }
            }
            $revision = filter_var($_POST['revision'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            if ($revision === false || $revision === null) { throw new \InvalidArgumentException('Révision invalide.'); }
            $this->store->save($scope, $id, $values, $revision);
        } catch (\Throwable $error) {
            wp_die(esc_html($error->getMessage()), '', ['response' => 409, 'back_link' => true]);
        }
        wp_safe_redirect(add_query_arg('saved', '1', $this->url($scope, $id)));
        exit;
    }
    public function render(): void
    {
        $scope = isset($_GET['scope']) && is_string($_GET['scope']) ? sanitize_key(wp_unslash($_GET['scope'])) : 'site';
        $id = isset($_GET['object_id']) && is_scalar($_GET['object_id']) ? absint($_GET['object_id']) : 0;
        echo '<div class="wrap"><h1>WaasKit — FluentBooking Addon</h1>';
        echo '<p>Fondation 4.0 · Réglages contextuels et règles serveur. Paiement, participants sans e-mail et capacité avancée : non activés dans cette version.</p>';
        echo '<h2>Choisir le contexte</h2><ul>';
        if (self::allowed('site', 0)) { echo '<li><a href="' . esc_url($this->url()) . '">Réglages globaux</a></li>'; }
        foreach (Calendar::all() as $calendar) {
            if (self::allowed('calendar', (int) $calendar->id)) {
                echo '<li><a href="' . esc_url($this->url('calendar', (int) $calendar->id)) . '">Agenda : ' . esc_html($calendar->title ?: '#' . $calendar->id) . '</a></li>';
            }
            foreach (CalendarSlot::where('calendar_id', $calendar->id)->get() as $event) {
                if (!self::allowed('calendar_event', (int) $event->id)) { continue; }
                echo '<li><a href="' . esc_url($this->url('calendar_event', (int) $event->id)) . '">Événement : ' . esc_html($event->title) . '</a></li>';
            }
        }
        echo '</ul>';
        if (!self::allowed($scope, $id)) { echo '<p>Choisissez un contexte autorisé ci-dessus.</p></div>'; return; }
        $title = $scope === 'site' ? 'Réglages globaux' : ($scope === 'calendar' ? 'Agenda : ' . Calendar::find($id)->title : 'Événement : ' . CalendarSlot::find($id)->title);
        echo '<h2>' . esc_html($title) . '</h2>';
        if (isset($_GET['saved'])) { echo '<div class="notice notice-success"><p>Réglages enregistrés.</p></div>'; }
        try {
            $stored = $this->store->read($scope, $id);
            $effective = $this->store->effective($scope, $id);
        } catch (\Throwable $error) {
            echo '<div class="notice notice-error"><p>' . esc_html($error->getMessage()) . '</p></div></div>'; return;
        }
        echo '<p>La règle de limite est un premier module expérimental : elle contrôle les créations qui passent par BookingService::createBooking. Elle ne remplace pas la capacité native et ne couvre pas les modifications ou reports.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('waaskit_fb_save_' . $scope . '_' . $id);
        foreach (['action' => 'waaskit_fb_save', 'scope' => $scope, 'object_id' => $id, 'revision' => $stored['revision']] as $key => $value) {
            echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr((string) $value) . '">';
        }
        echo '<table class="form-table"><tbody>';
        foreach (Schema::fields() as $key => $field) {
            $overridden = array_key_exists($key, $stored['values']);
            $value = $overridden ? $stored['values'][$key] : $effective[$key]['value'];
            echo '<tr><th scope="row"><label for="value-' . esc_attr($key) . '">' . esc_html($field['label']) . '</label></th><td>';
            echo '<label>Origine <select name="modes[' . esc_attr($key) . ']">';
            echo '<option value="inherit"' . selected($overridden, false, false) . '>Hériter</option><option value="override"' . selected($overridden, true, false) . '>Définir ici</option></select></label> ';
            if ($field['type'] === 'bool') {
                echo '<select id="value-' . esc_attr($key) . '" name="values[' . esc_attr($key) . ']"><option value="0"' . selected($value, false, false) . '>Désactivé</option><option value="1"' . selected($value, true, false) . '>Activé</option></select>';
            } else {
                echo '<input type="number" id="value-' . esc_attr($key) . '" name="values[' . esc_attr($key) . ']" min="0" max="1000" value="' . esc_attr((string) $value) . '">';
            }
            $display = is_bool($effective[$key]['value']) ? ($effective[$key]['value'] ? 'activé' : 'désactivé') : (string) $effective[$key]['value'];
            echo '<p class="description">Valeur effective : ' . esc_html($display) . ' — origine : ' . esc_html($effective[$key]['source']) . '.</p></td></tr>';
        }
        echo '</tbody></table>';
        if (Plugin::compatible()) { submit_button('Enregistrer les réglages'); }
        echo '</form>';
        if (current_user_can('manage_options')) { $this->diagnostics(); }
        echo '</div>';
    }
    private function diagnostics(): void
    {
        echo '<hr><h2>Diagnostics</h2><ul>';
        foreach (['WordPress' => get_bloginfo('version'), 'PHP' => PHP_VERSION, 'FluentBooking' => defined('FLUENT_BOOKING_VERSION') ? FLUENT_BOOKING_VERSION : 'absent', 'Pro' => defined('FLUENT_BOOKING_PRO_VERSION') ? FLUENT_BOOKING_PRO_VERSION : 'absent', 'Compatibilité du socle' => Plugin::compatible() ? '2.4.x détectée ; recette exécutée sur 2.4.0' : 'non prise en charge'] as $name => $value) {
            echo '<li>' . esc_html($name . ' : ' . $value) . '</li>';
        }
        echo '</ul><h2>Migration historique — simulation uniquement</h2><p>Aucune ancienne option ne modifie automatiquement les nouvelles règles ou les paiements.</p><ul>';
        $found = false;
        foreach (CalendarSlot::all() as $event) {
            $settings = is_array($event->settings) ? $event->settings : [];
            $legacy = array_intersect_key($settings, array_flip(['fbgrp_one_per_spot', 'fbgrp_price_per_guest', 'fbgrp_hide_guest_email']));
            if (!$legacy) { continue; }
            $found = true;
            echo '<li>' . esc_html($event->title . ' (#' . $event->id . ') : ' . wp_json_encode($legacy)) . ' — correspondance à valider ; aucune conversion.</li>';
        }
        if (!$found) { echo '<li>Aucun ancien réglage détecté.</li>'; }
        echo '</ul>';
    }
}
