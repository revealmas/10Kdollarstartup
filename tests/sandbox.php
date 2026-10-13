<?php
declare(strict_types=1);
require_once __DIR__.'/../private/SandboxCheck.php';
require_once __DIR__.'/../private/Admin.php';
use ApplyPilot\SandboxCheck;use ApplyPilot\Problem;
$passed=0;
function check(bool $value,string $name):void{global $passed;if(!$value)throw new RuntimeException($name);$passed++;echo 'PASS '.$name.PHP_EOL;}
function rejects(callable $fn,int $status,string $name):void{try{$fn();}catch(Problem $e){check($e->status===$status,$name);return;}throw new RuntimeException($name);}
$config=require __DIR__.'/../private/config.example.php';
$config['stripe_key']='rk_test_'.str_repeat('A',24);$config['stripe_webhook_secret']='whsec_'.str_repeat('B',24);
$calls=[];$params=[];$invalidAmount=false;
$gateway=function($method,$path,$input,$idempotency)use(&$calls,&$params,&$invalidAmount,$config){
 $calls[]=[$method,$path];
 if(str_starts_with($path,'/v1/payment_method_configurations/'))return ['id'=>$config['stripe_payment_method_configuration'],'active'=>true,'livemode'=>false,'card'=>['available'=>true]];
 if($method==='POST'&&$path==='/v1/checkout/sessions'){$params=$input;return ['id'=>'cs_test_Synthetic123','url'=>'https://checkout.stripe.com/c/pay/cs_test_Synthetic123'];}
 if(str_ends_with($path,'/expire'))return ['id'=>'cs_test_Synthetic123','status'=>'expired','livemode'=>false];
 return ['id'=>'cs_test_Synthetic123','status'=>'open','payment_status'=>'unpaid','livemode'=>false,'amount_total'=>$invalidAmount?901:900,'currency'=>'usd','mode'=>'payment','client_reference_id'=>$params['client_reference_id']];
};
$result=(new SandboxCheck($config,$gateway))->run();
check($result['ok']===true&&count($result['checks'])===5,'Synthetic diagnostic creates retrieves and expires a test Checkout');
check(count($calls)===4&&end($calls)[1]==='/v1/checkout/sessions/cs_test_Synthetic123/expire','Successful diagnostic leaves the session expired');
check($params['line_items'][0]['price_data']['unit_amount']===900&&$params['line_items'][0]['quantity']===1&&!isset($params['payment_method_types']),'Diagnostic exercises fixed USD 9 Checkout with reviewed dynamic methods');
check(!str_contains(json_encode($result),'rk_test_')&&!str_contains(json_encode($result),'checkout.stripe.com'),'Diagnostic does not return a credential or usable Checkout URL');
check($config['checkout_enabled']===false&&$config['privacy_confirmed']===false&&$config['licence_confirmed']===false,'Synthetic check leaves customer launch configuration unchanged');
$calls=[];rejects(fn()=>(new SandboxCheck(array_merge($config,['stripe_live'=>true]),$gateway))->run(),503,'Live mode cannot invoke sandbox diagnostic');check(count($calls)===0,'Live-mode rejection makes no provider request');
rejects(fn()=>(new SandboxCheck(array_merge($config,['stripe_webhook_secret'=>'']),$gateway))->run(),503,'Missing sandbox webhook configuration fails closed');
$invalidAmount=true;rejects(fn()=>(new SandboxCheck($config,$gateway))->run(),502,'Unexpected actual Checkout amount fails diagnostic');
check(str_ends_with(end($calls)[1],'/expire'),'Failed validation attempts test-session cleanup');
$admin=new ApplyPilot\Admin(new ApplyPilot\SandboxMemoryStore(),$config);
for($n=0;$n<3;$n++)$admin->reserveSandboxCheck();
rejects(fn()=>$admin->reserveSandboxCheck(),429,'Owner sandbox diagnostic quota is bounded');
echo 'SANDBOX RESULT '.$passed.' mocked checks passed; no external Stripe call.'.PHP_EOL;
