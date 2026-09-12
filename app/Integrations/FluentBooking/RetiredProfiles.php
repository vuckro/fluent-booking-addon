<?php
namespace WaasKit\FluentBooking\Integrations\FluentBooking;

use FluentBooking\App\Models\Booking;

/** Upgrade safety only. No participant, pricing or capacity feature is registered. */
final class RetiredProfiles
{
    public const META = 'fba_party_v1';

    public function __construct(private ConfigurationStore $store) {}

    public function register(): void
    {
        // Some integrations bypass BookingService but still use the native model.
        Booking::creating(function ($booking) {
            $profile = $this->store->effective('calendar_event', (int) $booking->event_id)['booking_profile']['value'];
            if (!empty($profile['enabled'])) {
                throw new \RuntimeException('Ancien profil expérimental : réservation suspendue jusqu’à vérification de la configuration.');
            }
        });
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
