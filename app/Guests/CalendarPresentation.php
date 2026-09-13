<?php
namespace WaasKit\FluentBooking\Guests;

/** Group events are shared across bookings: never publish individual snapshots. */
final class CalendarPresentation
{
    private const HEADING='Réservation de groupe — informations pratiques';

    public function register(): void
    {
        add_filter('fluent_booking/google_event_data', [$this, 'google'], 100, 2);
        add_filter('fluent_booking/outlook_event_data', [$this, 'outlook'], 100, 2);
    }

    private function applies($booking): bool
    {
        $snapshot=$booking->getMeta(BookingAdapter::META, []);
        return $booking->isMultiGuestBooking() && !empty($snapshot['attached']) && empty($snapshot['attached_seat']);
    }

    private function text(): string
    {
        // Use the stable booking list, not a customer's confirmation URL or the
        // first booking: that booking may later be cancelled or deleted.
        $url=admin_url('admin.php?page=fluent-booking').'#/scheduled-events?period=all';
        return self::HEADING."\n\n"
            ."Ce créneau peut réunir plusieurs réservations. Les invitations de l’agenda sont adressées aux contacts qui réservent ; ces contacts ne participent pas nécessairement.\n\n"
            ."Le nombre d’invités indiqué par l’agenda ne représente pas le nombre de places réservées.\n\n"
            ."Pour l’organisateur : participants, informations et suivi des réservations dans FluentBooking (connexion et droits d’accès requis) :\n".$url;
    }

    public function google(array $data, $booking): array
    {
        if (!$this->applies($booking)) {return $data;}
        $description=(string)($data['description']??'');
        if (!str_contains($description,self::HEADING)) {
            $data['description']=($description!==''?$description."\n\n":'').$this->text();
        }
        return $data;
    }

    public function outlook(array $data, $booking): array
    {
        if (!$this->applies($booking)) {return $data;}
        $body=$data['body']??['contentType'=>'text','content'=>''];
        $content=(string)($body['content']??'');
        if (!str_contains($content,self::HEADING)) {
            $html=strtolower($body['contentType']??'text')==='html';
            $body['content']=$content.($content!==''?($html?'<br><br>':"\n\n"):'')
                .($html?nl2br(esc_html($this->text())):$this->text());
            $data['body']=$body;
        }
        return $data;
    }
}
