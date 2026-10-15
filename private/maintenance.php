<?php
declare(strict_types=1);
// CLI only: schedule hourly, outside web root.
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/Transport.php';
require_once __DIR__.'/MaintenanceRunner.php';
require_once __DIR__.'/PublicJobs.php';
try {
    [$config,$store,$service]=\ApplyPilot\boot(__DIR__);
    $result=\ApplyPilot\runMaintenance($store,fn()=>$service->cleanup(),__DIR__);
    $result['public_jobs']=\ApplyPilot\refreshPublicJobs($store);
    echo json_encode($result).PHP_EOL;
}catch(\Throwable $e){echo json_encode(['ok'=>false,'error'=>'Scheduled cleanup failed; review private server operation.']).PHP_EOL;exit(1);}
