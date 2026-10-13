<?php
declare(strict_types=1);
require_once __DIR__.'/../private/SandboxPurchase.php';
use ApplyPilot\SandboxPurchase;use ApplyPilot\SandboxMemoryStore;use ApplyPilot\Service;use ApplyPilot\Problem;
$count=0;function check(bool $ok,string $name):void{global $count;if(!$ok)throw new RuntimeException($name);$count++;echo 'PASS '.$name.PHP_EOL;}
function rejects(callable $f,int $status,string $name):void{try{$f();}catch(Problem $e){check($e->status===$status,$name);return;}throw new RuntimeException($name);}
$c=require __DIR__.'/../private/config.example.php';$c['stripe_key']='rk_test_'.str_repeat('A',30);$c['stripe_webhook_secret']='whsec_fixture';
$session=[];$gateway=function($method,$path,$params,$key)use(&$session,$c){
 if(str_contains($path,'payment_method_configurations'))return ['id'=>$c['stripe_payment_method_configuration'],'active'=>true,'livemode'=>false,'card'=>['available'=>true]];
 if($method==='POST'){$session=['id'=>'cs_test_fixture','url'=>'https://checkout.stripe.com/c/pay/cs_test_fixture','metadata'=>$params['metadata'],'client_reference_id'=>$params['client_reference_id'],'status'=>'open','payment_status'=>'unpaid','amount_total'=>900,'currency'=>'usd','mode'=>'payment','livemode'=>false,'payment_intent'=>'pi_fixture','customer_details'=>['address'=>['country'=>'AE']]];return $session;}
 return $session;
};
$store=new SandboxMemoryStore();$qa=new SandboxPurchase($store,$c,$gateway);$preview=$qa->prepare('synthetic-browser-owner');$id=$preview['order_id'];
check(!isset($preview['package'])&&!isset($preview['preview']['interview_questions']),'QA response exposes only useful preview');
check($preview['price']['total']===9&&str_contains($preview['notice'],'No real applicant'),'QA is explicitly synthetic and fixed at USD 9');
check($store->transaction(fn(&$s)=>count($s['ai']))===0,'Synthetic preparation consumes no real AI reservation');
check(!$c['checkout_enabled']&&!$c['ai_enabled']&&!$c['licence_confirmed'],'QA does not change public launch flags');
rejects(fn()=>$qa->checkout($id,'synthetic-browser-owner',false),422,'Test Checkout requires preview review');
rejects(fn()=>$qa->checkout($id,'another-browser',true),404,'Another browser cannot charge this test order');
$service=new Service($store,$c,fn()=>throw new RuntimeException('No AI'),$gateway);
rejects(fn()=>$service->result($id,'synthetic-browser-owner'),402,'Synthetic paid contents remain locked before payment');
$qa->checkout($id,'synthetic-browser-owner',true);check($session['amount_total']===900,'QA calls ordinary Checkout implementation');
$store->transaction(function(&$s)use($id){unset($s['orders'][$id]['source']);});
rejects(fn()=>$qa->checkout($id,'synthetic-browser-owner',true),404,'Owner QA cannot bypass launch gates for normal customer orders');
$store->transaction(function(&$s)use($id){$s['orders'][$id]['source']='sandbox-qa';});
$session['status']='complete';$session['payment_status']='paid';
function event(string $type,array $object):array{$raw=json_encode(['id'=>'evt_'.bin2hex(random_bytes(4)),'type'=>$type,'livemode'=>false,'data'=>['object'=>$object]]);$now=time();return [$raw,'t='.$now.',v1='.hash_hmac('sha256',$now.'.'.$raw,'whsec_fixture')];}
[$raw,$header]=event('checkout.session.completed',['id'=>'cs_test_fixture']);$service->webhook($raw,$header);
check(isset($service->result($id,'synthetic-browser-owner')['package']),'Ordinary verified webhook unlocks the owned synthetic package');
[$raw,$header]=event('charge.refunded',['payment_intent'=>'pi_fixture']);$service->webhook($raw,$header);
rejects(fn()=>$service->result($id,'synthetic-browser-owner'),402,'Ordinary refund webhook revokes the synthetic package');
rejects(fn()=>(new SandboxPurchase($store,array_merge($c,['stripe_live'=>true]),$gateway))->prepare('owner'),503,'Live mode cannot invoke owner synthetic purchase flow');
echo 'SANDBOX PURCHASE RESULT '.$count.' mocked checks passed; no external provider call.'.PHP_EOL;
