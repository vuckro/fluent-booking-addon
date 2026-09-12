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
        echo '<div class="fba-profile"><p class="description">Ces réglages s’appliquent au contexte sélectionné. Les calendriers et événements peuvent utiliser leurs propres réglages.</p>';
        self::check('profile[enabled]', 'Autoriser plusieurs participants par réservation', $p['enabled'], 'Une seule personne réserve pour tout le groupe.');
        echo '<section class="fba-profile-section"><h4>Participants</h4><p>Le réservant doit être inclus dans la liste s’il participe à la séance.</p><div class="fba-profile-grid">';
        self::input('profile[min]', 'Minimum par réservation', $p['min'], 'number');
        self::input('profile[max]', 'Maximum par réservation', $p['max'], 'number');
        echo '</div>';
        self::check('profile[names]', 'Demander le nom de chaque participant', $p['names']);
        echo '<label class="fba-field">E-mail des participants supplémentaires<select name="profile[email]">';
        foreach (['hidden'=>'Ne pas le demander','optional'=>'Le demander, sans obligation','required'=>'Le rendre obligatoire'] as $key=>$label) { echo '<option value="' . esc_attr($key) . '"' . selected($p['email'], $key, false) . '>' . esc_html($label) . '</option>'; }
        echo '</select><span class="description">L’e-mail du réservant reste toujours obligatoire.</span></label></section>';
        echo '<section class="fba-profile-section"><h4>Catégories et tarifs</h4><p>Gardez une seule catégorie ou ajoutez, par exemple, « Adulte » et « Enfant ».</p>';
        self::check('profile[pricing]', 'Définir un prix pour chaque catégorie', $p['pricing'], 'Sinon, le tarif habituel de FluentBooking s’applique.');
        echo '<p class="description fba-pricing-note">Les prix utilisent la devise de FluentBooking. Paiements natifs Stripe ou hors ligne uniquement.</p><div class="fba-types">';
        foreach ($p['types'] as $i=>$type) { self::type($i, $type, $p['types']); }
        echo '</div><button class="button fba-add-type" type="button">Ajouter une catégorie</button><template id="fba-type-template">';
        self::type('__INDEX__', BookingProfile::defaults()['types'][0], $p['types'], true);
        echo '</template></section><section class="fba-profile-section"><h4>Places disponibles</h4><div class="fba-profile-grid">';
        self::input('profile[capacity]', 'Nombre de places par séance', $p['capacity'], 'number', '0 : conserver la capacité définie dans FluentBooking.');
        echo '</div><details><summary>Partager les places entre plusieurs événements</summary>';
        self::input('profile[pool]', 'Nom du groupe de places', $p['pool'], 'text', 'Facultatif. Exemple : atelier-cuisine. Utilisez le même nom et la même capacité sur les événements concernés.');
        echo '<p class="description">Les séances dont les horaires se chevauchent utilisent alors les mêmes places. Laissez vide pour garder une capacité indépendante.</p></details></section>';
        echo '<details class="fba-profile-transfer"><summary>Réutiliser ces réglages sur un autre événement</summary><p>Copiez le texte ci-dessous, puis collez-le dans la zone d’import du contexte de destination.</p><label class="fba-field">Copie des réglages enregistrés<textarea readonly rows="4">' . esc_textarea(wp_json_encode(['format'=>'fba-profile','schema'=>1,'profile'=>$p], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</textarea></label><label class="fba-field">Coller des réglages à importer<textarea name="profile[import]" rows="4"></textarea><span class="description">À l’enregistrement, ce contenu remplacera tous les réglages de participants ci-dessus.</span></label></details>';
        echo '<details><summary>Fonctions encore indisponibles dans cette alpha</summary><p class="description">Coupons avec tarifs par catégorie, acomptes, modification des participants et reports. Les tarifs par catégorie ne prennent pas en charge WooCommerce ni FluentCart.</p></details></div>';
    }
    private static function type($i, array $type, array $types, bool $blank = false): void
    {
        $prefix = 'profile[types][' . $i . ']';
        echo '<fieldset class="fba-type"><legend>' . esc_html($blank ? 'Nouvelle catégorie' : $type['label']) . '</legend><div class="fba-profile-grid">';
        self::input($prefix . '[label]', 'Nom affiché aux visiteurs', $blank ? '' : $type['label']);
        echo '<div class="fba-category-price">';
        self::input($prefix . '[price]', 'Prix par personne', number_format($type['price'] / 100, 2, '.', ''), 'text', '0 : cette catégorie participe gratuitement.');
        echo '</div></div><details><summary>Conditions de participation et options avancées</summary><div class="fba-profile-grid">';
        self::input($prefix . '[units]', 'Places utilisées par personne', $type['units'], 'number', 'Habituellement 1. Indiquez 0 si cette catégorie n’occupe pas de place.');
        self::input($prefix . '[min_age]', 'Âge minimum à la date de la séance', $type['min_age'], 'number', '0 : aucune restriction d’âge minimum.');
        self::input($prefix . '[max_age]', 'Âge maximum à la date de la séance', $type['max_age'], 'number', '120 : aucune restriction d’âge maximum.');
        echo '<label class="fba-field">Présence d’un accompagnateur<select class="fba-required-type" name="' . esc_attr($prefix . '[requires]') . '"><option value="">Aucun accompagnateur requis</option>';
        foreach ($types as $candidate) {
            if (!$blank && $candidate['id'] === $type['id']) { continue; }
            echo '<option value="' . esc_attr($candidate['id']) . '"' . selected($type['requires'], $candidate['id'], false) . '>Au moins un participant « ' . esc_html($candidate['label']) . ' »</option>';
        }
        echo '</select><span class="description">Exemple : un enfant doit être accompagné d’au moins un adulte dans la même réservation.</span></label></div><details><summary>Champs supplémentaires et identifiant technique</summary><p class="description">Ces options peuvent rester inchangées pour une configuration simple.</p>';
        self::input($prefix . '[id]', 'Identifiant de la catégorie', $blank ? '' : $type['id'], 'text', 'Lettres minuscules, chiffres ou tirets. Évitez de modifier un identifiant déjà utilisé.');
        echo '<label class="fba-field">Informations supplémentaires à demander<textarea name="' . esc_attr($prefix . '[fields]') . '" rows="3">';
        echo esc_textarea(implode("\n", array_map(static fn($f)=>$f['id'] . ' | ' . $f['label'] . ' | ' . ($f['required'] ? 'oui' : 'non'), $type['fields'])));
        echo '</textarea><span class="description">Facultatif. Une ligne par champ : identifiant | question affichée | obligatoire (oui/non). Exemple : allergies | Allergies alimentaires | non.</span></label></details></details><button type="button" class="button fba-remove-type">Retirer cette catégorie</button></fieldset>';
    }
    private static function check(string $name, string $label, bool $value, string $help = ''): void
    {
        echo '<label class="fba-check"><input type="checkbox" name="' . esc_attr($name) . '" value="1"' . checked($value, true, false) . '><span><span class="fba-check-title">' . esc_html($label) . '</span>' . ($help ? '<span class="description">' . esc_html($help) . '</span>' : '') . '</span></label>';
    }
    private static function input(string $name, string $label, $value, string $type = 'text', string $help = ''): void
    {
        echo '<label class="fba-field">' . esc_html($label) . '<input name="' . esc_attr($name) . '" type="' . esc_attr($type) . '" value="' . esc_attr((string) $value) . '"' . ($type === 'number' ? ' min="0" step="1"' : '') . '>' . ($help ? '<span class="description">' . esc_html($help) . '</span>' : '') . '</label>';
    }
}
