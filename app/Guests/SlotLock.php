<?php
namespace WaasKit\FluentBooking\Guests;

/** Serialize admission on an event and exact native slot using MySQL GET_LOCK. */
final class SlotLock
{
    private array $locks = [];

    public function acquire(int $eventId, string $startTime): string
    {
        global $wpdb;
        $lock = 'fba_' . substr(hash('sha256', DB_NAME . $wpdb->prefix . $eventId . $startTime), 0, 48);
        if (!isset($this->locks[$lock])) {
            if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,5)', $lock)) !== 1) {
                throw new \RuntimeException('Une réservation est en cours. Réessayez.', 422);
            }
            $this->locks[$lock] = true;
        }
        return $lock;
    }

    public function release(?string $one = null): void
    {
        global $wpdb;
        foreach (array_keys($this->locks) as $lock) {
            if ($one !== null && $one !== $lock) {
                continue;
            }
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
            unset($this->locks[$lock]);
        }
    }
}
