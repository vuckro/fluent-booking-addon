<?php
require dirname(__DIR__).'/autoload.php';
use WaasKit\FluentBooking\Guests\Options;
use WaasKit\FluentBooking\Guests\Pricing;
function check($ok,$name) {if(!$ok){throw new RuntimeException($name);}echo "PASS $name\n";}
function rejects($test,$name) {try{$test();}catch(InvalidArgumentException $e){check(true,$name);return;}check(false,$name);}
$options=Options::defaults();
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
