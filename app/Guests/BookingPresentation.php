<?php
namespace WaasKit\FluentBooking\Guests;

use FluentBooking\App\Models\Booking;

/** Read-only projections of the booking snapshot into native FluentBooking views. */
final class BookingPresentation
{
    public function register(): void
    {
        add_action('fluent_booking/format_booking_schedule', [$this, 'admin']);
        add_filter('fluent_booking/schedule_receipt_data', [$this, 'receipt'], 100, 2);
    }

    private function name($booking): string
    {
        return trim($booking->first_name.' '.$booking->last_name);
    }

    /** A child exposes only its own answers; the main booking exposes the whole party. */
    public function details($booking): array
    {
        $snapshot=$booking->getMeta(BookingAdapter::META, []);
        if (!$snapshot) {return [];}
        $child=!empty($snapshot['attached_seat']);
        $holder=$child ? Booking::find($booking->parent_id) : $booking;
        $holderSnapshot=$holder ? $holder->getMeta(BookingAdapter::META, []) : [];
        $attends=($holderSnapshot['holder_participates']??true)!==false;
        $contact=$holder ? $this->name($holder) : 'Contact indisponible';
        $people=[];
        if (!$child && $attends) {
            $people[]=['name'=>$contact, 'role'=>'Contact principal et participant', 'tariff'=>$snapshot['holder_tariff']['title']??'', 'answers'=>[]];
        }
        $labels=array_column($snapshot['fields']??[], 'label', 'id');
        foreach ($snapshot['guests']??[] as $index=>$guest) {
            $answers=[];
            foreach ($guest['fields']??[] as $key=>$value) {
                if ($value!=='' && $value!==null) {$answers[]=($labels[$key]??$key).' : '.(is_array($value)?implode(', ', $value):$value);}
            }
            $people[]=['name'=>$guest['name']?:'Participant '.($index+1), 'role'=>'Participant', 'tariff'=>$guest['tariff']['title']??'', 'answers'=>$answers];
        }
        return ['contact'=>$contact, 'holder_id'=>$holder ? (int)$holder->id : 0, 'attends'=>$attends, 'child'=>$child, 'people'=>$people];
    }

    private function description(array $person): string
    {
        return implode(' · ', array_filter(array_merge([$person['role'], $person['tariff']], $person['answers']), static fn($value)=>$value!==''));
    }

    /**
     * Stable booking metadata for FluentBooking's native e-mail shortcode
     * {{booking.custom.fba_participants_email}}. This only contains the party
     * recorded on the booking; it is never used in connected calendar events.
     */
    public function emailSummary($booking): string
    {
        $details=$this->details($booking);
        if (!$details) {return '';}
        $contact=esc_html($details['contact']);
        $status=$details['attends']?'participe':'ne participe pas';
        $html='<strong>Contact de réservation</strong><br>'.$contact.' — '.$status;
        if (!$details['people']) {return $html;}
        $html.='<br><br><strong>Participants</strong><br><ul style="margin:6px 0 0;padding-left:18px">';
        foreach ($details['people'] as $person) {
            $html.='<li><strong>'.esc_html($person['name']).'</strong>';
            $description=$this->description($person);
            if ($description!=='') {$html.=' — '.esc_html($description);}
            $html.='</li>';
        }
        return $html.'</ul>';
    }

    public function admin($booking): void
    {
        $details=$this->details($booking);
        if (!$details) {return;}
        // Enrich native read-only custom data. Never rewrite contact identity or seats.
        $fields=(array)$booking->custom_form_data;
        $fields['fba_contact']=['label'=>'Réservé par', 'value'=>esc_html($details['contact'].' — '.($details['attends']?'participe':'ne participe pas').' · réservation #'.$details['holder_id']), 'type'=>'text'];
        foreach ($details['people'] as $index=>$person) {
            $fields['fba_participant_'.$index]=['label'=>'Participant : '.$person['name'], 'value'=>esc_html($this->description($person)), 'type'=>'text'];
        }
        $booking->custom_form_data=$fields;
    }

    public function receipt(array $data, $booking): array
    {
        // Email-only metadata is rendered by native receipts as a custom field.
        // Hide its projection, never delete the value used by email shortcodes.
        unset($data['sections']['fba_participants_email']);
        $details=$this->details($booking);
        if (!$details) {return $data;}
        $data['sections']['what']['content']=esc_html($booking->calendar_event->title);
        $data['sections']['who']=['title'=>'Réservation et participants', 'content'=>[
            '<strong>Réservé par : '.esc_html($details['contact']).'</strong><br>'.($details['attends']?'La personne qui a réservé participe également.':'Cette personne a réservé pour les participants ci-dessous et ne participe pas au rendez-vous.'),
        ]];
        foreach ($details['people'] as $person) {
            $data['sections']['who']['content'][]='<strong>'.esc_html($person['name']).'</strong><br>'.esc_html($this->description($person));
        }
        $data['sections']['who']['content'][]='Hôte : '.esc_html($data['author']['name']??'');
        unset($data['sections']['guests']);
        // Native receipt has no heading filter; replace this exact heading only here.
        $data['extra_html']=str_replace('<h4>'.esc_html__('Customer Details', 'fluent-booking').'</h4>', '<h4>Informations de facturation</h4>', $data['extra_html']??'');
        if (!$details['attends'] && ($data['action_type']??'confirmation')==='confirmation') {
            $data['sub_heading']='Vous avez réservé pour les participants indiqués ci-dessous.';
            $data['bookmarks']=[]; // The contact must not receive an "I attend" calendar action.
        }
        return $data;
    }

}
