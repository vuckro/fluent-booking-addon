<?php
namespace WaasKit\FluentBooking\Guests;

use FluentBooking\App\Models\Booking;

/** Guard native admin operations whose scope differs from a customized party. */
final class BookingProtection
{
    public function register(): void
    {
        // The native controller deletes every booking in the group after this hook.
        // Stop before calendar deletion or any other irreversible side effect.
        add_action('fluent_booking/before_delete_booking', [$this, 'beforeDelete'], 1);
        Booking::updating([$this, 'beforeUpdate']);
    }

    public function beforeDelete($booking): void
    {
        if ($booking->getMeta(BookingAdapter::META, [])) {$this->refuseDelete();}
        if (!$booking->isMultiGuestBooking() || !$booking->group_id) {return;}
        foreach (Booking::where('event_id', $booking->event_id)->where('group_id', $booking->group_id)->get() as $member) {
            if ($member->getMeta(BookingAdapter::META, [])) {$this->refuseDelete();}
        }
    }

    private function refuseDelete(): void
    {
        throw new \RuntimeException('La suppression native effacerait le groupe du créneau. Annulez la réservation principale concernée pour libérer ses places et conserver son historique.', 422);
    }

    public function beforeUpdate($booking): void
    {
        if (empty($booking->getMeta(BookingAdapter::META, [])['attached_seat'])) {return;}
        $parent=Booking::find($booking->parent_id);
        if (!$parent) {return;}
        if ($booking->isDirty('status') && $booking->status!==$parent->status) {
            throw new \RuntimeException('Cet invité appartient à une réservation de groupe. Modifiez le statut depuis la réservation principale.', 422);
        }
        foreach (['start_time', 'end_time', 'calendar_id', 'event_id'] as $key) {
            if ($booking->isDirty($key) && (string)$booking->$key!==(string)$parent->$key) {
                throw new \RuntimeException('Cet invité appartient à une réservation de groupe. Reportez la réservation depuis la réservation principale.', 422);
            }
        }
    }
}
