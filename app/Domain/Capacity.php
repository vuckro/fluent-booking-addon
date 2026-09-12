<?php
namespace WaasKit\FluentBooking\Domain;

final class Capacity
{
    /** Half-open UTC intervals: an ending session does not occupy the next one. */
    public static function peak(array $intervals, string $start, string $end): int
    {
        if ($end <= $start) { throw new \InvalidArgumentException('Créneau invalide.'); }
        $points = [];
        foreach ($intervals as $row) {
            $a = max($start, $row['start']); $b = min($end, $row['end']);
            if ($a >= $b) { continue; }
            $points[$a] = ($points[$a] ?? 0) + (int) $row['units'];
            $points[$b] = ($points[$b] ?? 0) - (int) $row['units'];
        }
        ksort($points); $current = 0; $peak = 0;
        foreach ($points as $delta) { $current += $delta; $peak = max($peak, $current); }
        return $peak;
    }
}
