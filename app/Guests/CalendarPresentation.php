<?php
namespace WaasKit\FluentBooking\Guests;

/** Group events are shared across bookings: never publish individual snapshots. */
final class CalendarPresentation
{
    private const HEADING='Voir les détails de la réservation';

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

    private function text($booking): string
    {
        $url=admin_url('admin.php?page=fluent-booking').'#/scheduled-events?period=all&booking_id='.(int)$booking->id;
        return self::HEADING."\n".$url;
    }

    public function google(array $data, $booking): array
    {
        if (!$this->applies($booking)) {return $data;}
        $description=(string)($data['description']??'');
        if (!str_contains($description,self::HEADING)) {
            $data['description']=($description!==''?$description."\n\n":'').$this->text($booking);
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
                .($html?nl2br(esc_html($this->text($booking))):$this->text($booking));
            $data['body']=$body;
        }
        return $data;
    }
}
