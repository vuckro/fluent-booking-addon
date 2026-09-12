<?php
$path = getenv('WAASKIT_WP_PATH');
if (!$path || !is_file($path . '/wp-load.php')) { throw new RuntimeException('Local WordPress required.'); }
require $path . '/wp-load.php';
if (!in_array(parse_url(home_url(), PHP_URL_HOST), ['localhost','127.0.0.1'], true)) { throw new RuntimeException('Local tests only.'); }
use WaasKit\FluentBooking\Infrastructure\CapacityStore;
$store=new CapacityStore();
if (($argv[1] ?? '') === '--worker') {
    try {
        $store->hold(0, $argv[2], [], '2030-02-01 14:00:00', '2030-02-01 15:00:00',1,1);
        usleep(300000);
        echo 'held';
    } catch (InvalidArgumentException $e) { echo 'full'; }
    finally {$store->unlock();}
    exit;
}
$pool='test:'.wp_generate_uuid4(); $workers=[];
try {
    for($i=0;$i<2;$i++) {
        $pipes=[];
        $process=proc_open([PHP_BINARY,'-d','mysqli.default_socket='.ini_get('mysqli.default_socket'),__FILE__,'--worker',$pool], [1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if(!is_resource($process)) {throw new RuntimeException('Cannot start worker');}
        $workers[]=[$process,$pipes];
    }
    $results=[];
    foreach($workers as [$process,$pipes]) {
        $results[]=trim(stream_get_contents($pipes[1])); $errors=stream_get_contents($pipes[2]);
        fclose($pipes[1]);fclose($pipes[2]);
        if(proc_close($process)!==0 || $errors!=='') {throw new RuntimeException('Worker failed: '.$errors);}
    }
    sort($results);
    if($results!==['full','held']) {throw new RuntimeException('Overbooking protection failed: '.json_encode($results));}
    echo "PASS two concurrent connections competing for one place: exactly one hold\n";
    if($store->remaining($pool,[],'2030-02-01 14:00:00','2030-02-01 15:00:00',1)!==0) {throw new RuntimeException('Lost hold after process exit');}
    echo "PASS process exit does not silently release an unlinked hold\n";
} finally { $wpdb->delete($wpdb->prefix.'fba_capacity',['pool'=>$pool]); }
