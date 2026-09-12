<?php
namespace WaasKit\FluentBooking\Admin;

use WaasKit\FluentBooking\Configuration\Schema;

/** Form vocabulary and validation for the single supported module. */
final class SettingsForm
{
    public static function parse(array $input): array
    {
        $values = [];
        $enabled = $input['enabled_mode'] ?? null;
        if (!in_array($enabled, ['inherit', '0', '1'], true)) {
            throw new \InvalidArgumentException('Choisissez si la limite doit être appliquée.');
        }
        if ($enabled !== 'inherit') { $values['enabled'] = $enabled === '1'; }
        $mode = $input['limit_mode'] ?? null;
        if (!in_array($mode, ['inherit', 'override'], true)) {
            throw new \InvalidArgumentException('Choisissez la provenance du nombre maximum.');
        }
        if ($mode === 'override') {
            $raw = $input['max_participants'] ?? null;
            if (!is_string($raw) || !preg_match('/^(0|[1-9][0-9]{0,3})$/D', $raw)) {
                throw new \InvalidArgumentException('Saisissez un nombre entier entre 0 et 1 000.');
            }
            $values['max_participants'] = (int) $raw;
        }
        return Schema::validate($values);
    }

    public static function render(string $scope, array $stored, array $effective): void
    {
        $inherit = $scope === 'site' ? 'Utiliser la valeur par défaut' : 'Utiliser le réglage du niveau supérieur';
        $enabled = array_key_exists('enabled', $stored) ? ($stored['enabled'] ? '1' : '0') : 'inherit';
        $override = array_key_exists('max_participants', $stored);
        echo '<table class="form-table" role="presentation"><tbody><tr><th scope="row"><label for="fba-enabled">Appliquer cette limite</label></th><td><select id="fba-enabled" name="enabled_mode" aria-describedby="fba-enabled-help">';
        foreach (['inherit' => $inherit, '1' => 'Oui, appliquer la limite', '0' => 'Non, garder les limites FluentBooking'] as $value => $label) {
            echo '<option value="' . esc_attr((string) $value) . '"' . selected($enabled, (string) $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select><p class="description" id="fba-enabled-help">Désactiver cette option conserve les limites natives de FluentBooking.</p></td></tr><tr><th scope="row"><label for="fba-maximum">Personnes maximum par demande</label></th><td><div class="fba-controls"><label class="screen-reader-text" for="fba-limit-mode">Provenance du maximum</label><select id="fba-limit-mode" name="limit_mode">';
        echo '<option value="inherit"' . selected($override, false, false) . '>' . esc_html($inherit) . '</option><option value="override"' . selected($override, true, false) . '>Choisir un nombre ici</option></select>';
        echo '<input type="number" class="small-text" id="fba-maximum" name="max_participants" min="0" max="1000" step="1" value="' . esc_attr((string) $effective['max_participants']['value']) . '" aria-describedby="fba-limit-help"></div><p class="description" id="fba-limit-help">Le réservant et ses invités sont comptés ensemble. Exemple : 4 autorise une demande de 4 personnes au total. 0 n’ajoute aucune limite.</p></td></tr></tbody></table>';
        $applies = $effective['enabled']['value'] && $effective['max_participants']['value'] > 0;
        echo '<div class="fba-effective"><strong>Réglage actuellement enregistré : </strong>' . esc_html($applies ? $effective['max_participants']['value'] . ' personnes maximum par demande.' : 'Aucune limite supplémentaire appliquée.') . '<p class="description">';
        echo 'Activation : ' . esc_html(self::source($effective['enabled']['source'])) . ' · Maximum : ' . esc_html(self::source($effective['max_participants']['source'])) . '. Le résultat est actualisé après enregistrement.</p></div>';
    }

    private static function source(string $source): string
    {
        return ['produit' => 'valeur par défaut', 'site' => 'réglages globaux', 'agenda' => 'réglages du calendrier', 'événement' => 'réglages de cet événement'][$source] ?? $source;
    }
}
