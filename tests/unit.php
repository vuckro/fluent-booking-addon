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
$compatCheck = static fn(string $v) => version_compare($v, '2.4.0', '>=') && version_compare($v, '2.6.0', '<');
check($compatCheck('2.4.0'),'FluentBooking 2.4.0 in range');
check($compatCheck('2.4.15'),'FluentBooking 2.4.15 in range');
check($compatCheck('2.5.0'),'FluentBooking 2.5.0 in range');
check($compatCheck('2.5.9'),'FluentBooking 2.5.9 in range');
check(!$compatCheck('2.3.9'),'FluentBooking 2.3.9 rejected');
check(!$compatCheck('2.6.0'),'FluentBooking 2.6.0 rejected');

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

