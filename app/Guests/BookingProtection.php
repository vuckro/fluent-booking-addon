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
        throw new \RuntimeException('La suppression native effacerait le groupe du créneau. Annulez la réservation principale concernée pour libérer ses places et conserver son historique.');
    }

    public function beforeUpdate($booking): void
    {
        if (!$booking->isDirty('status') || empty($booking->getMeta(BookingAdapter::META, [])['attached_seat'])) {return;}
        $parent=Booking::find($booking->parent_id);
        if ($parent && $booking->status!==$parent->status) {
            throw new \RuntimeException('Cet invité appartient à une réservation de groupe. Modifiez le statut depuis la réservation principale.');
        }
    }
}
