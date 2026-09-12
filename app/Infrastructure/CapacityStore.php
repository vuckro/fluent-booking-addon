<?php
namespace WaasKit\FluentBooking\Infrastructure;

use WaasKit\FluentBooking\Domain\Capacity;
use FluentBooking\App\Models\Booking;

/** Persist before native creation. Unlinked holds never silently expire. */
final class CapacityStore
{
    public const VERSION = 1;
    private bool $locked = false;
    private string $lockName = '';
    private function table(): string { global $wpdb; return $wpdb->prefix . 'fba_capacity'; }
    public function install(): void
    {
        if ((int) get_option('fba_db_version') === self::VERSION) { return; }
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = $this->table(); $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            token varchar(36) NOT NULL,
            booking_id bigint(20) unsigned DEFAULT NULL,
            event_id bigint(20) unsigned NOT NULL,
            pool varchar(80) NOT NULL,
            start_time datetime NOT NULL,
            end_time datetime NOT NULL,
            units int unsigned NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY token (token),
            UNIQUE KEY booking_id (booking_id),
            KEY pool_time (pool,start_time,end_time)
        ) $charset;");
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table) {
            update_option('fba_db_version', self::VERSION, false);
        }
    }
    public function lock(): void
    {
        if ($this->locked) { return; }
        global $wpdb;
        // One short critical section per site prevents cross-pool lock ordering errors.
        $name = 'fba:' . substr(hash('sha256', DB_NAME . ':' . $wpdb->prefix), 0, 50);
        if ((string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $name)) !== '1') { throw new \RuntimeException('Réservations en cours. Veuillez réessayer.'); }
        $this->locked = true; $this->lockName = $name;
        register_shutdown_function([$this, 'unlock']);
    }
    public function unlock(): void
    {
        if (!$this->locked) { return; }
        global $wpdb;
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $this->lockName)); $this->locked = false;
    }
    public function intervals(string $pool, array $eventIds, string $start, string $end): array
    {
        global $wpdb;
        if ((int) get_option('fba_db_version') !== self::VERSION) { throw new \RuntimeException('Ouvrez Modules pour initialiser le stockage des capacités.'); }
        $bookings = $wpdb->prefix . 'fcal_bookings'; $table = $this->table();
        // Native status is authoritative; time is frozen until an amendment workflow exists.
        $rows = $wpdb->get_results($wpdb->prepare("SELECT h.start_time AS start,
            h.end_time AS end, h.units FROM $table h
            LEFT JOIN $bookings b ON b.id=h.booking_id WHERE h.pool=%s
            AND (h.booking_id IS NULL OR b.status IN ('scheduled','pending'))
            AND h.start_time<%s AND h.end_time>%s", $pool, $end, $start), ARRAY_A);
        if ($wpdb->last_error) { throw new \RuntimeException('Lecture de capacité impossible.'); }
        $ids = implode(',', array_map('intval', $eventIds));
        if ($ids) {
            $native = $wpdb->get_results($wpdb->prepare("SELECT b.start_time AS start,b.end_time AS end,1 AS units
                FROM $bookings b LEFT JOIN $table h ON h.booking_id=b.id
                WHERE b.event_id IN ($ids) AND h.id IS NULL AND b.status IN ('scheduled','pending')
                AND b.start_time<%s AND b.end_time>%s", $end, $start), ARRAY_A);
            if ($wpdb->last_error) { throw new \RuntimeException('Lecture des réservations impossible.'); }
            $rows = array_merge($rows, $native);
        }
        return $rows;
    }
    public function remaining(string $pool, array $eventIds, string $start, string $end, int $limit): int
    {
        return max(0, $limit - Capacity::peak($this->intervals($pool, $eventIds, $start, $end), $start, $end));
    }
    public function hold(int $eventId, string $pool, array $eventIds, string $start, string $end, int $units, int $limit): string
    {
        $this->lock();
        if ($units > $this->remaining($pool, $eventIds, $start, $end, $limit)) { $this->unlock(); throw new \InvalidArgumentException('Il ne reste pas assez de places pour ce groupe.'); }
        global $wpdb; $token = wp_generate_uuid4();
        if (!$wpdb->insert($this->table(), ['token'=>$token, 'event_id'=>$eventId, 'pool'=>$pool, 'start_time'=>$start, 'end_time'=>$end, 'units'=>$units, 'created_at'=>current_time('mysql', true)])) { $this->unlock(); throw new \RuntimeException('Impossible de retenir les places.'); }
        return $token;
    }
    public function attach(string $token, int $bookingId): void
    {
        global $wpdb;
        if ($wpdb->update($this->table(), ['booking_id'=>$bookingId], ['token'=>$token, 'booking_id'=>null]) !== 1) { throw new \RuntimeException('Liaison des places à vérifier.'); }
        $this->unlock();
    }
    public function orphanCount(): int
    {
        global $wpdb;
        if ((int) get_option('fba_db_version') !== self::VERSION) { return 0; }
        return (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . $this->table() . ' WHERE booking_id IS NULL');
    }
    public function hasLiveReservations(array $eventIds): bool
    {
        global $wpdb;
        if ((int) get_option('fba_db_version') !== self::VERSION) { return false; }
        $table = $this->table(); $bookings = $wpdb->prefix . 'fcal_bookings';
        $ids = implode(',', array_map('intval', $eventIds));
        if (!$ids) { return false; }
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table h LEFT JOIN $bookings b ON b.id=h.booking_id WHERE h.event_id IN ($ids) AND (h.booking_id IS NULL OR b.status IN ('scheduled','pending'))");
        if ($wpdb->last_error) { throw new \RuntimeException('Vérification des réservations impossible.'); }
        return (int) $count > 0;
    }
    public function record(int $bookingId): ?array
    {
        global $wpdb;
        if ((int) get_option('fba_db_version') !== self::VERSION) { return null; }
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . $this->table() . ' WHERE booking_id=%d', $bookingId), ARRAY_A);
    }
}
