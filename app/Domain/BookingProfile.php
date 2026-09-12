<?php
namespace WaasKit\FluentBooking\Domain;

/** One inherited profile; no event IDs, credentials or client-specific behavior. */
final class BookingProfile
{
    public static function defaults(): array
    {
        return ['enabled' => false, 'min' => 1, 'max' => 6, 'email' => 'hidden', 'names' => true,
            'pricing' => false, 'capacity' => 0, 'pool' => '', 'types' => [
                ['id' => 'participant', 'label' => 'Participant', 'price' => 0, 'units' => 1,
                 'min_age' => 0, 'max_age' => 120, 'requires' => '', 'fields' => []]
            ]];
    }
    public static function validate(array $profile): array
    {
        $defaults = self::defaults();
        if (array_diff_key($profile, $defaults)) { throw new \InvalidArgumentException('Champ de profil inconnu.'); }
        $p = array_replace($defaults, $profile);
        foreach (['enabled', 'names', 'pricing'] as $key) {
            if (!is_bool($p[$key])) { throw new \InvalidArgumentException('Booléen requis : ' . $key); }
        }
        foreach (['min' => [1, 100], 'max' => [1, 100], 'capacity' => [0, 100000]] as $key => [$min, $max]) {
            self::integer($p[$key], $min, $max, $key);
        }
        if ($p['min'] > $p['max']) { throw new \InvalidArgumentException('Le minimum dépasse le maximum.'); }
        if (!in_array($p['email'], ['hidden', 'optional', 'required'], true)) { throw new \InvalidArgumentException('Mode e-mail invalide.'); }
        if (!is_string($p['pool']) || !preg_match('/^[a-z0-9_-]{0,64}$/D', $p['pool'])) { throw new \InvalidArgumentException('Identifiant de jauge invalide.'); }
        if (!is_array($p['types']) || !array_is_list($p['types']) || count($p['types']) < 1 || count($p['types']) > 20) { throw new \InvalidArgumentException('Définir entre 1 et 20 types.'); }
        $ids = [];
        foreach ($p['types'] as &$type) {
            if (!is_array($type) || array_diff_key($type, array_flip(['id','label','price','units','min_age','max_age','requires','fields']))) { throw new \InvalidArgumentException('Type invalide.'); }
            $type = array_replace($defaults['types'][0], $type);
            if (!is_string($type['id']) || !preg_match('/^[a-z][a-z0-9_-]{0,31}$/D', $type['id']) || isset($ids[$type['id']])) { throw new \InvalidArgumentException('Identifiant de type invalide ou dupliqué.'); }
            $ids[$type['id']] = true;
            self::label($type['label']);
            foreach (['price'=>[0,10000000], 'units'=>[0,1000], 'min_age'=>[0,120], 'max_age'=>[0,120]] as $key=>[$min,$max]) { self::integer($type[$key], $min, $max, $key); }
            if ($type['min_age'] > $type['max_age']) { throw new \InvalidArgumentException('Bornes d’âge invalides.'); }
            if (!is_string($type['requires'])) { throw new \InvalidArgumentException('Type accompagnateur invalide.'); }
            if (!is_array($type['fields']) || !array_is_list($type['fields']) || count($type['fields']) > 10) { throw new \InvalidArgumentException('Champs invalides.'); }
            $keys = [];
            foreach ($type['fields'] as $field) {
                if (!is_array($field) || array_diff_key($field, array_flip(['id','label','required'])) || count($field) !== 3 || !is_string($field['id']) || !preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $field['id']) || isset($keys[$field['id']]) || !is_bool($field['required'])) { throw new \InvalidArgumentException('Champ participant invalide (id, label, required).'); }
                self::label($field['label']); $keys[$field['id']] = true;
            }
        }
        unset($type);
        foreach ($p['types'] as $type) {
            if ($type['requires'] !== '' && (!isset($ids[$type['requires']]) || $type['requires'] === $type['id'])) { throw new \InvalidArgumentException('L’accompagnateur doit être un autre type existant.'); }
        }
        return $p;
    }
    private static function label($value): void
    {
        if (!is_string($value) || trim($value) === '' || strlen($value) > 160 || strip_tags($value) !== $value) { throw new \InvalidArgumentException('Libellé invalide.'); }
    }
    private static function integer($value, int $min, int $max, string $key): void
    {
        if (!is_int($value) || $value < $min || $value > $max) { throw new \InvalidArgumentException('Entier hors limites : ' . $key); }
    }
}
