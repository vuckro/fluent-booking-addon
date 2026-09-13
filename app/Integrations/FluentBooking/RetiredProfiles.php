<?php
namespace WaasKit\FluentBooking\Integrations\FluentBooking;

use FluentBooking\App\Models\Booking;

/** Upgrade safety only. No participant, pricing or capacity feature is registered. */
final class RetiredProfiles
{
    public const META = 'fba_party_v1';

    public function register(): void
    {
        Booking::updating(static function ($booking) {
            if (!$booking->getMeta(self::META, [])) { return; }
            foreach (['start_time', 'end_time', 'event_id', 'calendar_id'] as $key) {
                if ($booking->isDirty($key)) {
                    throw new \RuntimeException('Cette réservation historique contient des participants expérimentaux. Sa modification nécessite une vérification manuelle.');
                }
            }
            if ($booking->isDirty('status') && in_array($booking->status, ['pending', 'scheduled'], true)
                && !in_array($booking->getOriginal('status'), ['pending', 'scheduled'], true)) {
                throw new \RuntimeException('La réactivation de cette réservation historique nécessite une vérification manuelle.');
            }
        });
    }
}
