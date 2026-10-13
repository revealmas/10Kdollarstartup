<?php
declare(strict_types=1);
// CLI only: schedule hourly, outside web root.
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/Transport.php';
[$config,$store,$service]=\ApplyPilot\boot(__DIR__);
echo json_encode($service->cleanup()).PHP_EOL;
