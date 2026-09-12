<?php
namespace WaasKit\FluentBooking\Admin;

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
                wp_enqueue_script('fba-settings', plugins_url('assets/admin/settings.js', dirname(__DIR__, 2) . '/wk-fluent-multireservation.php'), [], Plugin::VERSION, true);
                $cssVersion = Plugin::VERSION . '.' . filemtime(dirname(__DIR__, 2) . '/assets/admin/settings.css');
                wp_enqueue_style('waaskit-fb-admin', plugins_url('assets/admin/settings.css', dirname(__DIR__, 2) . '/wk-fluent-multireservation.php'), [], $cssVersion);
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
            $values = SettingsForm::parse(wp_unslash($_POST));
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
        (new Header())->render();
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
        if (!empty($effective['booking_profile']['value']['enabled'])) {
            echo '<div class="notice notice-warning inline"><p>Un ancien profil expérimental est encore actif pour ce contexte. Les nouvelles réservations concernées sont bloquées. Un administrateur doit examiner ce profil avant de revenir aux réglages natifs.</p></div>';
        }
        echo '<section class="fba-card" aria-labelledby="fba-participants"><header class="fba-card-header"><div><h3 id="fba-participants">Limite de participants</h3><p>Limitez le nombre de personnes dans une seule demande de réservation.</p></div></header>';
        echo '<form class="fba-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('waaskit_fb_save_' . $scope . '_' . $id);
        foreach (['action' => 'waaskit_fb_save', 'scope' => $scope, 'object_id' => $id, 'revision' => $stored['revision']] as $key => $value) {
            echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr((string) $value) . '">';
        }
        SettingsForm::render($scope, $stored['values'], $effective);
        if (Plugin::compatible()) { submit_button('Enregistrer les réglages'); }
        echo '</form><footer class="fba-card-footer">Cette limite complète la capacité des créneaux définie dans FluentBooking. Elle ne change ni les prix, ni les paiements, ni les réservations existantes. Les modifications et reports ne sont pas couverts.</footer></section>';
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
        echo '</select> <button class="button" type="submit">Afficher les réglages</button>';
        $this->calendarLink($scope, $id);
        echo '</form>';
        echo '<p class="description fba-context-help">Un réglage global s’applique à tous les calendriers. Chaque calendrier ou événement peut utiliser sa propre valeur.</p>';
    }

    private function calendarLink(string $scope, int $id): void
    {
        if (!self::allowed($scope, $id)) { return; }
        $publicUrl = '';
        if ($scope === 'calendar_event') {
            $publicUrl = CalendarSlot::find($id)->getPublicUrl();
        } elseif ($scope === 'calendar') {
            $publicUrl = Calendar::find($id)->getLandingPageUrl();
        }
        if ($publicUrl) {
            echo '<a class="button" href="' . esc_url($publicUrl) . '" target="_blank" rel="noopener noreferrer">' . ($scope === 'calendar_event' ? 'Voir la page de réservation' : 'Voir le calendrier') . ' <span aria-hidden="true">↗</span><span class="screen-reader-text"> (nouvel onglet)</span></a>';
        } elseif ($scope !== 'site') {
            echo '<span class="description">La page publique de ce calendrier n’est pas activée dans FluentBooking.</span>';
        }
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
        echo '<div><dt>Modules disponibles</dt><dd>Limite de participants par demande</dd></div>';
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
