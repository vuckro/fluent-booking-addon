<?php
namespace WaasKit\FluentBooking\Integrations\FluentBooking;

use FluentBooking\App\Models\Meta;
use FluentBooking\App\Services\Helper;
use WaasKit\FluentBooking\Guests\Options;

/** One configuration per event. Native questions own all participant limits. */
final class ConfigurationStore
{
    public const KEY = 'waaskit_fluent_booking_config';
    public const VERSION = 2;
    public const MIGRATED = 'fba_native_settings_migrated';

    public static function migrationRequired(): bool
    {
        if ((int) get_option(self::MIGRATED, 0) === self::VERSION) { return false; }
        if (get_option(self::KEY, false) !== false) { return true; }
        foreach (Meta::where('key', self::KEY)->get() as $meta) {
            if (!is_array($meta->value) || ($meta->value['schema'] ?? 0) !== self::VERSION) { return true; }
        }
        return false;
    }

    public function read(string $scope, int $id = 0): array
    {
        $this->checkScope($scope, $id);
        $raw = Helper::getMeta($scope, $id, self::KEY);
        if ($raw === null || $raw === false) {
            return ['schema'=>self::VERSION, 'revision'=>0, 'values'=>[]];
        }
        if (!is_array($raw) || ($raw['schema'] ?? null) !== self::VERSION
            || !is_int($raw['revision'] ?? null) || !is_array($raw['values'] ?? null)) {
            throw new \RuntimeException('Ancienne configuration : exécuter la migration documentée avant de modifier cet événement.');
        }
        $this->validate($raw['values']);
        return $raw;
    }

    public function save(string $scope, int $id, array $values, int $revision): void
    {
        $this->checkScope($scope, $id);
        $this->validate($values);
        $lock = self::KEY . '_lock_' . $scope . '_' . $id;
        if (!add_option($lock, time(), '', false)) {
            throw new \RuntimeException('Une sauvegarde est en cours. Réessayez.');
        }
        try {
            if ($this->read($scope, $id)['revision'] !== $revision) {
                throw new \RuntimeException('Les réglages ont changé. Rechargez la page.');
            }
            $next = ['schema'=>self::VERSION, 'revision'=>$revision + 1, 'values'=>$values];
            Helper::updateMeta($scope, $id, self::KEY, $next);
            if ($this->read($scope, $id) !== $next) {
                throw new \RuntimeException('La sauvegarde n’a pas pu être vérifiée.');
            }
        } finally {
            delete_option($lock);
        }
    }

    private function validate(array $values): void
    {
        if (array_diff(array_keys($values), ['guest_options'])) {
            throw new \InvalidArgumentException('Les limites se règlent dans FluentBooking, pas dans cet add-on.');
        }
        if (array_key_exists('guest_options', $values)) {
            if (!is_array($values['guest_options'])) { throw new \InvalidArgumentException('Options invalides.'); }
            Options::validate($values['guest_options']);
        }
    }

    private function checkScope(string $scope, int $id): void
    {
        if ($scope !== 'calendar_event' || $id <= 0) {
            throw new \InvalidArgumentException('Choisissez un événement.');
        }
    }
}
