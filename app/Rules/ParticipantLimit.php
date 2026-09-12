<?php
namespace WaasKit\FluentBooking\Rules;
final class ParticipantLimit implements Rule
{
    public function id(): string { return 'participant_limit'; }
    public function validate(array $context, array $settings): ?string
    {
        $limit = $settings['max_participants'] ?? 0;
        return $limit > 0 && $context['participant_count'] > $limit
            ? sprintf('Cette demande dépasse la limite de %d participants.', $limit) : null;
    }
}
