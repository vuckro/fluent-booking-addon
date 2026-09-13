<?php
namespace WaasKit\FluentBooking\Admin;

use WaasKit\FluentBooking\Configuration\Schema;

/** One user decision maps to the existing two stored settings. */
final class SettingsForm
{
    public static function mode(string $scope, array $stored, array $effective): string
    {
        if ($scope !== 'site' && !array_key_exists('enabled', $stored) && !array_key_exists('max_participants', $stored)) {
            return 'inherit';
        }
        return $effective['enabled']['value'] && $effective['max_participants']['value'] > 0 ? 'limit' : 'off';
    }

    public static function parse(array $input, string $scope, array $stored, array $effective): array
    {
        $mode = $input['policy'] ?? null;
        if (!in_array($mode, $scope === 'site' ? ['off', 'limit'] : ['inherit', 'off', 'limit'], true)) {
            throw new \InvalidArgumentException('Choisissez comment limiter les participants.');
        }
        $maximum = 0;
        if ($mode === 'limit') {
            $raw = $input['max_participants'] ?? null;
            if (!is_string($raw) || !preg_match('/^[1-9][0-9]{0,3}$/D', $raw) || (int) $raw > 1000) {
                throw new \InvalidArgumentException('Indiquez un maximum entre 1 et 1 000 personnes.');
            }
            $maximum = (int) $raw;
        }
        // An unchanged form must preserve earlier per-field inheritance, including false/zero.
        if ($mode === self::mode($scope, $stored, $effective)
            && ($mode !== 'limit' || $maximum === $effective['max_participants']['value'])) {
            return array_intersect_key($stored, array_flip(['enabled', 'max_participants']));
        }
        if ($mode === 'inherit') { return []; }
        return Schema::validate(['enabled' => $mode === 'limit', 'max_participants' => $maximum]);
    }

    public static function summary(array $effective): string
    {
        return $effective['enabled']['value'] && $effective['max_participants']['value'] > 0
            ? $effective['max_participants']['value'] . ' personnes maximum par réservation'
            : 'Pas de limite ajoutée par l’extension';
    }

    public static function render(string $scope, array $stored, array $effective, array $parent): void
    {
        $mode = self::mode($scope, $stored, $effective);
        $choices = [];
        if ($scope !== 'site') {
            $choices['inherit'] = [$scope === 'calendar' ? 'Utiliser les réglages communs' : 'Utiliser les réglages du calendrier',
                self::summary($parent) . '. Les prochaines modifications de ces réglages seront aussi appliquées ici.'];
        }
        $choices['off'] = ['Garder uniquement les limites FluentBooking', 'L’extension n’ajoute aucune restriction au nombre de personnes.'];
        $choices['limit'] = ['Fixer un maximum de personnes par réservation', 'Le réservant et ses invités sont comptés ensemble.'];
        echo '<fieldset class="fba-policy"><legend>Combien de personnes peut-on inscrire en une réservation ?</legend>';
        foreach ($choices as $value => [$title, $description]) {
            echo '<label class="fba-choice"><input type="radio" name="policy" value="' . esc_attr($value) . '"' . checked($mode, $value, false) . '><span><strong>' . esc_html($title) . '</strong><span class="description">' . esc_html($description) . '</span></span></label>';
        }
        $maximum = (int) $effective['max_participants']['value'];
        echo '<div class="fba-maximum"><label for="fba-maximum">Maximum de personnes</label> <input type="number" class="small-text" id="fba-maximum" name="max_participants" min="1" max="1000" step="1" value="' . ($maximum > 0 ? esc_attr((string) $maximum) : '') . '" placeholder="Ex. 2" aria-describedby="fba-limit-help"><p class="description" id="fba-limit-help">Exemple : 2 permet à une personne de réserver pour elle et 1 invité. Ce nombre sert uniquement si vous choisissez de fixer un maximum.</p></div></fieldset>';
        echo '<div class="fba-effective"><strong>Réglage actuellement enregistré : </strong>' . esc_html(self::summary($effective)) . '.<p class="description">Vos changements seront appliqués après enregistrement.</p></div>';
    }
}
