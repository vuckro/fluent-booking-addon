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
            $hook = add_submenu_page('fluent-booking', 'Modules', 'Modules', 'read', 'waaskit-fluent-booking', [$this, 'render']);
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
        return $scope === 'calendar_event' && $id > 0 && (bool) CalendarSlot::find($id)
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
            $stored = $this->store->read($scope, $id);
            $values = ['guest_options'=>GuestOptionsForm::parse(wp_unslash($_POST))];
            if (!CalendarSlot::find($id)->isMultiGuestEvent()) { throw new \InvalidArgumentException('Choisissez un événement de groupe.'); }
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
        if ($scope !== 'calendar_event') {
            $scope='calendar_event';$id=0;
            foreach(CalendarSlot::all() as $event) {if($event->isMultiGuestEvent() && self::allowed($scope,(int)$event->id)) {$id=(int)$event->id;break;}}
        }
        echo '<div class="wrap fba-settings"><header class="fba-header"><div><h1>Modules</h1><p>Personnalisez les informations et les tarifs des invités de votre événement.</p></div><a href="https://github.com/vuckro/fluent-booking-addon" target="_blank" rel="noopener noreferrer">Version alpha par WaasKit <span aria-hidden="true">↗</span><span class="screen-reader-text"> (nouvel onglet)</span></a></header>';
        $this->navigation($scope, $id);
        if (!self::allowed($scope, $id)) {
            echo '<p>Sélectionnez un événement de groupe accessible.</p></div>'; return;
        }
        try {
            $stored = $this->store->read($scope, $id);
        } catch (\Throwable $error) {
            echo '<div class="notice notice-error inline"><p>' . esc_html($error->getMessage()) . '</p></div></div>'; return;
        }
        if (isset($_GET['saved'])) { echo '<div class="notice notice-success inline"><p>Réglages enregistrés.</p></div>'; }
        if (!CalendarSlot::find($id)->isMultiGuestEvent()) {echo '<p>La personnalisation est disponible sur les événements de groupe.</p></div>';return;}
        echo '<section class="fba-card"><header class="fba-card-header"><div><h3>Invités et tarifs</h3><p>Les places et le maximum de personnes se règlent dans FluentBooking.</p></div></header>';
        echo '<form class="fba-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('waaskit_fb_save_' . $scope . '_' . $id);
        foreach (['action' => 'waaskit_fb_save', 'scope' => $scope, 'object_id' => $id, 'revision' => $stored['revision']] as $key => $value) {
            echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr((string) $value) . '">';
        }
        GuestOptionsForm::render(\WaasKit\FluentBooking\Guests\Options::validate($stored['values']['guest_options'] ?? []));
        if (Plugin::compatible()) { submit_button('Enregistrer les réglages'); }
        echo '</form><footer class="fba-card-footer">Ces options s’appliquent aux nouvelles réservations. Les réservations existantes conservent leur tarif ; leur report n’est pas pris en charge dans ce mode.</footer></section>';
        $this->guestGuidance($scope, $id);
        if (current_user_can('manage_options')) {
            echo '<details><summary>Diagnostics</summary>'; $this->diagnostics(); echo '</details>';
        }
        echo '</div>';
    }

    private function guestGuidance(string $scope, int $id): void
    {
        $event=CalendarSlot::find($id);
        $options=\WaasKit\FluentBooking\Guests\Options::validate($this->store->read('calendar_event',$id)['values']['guest_options']??[]);
        $native=['enabled'=>false,'limit'=>1];
        foreach($event->getBookingFields() as $field) {if(($field['name']??'')==='guests') {$native=$field;}}
        $base=admin_url('admin.php?page=fluent-booking#/calendars/'.(int)$event->calendar_id.'/slot-settings/'.$id.'/');
        $modes=['required'=>'obligatoire','optional'=>'facultatif','hidden'=>'masqué'];
        $enabled = $options['enabled'];
        $guestsAllowed = !empty($native['enabled']);
        $summary = [
            ['Invités supplémentaires', $guestsAllowed ? 'Autorisés' : 'Désactivés', 'Dans les questions FluentBooking'],
            ['Capacité du créneau', (int) $event->getMaxBookingPerSlot() . ' personnes', 'Toutes les réservations du créneau réunies'],
            ['Maximum par réservation', $guestsAllowed ? min((int) ($native['limit'] ?? 10), (int) $event->getMaxBookingPerSlot()) . ' personnes' : '1 personne', 'Maximum possible sur un créneau vide ; diminue avec les réservations'],
        ];
        if ($enabled) {
            $summary[] = ['Prix de base', $options['native_tariffs'] ? 'Au choix, par personne' : ($options['per_person_price'] ? 'Par personne' : 'Par réservation'), $options['native_tariffs'] ? 'Un seul tarif pour vous et pour chaque invité' : 'Ancien calcul personnalisé'];
            $summary[] = ['Identité des invités', 'Nom ' . $modes[$options['name_mode']], 'Courriel ' . $modes[$options['email_mode']]];
            $summary[] = ['Champs supplémentaires', count($options['fields']) ? count($options['fields']) . ' configuré(s)' : 'Aucun', count($options['fields']) ? implode(' · ', array_column($options['fields'], 'label')) : 'Aucune information complémentaire demandée'];
        }
        echo '<section class="fba-card fba-native-guide"><header class="fba-card-header"><div><h3>Résumé des réglages</h3><p>Valeurs enregistrées. Ce résumé est actualisé après chaque enregistrement.</p></div><span class="fba-summary-status '.($enabled?'is-enabled':'is-disabled').'">' . ($enabled ? 'Personnalisation activée' : 'Mode FluentBooking') . '</span></header><div class="fba-summary-body">';
        if ($enabled && !$guestsAllowed) {
            echo '<p class="fba-summary-notice"><strong>Invités désactivés dans FluentBooking.</strong> Activez « Invités supplémentaires » dans les questions de l’événement pour utiliser ces options.</p>';
        }
        echo '<dl class="fba-summary-grid">';
        foreach ($summary as [$label, $value, $hint]) {
            echo '<div><dt>' . esc_html($label) . '</dt><dd><strong>' . esc_html($value) . '</strong><span>' . esc_html($hint) . '</span></dd></div>';
        }
        echo '</dl><div class="fba-summary-links"><a class="button" href="' . esc_url($base . 'question-settings') . '">Réglages des invités</a><a class="button" href="' . esc_url($base . 'payment-settings') . '">Réglages du tarif de base</a></div></div></section>';
    }

    private function navigation(string $scope, int $id): void
    {
        echo '<form class="fba-context" method="get" action="' . esc_url(admin_url('admin.php')) . '"><input type="hidden" name="page" value="waaskit-fluent-booking"><label for="fba-context">À configurer</label><select id="fba-context" name="context">';
        foreach (CalendarSlot::all() as $event) {
            if ($event->isMultiGuestEvent() && self::allowed('calendar_event',(int)$event->id)) {
                $calendar=Calendar::find($event->calendar_id);
                $this->contextOption($event->title.' ('.($calendar->title??'').')','calendar_event',(int)$event->id,$scope,$id);
            }
        }
        echo '</select> <button class="button" type="submit">Afficher les réglages</button>';
        $this->calendarLink($scope, $id);
        echo '</form>';
        echo '<p class="description fba-context-help">Choisissez l’événement dont vous souhaitez personnaliser les invités. Les capacités, disponibilités et tarifs de base restent dans FluentBooking.</p>';
    }

    private function calendarLink(string $scope, int $id): void
    {
        if (!self::allowed($scope, $id)) { return; }
        $publicUrl = '';
        if ($scope === 'calendar_event') {
            $publicUrl = CalendarSlot::find($id)->getPublicUrl();

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
        echo '<div><dt>Modules disponibles</dt><dd>Informations et tarifs par invité</dd></div>';
        echo '</dl></section></div>';
    }
}
