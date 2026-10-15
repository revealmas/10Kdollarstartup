<?php
// Test-only router, never deployed. Simulates the TLS terminator for local tests.
if(PHP_SAPI!=='cli-server'){http_response_code(404);exit;}
$_SERVER['HTTPS']='on';
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(in_array($path,['/api.php','/admin-api.php'],true)){require __DIR__.'/../public'.$path;return true;}
return false;
