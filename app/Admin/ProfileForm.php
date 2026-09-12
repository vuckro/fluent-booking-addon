<?php
namespace WaasKit\FluentBooking\Admin;

use WaasKit\FluentBooking\Domain\BookingProfile;

final class ProfileForm
{
    public static function parse(array $raw): array
    {
        if (!empty($raw['import'])) {
            if (!is_string($raw['import']) || strlen($raw['import']) > 50000) { throw new \InvalidArgumentException('Import trop volumineux.'); }
            $import = json_decode($raw['import'], true, 20, JSON_THROW_ON_ERROR);
            if (!is_array($import) || ($import['format'] ?? '') !== 'fba-profile' || ($import['schema'] ?? 0) !== 1 || !is_array($import['profile'] ?? null)) { throw new \InvalidArgumentException('Format de profil incompatible.'); }
            return BookingProfile::validate($import['profile']);
        }
        $p = BookingProfile::defaults();
        foreach (['enabled','pricing','names'] as $key) { $p[$key] = isset($raw[$key]) && $raw[$key] === '1'; }
        foreach (['min','max','capacity'] as $key) { $p[$key] = self::integer($raw[$key] ?? ''); }
        foreach (['pool','email'] as $key) { $p[$key] = $raw[$key] ?? ''; }
        $p['types'] = [];
        if (!is_array($raw['types'] ?? null)) { throw new \InvalidArgumentException('Types requis.'); }
        foreach ($raw['types'] as $row) {
            if (!is_array($row)) { throw new \InvalidArgumentException('Type invalide.'); }
            if (($row['id'] ?? '') === '' && ($row['label'] ?? '') === '') { continue; }
            $type = ['id'=>$row['id'] ?? '', 'label'=>$row['label'] ?? '', 'requires'=>$row['requires'] ?? ''];
            foreach (['units','min_age','max_age'] as $key) { $type[$key] = self::integer($row[$key] ?? ''); }
            $money = str_replace(',', '.', $row['price'] ?? '');
            if (!preg_match('/^(0|[1-9][0-9]{0,5})(?:\.([0-9]{1,2}))?$/D', $money, $parts)) { throw new \InvalidArgumentException('Prix invalide (deux décimales maximum).'); }
            $type['price'] = (int) $parts[1] * 100 + (int) str_pad($parts[2] ?? '', 2, '0');
            $type['fields'] = [];
            // Optional fields are represented as one simple line per field, not executable configuration.
            foreach (explode("\n", (string) ($row['fields'] ?? '')) as $line) {
                if (trim($line) === '') { continue; }
                $parts = array_map('trim', explode('|', $line));
                if (count($parts) !== 3 || !in_array($parts[2], ['oui','non'], true)) { throw new \InvalidArgumentException('Champ : identifiant | libellé | oui ou non.'); }
                $type['fields'][] = ['id'=>$parts[0], 'label'=>$parts[1], 'required'=>$parts[2] === 'oui'];
            }
            $p['types'][] = $type;
        }
        return BookingProfile::validate($p);
    }
    private static function integer($raw): int
    {
        if (!is_string($raw) || !preg_match('/^(0|[1-9][0-9]{0,7})$/D', $raw)) { throw new \InvalidArgumentException('Nombre entier requis.'); }
        return (int) $raw;
    }
    public static function render(array $p): void
    {
        $p = BookingProfile::validate($p);
        echo '<details><summary>Configurer les participants, tarifs et capacités</summary><div class="fba-profile"><p>Un profil regroupe les participants, leurs tarifs et la jauge. Il remplace le profil hérité dans son ensemble.</p>';
        foreach (['enabled'=>'Activer les participants multiples', 'names'=>'Demander le nom des participants', 'pricing'=>'Tarifs par type (paiement natif Stripe ou hors ligne)'] as $key=>$label) {
            echo '<label class="fba-check"><input type="checkbox" name="profile[' . esc_attr($key) . ']" value="1"' . checked($p[$key], true, false) . '> ' . esc_html($label) . '</label>';
        }
        echo '<div class="fba-profile-grid">';
        foreach (['min'=>'Minimum de participants','max'=>'Maximum de participants','capacity'=>'Capacité (0 = capacité native)'] as $key=>$label) { self::input('profile[' . $key . ']', $label, $p[$key], 'number'); }
        self::input('profile[pool]', 'Jauge partagée (identifiant commun, facultatif)', $p['pool']);
        echo '<label>E-mail des participants<select name="profile[email]">';
        foreach (['hidden'=>'Masqué','optional'=>'Facultatif','required'=>'Obligatoire'] as $key=>$label) { echo '<option value="' . esc_attr($key) . '"' . selected($p['email'], $key, false) . '>' . esc_html($label) . '</option>'; }
        echo '</select></label></div><p>Le réservant conserve son e-mail de contact. Incluez-le dans la liste s’il participe. Les prix utilisent la devise FluentBooking.</p><div class="fba-types">';
        foreach ($p['types'] as $i=>$type) { self::type($i, $type); }
        echo '</div><button class="button fba-add-type" type="button">Ajouter un type</button><template id="fba-type-template">';
        self::type('__INDEX__', BookingProfile::defaults()['types'][0], true);
        echo '</template><details><summary>Copier ou importer un profil</summary><label>Profil exportable<textarea readonly rows="4">' . esc_textarea(wp_json_encode(['format'=>'fba-profile','schema'=>1,'profile'=>$p], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</textarea></label><label>Importer un profil (remplace les champs ci-dessus à l’enregistrement)<textarea name="profile[import]" rows="4"></textarea></label></details>';
        echo '<p class="description">Jauge partagée : tous les événements portant le même identifiant doivent utiliser la même capacité. Les créneaux qui se chevauchent partagent les places.</p><p class="description">Alpha : coupons avec tarifs par type, acomptes, modification de participants et reports non disponibles. WooCommerce et FluentCart ne sont pas pris en charge pour ces tarifs.</p></div></details>';
    }
    private static function type($i, array $type, bool $blank = false): void
    {
        $prefix = 'profile[types][' . $i . ']';
        echo '<fieldset class="fba-type"><legend>Type de participant</legend><div class="fba-profile-grid">';
        self::input($prefix . '[id]', 'Identifiant', $blank ? '' : $type['id']);
        self::input($prefix . '[label]', 'Libellé', $blank ? '' : $type['label']);
        self::input($prefix . '[price]', 'Prix par participant', number_format($type['price'] / 100, 2, '.', ''));
        self::input($prefix . '[units]', 'Places consommées', $type['units'], 'number');
        self::input($prefix . '[min_age]', 'Âge minimum', $type['min_age'], 'number');
        self::input($prefix . '[max_age]', 'Âge maximum', $type['max_age'], 'number');
        self::input($prefix . '[requires]', 'Accompagnateur requis (identifiant du type)', $type['requires']);
        echo '</div><details><summary>Champs supplémentaires</summary><label>Une ligne : identifiant | libellé | obligatoire (oui/non)<textarea name="' . esc_attr($prefix . '[fields]') . '" rows="3">';
        echo esc_textarea(implode("\n", array_map(static fn($f)=>$f['id'] . ' | ' . $f['label'] . ' | ' . ($f['required'] ? 'oui' : 'non'), $type['fields'])));
        echo '</textarea></label></details><button type="button" class="button fba-remove-type">Supprimer ce type</button></fieldset>';
    }
    private static function input(string $name, string $label, $value, string $type = 'text'): void
    {
        echo '<label>' . esc_html($label) . '<input name="' . esc_attr($name) . '" type="' . esc_attr($type) . '" value="' . esc_attr((string) $value) . '"' . ($type === 'number' ? ' min="0" step="1"' : '') . '></label>';
    }
}
