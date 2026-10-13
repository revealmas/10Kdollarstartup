<?php
declare(strict_types=1);
// CLI only: schedule hourly, outside web root.
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/Transport.php';
[$config,$store,$service]=\ApplyPilot\boot(__DIR__);
echo json_encode($service->cleanup()).PHP_EOL;
// Own private session directories avoid shared-host 24-minute cleanup rules.
foreach(['customer'=>86400,'owner'=>1800] as $kind=>$ttl)
    foreach(glob(__DIR__.'/data/sessions/'.$kind.'/sess_*') ?: [] as $file)
        if(is_file($file) && filemtime($file)<time()-$ttl) unlink($file);
