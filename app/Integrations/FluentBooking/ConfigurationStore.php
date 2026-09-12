<?php
namespace WaasKit\FluentBooking\Integrations\FluentBooking;

use WaasKit\FluentBooking\Configuration\Schema;
use FluentBooking\App\Services\Helper;
use FluentBooking\App\Models\CalendarSlot;

final class ConfigurationStore
{
    public const KEY = 'waaskit_fluent_booking_config';
    public function read(string $scope, int $id = 0): array
    {
        $this->checkScope($scope, $id);
        $raw = $scope === 'site' ? get_option(self::KEY, null) : Helper::getMeta($scope, $id, self::KEY);
        if ($raw === null || $raw === false) {
            return ['schema' => Schema::VERSION, 'revision' => 0, 'values' => []];
        }
        if (!is_array($raw) || ($raw['schema'] ?? null) !== Schema::VERSION || !is_int($raw['revision'] ?? null) || !is_array($raw['values'] ?? null)) {
            throw new \RuntimeException('Configuration incompatible ou invalide. Aucune écriture effectuée.');
        }
        Schema::validate($raw['values']);
        return $raw;
    }
    public function save(string $scope, int $id, array $values, int $revision): void
    {
        $this->checkScope($scope, $id);
        Schema::validate($values);
        // add_option is backed by a unique option_name: serialize writers per scope.
        $lock = self::KEY . '_lock_' . $scope . '_' . $id;
        if (!add_option($lock, time(), '', false)) {
            throw new \RuntimeException('Une écriture est déjà en cours. Réessayer ; si le verrou persiste, contacter un administrateur.');
        }
        $capacity = null;
        try {
            // Another request may have committed since this request first read options.
            if ($scope === 'site') {
                wp_cache_delete(self::KEY, 'options');
                wp_cache_delete('notoptions', 'options');
                wp_cache_delete('alloptions', 'options');
            }
            $current = $this->read($scope, $id);
            if ($current['revision'] !== $revision) {
                throw new \RuntimeException('Les réglages ont changé. Recharger la page avant de les modifier.');
            }
            if (array_key_exists('booking_profile', $values) || array_key_exists('booking_profile', $current['values'])) {
                $capacity = new \WaasKit\FluentBooking\Infrastructure\CapacityStore();
                $capacity->lock();
                // Keep pool assignment stable for existing holds. Rates and labels may change;
                    // booked parties keep their immutable snapshots.
                    $before = \WaasKit\FluentBooking\Domain\BookingProfile::validate($current['values']['booking_profile'] ?? []);
                    $after = \WaasKit\FluentBooking\Domain\BookingProfile::validate($values['booking_profile'] ?? []);
                    $affected = $scope === 'calendar_event' ? [$id] : ($scope === 'calendar' ? CalendarSlot::where('calendar_id', $id)->pluck('id')->all() : CalendarSlot::query()->pluck('id')->all());
                    if ($before['pool'] !== $after['pool'] && $capacity->hasLiveReservations($affected)) {
                        throw new \RuntimeException('La jauge partagée ne peut pas être changée tant que des réservations ou retenues sont actives.');
                    }
            }
            $next = ['schema'  => Schema::VERSION, 'revision' => $revision + 1, 'values' => $values];
            if ($scope === 'site') {
                update_option(self::KEY, $next, false);
            } else {
                Helper::updateMeta($scope, $id, self::KEY, $next);
            }
            if ($this->read($scope, $id) !== $next) {
                throw new \RuntimeException('La sauvegarde n’a pas pu être vérifiée.');
            }
        } finally {
            if ($capacity) { $capacity->unlock(); }
            delete_option($lock);
        }
    }
    public function effective(string $scope, int $id = 0): array
    {
        $layers = ['site' => $this->read('site')['values']];
        if ($scope === 'calendar') {
            $layers['agenda'] = $this->read('calendar', $id)['values'];
        } elseif ($scope === 'calendar_event') {
            $event = CalendarSlot::find($id);
            if (!$event) {
                throw new \InvalidArgumentException('Événement introuvable.');
            }
            $layers['agenda'] = $this->read('calendar', (int) $event->calendar_id)['values'];
            $layers['événement'] = $this->read('calendar_event', $id)['values'];
        }
        return Schema::resolve($layers);
    }
    private function checkScope(string $scope, int $id): void
    {
        if (!in_array($scope, ['site', 'calendar', 'calendar_event'], true) || ($scope === 'site' ? $id !== 0 : $id <= 0)) {
            throw new \InvalidArgumentException('Contexte invalide.');
        }
    }
}
