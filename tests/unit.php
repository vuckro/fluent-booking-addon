<?php
require dirname(__DIR__).'/autoload.php';
use WaasKit\FluentBooking\Guests\Options;
use WaasKit\FluentBooking\Guests\Pricing;
function check($ok,$name) {if(!$ok){throw new RuntimeException($name);}echo "PASS $name\n";}
function rejects($test,$name) {try{$test();}catch(InvalidArgumentException $e){check(true,$name);return;}check(false,$name);}
$options=Options::defaults();$options['native_tariffs']=false;
check(!isset($options['max_participants'],$options['per_person_seats']),'no duplicate participant or seat setting');
rejects(fn()=>Options::validate(['max_participants'=>2]),'native limits cannot be saved in guest options');
rejects(fn()=>Options::validate(['per_person_seats'=>false]),'seat counting is not optional');
$options['fields']=[['id'=>'type','label'=>'Tarif','type'=>'select','choices'=>['Adulte','Enfant'],'required'=>true,'pricing'=>'replace','prices'=>[5000,2500]],['id'=>'extra','label'=>'Supplément','type'=>'checkbox','choices'=>[],'required'=>false,'pricing'=>'add','prices'=>[1000]]];
Options::validate($options);
$guests=[['fields'=>['type'=>'Adulte','extra'=>'1']],['fields'=>['type'=>'Enfant','extra'=>'']]];
check(Pricing::quote(5000,$options,$guests)['total']===13500,'replacement then supplement, once per guest');
$options['per_person_price']=false;
check(Pricing::quote(5000,$options,$guests)['total']===13500,'explicit guest prices do not depend on base multiplication');
$options['fields']=[];
check(Pricing::quote(5000,$options,$guests)['total']===5000,'flat price without priced choices');
$options['per_person_price']=true;
check(Pricing::quote(5000,$options,$guests)['total']===15000,'base per person');
rejects(fn()=>Options::validate(['email_mode'=>'bogus']),'invalid identity mode rejected');

$catalogue=[['id'=>'adult','title'=>'Adulte','cents'=>7000],['id'=>'child','title'=>'Enfant','cents'=>5500]];
check(\WaasKit\FluentBooking\Guests\NativeTariffs::quote($catalogue,'adult',[['tariff'=>'child']])['total']===12500,'native choices summed once');
rejects(fn()=>\WaasKit\FluentBooking\Guests\NativeTariffs::quote($catalogue,'adult',[[]]),'guest without tariff is never silently skipped');
rejects(fn()=>\WaasKit\FluentBooking\Guests\NativeTariffs::quote($catalogue,'adult',[null]),'malformed guest rejected by pricing engine');
check(Options::defaults()['native_tariffs']===true && Options::validate(['enabled'=>false])['native_tariffs']===false,'new mode default preserves pre-existing configurations');

$legacy=Options::validate(['enabled'=>true,'native_tariffs'=>true]);
check($legacy['customize_guests']===true,'existing customization retained');
$info=Options::effective(['enabled'=>false,'customize_guests'=>true,'native_tariffs'=>true,'email_mode'=>'hidden']);
check($info['enabled'] && !$info['pricing_enabled'] && !$info['native_tariffs'] && $info['email_mode']==='hidden','identity works without custom pricing');
$price=Options::effective(['enabled'=>true,'customize_guests'=>false,'native_tariffs'=>true,'email_mode'=>'hidden']);
check($price['pricing_enabled'] && $price['email_mode']==='required' && !$price['fields'],'pricing alone uses default identity');
$f=['id'=>'number','label'=>'Nombre','type'=>'number','required'=>false,'choices'=>[],'min'=>'0','max'=>'10'];
Options::validate(['fields'=>[$f]]);
$answer=static fn($v)=>Options::answers([['email'=>'','fields'=>['number'=>$v]]],[['email'=>'','name'=>'']],[$f]);
check($answer('0')[0]['fields']['number']==='0' && $answer('10')[0]['fields']['number']==='10','numeric endpoints accepted');
check($answer('')[0]['fields']['number']==='','optional number can be blank');
rejects(fn()=>$answer('-1'),'below minimum rejected server-side');
rejects(fn()=>$answer('11'),'above maximum rejected server-side');
rejects(fn()=>Options::validate(['fields'=>[array_replace($f,['min'=>'11'])]]),'inverted numeric bounds rejected');
rejects(fn()=>Options::validate(['fields'=>[array_replace($f,['max'=>'NaN'])]]),'invalid numeric bounds rejected');

use WaasKit\FluentBooking\Guests\Participation;
check(Participation::requested([],false)===true,'old payload defaults to attending holder');
rejects(fn()=>Participation::requested(['holder_participates'=>false],false),'forged opt-out rejected');
rejects(fn()=>Participation::requested(['holder_participates'=>null],true),'null participation rejected');
rejects(fn()=>Participation::count(0,false),'at least one actual participant required');
check(Participation::count(5,false)===5 && Participation::count(4,true)===5,'one contact does not add an unwanted seat');
check(\WaasKit\FluentBooking\Guests\NativeTariffs::quote($catalogue,'adult',[['tariff'=>'child']],false)['total']===5500,'nonattending holder has no tariff');

use WaasKit\FluentBooking\Plugin;
check(!Plugin::compatible(),'unloaded FluentBooking is incompatible');
$compatCheck = static fn(string $v) => version_compare($v, '2.4.0', '>=') && version_compare($v, '2.7.0', '<');
check($compatCheck('2.4.0'),'FluentBooking 2.4.0 in range');
check($compatCheck('2.4.15'),'FluentBooking 2.4.15 in range');
check($compatCheck('2.5.0'),'FluentBooking 2.5.0 in range');
check($compatCheck('2.5.9'),'FluentBooking 2.5.9 in range');
check($compatCheck('2.6.0'),'FluentBooking 2.6.0 in range');
check($compatCheck('2.6.9'),'FluentBooking 2.6.9 in range');
check(!$compatCheck('2.3.9'),'FluentBooking 2.3.9 rejected');
check(!$compatCheck('2.7.0'),'FluentBooking 2.7.0 rejected');

if(!function_exists('is_email')) {function is_email($e) {return (bool)filter_var($e,FILTER_VALIDATE_EMAIL);}}

use WaasKit\FluentBooking\Guests\Identity;
check(Options::defaults()['split_name']===false,'split_name is disabled by default');
check(Options::validate(['split_name'=>true])['split_name']===true,'split_name accepted as boolean');
rejects(fn()=>Options::validate(['split_name'=>'oui']),'invalid split_name rejected');
check(Options::effective(['split_name'=>true,'customize_guests'=>false])['split_name']===false,'split_name disabled when customization collapsed');
$splitIdentity=Identity::rows(json_encode([['first_name'=>'Jean','last_name'=>'Dupont','email'=>'jean@example.invalid']]),array_replace(Options::defaults(),['split_name'=>true,'email_mode'=>'optional']));
check($splitIdentity[0]['name']==='Jean Dupont' && $splitIdentity[0]['first_name']==='Jean' && $splitIdentity[0]['last_name']==='Dupont','split names parsed and combined');
rejects(fn()=>Identity::rows(json_encode([['first_name'=>'','last_name'=>'Dupont','email'=>'jean@example.invalid']]),array_replace(Options::defaults(),['split_name'=>true,'email_mode'=>'optional'])),'missing first_name rejected when required');
rejects(fn()=>Identity::rows(json_encode([['first_name'=>'Jean','last_name'=>'','email'=>'jean@example.invalid']]),array_replace(Options::defaults(),['split_name'=>true,'email_mode'=>'optional'])),'missing last_name rejected when required');
$optIdentity=Identity::rows(json_encode([['first_name'=>'','last_name'=>'','email'=>'jean@example.invalid']]),array_replace(Options::defaults(),['split_name'=>true,'name_mode'=>'optional','email_mode'=>'optional']));
check($optIdentity[0]['name']==='' && $optIdentity[0]['first_name']==='' && $optIdentity[0]['last_name']==='','optional split names can be empty');
$hidIdentity=Identity::rows(json_encode([['first_name'=>'Jean','last_name'=>'Dupont','email'=>'jean@example.invalid']]),array_replace(Options::defaults(),['split_name'=>true,'name_mode'=>'hidden','email_mode'=>'optional']));
check($hidIdentity[0]['name']==='' && $hidIdentity[0]['first_name']==='' && $hidIdentity[0]['last_name']==='','hidden split names are stripped');
$ansPreserved=Options::answers([['email'=>'test@example.invalid','fields'=>[]]],[['first_name'=>'Jean','last_name'=>'Dupont','name'=>'Jean Dupont','email'=>'test@example.invalid']],[]);
check($ansPreserved[0]['first_name']==='Jean' && $ansPreserved[0]['last_name']==='Dupont' && $ansPreserved[0]['name']==='Jean Dupont','first_name and last_name preserved in answers');

if(!function_exists('esc_html')) {function esc_html($s) {return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}}
if(!function_exists('esc_attr')) {function esc_attr($s) {return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}}
if(!function_exists('esc_html__')) {function esc_html__($s,$d='') {return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}}
if(!function_exists('esc_attr__')) {function esc_attr__($s,$d='') {return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}}

use WaasKit\FluentBooking\Guests\RescheduleCoordinator;
use WaasKit\FluentBooking\Guests\SlotLock;
check(RescheduleCoordinator::matchRestRescheduleRoute('GET','/fluent-booking/v2/bookings/reschedule/2/210/slots')===['action'=>'slots','event_id'=>2,'booking_id'=>210],'FluentBooking 2.6 reschedule slots route matched');
check(RescheduleCoordinator::matchRestRescheduleRoute('POST','/fluent-booking/v2/bookings/reschedule/2/210')===['action'=>'reschedule','event_id'=>2,'booking_id'=>210],'FluentBooking 2.6 reschedule submit route matched');
check(RescheduleCoordinator::matchRestRescheduleRoute('GET','/fluent-booking/v2/bookings/2/reschedule/210/slots')===['action'=>'slots','event_id'=>2,'booking_id'=>210],'legacy reschedule slots route fallback matched');
check(RescheduleCoordinator::matchRestRescheduleRoute('POST','/fluent-booking/v2/bookings/2/reschedule/210')===['action'=>'reschedule','event_id'=>2,'booking_id'=>210],'legacy reschedule submit route fallback matched');
check(RescheduleCoordinator::matchRestRescheduleRoute('POST','/fluent-booking/v2/bookings/create/2')===null,'create route excluded from reschedule matcher');

$spots=['2026-11-18'=>[['time'=>'10:00','remaining'=>1]],'2026-11-21'=>[['time'=>'10:00','remaining'=>1],['time'=>'14:00','remaining'=>3]]];
$filteredSpots=RescheduleCoordinator::filterSpotsByRequiredSeats($spots,2);
check(!isset($filteredSpots['2026-11-18']) && count($filteredSpots['2026-11-21'])===1 && $filteredSpots['2026-11-21'][0]['time']==='14:00','multi-seat reschedule removes insufficient slots and empty days');

$coordinator=new RescheduleCoordinator(new SlotLock());
$summaryHtml=$coordinator->buildRescheduleSummaryHtml(1,['contact'=>'Francine Sauser','attends'=>false,'people'=>[['name'=>'Clara Sauser','role'=>'Participant','tariff'=>'Enfant','answers'=>['Âge du participant : 12']]]]);
check(str_contains($summaryHtml,'class="fba-reschedule-summary"') && str_contains($summaryHtml,'Francine Sauser (ne participe pas)') && str_contains($summaryHtml,'Clara Sauser') && str_contains($summaryHtml,'Participant · Enfant · Âge du participant : 12') && !str_contains($summaryHtml,'style='),'reschedule summary renders contact & participants cleanly without inline styles');

$fixedEvent=new class {
    public int $duration=90;
    public function getDuration($d=null): int {return $this->duration;}
};
$multiEventKeep=new class {
    public int $duration=90;
    public function getDuration($d=null): int {return in_array((int)$d,[60,120],true)?(int)$d:90;}
};
$multiEventFallback=new class {
    public int $duration=90;
    public function getDuration($d=null): int {return in_array((int)$d,[60,90],true)?(int)$d:90;}
};
$legacyBooking=(object)['slot_minutes'=>120];
check(RescheduleCoordinator::effectiveSlotMinutes($legacyBooking,$fixedEvent)===90,'legacy 120m booking resolves to current 90m fixed event duration');
check(RescheduleCoordinator::effectiveSlotMinutes($legacyBooking,$multiEventKeep)===120,'120m booking stays 120m when 120m is still valid multi-duration');
check(RescheduleCoordinator::effectiveSlotMinutes($legacyBooking,$multiEventFallback)===90,'120m booking resolves to default 90m when 120m is removed from multi-duration');

$normalizedSingle=RescheduleCoordinator::applyEffectiveDurationToBookingData(['start_time'=>'2026-12-02 12:30:00','end_time'=>'2026-12-02 14:30:00','slot_minutes'=>120],90);
check($normalizedSingle['slot_minutes']===90 && $normalizedSingle['end_time']==='2026-12-02 14:00:00','applyEffectiveDurationToBookingData aligns scalar end_time and slot_minutes to 90m');
$normalizedArray=RescheduleCoordinator::applyEffectiveDurationToBookingData(['start_time'=>['2026-12-02 12:30:00'],'end_time'=>['2026-12-02 14:30:00'],'slot_minutes'=>120],90);
check($normalizedArray['slot_minutes']===90 && $normalizedArray['end_time']===['2026-12-02 14:00:00'],'applyEffectiveDurationToBookingData aligns array end_time and slot_minutes to 90m');

use WaasKit\FluentBooking\Guests\BookingAdapter;
$rawVars=[
    'slot'=>['total_payment'=>'55','settings'=>['submit_button_text'=>'Régler la réservation →']],
    'payment_items'=>[['title'=>'Enfant','value'=>55]],
    'payment_methods'=>['stripe'=>[]],
    'multi_payment_items'=>[['title'=>'Enfant','value'=>55]],
    'i18n'=>['Schedule_Meeting'=>'Réserver un rendez-vous','Continue_to_Payments'=>'Passer au paiement','Confirm_Payment'=>'Confirmer le paiement'],
    'i18'=>['Schedule_Meeting'=>'Confirmer le report'],
    'form_fields'=>[['name'=>'rescheduling_reason','help_text'=>'']],
];
$cleanVars=BookingAdapter::normalizeReschedulePublicVars($rawVars,true,'Confirmer le report','Report sans nouveau paiement : votre règlement initial est conservé.');
check(!isset($cleanVars['payment_items'],$cleanVars['payment_methods'],$cleanVars['multi_payment_items'])
    && $cleanVars['slot']['total_payment']===''
    && $cleanVars['slot']['settings']['submit_button_text']==='Confirmer le report'
    && $cleanVars['i18n']['Schedule_Meeting']==='Confirmer le report'
    && $cleanVars['i18n']['Continue_to_Payments']==='Confirmer le report'
    && $cleanVars['i18n']['Confirm_Payment']==='Confirmer le report'
    && $cleanVars['form_fields'][0]['help_text']==='Report sans nouveau paiement : votre règlement initial est conservé.',
    'normalizeReschedulePublicVars replaces payment CTA with Confirmer le report and injects no-payment help text');
$customHelpVars=BookingAdapter::normalizeReschedulePublicVars(['form_fields'=>[['name'=>'rescheduling_reason','help_text'=>'Custom']]],false,'Confirmer le report','Report sans nouveau paiement');
check($customHelpVars['form_fields'][0]['help_text']==='Custom','existing custom help_text on rescheduling_reason is preserved');


