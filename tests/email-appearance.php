<?php
$path=getenv('WAASKIT_WP_PATH');if(!$path){throw new RuntimeException('Local WordPress required.');}require $path.'/wp-load.php';
if(!in_array(parse_url(home_url(),PHP_URL_HOST),['localhost','127.0.0.1'],true)){throw new RuntimeException('Local only.');}
use WaasKit\FluentBooking\Emails\Appearance;
use FluentBooking\App\Services\Mailer;
function check($ok,$message){if(!$ok){throw new RuntimeException($message);}echo "PASS $message\n";}
$captured=[];
// Stop before any transport. No email is sent during this test.
add_filter('pre_wp_mail',static function($result,$mail)use(&$captured){$captured=$mail;return true;},PHP_INT_MAX,2);
add_filter('pre_http_request',static fn()=>new WP_Error('blocked','Test'),PHP_INT_MAX);
$wpdb->query('START TRANSACTION');
try {
 delete_option(Appearance::OPTION);delete_option(Appearance::ENABLED_OPTION);
 $email_body='<p>Test FluentBooking</p>';$email_footer='';
 ob_start();include $path.'/wp-content/plugins/fluent-booking/app/Views/emails/template.php';$html=ob_get_clean();
 $emogrifier=new FluentBooking\App\Services\Libs\Emogrifier\Emogrifier($html);
 $html=(string)$emogrifier->emogrify();
 check(str_contains($html,'#0069ff'),'real native template contains blue border after CSS inlining');
 Mailer::send('nobody@example.invalid','Test',$html);
 check($captured['message']===$html,'disabled by default preserves native email');
 update_option(Appearance::ENABLED_OPTION,true,false);
 Mailer::send('nobody@example.invalid','Test',$html);
 check(str_contains($captured['message'],'border-top: 4px solid #111111'),'native email uses default black border');
 check($captured['to']==='nobody@example.invalid' && $captured['subject']==='Test','recipient and subject preserved');
 update_option(Appearance::OPTION,'#AABBCC',false);
 Mailer::send('nobody@example.invalid','Test',$html);
 check(str_contains($captured['message'],'border-top: 4px solid #aabbcc'),'saved global color applied');
 wp_mail('nobody@example.invalid','Other WordPress email',$html);
 check($captured['message']===$html,'unrelated email unchanged even with identical FluentBooking body');
 update_option(Appearance::OPTION,'#0069ff',false);
 Mailer::send('nobody@example.invalid','Test',$html);
 check($captured['message']===$html,'native blue can be restored');
 update_option(Appearance::OPTION,'red;display:none',false);
 check(Appearance::color()==='#111111','invalid stored color falls back safely');
 update_option(Appearance::OPTION,['bad'],false);
 check(Appearance::color()==='#111111','non-scalar stored value handled safely');
 $changed=str_replace('#0069ff','#123456',$html);
 Mailer::send('nobody@example.invalid','Test',$changed);
 check($captured['message']===$changed,'another template customization is not overwritten');
 update_option(Appearance::ENABLED_OPTION,false,false);
 Mailer::send('nobody@example.invalid','Test',$html);check($captured['message']===$html,'disabling restores native template');
 $beforeUser=get_current_user_id();wp_set_current_user(0);ob_start();Appearance::render();$form=ob_get_clean();
 check($form==='','global control hidden from unauthorized users');wp_set_current_user($beforeUser);
} finally {$wpdb->query('ROLLBACK');wp_cache_delete(Appearance::OPTION,'options');wp_cache_delete(Appearance::ENABLED_OPTION,'options');wp_cache_delete('notoptions','options');wp_cache_delete('alloptions','options');}
