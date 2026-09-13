<?php
namespace WaasKit\FluentBooking\Guests;

/** A contact is not necessarily a participant. Defaults preserve historical bookings. */
final class Participation
{
    public static function requested(array $payload, bool $allowed): bool
    {
        $participates=array_key_exists('holder_participates',$payload)?$payload['holder_participates']:true;
        if (!is_bool($participates)) {throw new \InvalidArgumentException('Choix de participation invalide.');}
        if (!$participates && !$allowed) {throw new \InvalidArgumentException('Le réservant doit participer à cet événement.');}
        return $participates;
    }
    public static function count(int $guests, bool $participates): int
    {
        $count=$guests+($participates?1:0);
        if ($count<1) {throw new \InvalidArgumentException('Ajoutez au moins une personne qui participe.');}
        return $count;
    }
}
