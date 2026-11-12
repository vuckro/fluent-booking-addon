<?php
namespace WaasKit\FluentBooking\Guests;

use FluentBooking\App\Models\Booking;
use FluentBooking\App\Services\Helper;
use WaasKit\FluentBooking\Plugin;

/** Coordinate multi-seat rescheduling across landing pages, REST routes and modal views. */
final class RescheduleCoordinator
{
    private array $ignoreAvailabilityBookingIds = [];
    private ?int $reschedulingBookingId = null;
    private array $stagedDurations = [];

    public function __construct(
        private SlotLock $slotLock,
        private ?\Closure $eventEnabled = null
    ) {}

    public function register(): void
    {
        add_action('init', [$this, 'redirectAttachedSeatReschedule'], 20);
        add_action('fluent_booking/before_calendar_event_landing_page', [$this, 'renderRescheduleNotice'], 20);
        add_filter('fluent_booking/event_landing_page_vars', [$this, 'filterLandingPageVars'], 20, 4);
        add_filter('fluent_booking/available_slots_for_view', [$this, 'filterRescheduleSlots'], 100, 2);
        add_action('fluent_booking/before_creating_schedule', [$this, 'beforePublicReschedule'], 5, 3);
        add_action('fluent_booking/after_booking_rescheduled', [$this, 'afterBookingRescheduled'], 5, 1);
        add_filter('rest_post_dispatch', [$this, 'resetRestState'], 10, 1);
    }

    public function resetRestState($response)
    {
        $this->reschedulingBookingId = null;
        $this->rollbackStagedDurations();
        return $response;
    }

    public static function effectiveSlotMinutes($booking, $event): int
    {
        $bookedMinutes = (int) ($booking->slot_minutes ?? 0);
        $resolved = is_object($event) && method_exists($event, 'getDuration')
            ? (int) $event->getDuration($bookedMinutes > 0 ? $bookedMinutes : null)
            : (int) ($event->duration ?? $bookedMinutes);
        return $resolved > 0 ? $resolved : $bookedMinutes;
    }

    public static function applyEffectiveDurationToBookingData(array $data, int $effectiveMinutes): array
    {
        if ($effectiveMinutes <= 0) {
            return $data;
        }
        $data['slot_minutes'] = $effectiveMinutes;
        $start = $data['start_time'] ?? null;
        if (is_string($start) && $start !== '' && strtotime($start) !== false) {
            $data['end_time'] = gmdate('Y-m-d H:i:s', strtotime($start) + ($effectiveMinutes * 60));
        } elseif (is_array($start)) {
            $data['end_time'] = array_map(
                static fn($dt) => (is_string($dt) && strtotime($dt) !== false)
                    ? gmdate('Y-m-d H:i:s', strtotime($dt) + ($effectiveMinutes * 60))
                    : $dt,
                $start
            );
        }
        return $data;
    }

    private function appliesToBooking($booking, $event): bool
    {
        if (!empty($booking->getMeta(BookingAdapter::META, []))) {
            return true;
        }
        return $this->eventEnabled !== null && ($this->eventEnabled)((int) $event->id);
    }

    private function stageEffectiveDuration(Booking $booking, int $effectiveMinutes): void
    {
        if ($effectiveMinutes <= 0 || (int) $booking->slot_minutes === $effectiveMinutes) {
            return;
        }
        $bookingId = (int) $booking->id;
        if (!isset($this->stagedDurations[$bookingId])) {
            $this->stagedDurations[$bookingId] = [
                'slot_minutes' => (int) $booking->slot_minutes,
                'start_time'   => (string) $booking->start_time,
            ];
        }
        Booking::where('id', $bookingId)->update(['slot_minutes' => $effectiveMinutes]);
        $booking->slot_minutes = $effectiveMinutes;
    }

    public function rollbackStagedDurations(): void
    {
        foreach ($this->stagedDurations as $bookingId => $orig) {
            Booking::where('id', $bookingId)
                ->where('start_time', $orig['start_time'])
                ->update(['slot_minutes' => $orig['slot_minutes']]);
        }
        $this->stagedDurations = [];
    }

    public function normalizePublicRescheduleData(array $data, array $posted, $event): array
    {
        if (empty($posted['rescheduling_hash']) || !is_object($event)) {
            return $data;
        }
        $existing = Booking::where('hash', sanitize_text_field((string) $posted['rescheduling_hash']))
            ->where('event_id', $event->id)
            ->first();
        if (!$existing || !$this->appliesToBooking($existing, $event)) {
            return $data;
        }
        $effective = self::effectiveSlotMinutes($existing, $event);
        if ($effective > 0 && (int) $existing->slot_minutes !== $effective) {
            return self::applyEffectiveDurationToBookingData($data, $effective);
        }
        return $data;
    }

    public function beginRestRescheduleSlots(int $bookingId): void
    {
        $this->reschedulingBookingId = $bookingId > 0 ? $bookingId : null;
    }

    public function isIgnoringAvailability(int $bookingId): bool
    {
        return !empty($this->ignoreAvailabilityBookingIds[$bookingId]);
    }

    public static function matchRestRescheduleRoute(string $method, string $route): ?array
    {
        $upper = strtoupper($method);
        if ($upper === 'GET' && preg_match(
            '#^/fluent-booking/v2/bookings/(?:reschedule/(?P<event_id>\d+)/(?P<booking_id>\d+)|(?P<legacy_event_id>\d+)/reschedule/(?P<legacy_booking_id>\d+))/slots$#',
            $route,
            $matches
        ) === 1) {
            return [
                'action'     => 'slots',
                'event_id'   => (int) ($matches['event_id'] !== '' ? $matches['event_id'] : $matches['legacy_event_id']),
                'booking_id' => (int) ($matches['booking_id'] !== '' ? $matches['booking_id'] : $matches['legacy_booking_id']),
            ];
        }
        if ($upper === 'POST' && preg_match(
            '#^/fluent-booking/v2/bookings/(?:reschedule/(?P<event_id>\d+)/(?P<booking_id>\d+)|(?P<legacy_event_id>\d+)/reschedule/(?P<legacy_booking_id>\d+))$#',
            $route,
            $matches
        ) === 1) {
            return [
                'action'     => 'reschedule',
                'event_id'   => (int) ($matches['event_id'] !== '' ? $matches['event_id'] : $matches['legacy_event_id']),
                'booking_id' => (int) ($matches['booking_id'] !== '' ? $matches['booking_id'] : $matches['legacy_booking_id']),
            ];
        }
        return null;
    }

    public static function filterSpotsByRequiredSeats(array $availableSpots, int $seats): array
    {
        if ($seats <= 1) {
            return $availableSpots;
        }
        foreach ($availableSpots as $day => $slots) {
            if (!is_array($slots)) {
                continue;
            }
            $filtered = array_values(array_filter(
                $slots,
                static fn($slot) => !isset($slot['remaining']) || !is_numeric($slot['remaining']) || (int) $slot['remaining'] >= $seats
            ));
            if ($filtered) {
                $availableSpots[$day] = $filtered;
            } else {
                unset($availableSpots[$day]);
            }
        }
        return $availableSpots;
    }

    public function filterLandingPageVars(array $data, $calendar, $calendarEvent, $existingBooking = null): array
    {
        if ($existingBooking !== null && isset($data['js_files']) && is_array($data['js_files'])) {
            unset(
                $data['js_files']['fluent-booking-checkout-sdk-stripe-js'],
                $data['js_files']['fluent-booking-checkout-handler-stripe-js']
            );
        }
        return $data;
    }

    public function redirectAttachedSeatReschedule(): void
    {
        if (is_admin() || ($_GET['fluent-booking'] ?? '') !== 'booking' || ($_GET['type'] ?? '') !== 'reschedule' || empty($_GET['meeting_hash'])) {
            return;
        }
        $hash = sanitize_text_field(wp_unslash((string) $_GET['meeting_hash']));
        $booking = Booking::where('hash', $hash)->first();
        if (!$booking || empty($booking->getMeta(BookingAdapter::META, [])['attached_seat']) || !$booking->parent_id) {
            return;
        }
        $parent = Booking::find($booking->parent_id);
        if ($parent && method_exists($parent, 'getRescheduleUrl')) {
            wp_safe_redirect($parent->getRescheduleUrl());
            exit;
        }
    }

    public function buildRescheduleSummaryHtml(int $seats, array $details): string
    {
        $seatsLabel = $seats . ($seats > 1 ? ' places conservées' : ' place conservée');
        $html = '<section class="fba-reschedule-summary" aria-label="' . esc_attr__('Récapitulatif de la réservation reportée', 'waaskit-fluent-booking') . '">';
        $html .= '<p class="fba-reschedule-lead"><strong>' . esc_html__('Report sans nouveau paiement', 'waaskit-fluent-booking') . '</strong> — '
            . esc_html(sprintf('Choisissez simplement votre nouveau créneau (%s). Les participants et tarifs enregistrés sont déplacés ensemble :', $seatsLabel))
            . '</p>';
        if (!empty($details['contact'])) {
            $status = !empty($details['attends']) ? 'participe' : 'ne participe pas';
            $html .= '<p class="fba-reschedule-contact">' . esc_html('Réservé par : ' . $details['contact'] . ' (' . $status . ')') . '</p>';
        }
        if (!empty($details['people']) && is_array($details['people'])) {
            $html .= '<ul class="fba-reschedule-people">';
            foreach ($details['people'] as $person) {
                $parts = array_values(array_filter(array_merge(
                    [(string) ($person['role'] ?? ''), (string) ($person['tariff'] ?? '')],
                    (array) ($person['answers'] ?? [])
                ), static fn($v) => $v !== ''));
                $desc = $parts ? ' — ' . implode(' · ', $parts) : '';
                $html .= '<li><strong>' . esc_html((string) ($person['name'] ?? '')) . '</strong>' . esc_html($desc) . '</li>';
            }
            $html .= '</ul>';
        }
        $html .= '</section>';
        return $html;
    }

    public function renderRescheduleNotice(): void
    {
        if (($_REQUEST['type'] ?? '') !== 'reschedule' || empty($_REQUEST['meeting_hash'])) {
            return;
        }
        $hash = sanitize_text_field(wp_unslash((string) $_REQUEST['meeting_hash']));
        $booking = Booking::where('hash', $hash)->first();
        if (!$booking) {
            return;
        }
        $snapshot = $booking->getMeta(BookingAdapter::META, []);
        if (!$snapshot || !empty($snapshot['attached_seat'])) {
            return;
        }
        $details = (new BookingPresentation())->details($booking);
        $seats = max(1, (int) ($snapshot['seats'] ?? $snapshot['count'] ?? 1));
        echo $this->buildRescheduleSummaryHtml($seats, $details); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    private function resolveReschedulingBooking(): ?Booking
    {
        if ($this->reschedulingBookingId !== null) {
            return Booking::find($this->reschedulingBookingId);
        }
        $hash = sanitize_text_field(wp_unslash((string) (
            $_REQUEST['rescheduling_hash'] ?? (($_REQUEST['type'] ?? '') === 'reschedule' ? ($_REQUEST['meeting_hash'] ?? '') : '')
        )));
        return $hash !== '' ? Booking::where('hash', $hash)->first() : null;
    }

    public function filterRescheduleSlots($availableSpots, $event)
    {
        if (!is_array($availableSpots) || !$availableSpots) {
            return $availableSpots;
        }
        $booking = $this->resolveReschedulingBooking();
        if ($booking && !empty($booking->getMeta(BookingAdapter::META, [])['attached_seat']) && $booking->parent_id) {
            $booking = Booking::find($booking->parent_id);
        }
        if (!$booking || (int) $booking->event_id !== (int) $event->id) {
            return $availableSpots;
        }
        $snapshot = $booking->getMeta(BookingAdapter::META, []);
        $seats = max(1, (int) ($snapshot['seats'] ?? $snapshot['count'] ?? 1));
        if (!$snapshot || !empty($snapshot['attached_seat']) || $seats <= 1) {
            return $availableSpots;
        }
        return self::filterSpotsByRequiredSeats($availableSpots, $seats);
    }

    public function beforePublicReschedule($bookingData, $postedData, $calendarEvent): void
    {
        if (empty($postedData['rescheduling_hash']) || !is_object($calendarEvent)) {
            return;
        }
        $existing = Booking::where('hash', sanitize_text_field((string) $postedData['rescheduling_hash']))->first();
        if (!$existing || (int) $existing->event_id !== (int) $calendarEvent->id || !$this->appliesToBooking($existing, $calendarEvent)) {
            return;
        }
        if ($existing->getMeta(BookingAdapter::META, [])) {
            try {
                $this->guardCapacity($existing, $calendarEvent, (string) ($bookingData['start_time'] ?? ''), false);
            } catch (\Throwable $error) {
                wp_send_json(['message' => $error->getMessage()], 422);
            }
        }
        $startTime = $bookingData['start_time'] ?? '';
        if (is_string($startTime) && $startTime !== '' && $startTime !== (string) $existing->start_time) {
            $this->stageEffectiveDuration($existing, self::effectiveSlotMinutes($existing, $calendarEvent));
        }
    }

    public function beforeRestReschedule($booking, $event, $request): ?\WP_Error
    {
        if (!$booking || !$event || (int) $booking->event_id !== (int) $event->id || !$this->appliesToBooking($booking, $event)) {
            return null;
        }
        $snapshot = $booking->getMeta(BookingAdapter::META, []);
        $ignore = filter_var($request->get_param('ignore_availability'), FILTER_VALIDATE_BOOLEAN);
        if ($snapshot) {
            if (!empty($snapshot['attached_seat'])) {
                $msg = 'Cet invité appartient à une réservation de groupe. Reportez la réservation depuis la réservation principale'
                    . ($booking->parent_id ? ' (#' . (int) $booking->parent_id . ')' : '') . '.';
                return new \WP_Error('fba_reschedule_refused', $msg, ['status' => 422]);
            }
            if ($ignore) {
                $this->ignoreAvailabilityBookingIds[(int) $booking->id] = true;
            }
        }
        $eventTime = sanitize_text_field((string) ($request->get_param('event_time') ?? ''));
        $timezone = sanitize_text_field((string) ($request->get_param('timezone') ?? ''));
        if ($eventTime !== '' && in_array($timezone, \DateTimeZone::listIdentifiers(), true) && \DateTime::createFromFormat('Y-m-d H:i:s', $eventTime)) {
            $startTime = \FluentBooking\App\Services\DateTimeHelper::convertToUtc($eventTime, $timezone);
            if ($snapshot) {
                try {
                    $this->guardCapacity($booking, $event, $startTime, $ignore);
                } catch (\Throwable $error) {
                    unset($this->ignoreAvailabilityBookingIds[(int) $booking->id]);
                    return new \WP_Error('fba_reschedule_refused', $error->getMessage(), ['status' => 422]);
                }
            }
            if (in_array($booking->status, ['scheduled', 'pending', 'approved', 'rescheduled'], true)
                && $startTime !== ''
                && $startTime !== (string) $booking->start_time) {
                $this->stageEffectiveDuration($booking, self::effectiveSlotMinutes($booking, $event));
            }
        }
        return null;
    }

    public function afterBookingRescheduled($booking): void
    {
        unset($this->stagedDurations[(int) $booking->id], $this->ignoreAvailabilityBookingIds[(int) $booking->id]);
        $this->slotLock->release();
        $snapshot = $booking->getMeta(BookingAdapter::META, []);
        if (!$snapshot || !empty($snapshot['attached_seat'])) {
            return;
        }
        $customFields = (array) $booking->getMeta('custom_fields_data', []);
        $customFields['fba_participants_email'] = (new BookingPresentation())->emailSummary($booking);
        Helper::updateBookingMeta($booking->id, 'custom_fields_data', $customFields);
    }

    public function guardCapacity($booking, $event, string $startTime, bool $ignoreAvailability = false): void
    {
        $snapshot = $booking->getMeta(BookingAdapter::META, []);
        if (!$snapshot) {
            return;
        }
        if (!Plugin::compatible()) {
            throw new \RuntimeException('Ces options nécessitent FluentBooking 2.4.x à 2.6.x.', 422);
        }
        if (!empty($snapshot['attached_seat'])) {
            throw new \RuntimeException(
                'Cet invité appartient à une réservation de groupe. Reportez la réservation depuis la réservation principale'
                . ($booking->parent_id ? ' (#' . (int) $booking->parent_id . ')' : '') . '.',
                422
            );
        }
        if ($ignoreAvailability || $startTime === '') {
            return;
        }
        $this->slotLock->acquire((int) $event->id, $startTime);
        $bookingId = (int) $booking->id;
        $seats = max(1, (int) ($snapshot['seats'] ?? $snapshot['count'] ?? 1));
        $used = Booking::where('event_id', $event->id)
            ->where('start_time', $startTime)
            ->whereIn('status', ['pending', 'scheduled', 'approved', 'completed'])
            ->where('id', '!=', $bookingId)
            ->where(static function ($query) use ($bookingId) {
                $query->whereNull('parent_id')->orWhere('parent_id', '!=', $bookingId);
            })
            ->count();
        if ($used + $seats > (int) $event->getMaxBookingPerSlot()) {
            $this->slotLock->release();
            throw new \RuntimeException(
                'Il ne reste pas assez de places sur ce créneau pour déplacer toute la réservation ('
                . $seats . ($seats > 1 ? ' places requises' : ' place requise') . ').',
                422
            );
        }
    }
}
