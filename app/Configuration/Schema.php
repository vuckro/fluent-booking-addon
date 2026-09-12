<?php
namespace WaasKit\FluentBooking\Configuration;

final class Schema
{
    public const VERSION = 1;
    public static function fields(): array
    {
        return [
            'enabled' => ['type' => 'bool', 'default' => false, 'label' => 'Activer les règles de cet add-on'],
            'max_participants' => ['type' => 'int', 'default' => 0, 'min' => 0, 'max' => 1000, 'label' => 'Maximum de participants par demande (0 = sans limite supplémentaire)'],
            'booking_profile' => ['type' => 'profile', 'default' => [], 'label' => 'Participants, tarifs et capacités'],
        ];
    }
    public static function validate(array $values): array
    {
        $fields = self::fields();
        foreach ($values as $key => $value) {
            if (!isset($fields[$key])) {
                throw new \InvalidArgumentException('Réglage inconnu : ' . $key);
            }
            $field = $fields[$key];
            if ($field['type'] === 'profile') {
                if (!is_array($value)) { throw new \InvalidArgumentException('Profil invalide.'); }
                \WaasKit\FluentBooking\Domain\BookingProfile::validate($value);
            }
            if ($field['type'] === 'bool' && !is_bool($value)) {
                throw new \InvalidArgumentException('Valeur booléenne requise : ' . $key);
            }
            if ($field['type'] === 'int' && (!is_int($value) || $value < $field['min'] || $value > $field['max'])) {
                throw new \InvalidArgumentException('Nombre entier hors limites : ' . $key);
            }
        }
        return $values;
    }
    public static function resolve(array $layers): array
    {
        $resolved = [];
        foreach (self::fields() as $key => $field) {
            $resolved[$key] = ['value' => $field['default'], 'source' => 'produit'];
        }
        foreach ($layers as $source => $values) {
            foreach (self::validate($values) as $key => $value) {
                $resolved[$key] = ['value' => $value, 'source' => $source];
            }
        }
        return $resolved;
    }
}
