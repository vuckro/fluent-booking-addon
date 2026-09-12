<?php
namespace WaasKit\FluentBooking\Integrations\FluentBooking;

use WaasKit\FluentBooking\Plugin;

/** Use FluentBooking's existing generic integration screen, not a custom Vue route. */
final class NativeSettings
{
    public const KEY = 'waaskit_addon';

    public function __construct(private ConfigurationStore $store) {}

    public static function supported(): bool
    {
        return Plugin::compatible() && defined('FLUENT_BOOKING_PRO_VERSION')
            && version_compare(FLUENT_BOOKING_PRO_VERSION, '2.4.0', '>=')
            && version_compare(FLUENT_BOOKING_PRO_VERSION, '2.5.0', '<');
    }

    public static function url(): string
    {
        return admin_url('admin.php?page=fluent-booking#/settings/configure-integrations/' . self::KEY);
    }

    public function register(): void
    {
        add_filter('fluent_booking/settings_menu_items', [$this, 'menu'], 40);
        add_filter('fluent_booking/get_client_settings_' . self::KEY, [$this, 'settings']);
        add_filter('fluent_booking/get_client_field_settings_' . self::KEY, [$this, 'fields']);
        add_action('fluent_booking/save_client_settings_' . self::KEY, [$this, 'save']);
    }

    public function menu(array $items): array
    {
        if (!self::supported() || !current_user_can('manage_options')) { return $items; }
        $items[self::KEY] = [
            'title' => __('Modules', 'waaskit-fluent-booking'),
            'disable' => false,
            'el_icon' => 'Operation',
            'component_type' => 'StandAloneComponent',
            'class' => 'waaskit_addon',
            'route' => ['name' => 'configure-integrations', 'params' => ['settings_key' => self::KEY]],
        ];
        return $items;
    }

    private function authorize(): void
    {
        if (!current_user_can('manage_options')) {
            throw new \RuntimeException('Accès refusé aux réglages globaux.');
        }
        if (!self::supported()) {
            throw new \RuntimeException('Cet écran nécessite FluentBooking et Pro 2.4.x.');
        }
    }

    public function settings($unused = []): array
    {
        $this->authorize();
        $stored = $this->store->read('site');
        $values = $stored['values'];
        return [
            '_revision' => (string) $stored['revision'],
            'enabled' => array_key_exists('enabled', $values) ? ($values['enabled'] ? 'on' : 'off') : 'inherit',
            'max_participants' => array_key_exists('max_participants', $values) ? (string) $values['max_participants'] : '',
        ];
    }

    public function fields($unused = []): array
    {
        $this->authorize();
        $effective = $this->store->effective('site');
        $contextUrl = add_query_arg(['page' => 'waaskit-fluent-booking', 'scope' => 'site'], admin_url('admin.php'));
        $diagnosticsUrl = add_query_arg('tab', 'diagnostics', $contextUrl);
        return [
            'title' => __('Modules — Participants', 'waaskit-fluent-booking'),
            'subtitle' => __('Réglages globaux de Fluent Booking Addon, hérités par vos calendriers et événements.', 'waaskit-fluent-booking'),
            'description' => '<p>Limitez le nombre de participants par demande de réservation. Les réglages spécifiques d’un calendrier ou d’un événement restent prioritaires.</p>'
                . '<p><a href="' . esc_url($contextUrl) . '">Réglages par calendrier et événement</a> · <a href="' . esc_url($diagnosticsUrl) . '">Diagnostics</a></p>'
                . '<p><small><a href="https://github.com/vuckro/fluent-booking-addon">Version alpha par WaasKit</a> · Module expérimental : les modifications et reports ne sont pas couverts. Cette limite ne remplace pas la capacité de la séance.</small></p>',
            'fields' => [
                'enabled' => [
                    'type' => 'select',
                    'label' => __('Activation des règles', 'waaskit-fluent-booking'),
                    'options' => [
                        'inherit' => __('Hériter des valeurs par défaut', 'waaskit-fluent-booking'),
                        'off' => __('Désactivé', 'waaskit-fluent-booking'),
                        'on' => __('Activé', 'waaskit-fluent-booking'),
                    ],
                    'inline_help' => esc_html('Valeur effective : ' . ($effective['enabled']['value'] ? 'activé' : 'désactivé') . '. Les valeurs sont actualisées après enregistrement.'),
                ],
                'max_participants' => [
                    'type' => 'text',
                    'label' => __('Maximum de participants par demande', 'waaskit-fluent-booking'),
                    'placeholder' => __('Laisser vide pour hériter', 'waaskit-fluent-booking'),
                    'inline_help' => esc_html('De 0 à 1 000. La valeur 0 n’ajoute aucune limite. Laisser vide pour hériter des valeurs par défaut. Valeur effective : ' . $effective['max_participants']['value'] . '.'),
                ],
            ],
            'save_btn_text' => __('Enregistrer les réglages', 'waaskit-fluent-booking'),
        ];
    }

    public function save($input): void
    {
        $this->authorize();
        if (!is_array($input) || !isset($input['_revision'], $input['enabled'], $input['max_participants'])
            || array_diff(array_keys($input), ['_revision', 'enabled', 'max_participants'])) {
            throw new \InvalidArgumentException('Formulaire incomplet ou invalide. Rechargez la page.');
        }
        $revision = filter_var($input['_revision'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($revision === false || !in_array($input['enabled'], ['inherit', 'off', 'on'], true)
            || !is_string($input['max_participants'])) {
            throw new \InvalidArgumentException('Valeur de réglage invalide.');
        }
        $values = [];
        if ($input['enabled'] !== 'inherit') { $values['enabled'] = $input['enabled'] === 'on'; }
        $max = trim($input['max_participants']);
        if ($max !== '') {
            if (!preg_match('/^(0|[1-9][0-9]{0,3})$/D', $max) || (int) $max > 1000) {
                throw new \InvalidArgumentException('Le maximum doit être un entier compris entre 0 et 1 000.');
            }
            $values['max_participants'] = (int) $max;
        }
        $this->store->save('site', 0, $values, $revision);
    }
}
