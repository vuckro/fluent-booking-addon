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
            $hook = add_submenu_page('fluent-booking', 'Fluent Booking Addon', 'Modules', 'read', 'waaskit-fluent-booking', [$this, 'render']);
            add_filter('admin_body_class', static function ($classes) use ($hook) {
                return get_current_screen()->id === $hook ? $classes . ' fba-admin' : $classes;
            });
            add_action('admin_enqueue_scripts', static function ($screen) use ($hook) {
                if ($screen !== $hook) { return; }
                wp_enqueue_script('waaskit-fb-theme', plugins_url('assets/admin/theme.js', dirname(__DIR__, 2) . '/wk-fluent-multireservation.php'), [], Plugin::VERSION, false);
                wp_enqueue_script('fba-profile', plugins_url('assets/admin/profile.js', dirname(__DIR__, 2) . '/wk-fluent-multireservation.php'), [], Plugin::VERSION, true);
                wp_enqueue_style('waaskit-fb-admin', plugins_url('assets/admin/settings.css', dirname(__DIR__, 2) . '/wk-fluent-multireservation.php'), [], Plugin::VERSION);
            });
        }, 30);
        add_filter('fluent_booking/admin_menu_items', [$this, 'menuItems']);
        add_action('admin_post_waaskit_fb_save', [$this, 'save']);
    }
    public function menuItems(array $items): array
    {
        if (!is_admin() || !current_user_can('read')) { return $items; }
        $items[] = ['key' => 'waaskit-modules', 'label' => __('Modules', 'waaskit-fluent-booking'),
            'permalink' => admin_url('admin.php?page=waaskit-fluent-booking')];
        return $items;
    }

    private function header(): void
    {
        $base = admin_url('admin.php?page=fluent-booking#/');
        $items = [
            ['key' => 'dashboard', 'label' => __('Dashboard', 'fluent-booking'), 'permalink' => $base],
            ['key' => 'calendars', 'label' => __('Calendars', 'fluent-booking'), 'permalink' => $base . 'calendars'],
            ['key' => 'scheduled-events', 'label' => __('Bookings', 'fluent-booking'), 'permalink' => $base . 'scheduled-events'],
            ['key' => 'availability', 'label' => __('Availability', 'fluent-booking'), 'permalink' => $base . 'availability'],
        ];
        $assets = \FluentBooking\App\App::getInstance()['url.assets'];
        echo '<nav class="fba-navigation" aria-label="FluentBooking"><a class="fba-logo" href="' . esc_url($base) . '"><img src="' . esc_url($assets . 'images/logo.svg') . '" class="fba-logo-light" alt="FluentBooking"><img src="' . esc_url($assets . 'images/logo_dark.svg') . '" class="fba-logo-dark" alt="FluentBooking"></a><div class="fba-navigation-links">';
        foreach (apply_filters('fluent_booking/admin_menu_items', $items) as $item) {
            $active = $item['key'] === 'waaskit-modules';
            echo '<a href="' . esc_url($item['permalink']) . '"' . ($active ? ' aria-current="page"' : '') . '>' . esc_html($item['label']) . '</a>';
        }
        echo '</div><div class="fba-navigation-actions"><button type="button" class="fba-theme-toggle" aria-label="Activer le mode sombre" aria-pressed="false"><svg class="fcal_light_icon" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"> <path d="M17.9163 11.7317C16.9166 12.2654 15.7748 12.5681 14.5623 12.5681C10.6239 12.5681 7.43128 9.37543 7.43128 5.43705C7.43128 4.22456 7.73388 3.08274 8.2677 2.08301C4.72272 2.91382 2.08301 6.09562 2.08301 9.89393C2.08301 14.3246 5.67476 17.9163 10.1054 17.9163C13.9038 17.9163 17.0855 15.2767 17.9163 11.7317Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> </svg><svg class="fcal_dark_icon" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#clip0_2906_20675)"> <path d="M14.1663 10.0007C14.1663 12.3018 12.3009 14.1673 9.99967 14.1673C7.69849 14.1673 5.83301 12.3018 5.83301 10.0007C5.83301 7.69946 7.69849 5.83398 9.99967 5.83398C12.3009 5.83398 14.1663 7.69946 14.1663 10.0007Z" stroke="currentColor" stroke-width="1.5"/> <path d="M9.99984 1.66699V2.91699M9.99984 17.0837V18.3337M15.8922 15.8931L15.0083 15.0092M4.99089 4.99137L4.107 4.10749M18.3332 10.0003H17.0832M2.9165 10.0003H1.6665M15.8926 4.10758L15.0087 4.99147M4.99129 15.0093L4.10741 15.8932" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></g><defs><clipPath id="clip0_2906_20675"><rect width="20" height="20" fill="currentColor"/></clipPath></defs> </svg></button>';
        if (PermissionManager::userCan('manage_all_data')) {
            echo '<a class="fba-navigation-settings" href="' . esc_url($base . 'settings/general-settings') . '"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024"><path fill="currentColor" d="M600.704 64a32 32 0 0 1 30.464 22.208l35.2 109.376c14.784 7.232 28.928 15.36 42.432 24.512l112.384-24.192a32 32 0 0 1 34.432 15.36L944.32 364.8a32 32 0 0 1-4.032 37.504l-77.12 85.12a357.12 357.12 0 0 1 0 49.024l77.12 85.248a32 32 0 0 1 4.032 37.504l-88.704 153.6a32 32 0 0 1-34.432 15.296L708.8 803.904c-13.44 9.088-27.648 17.28-42.368 24.512l-35.264 109.376A32 32 0 0 1 600.704 960H423.296a32 32 0 0 1-30.464-22.208L357.696 828.48a351.616 351.616 0 0 1-42.56-24.64l-112.32 24.256a32 32 0 0 1-34.432-15.36L79.68 659.2a32 32 0 0 1 4.032-37.504l77.12-85.248a357.12 357.12 0 0 1 0-48.896l-77.12-85.248A32 32 0 0 1 79.68 364.8l88.704-153.6a32 32 0 0 1 34.432-15.296l112.32 24.256c13.568-9.152 27.776-17.408 42.56-24.64l35.2-109.312A32 32 0 0 1 423.232 64H600.64zm-23.424 64H446.72l-36.352 113.088-24.512 11.968a294.113 294.113 0 0 0-34.816 20.096l-22.656 15.36-116.224-25.088-65.28 113.152 79.68 88.192-1.92 27.136a293.12 293.12 0 0 0 0 40.192l1.92 27.136-79.808 88.192 65.344 113.152 116.224-25.024 22.656 15.296a294.113 294.113 0 0 0 34.816 20.096l24.512 11.968L446.72 896h130.688l36.48-113.152 24.448-11.904a288.282 288.282 0 0 0 34.752-20.096l22.592-15.296 116.288 25.024 65.28-113.152-79.744-88.192 1.92-27.136a293.12 293.12 0 0 0 0-40.256l-1.92-27.136 79.808-88.128-65.344-113.152-116.288 24.96-22.592-15.232a287.616 287.616 0 0 0-34.752-20.096l-24.448-11.904L577.344 128zM512 320a192 192 0 1 1 0 384 192 192 0 0 1 0-384zm0 64a128 128 0 1 0 0 256 128 128 0 0 0 0-256z"></path></svg> ' . esc_html__('Settings', 'fluent-booking') . '</a>';
        }
        echo '</div></nav>';
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
                $mode = $modes[$key] ?? ($field['type'] === 'profile' ? 'inherit' : null);
                if (!in_array($mode, ['inherit', 'override'], true)) { throw new \InvalidArgumentException('Mode de réglage invalide.'); }
                if ($mode === 'inherit') { continue; }
                if ($field['type'] === 'profile') {
                    $values[$key] = ProfileForm::parse(isset($_POST['profile']) && is_array($_POST['profile']) ? wp_unslash($_POST['profile']) : []);
                    continue;
                }
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
        $this->header();
        $scope = isset($_GET['scope']) && is_string($_GET['scope']) ? sanitize_key(wp_unslash($_GET['scope'])) : 'site';
        $id = isset($_GET['object_id']) && is_scalar($_GET['object_id']) ? absint($_GET['object_id']) : 0;
        if (isset($_GET['context']) && is_string($_GET['context']) && preg_match('/^(site|calendar|calendar_event):([0-9]+)$/D', $_GET['context'], $match)) {
            $scope = $match[1]; $id = (int) $match[2];
        }
        echo '<div class="wrap fba-settings"><header class="fba-header"><div><h1>Fluent Booking Addon</h1><p>Gérez vos modules et leurs réglages.</p></div><a href="https://github.com/vuckro/fluent-booking-addon" target="_blank" rel="noopener noreferrer">Version alpha par WaasKit <span aria-hidden="true">↗</span><span class="screen-reader-text"> (nouvel onglet)</span></a></header>';
        echo '<h2>Modules</h2>';
        $this->navigation($scope, $id);
        if (!self::allowed($scope, $id)) {
            echo '<p>Sélectionnez un calendrier ou un événement accessible.</p></div>'; return;
        }
        try {
            $stored = $this->store->read($scope, $id);
            $effective = $this->store->effective($scope, $id);
        } catch (\Throwable $error) {
            echo '<div class="notice notice-error inline"><p>' . esc_html($error->getMessage()) . '</p></div></div>'; return;
        }
        if (isset($_GET['saved'])) { echo '<div class="notice notice-success inline"><p>Réglages enregistrés.</p></div>'; }
        echo '<section class="fba-card" aria-labelledby="fba-participants"><header class="fba-card-header"><div><h3 id="fba-participants">Participants</h3><p>Limitez le nombre de participants par demande de réservation.</p></div><span class="fba-badge">Expérimental</span></header>';
        echo '<form class="fba-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('waaskit_fb_save_' . $scope . '_' . $id);
        foreach (['action' => 'waaskit_fb_save', 'scope' => $scope, 'object_id' => $id, 'revision' => $stored['revision']] as $key => $value) {
            echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr((string) $value) . '">';
        }
        echo '<table class="form-table" role="presentation"><tbody>';
        foreach (Schema::fields() as $key => $field) {
            $overridden = array_key_exists($key, $stored['values']);
            $value = $overridden ? $stored['values'][$key] : $effective[$key]['value'];
            $label = $field['label'];
            echo '<tr><th scope="row"><label for="value-' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td>';
            echo '<label class="screen-reader-text" for="mode-' . esc_attr($key) . '">Application : ' . esc_html($label) . '</label><select id="mode-' . esc_attr($key) . '" name="modes[' . esc_attr($key) . ']">';
            echo '<option value="inherit"' . selected($overridden, false, false) . '>Hériter</option><option value="override"' . selected($overridden, true, false) . '>Définir ici</option></select> ';
            if ($field['type'] === 'profile') {
                ProfileForm::render($value);
                echo '</td></tr>';
                continue;
            } elseif ($field['type'] === 'bool') {
                echo '<select id="value-' . esc_attr($key) . '" name="values[' . esc_attr($key) . ']"><option value="0"' . selected($value, false, false) . '>Désactivé</option><option value="1"' . selected($value, true, false) . '>Activé</option></select>';
            } else {
                echo '<input class="small-text" type="number" id="value-' . esc_attr($key) . '" name="values[' . esc_attr($key) . ']" min="0" max="1000" value="' . esc_attr((string) $value) . '">';
            }
            $display = is_bool($effective[$key]['value']) ? ($effective[$key]['value'] ? 'activé' : 'désactivé') : (string) $effective[$key]['value'];
            $source = ['produit' => 'valeurs par défaut', 'site' => 'réglages globaux', 'agenda' => 'calendrier', 'événement' => 'cet événement'][$effective[$key]['source']] ?? $effective[$key]['source'];
            echo '<p class="description">Valeur effective : ' . esc_html($display . ' · ' . $source) . '</p></td></tr>';
        }
        echo '</tbody></table><p class="description fba-help">Choisissez « Définir ici » pour appliquer une valeur. « Hériter » ignore la valeur saisie. Le maximum 0 n’ajoute aucune limite.</p>';
        if (Plugin::compatible()) { submit_button('Enregistrer les réglages'); }
        echo '</form><footer class="fba-card-footer">Nouvelles demandes uniquement, hors modifications et reports. Ne remplace pas la capacité de la séance.</footer></section>';
        if (current_user_can('manage_options')) {
            echo '<details><summary>Diagnostics</summary>'; $this->diagnostics(); echo '</details>';
        }
        echo '</div>';
    }

    private function navigation(string $scope, int $id): void
    {
        echo '<form class="fba-context" method="get" action="' . esc_url(admin_url('admin.php')) . '"><input type="hidden" name="page" value="waaskit-fluent-booking"><label for="fba-context">Réglages de </label><select id="fba-context" name="context">';
        if (self::allowed('site', 0)) { $this->contextOption('Tous les calendriers', 'site', 0, $scope, $id); }
        $events = [];
        foreach (CalendarSlot::all() as $event) { $events[(int) $event->calendar_id][] = $event; }
        foreach (Calendar::all() as $calendar) {
            $calendarId = (int) $calendar->id;
            if (self::allowed('calendar', $calendarId)) {
                $this->contextOption('Calendrier : ' . $calendar->title, 'calendar', $calendarId, $scope, $id);
            }
            foreach ($events[$calendarId] ?? [] as $event) {
                if (self::allowed('calendar_event', (int) $event->id)) {
                    $this->contextOption($calendar->title . ' / ' . $event->title, 'calendar_event', (int) $event->id, $scope, $id);
                }
            }
        }
        echo '</select> <button class="button" type="submit">Afficher</button><p class="description">Les événements héritent de leur calendrier, puis des réglages globaux.</p></form>';
    }

    private function contextOption(string $label, string $scope, int $id, string $currentScope, int $currentId): void
    {
        echo '<option value="' . esc_attr($scope . ':' . $id) . '"' . selected($scope === $currentScope && $id === $currentId, true, false) . '>' . esc_html($label) . '</option>';
    }

    private function diagnostics(): void
    {
        echo '<div class="fba-diagnostics"><section><h3>État de l’installation</h3><p>Versions installées et compatibilité de l’extension.</p><dl class="fba-system-list">';
        foreach (['WordPress' => get_bloginfo('version'), 'PHP' => PHP_VERSION, 'FluentBooking' => defined('FLUENT_BOOKING_VERSION') ? FLUENT_BOOKING_VERSION : 'absent', 'Pro' => defined('FLUENT_BOOKING_PRO_VERSION') ? FLUENT_BOOKING_PRO_VERSION : 'absent', 'Compatibilité du socle' => Plugin::compatible() ? '2.4.x détectée ; recette exécutée sur 2.4.0' : 'non prise en charge'] as $name => $value) {
            echo '<div><dt>' . esc_html($name) . '</dt><dd>' . esc_html($value) . '</dd></div>';
        }
        echo '<div><dt>Retenues de places à examiner</dt><dd>' . (int) (new \WaasKit\FluentBooking\Infrastructure\CapacityStore())->orphanCount() . '</dd></div>';
        echo '</dl></section><section><h3>Migration historique — simulation uniquement</h3><p>Aucune ancienne option ne modifie automatiquement les nouvelles règles ou les paiements.</p><ul>';
        $found = false;
        foreach (CalendarSlot::all() as $event) {
            $settings = is_array($event->settings) ? $event->settings : [];
            $legacy = array_intersect_key($settings, array_flip(['fbgrp_one_per_spot', 'fbgrp_price_per_guest', 'fbgrp_hide_guest_email']));
            if (!$legacy) { continue; }
            $found = true;
            echo '<li>' . esc_html($event->title . ' (#' . $event->id . ') : ' . wp_json_encode($legacy)) . ' — correspondance à valider ; aucune conversion.</li>';
        }
        if (!$found) { echo '<li>Aucun ancien réglage détecté.</li>'; }
        echo '</ul></section></div>';
    }
}
