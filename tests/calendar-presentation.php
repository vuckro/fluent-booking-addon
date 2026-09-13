<?php
require dirname(__DIR__).'/autoload.php';

use WaasKit\FluentBooking\Guests\CalendarPresentation;

function admin_url($path) {return 'https://example.test/wp-admin/'.$path;}
function esc_html($value) {return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');}
function check($ok,$message) {if (!$ok) {throw new RuntimeException($message);} echo "PASS $message\n";}
final class CalendarBookingFixture
{
    public function __construct(public array $snapshot, public bool $group=true) {}
    public function getMeta($key,$default=[]) {return $this->snapshot;}
    public function isMultiGuestBooking() {return $this->group;}
}
$view=new CalendarPresentation();
$booking=new CalendarBookingFixture(['attached'=>true,'holder_participates'=>false,'guests'=>[['name'=>'Jean','email'=>'private@example.test','fields'=>['age'=>10],'tariff'=>['title'=>'Enfant','cents'=>5500]]]]);
$google=['summary'=>'Agenda collectif','attendees'=>[['email'=>'contact@example.test','responseStatus'=>'accepted']],'start'=>['dateTime'=>'2033-01-01T09:00:00Z'],'location'=>'Lieu','extendedProperties'=>['private'=>['booking_id'=>123]]];
$result=$view->google($google,$booking);
check(isset($result['description']),'Google group event gets a description');
$unchanged=$result;unset($unchanged['description']);check($unchanged===$google,'native attendees, RSVP, title, dates and metadata remain unchanged');
foreach (['Jean','private@example.test','Âge : 10','5500','Enfant','meeting_hash','booking_id='] as $private) {
    check(!str_contains($result['description'],$private),'description excludes private datum '.$private);
}
check(str_contains($result['description'],'droits d’accès requis'),'description identifies protected organizer access');
check(str_contains($result['description'],'ne participent pas nécessairement'),'contact and participant roles explained');
check($view->google($result,$booking)===$result,'Google formatting is idempotent');
$existing=$google+['description'=>'Existing integration content'];
check(str_starts_with($view->google($existing,$booking)['description'],'Existing integration content'),'existing description preserved');
foreach ([new CalendarBookingFixture([]),new CalendarBookingFixture(['attached'=>true],false),new CalendarBookingFixture(['attached_seat'=>true])] as $index=>$other) {
    check($view->google($google,$other)===$google,'unrelated Google booking untouched '.$index);
    check($view->outlook($google,$other)===$google,'unrelated Outlook booking untouched '.$index);
}
$outlook=['subject'=>'Agenda collectif','attendees'=>$google['attendees'],'location'=>['displayName'=>'Lieu']];
$result=$view->outlook($outlook,$booking);
check($result['body']['contentType']==='text' && str_contains($result['body']['content'],'Réservation de groupe'),'Outlook gets a text description');
$unchanged=$result;unset($unchanged['body']);check($unchanged===$outlook,'Outlook native event fields preserved');
check($view->outlook($result,$booking)===$result,'Outlook formatting is idempotent');
$outlook['body']=['contentType'=>'HTML','content'=>'<p>Existing content</p>'];
$result=$view->outlook($outlook,$booking);
check(str_starts_with($result['body']['content'],'<p>Existing content</p>') && str_contains($result['body']['content'],'<br'),'existing HTML body preserved with HTML line breaks');
check(!str_contains($result['body']['content'],'Jean'),'Outlook never copies participant identity');
