<?php
require dirname(__DIR__).'/autoload.php';

use WaasKit\FluentBooking\Guests\CalendarPresentation;

function admin_url($path) {return 'https://example.test/wp-admin/'.$path;}
function esc_html($value) {return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');}
function check($ok,$message) {if (!$ok) {throw new RuntimeException($message);} echo "PASS $message\n";}
final class CalendarBookingFixture
{
    public function __construct(public array $snapshot, public bool $group=true, public int $id=516) {}
    public function getMeta($key,$default=[]) {return $this->snapshot;}
    public function isMultiGuestBooking() {return $this->group;}
}
$view=new CalendarPresentation();
$booking=new CalendarBookingFixture(['attached'=>true,'holder_participates'=>false,'guests'=>[['name'=>'Jean','email'=>'private@example.test','fields'=>['age'=>10],'tariff'=>['title'=>'Enfant','cents'=>5500]]]]);
$google=['summary'=>'Agenda collectif','attendees'=>[['email'=>'contact@example.test','responseStatus'=>'accepted']],'start'=>['dateTime'=>'2033-01-01T09:00:00Z'],'location'=>'Lieu','extendedProperties'=>['private'=>['booking_id'=>123]]];
$result=$view->google($google,$booking);
check(isset($result['description']),'Google group event gets a description');
$unchanged=$result;unset($unchanged['description']);check($unchanged===$google,'native attendees, RSVP, title, dates and metadata remain unchanged');
foreach (['Jean','private@example.test','Âge : 10','5500','Enfant','meeting_hash'] as $private) {
    check(!str_contains($result['description'],$private),'description excludes private datum '.$private);
}
check(str_contains($result['description'],'/wp-admin/admin.php?page=fluent-booking#/scheduled-events?period=all&booking_id=516'),'description links to the originating booking');
check(count(explode("\n",$result['description']))===2,'description contains only a label and a link');
check(str_ends_with($view->google($google,new CalendarBookingFixture($booking->snapshot,true,742))['description'],'booking_id=742'),'booking link is generated dynamically');
check($view->google($result,$booking)===$result,'Google formatting is idempotent');
$existing=$google+['description'=>'Existing integration content'];
check(str_starts_with($view->google($existing,$booking)['description'],'Existing integration content'),'existing description preserved');
foreach ([new CalendarBookingFixture([]),new CalendarBookingFixture(['attached'=>true],false),new CalendarBookingFixture(['attached_seat'=>true])] as $index=>$other) {
    check($view->google($google,$other)===$google,'unrelated Google booking untouched '.$index);
    check($view->outlook($google,$other)===$google,'unrelated Outlook booking untouched '.$index);
}
$outlook=['subject'=>'Agenda collectif','attendees'=>$google['attendees'],'location'=>['displayName'=>'Lieu']];
$result=$view->outlook($outlook,$booking);
check($result['body']['contentType']==='text' && str_contains($result['body']['content'],'Voir les détails de la réservation'),'Outlook gets a text description');
$unchanged=$result;unset($unchanged['body']);check($unchanged===$outlook,'Outlook native event fields preserved');
check($view->outlook($result,$booking)===$result,'Outlook formatting is idempotent');
$outlook['body']=['contentType'=>'HTML','content'=>'<p>Existing content</p>'];
$result=$view->outlook($outlook,$booking);
check(str_starts_with($result['body']['content'],'<p>Existing content</p>') && str_contains($result['body']['content'],'<br'),'existing HTML body preserved with HTML line breaks');
check(!str_contains($result['body']['content'],'Jean'),'Outlook never copies participant identity');
