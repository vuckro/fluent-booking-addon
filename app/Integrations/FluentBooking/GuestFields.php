<?php
namespace WaasKit\FluentBooking\Integrations\FluentBooking;

/** Configure the native guest component instead of replacing its DOM or requests. */
final class GuestFields
{
    public function __construct(private ConfigurationStore $store) {}

    public function register(): void
    {
        add_filter('fluent_booking/public_event_vars', function (array $vars, $event) {
            $vars['form_fields'] = $this->fields($vars['form_fields'], $event);
            return $vars;
        }, 100, 2);
        add_action('fluent_booking/starting_scheduling_ajax', function (array $posted) {
            $event = \FluentBooking\App\Models\CalendarSlot::find((int) ($posted['event_id'] ?? 0));
            if (!$event) { return; }
            $error = $this->validatePosted($posted, $event);
            if ($error) { wp_send_json(['message' => $error->get_error_message()], 422); }
        });
        add_filter('fluent_booking/initialize_booking_data', [$this, 'captureCount'], 100, 3);
        add_filter('fluent_booking/schedule_validation_rules_data', [$this, 'validationRules'], 100, 3);
    }

    private function maximum(int $eventId): int
    {
        $settings = $this->store->effective('calendar_event', $eventId);
        return $settings['enabled']['value'] ? $settings['max_participants']['value'] : 0;
    }

    public function fields(array $fields, $event): array
    {
        // Only called for public event variables; the native question editor is unchanged.
        $maximum = $this->maximum((int) $event->id);
        if ($maximum <= 0) { return $fields; }
        foreach ($fields as &$field) {
            if (($field['name'] ?? '') !== 'guests') { continue; }
            // FluentBooking 2.4's multi-guests component compares guests.length < limit - 1.
            $field['limit'] = min((int) ($field['limit'] ?? 10), $maximum);
            if ($maximum === 1) { $field['required'] = false; }
        }
        unset($field);
        return $fields;
    }

    public function validatePosted(array $posted, $event): ?\WP_Error
    {
        $maximum = $this->maximum((int) $event->id);
        $custom = $this->store->read('calendar_event', (int) $event->id)['values']['guest_options']['enabled'] ?? false;
        if ($maximum <= 0 && !$custom) { return null; }
        $options = \WaasKit\FluentBooking\Guests\Options::validate($this->store->read('calendar_event',(int)$event->id)['values']['guest_options']??[]);
        if (\WaasKit\FluentBooking\Guests\Identity::attached($options)) {
            try {
                $rows=\WaasKit\FluentBooking\Guests\Identity::rows($posted['fba_extra_'.$event->id]??'[]',$options);
                if($maximum>0 && count($rows)+1>$maximum) {throw new \InvalidArgumentException('Le nombre de personnes dépasse la limite de cette réservation.');}
                return null;
            } catch(\Throwable $e) {return new \WP_Error('fba_guests',$e->getMessage());}
        }
        $guests = $posted['guests'] ?? [];
        if (!is_array($guests)) { return new \WP_Error('fba_guests', 'La liste des invités est invalide.'); }
        if ($maximum > 0 && 1 + count($guests) > $maximum) {
            return new \WP_Error('fba_maximum', $maximum === 1 ? 'Cette réservation est limitée à une personne, sans invité.' : sprintf('Cette réservation est limitée à %d personnes au total, vous compris.', $maximum));
        }
        if (!$event->isMultiGuestEvent()) { return null; }
        $emails = [strtolower((string) ($posted['email'] ?? ''))];
        foreach ($guests as $guest) {
            if (!is_array($guest) || !is_string($guest['name'] ?? null) || trim($guest['name']) === ''
                || !is_string($guest['email'] ?? null) || !is_email($guest['email'])) {
                return new \WP_Error('fba_guest_identity', 'Indiquez un nom et un e-mail valide pour chaque invité.');
            }
            $emails[] = strtolower($guest['email']);
        }
        return count(array_unique($emails)) === count($emails) ? null
            : new \WP_Error('fba_guest_email', 'Chaque participant doit avoir une adresse e-mail différente, y compris le réservant.');
    }

    public function captureCount(array $data, array $posted, $event): array
    {
        // The native controller may slice guests later to its remaining capacity.
        // Keep the original count so the service refuses instead of silently dropping people.
        $data['_fba_requested_count'] = 1 + count((array) ($posted['guests'] ?? []));
        return $data;
    }

    public function validationRules(array $config, array $posted, $event): array
    {
        $options=\WaasKit\FluentBooking\Guests\Options::validate($this->store->read('calendar_event',(int)$event->id)['values']['guest_options']??[]);
        if ($this->maximum((int) $event->id) === 1 || \WaasKit\FluentBooking\Guests\Identity::attached($options)) {
            unset($config['rules']['guests']);
        }
        return $config;
    }
}
