<?php
declare(strict_types=1);
require_once __DIR__.'/../private/MaintenanceRunner.php';require_once __DIR__.'/../private/Admin.php';
$passed=0;
function check(bool $ok,string $name):void{global $passed;if(!$ok)throw new RuntimeException($name);$passed++;echo 'PASS '.$name.PHP_EOL;}
$dir=sys_get_temp_dir().'/applypilot-maintenance-'.bin2hex(random_bytes(8));mkdir($dir,0700);
$key=base64_encode(random_bytes(32));$store=new ApplyPilot\EncryptedFileStore($dir.'/data/state.enc',$key);
foreach(['customer','owner']as$kind){mkdir($dir.'/data/sessions/'.$kind,0700,true);file_put_contents($dir.'/data/sessions/'.$kind.'/sess_old','synthetic-expired-session');file_put_contents($dir.'/data/sessions/'.$kind.'/sess_current','synthetic-active-session');touch($dir.'/data/sessions/'.$kind.'/sess_old',time()-($kind==='customer'?86500:1900));}
$result=ApplyPilot\runMaintenance($store,fn()=>['packages_purged'=>3,'unexpected'=>'synthetic-sensitive-extra'], $dir);
check($result['ok']&&$result['packages_purged']===3&&$result['sessions_purged']===2,'Maintenance confirms purged packages and expired sessions');
check(!file_exists($dir.'/data/sessions/customer/sess_old')&&!file_exists($dir.'/data/sessions/owner/sess_old'),'Expired private sessions removed');
check(file_exists($dir.'/data/sessions/customer/sess_current')&&file_exists($dir.'/data/sessions/owner/sess_current'),'Active private sessions preserved');
check(!str_contains(json_encode($result),'synthetic-sensitive-extra'),'Cleanup output excludes unexpected private fields');
$admin=new ApplyPilot\Admin($store,['storage_key'=>$key]);$snapshot=$admin->snapshot();check($snapshot['maintenance']['ok']&&isset($snapshot['maintenance']['checked_at']),'Owner snapshot exposes actual cleanup health');
try{ApplyPilot\runMaintenance($store,fn()=>throw new RuntimeException('synthetic-secret-must-not-leak'),$dir);throw new RuntimeException('failure ignored');}catch(ApplyPilot\Problem $e){check($e->status===503&&!str_contains($e->getMessage(),'synthetic-secret'),'Cleanup failure is recorded without sensitive error disclosure');}
check($admin->snapshot()['maintenance']['ok']===false,'Owner health reports the latest failed execution');
$store->transaction(function(&$s){$s['maintenance']['secret']='synthetic-sensitive-extra';});check(!str_contains(json_encode($admin->snapshot()['maintenance']),'synthetic-sensitive-extra'),'Snapshot allowlist excludes unexpected maintenance fields');
foreach(['customer','owner']as$kind){foreach(glob($dir.'/data/sessions/'.$kind.'/*')as$f)unlink($f);rmdir($dir.'/data/sessions/'.$kind);}rmdir($dir.'/data/sessions');foreach(glob($dir.'/data/*')as$f)unlink($f);rmdir($dir.'/data');rmdir($dir);
echo 'MAINTENANCE RESULT '.$passed.' checks passed; temporary synthetic data only.'.PHP_EOL;
