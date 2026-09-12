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
            add_action('admin_enqueue_scripts', static function ($current) use ($hook) {
                if ($current !== $hook) { return; }
                $base = plugins_url('assets/admin/', dirname(__DIR__, 2) . '/wk-fluent-multireservation.php');
                wp_enqueue_style('waaskit-fb-admin', $base . 'settings.css', [], Plugin::VERSION);
                wp_enqueue_script('waaskit-fb-admin', $base . 'settings.js', [], Plugin::VERSION, true);
            });
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
        $diagnostics = isset($_GET['tab']) && $_GET['tab'] === 'diagnostics' && current_user_can('manage_options');
        echo '<div class="wrap fba-dashboard"><header class="fba-header"><div><h1>Fluent Booking Addon</h1><p>Organisez vos modules et adaptez leurs réglages à chaque calendrier.</p></div>';
        echo '<a class="fba-credit" href="https://github.com/vuckro/fluent-booking-addon">Version alpha par WaasKit <span aria-hidden="true">↗</span></a></header>';
        echo '<nav class="nav-tab-wrapper" aria-label="Navigation de l’extension"><a class="nav-tab' . (!$diagnostics ? ' nav-tab-active' : '') . '" href="' . esc_url($this->url($scope, $id)) . '"' . (!$diagnostics ? ' aria-current="page"' : '') . '>Modules et réglages</a>';
        if (current_user_can('manage_options')) {
            echo '<a class="nav-tab' . ($diagnostics ? ' nav-tab-active' : '') . '" href="' . esc_url(add_query_arg('tab', 'diagnostics', $this->url($scope, $id))) . '"' . ($diagnostics ? ' aria-current="page"' : '') . '>Diagnostics</a>';
        }
        echo '</nav>';
        if ($diagnostics) { echo '<div class="fba-panel fba-diagnostics">'; $this->diagnostics(); echo '</div></div>'; return; }
        echo '<div class="fba-layout">';
        $this->navigation($scope, $id);
        echo '<main class="fba-content" id="fba-settings">';
        if (!self::allowed($scope, $id)) { echo '<div class="fba-panel"><h2>Choisir un calendrier</h2><p>Sélectionnez un calendrier ou un événement accessible dans la navigation.</p></div></main></div></div>'; return; }
        $title = $scope === 'site' ? 'Réglages globaux' : ($scope === 'calendar' ? Calendar::find($id)->title : CalendarSlot::find($id)->title);
        $description = match ($scope) {
            'site' => 'Ces valeurs s’appliquent aux calendriers et événements qui en héritent.',
            'calendar' => 'Ces valeurs s’appliquent aux événements de ce calendrier, sauf réglage spécifique.',
            default => 'Personnalisez cet événement ou conservez les valeurs héritées de son calendrier.',
        };
        echo '<div class="fba-context"><p class="fba-eyebrow">' . esc_html(match ($scope) { 'site' => 'Tous les calendriers', 'calendar' => 'Calendrier', default => 'Événement' }) . '</p><h2>' . esc_html($title) . '</h2><p>' . esc_html($description) . '</p></div>';
        if (isset($_GET['saved'])) { echo '<div class="notice notice-success inline" role="status"><p>Réglages enregistrés.</p></div>'; }
        try {
            $stored = $this->store->read($scope, $id);
            $effective = $this->store->effective($scope, $id);
        } catch (\Throwable $error) {
            echo '<div class="notice notice-error inline"><p>' . esc_html($error->getMessage()) . '</p></div></main></div></div>'; return;
        }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('waaskit_fb_save_' . $scope . '_' . $id);
        foreach (['action' => 'waaskit_fb_save', 'scope' => $scope, 'object_id' => $id, 'revision' => $stored['revision']] as $key => $value) {
            echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr((string) $value) . '">';
        }
        echo '<section class="fba-panel" aria-labelledby="fba-module-title"><div class="fba-panel-heading"><div><h3 id="fba-module-title">Participants</h3><p>Limitez le nombre de participants par demande de réservation.</p></div><span class="fba-badge">Expérimental</span></div>';
        foreach (Schema::fields() as $key => $field) {
            $overridden = array_key_exists($key, $stored['values']);
            $value = $overridden ? $stored['values'][$key] : $effective[$key]['value'];
            $label = $key === 'enabled' ? 'Activation des règles' : $field['label'];
            if ($key === 'max_participants') { $label = 'Maximum de participants'; }
            echo '<div class="fba-setting"><div class="fba-setting-label"><label for="value-' . esc_attr($key) . '">' . esc_html($label) . '</label>';
            echo '<p>' . esc_html($key === 'enabled' ? 'Activez les règles de l’extension pour ce contexte.' : 'Par demande. La valeur 0 n’ajoute aucune limite.') . '</p></div><div class="fba-setting-control">';
            echo '<div class="fba-controls"><label class="fba-control-label" for="mode-' . esc_attr($key) . '">Application<select id="mode-' . esc_attr($key) . '" name="modes[' . esc_attr($key) . ']" data-fba-mode="value-' . esc_attr($key) . '">';
            echo '<option value="inherit"' . selected($overridden, false, false) . '>Hériter</option><option value="override"' . selected($overridden, true, false) . '>Définir ici</option></select></label><div class="fba-value"><span aria-hidden="true">Valeur</span>';
            if ($field['type'] === 'bool') {
                echo '<select id="value-' . esc_attr($key) . '" aria-describedby="effective-' . esc_attr($key) . '" name="values[' . esc_attr($key) . ']"><option value="0"' . selected($value, false, false) . '>Désactivé</option><option value="1"' . selected($value, true, false) . '>Activé</option></select>';
            } else {
                echo '<input type="number" id="value-' . esc_attr($key) . '" aria-describedby="effective-' . esc_attr($key) . '" name="values[' . esc_attr($key) . ']" min="0" max="1000" value="' . esc_attr((string) $value) . '">';
            }
            $display = is_bool($effective[$key]['value']) ? ($effective[$key]['value'] ? 'activé' : 'désactivé') : (string) $effective[$key]['value'];
            $source = ['produit' => 'valeurs par défaut', 'site' => 'réglages globaux', 'agenda' => 'calendrier', 'événement' => 'cet événement'][$effective[$key]['source']] ?? $effective[$key]['source'];
            echo '</div></div><p class="fba-effective" id="effective-' . esc_attr($key) . '">Valeur effective : <strong>' . esc_html($display) . '</strong> · ' . esc_html($source) . '</p></div></div>';
        }
        echo '<details class="fba-help"><summary>Ce que couvre ce module</summary><p>Cette première règle limite les nouvelles demandes prises en charge. Elle ne remplace pas le nombre de places de la séance et ne s’applique pas aux modifications ou aux reports.</p></details></section>';
        echo '<div class="fba-save">';
        if (Plugin::compatible()) { submit_button('Enregistrer les réglages', 'primary', 'submit', false); }
        echo '<p>Les valeurs effectives sont actualisées après enregistrement.</p></div></form></main></div></div>';
    }
    private function navigation(string $scope, int $id): void
    {
        echo '<aside class="fba-sidebar"><nav aria-label="Calendriers et événements"><h2>Contexte des réglages</h2><p class="description">Global → calendrier → événement</p>';
        if (self::allowed('site', 0)) { $this->contextLink('Réglages globaux', 'site', 0, $scope === 'site'); }
        $currentCalendar = $scope === 'calendar_event' && self::allowed($scope, $id) ? (int) CalendarSlot::find($id)->calendar_id : ($scope === 'calendar' ? $id : 0);
        $groups = 0;
        foreach (Calendar::all() as $calendar) {
            $events = CalendarSlot::where('calendar_id', $calendar->id)->get();
            $visible = [];
            foreach ($events as $event) {
                if (self::allowed('calendar_event', (int) $event->id)) { $visible[] = $event; }
            }
            $canEdit = self::allowed('calendar', (int) $calendar->id);
            if (!$canEdit && !$visible) { continue; }
            $open = $currentCalendar === (int) $calendar->id || ($currentCalendar === 0 && $groups === 0);
            $groups++;
            echo '<details class="fba-agenda"' . ($open ? ' open' : '') . '><summary>' . esc_html($calendar->title ?: 'Calendrier #' . $calendar->id) . '<span class="fba-count" aria-label="' . esc_attr(count($visible) . ' événements accessibles') . '">' . count($visible) . '</span></summary><div class="fba-agenda-links">';
            if ($canEdit) { $this->contextLink('Réglages du calendrier', 'calendar', (int) $calendar->id, $scope === 'calendar' && $id === (int) $calendar->id); }
            foreach ($visible as $event) { $this->contextLink($event->title, 'calendar_event', (int) $event->id, $scope === 'calendar_event' && $id === (int) $event->id); }
            if (!$visible) { echo '<p class="description">Aucun événement accessible.</p>'; }
            echo '</div></details>';
        }
        if (!$groups) { echo '<p class="description">Aucun calendrier accessible.</p>'; }
        echo '</nav></aside>';
    }
    private function contextLink(string $label, string $scope, int $id, bool $active): void
    {
        echo '<a class="fba-context-link' . ($active ? ' is-active' : '') . '" href="' . esc_url($this->url($scope, $id)) . '"' . ($active ? ' aria-current="page"' : '') . '>' . esc_html($label) . '</a>';
    }
    private function diagnostics(): void
    {
        echo '<h2>État de l’installation</h2><p>Versions installées et compatibilité de l’extension.</p><ul class="fba-system-list">';
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
