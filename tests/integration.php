<?php
$path=getenv('WAASKIT_WP_PATH');if(!$path){throw new RuntimeException('Local WordPress required.');}require $path.'/wp-load.php';require_once ABSPATH.'wp-admin/includes/template.php';
if(!in_array(parse_url(home_url(),PHP_URL_HOST),['localhost','127.0.0.1'],true)){throw new RuntimeException('Local only.');}
use WaasKit\FluentBooking\Integrations\FluentBooking\ConfigurationStore;
use WaasKit\FluentBooking\Admin\SettingsPage;
use WaasKit\FluentBooking\Guests\Options;
function check($ok,$name){if(!$ok){throw new RuntimeException($name);}echo "PASS $name\n";}
function rejects($test,$name){try{$test();}catch(Throwable $e){check(true,$name);return;}check(false,$name);}
$store=new ConfigurationStore();$before=$store->read('calendar_event',2);
$wpdb->query('START TRANSACTION');
try {
 wp_set_current_user(1);
 check(SettingsPage::allowed('calendar_event',2),'admin can edit event');
 check(!SettingsPage::allowed('site',0) && !SettingsPage::allowed('calendar',1),'obsolete scopes not editable');
 wp_set_current_user(0);check(!SettingsPage::allowed('calendar_event',2),'anonymous cannot edit');wp_set_current_user(1);
 rejects(fn()=>$store->save('calendar_event',2,['max_participants'=>2],$before['revision']),'duplicate limit setting refused');
 rejects(fn()=>$store->read('site'),'global configuration removed');
 $options=Options::defaults();$store->save('calendar_event',2,['guest_options'=>$options],$before['revision']);
 rejects(fn()=>$store->save('calendar_event',2,['guest_options'=>$options],$before['revision']),'stale revision refused');
 $_GET=['page'=>'waaskit-fluent-booking','scope'=>'calendar_event','object_id'=>2];
 ob_start();(new SettingsPage($store))->render();$html=ob_get_clean();
 check(str_contains($html,'<h1>Modules</h1>'),'page title Modules');
 check(!str_contains($html,'Limite de participants') && !str_contains($html,'name="policy"'),'duplicate block absent');
 check(!str_contains($html,'Réglages communs') && !str_contains($html,'Utiliser les réglages'),'inheritance navigation removed');
 check(str_contains($html,'Résumé des réglages') && str_contains($html,'question-settings'),'saved summary links to native questions');
 check(str_contains($html,'fba-guest-details" hidden'),'disabled feature hides its options');
}finally{$wpdb->query('ROLLBACK');wp_cache_flush();}
check($store->read('calendar_event',2)===$before,'settings restored after test');
