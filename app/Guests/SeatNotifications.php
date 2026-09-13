<?php
namespace WaasKit\FluentBooking\Guests;

use FluentBooking\App\Models\Booking;
use FluentBooking\App\Hooks\Handlers\NotificationHandler;

/** The main contact owns notifications, including jobs queued before an upgrade. */
final class SeatNotifications
{
    public function register(): void
    {
        add_action('wp_loaded', [$this, 'protectNotifications'], 20);
    }

    public function protectNotifications(): void
    {
        global $wp_filter;
        foreach ($wp_filter as $hook=>$filter) {
            if (!str_starts_with($hook, 'fluent_booking/')) {continue;}
            foreach ($filter->callbacks as $priority=>$callbacks) {
                foreach ($callbacks as $entry) {
                    $callback=$entry['function'];
                    if (!is_array($callback) || !($callback[0] instanceof NotificationHandler)) {continue;}
                    remove_action($hook, $callback, $priority);
                    add_action($hook, function (...$args) use ($callback) {
                        return $this->dispatch($callback, $args);
                    }, $priority, $entry['accepted_args']);
                }
            }
        }
    }

    public function dispatch(callable $callback, array $args)
    {
        $booking=is_object($args[0])?$args[0]:Booking::find($args[0]);
        if ($booking && !empty($booking->getMeta(BookingAdapter::META, [])['attached_seat'])) {return null;}
        return $callback(...$args);
    }
}
