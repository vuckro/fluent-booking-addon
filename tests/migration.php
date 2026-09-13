<?php
$path=getenv('WAASKIT_WP_PATH');if(!$path){throw new RuntimeException('Local WordPress required.');}require $path.'/wp-load.php';
if(!in_array(parse_url(home_url(),PHP_URL_HOST),['localhost','127.0.0.1'],true)){throw new RuntimeException('Local only.');}
use WaasKit\FluentBooking\Integrations\FluentBooking\ConfigurationStore;
use FluentBooking\App\Services\Helper;
use FluentBooking\App\Models\CalendarSlot;
function check($ok,$name){if(!$ok){throw new RuntimeException($name);}echo "PASS $name\n";}
$originalConfig=Helper::getMeta('calendar_event',2,ConfigurationStore::KEY);
$originalFields=CalendarSlot::find(2)->getMeta('booking_fields',null);
$wpdb->query('START TRANSACTION');
try {
 delete_option(ConfigurationStore::MIGRATED);
 update_option(ConfigurationStore::KEY,['schema'=>1,'revision'=>1,'values'=>['enabled'=>true,'max_participants'=>4]],false);
 Helper::updateMeta('calendar_event',2,ConfigurationStore::KEY,['schema'=>1,'revision'=>1,'values'=>['max_participants'=>2]]);
 putenv('FBA_MIGRATE_APPLY=0');
 $result=(static function(){ob_start();try{require dirname(__DIR__).'/scripts/migrate-native-limits.php';return $plan;}finally{ob_end_clean();}})();
 $guest=static function($fields){foreach($fields as $f){if(($f['name']??'')==='guests'){return $f;}}return [];};
 check($guest($result[2]['booking_fields'])['limit']===2,'group migration transfers effective maximum including holder');
 check($guest($result[1]['booking_fields'])['limit']===3,'individual migration transfers maximum minus holder');
 check(empty($guest($result[1]['booking_fields'])['enabled']),'migration never enables previously disabled guests');
 check(CalendarSlot::find(2)->getMeta('booking_fields',null)===$originalFields,'preview changes no native questions');
}finally{$wpdb->query('ROLLBACK');wp_cache_flush();putenv('FBA_MIGRATE_APPLY');}
check(Helper::getMeta('calendar_event',2,ConfigurationStore::KEY)===$originalConfig,'migration fixture restored');
check(!ConfigurationStore::migrationRequired(),'live installation remains migrated');
