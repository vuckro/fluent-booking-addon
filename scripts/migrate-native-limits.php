<?php
/** CLI only. Preview by default. Apply with FBA_MIGRATE_APPLY=1 and FBA_MIGRATE_BACKUP=/private/path.json. */
if (PHP_SAPI !== 'cli') { exit; }
$path=getenv('WAASKIT_WP_PATH');
if (!$path) {throw new RuntimeException('WAASKIT_WP_PATH requis.');}
require_once $path.'/wp-load.php';
use FluentBooking\App\Models\Calendar;
use FluentBooking\App\Models\CalendarSlot;
use FluentBooking\App\Services\Helper;
use WaasKit\FluentBooking\Integrations\FluentBooking\ConfigurationStore as Store;
if (!Store::migrationRequired()) {echo "Configuration déjà migrée.\n";return;}
$read=static function($raw) {
    if($raw===null || $raw===false) {return [];}
    if(!is_array($raw) || !in_array($raw['schema']??0,[1,2],true) || !is_array($raw['values']??null)) {throw new RuntimeException('Configuration inconnue : migration arrêtée.');}
    if(!empty($raw['values']['booking_profile']['enabled'])) {throw new RuntimeException('Ancien profil expérimental actif : examen manuel requis.');}
    return $raw['values'];
};
$site=get_option(Store::KEY,false);$siteValues=$read($site);$calendars=[];$events=[];$plan=[];
foreach(Calendar::all() as $calendar) {$calendars[$calendar->id]=Helper::getMeta('calendar',$calendar->id,Store::KEY);$read($calendars[$calendar->id]);}
foreach(CalendarSlot::all() as $event) {
    $raw=Helper::getMeta('calendar_event',$event->id,Store::KEY);$values=$read($raw);
    $effective=array_replace(['enabled'=>false,'max_participants'=>0],$siteValues,$read($calendars[$event->calendar_id]??null),$values);
    $fields=$event->getMeta('booking_fields',null);$nextFields=$fields;
    if(!empty($effective['enabled']) && (int)$effective['max_participants']>0) {
        $nextFields=$event->getBookingFields();
        foreach($nextFields as &$field) {
            if(($field['name']??'')!=='guests') {continue;}
            $limit=$event->isMultiGuestEvent()?(int)$effective['max_participants']:max(0,(int)$effective['max_participants']-1);
            $field['limit']=min((int)($field['limit']??10),$limit);
            if((int)$effective['max_participants']===1) {$field['enabled']=false;$field['required']=false;}
        } unset($field);
    }
    $next=[];
    if(isset($values['guest_options'])) {
        $next['guest_options']=$values['guest_options'];unset($next['guest_options']['per_person_seats']);
        \WaasKit\FluentBooking\Guests\Options::validate($next['guest_options']);
    }
    $events[$event->id]=['config'=>$raw,'booking_fields'=>$fields];
    $plan[$event->id]=['values'=>$next,'booking_fields'=>$nextFields,'revision'=>(int)($raw['revision']??0)+1];
    echo 'Événement '.$event->id.': '.($nextFields===$fields?'limite native inchangée':'limite transférée vers FluentBooking')."\n";
}
if(getenv('FBA_MIGRATE_APPLY')!=='1') {echo "Simulation uniquement.\n";return;}
$backup=getenv('FBA_MIGRATE_BACKUP');
if(!$backup || !($file=fopen($backup,'x'))) {throw new RuntimeException('Chemin de sauvegarde neuf et accessible requis.');}
chmod($backup,0600);
$payload=json_encode(['site'=>$site,'calendars'=>$calendars,'events'=>$events,'plan'=>$plan],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
if(fwrite($file,$payload)!==strlen($payload)) {fclose($file);throw new RuntimeException('Sauvegarde incomplète.');}fclose($file);
global $wpdb;
$wpdb->query('START TRANSACTION');
try {
    if(get_option(Store::KEY,false)!==$site) {throw new RuntimeException('Configuration globale modifiée pendant la migration.');}
    foreach($plan as $id=>$next) {
        $event=CalendarSlot::find($id);
        if(Helper::getMeta('calendar_event',$id,Store::KEY)!==$events[$id]['config'] || $event->getMeta('booking_fields',null)!==$events[$id]['booking_fields']) {throw new RuntimeException('Événement modifié pendant la migration.');}
        if($next['booking_fields']!==$events[$id]['booking_fields']) {$event->setBookingFields($next['booking_fields']);}
        $config=['schema'=>Store::VERSION,'revision'=>$next['revision'],'values'=>$next['values']];
        Helper::updateMeta('calendar_event',$id,Store::KEY,$config);
        if(Helper::getMeta('calendar_event',$id,Store::KEY)!==$config || $event->getMeta('booking_fields',null)!==$next['booking_fields']) {throw new RuntimeException('Écriture non vérifiée.');}
    }
    foreach($calendars as $id=>$raw) {
        if(Helper::getMeta('calendar',$id,Store::KEY)!==$raw) {throw new RuntimeException('Calendrier modifié pendant la migration.');}
        Helper::deleteMeta('calendar',$id,Store::KEY);
    }
    delete_option(Store::KEY);update_option(Store::MIGRATED,Store::VERSION,false);
    if((int)get_option(Store::MIGRATED)!==Store::VERSION) {throw new RuntimeException('Migration non vérifiée.');}
    $wpdb->query('COMMIT');echo "Migration vérifiée. Sauvegarde : $backup\n";
} catch(Throwable $e) {$wpdb->query('ROLLBACK');throw $e;} finally {wp_cache_flush();}
