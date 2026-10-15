<?php
declare(strict_types=1);
require_once __DIR__.'/../private/Core.php';
require_once __DIR__.'/../private/IntegrationSettings.php';
use ApplyPilot\EncryptedFileStore;
$dir=sys_get_temp_dir().'/applypilot-test-'.bin2hex(random_bytes(8));mkdir($dir,0700);
$file=$dir.'/state.enc';$key=base64_encode(random_bytes(32));$store=new EncryptedFileStore($file,$key);
function check(bool $ok,string $name):void{if(!$ok)throw new RuntimeException($name);echo 'PASS '.$name.PHP_EOL;}
$store->transaction(function(&$s){$s['fixture']='private-synthetic-CV';$s['counter']=0;});
check(strpos(file_get_contents($file),'private-synthetic-CV')===false,'No plaintext CV in encrypted file');
check($store->transaction(fn(&$s)=>$s['fixture'])==='private-synthetic-CV','Encrypted storage round trip');
$wrong=new EncryptedFileStore($file,base64_encode(random_bytes(32)));
try{$wrong->transaction(fn(&$s)=>null);throw new RuntimeException('wrong key accepted');}catch(ApplyPilot\Problem $e){echo 'PASS Wrong encryption key rejected'.PHP_EOL;}
$original=file_get_contents($file);$tampered=$original;$tampered[30]=chr(ord($tampered[30])^1);file_put_contents($file,$tampered);
try{$store->transaction(fn(&$s)=>null);throw new RuntimeException('tamper accepted');}catch(ApplyPilot\Problem $e){echo 'PASS Authenticated encryption rejects tampering'.PHP_EOL;}
file_put_contents($file,$original);
if(function_exists('pcntl_fork')){
 $pids=[];
 for($w=0;$w<4;$w++){ $pid=pcntl_fork();if($pid===0){for($n=0;$n<25;$n++)$store->transaction(function(&$s){$s['counter']++;});exit(0);}if($pid<0)throw new RuntimeException('fork failed');$pids[]=$pid; }
 foreach($pids as $pid){pcntl_waitpid($pid,$status);check(pcntl_wexitstatus($status)===0,'Concurrent writer exited successfully');}
 check($store->transaction(fn(&$s)=>$s['counter'])===100,'Four writers: no lost updates across 100 commits');
}else{echo 'SKIP pcntl concurrency unavailable'.PHP_EOL;}
$settings=new ApplyPilot\IntegrationSettings($dir,['storage_key'=>$key]);
$fixture='rk_test_'.str_repeat('A',24);
$status=$settings->save(['stripe_key'=>$fixture,'deepseek_model'=>'synthetic-model']);
check($status['stripe_test_configured']===true&&!str_contains(json_encode($status),$fixture),'Settings response excludes API credential');
check(!str_contains(file_get_contents($dir.'/data/integrations.enc'),$fixture),'API credentials encrypted at rest');
check($settings->save(['stripe_key'=>''])['stripe_test_configured']===true,'Blank input preserves configured credential');
try{$settings->save(['stripe_key'=>'rk_live_'.str_repeat('B',24)]);throw new RuntimeException('live key accepted');}catch(ApplyPilot\Problem $e){check($e->status===422,'Owner setup rejects live credentials');}
check($settings->read()['stripe_key']===$fixture,'Invalid update leaves previous credential intact');
check($settings->save(['stripe_payment_method_configuration'=>'pmc_syntheticSandbox'])['stripe_payment_method_configuration']==='pmc_syntheticSandbox','Sandbox payment configuration can be stored without credentials in response');
try{$settings->save(['stripe_payment_method_configuration'=>'https://evil.example']);throw new RuntimeException('invalid configuration accepted');}catch(ApplyPilot\Problem $e){check($e->status===422,'Payment configuration rejects URL injection');}
check($settings->read()['stripe_payment_method_configuration']==='pmc_syntheticSandbox','Invalid payment configuration preserves previous value');
check($settings->save(['support_email'=>'support@example.invalid'])['support_email']==='support@example.invalid','Owner can configure a valid public support email without exposing credentials');
try{$settings->save(['deepseek_model'=>'changed-model','support_email'=>"support@example.invalid\r\nBcc: other@example.invalid"]);throw new RuntimeException('invalid email accepted');}catch(ApplyPilot\Problem $e){check($e->status===422,'Support address rejects header injection');}
check($settings->read()['deepseek_model']==='synthetic-model'&&$settings->read()['support_email']==='support@example.invalid','Invalid support update preserves all previous settings atomically');
foreach(glob($dir.'/data/*') as $f)unlink($f);rmdir($dir.'/data');
foreach(glob($dir.'/*') as $f)unlink($f);rmdir($dir);
echo 'Storage checks complete.'.PHP_EOL;
