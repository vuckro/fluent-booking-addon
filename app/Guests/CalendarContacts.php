<?php
namespace WaasKit\FluentBooking\Guests;

/** Attached seats are stock records, never independent calendar contacts. */
final class CalendarContacts
{
    public function register(): void
    {
        // Pro registers its provider callbacks during initialization. Wrap only
        // those callbacks, preserving their priority and third-party listeners.
        add_action('wp_loaded', [$this, 'protectProviders'], 20);
    }

    public function protectProviders(): void
    {
        global $wp_filter;
        foreach (['google', 'outlook', 'apple_calendar', 'next_cloud_calendar'] as $driver) {
            foreach (['create_remote_calendar_event_', 'refresh_remote_calendar_group_members_', 'cancel_remote_calendar_event_', 'patch_remote_calendar_event_', 'delete_remote_calendar_event_'] as $action) {
                $hook='fluent_booking/'.$action.$driver;
                foreach (($wp_filter[$hook]->callbacks??[]) as $priority=>$callbacks) {
                    foreach ($callbacks as $entry) {
                        $callback=$entry['function'];
                        if (!is_array($callback) || !is_object($callback[0]) || !str_starts_with(get_class($callback[0]), 'FluentBookingPro\\App\\Services\\Integrations\\Calendars\\')) {continue;}
                        remove_action($hook, $callback, $priority);
                        add_action($hook, function (...$args) use ($callback, $action) {
                            return $this->dispatch($callback, $args, $action==='refresh_remote_calendar_group_members_');
                        }, $priority, $entry['accepted_args']);
                    }
                }
            }
        }
    }

    public function dispatch(callable $callback, array $args, bool $group=false)
    {
        if (self::isSeat($args[1])) {return null;}
        if ($group) {
            // A new collection preserves the native stock query and other listeners.
            $args[2]=$args[2]->filter(static fn($booking)=>!self::isSeat($booking));
        }
        return $callback(...$args);
    }

    private static function isSeat($booking): bool
    {
        return !empty($booking->getMeta(BookingAdapter::META, [])['attached_seat']);
    }
}
