<?php
declare(strict_types=1);
require_once __DIR__.'/../private/Core.php';
use ApplyPilot\Service; use ApplyPilot\Problem;
class MemoryStore implements ApplyPilot\Store {
    public array $data=['orders'=>[],'counts'=>[],'limits'=>[],'jobs'=>[],'jobs_at'=>0,'ai'=>[]];
    public function transaction(callable $fn) {
        $copy=$this->data; $out=$fn($copy); $this->data=$copy; return $out;
    }
}
$passed=0;
function ok(bool $condition,string $name): void {
    global $passed; if(!$condition) throw new RuntimeException('FAIL: '.$name);
    $passed++; echo 'PASS '.$name.PHP_EOL;
}
function fails(callable $fn,int $status,string $name): void {
    try {$fn();} catch(Problem $e) {ok($e->status===$status,$name);return;}
    throw new RuntimeException('FAIL: '.$name.' did not reject');
}
$now=1791876000;
$config=require __DIR__.'/../private/config.example.php';
$config=array_merge($config,['storage_key'=>'test-only','stripe_webhook_secret'=>'whsec_test_fixture',
    'stripe_live'=>false,'ai_enabled'=>true,'privacy_confirmed'=>true,'checkout_enabled'=>true,
    'licence_confirmed'=>true,'tax_confirmed'=>true,'merchant_confirmed'=>true,'production_tests_passed'=>true,
    'support_email'=>'support@example.com','merchant_descriptor'=>'MAFSAN APPLYPILOT','country_tax_rates'=>['AE'=>'']]);
$resume='Candidate has five years of project coordination experience. Managed supplier communication and delivery schedules. Supported the preparation of monthly progress reports. Contact candidate@example.com or +971 50 123 4567.';
$job='A project coordinator role requiring supplier communication, reporting, schedule tracking and experience supporting stakeholder updates.';
$report=['summary'=>'Your supplied CV shows relevant coordination experience. Verify the posting requirements before applying.',
    'strengths'=>[['quote'=>'Managed supplier communication and delivery schedules.','reason'=>'This is relevant to supplier coordination in the posting.'],
        ['quote'=>'Supported the preparation of monthly progress reports.','reason'=>'This provides evidence of relevant reporting exposure.']],
    'gaps'=>['The CV does not evidence the employer’s specific scheduling tools.','Verify any location and work authorisation requirements.'],
    'interview_questions'=>['How did you coordinate supplier delivery schedules?','How did you prepare progress reporting?'],
    'tips'=>['Review the employer’s current role requirements before submitting.','Explain only experience you can substantiate.']];
$store=new MemoryStore(); $session=[]; $stripeCalls=[];
$stripe=function($method,$path,$params,$key)use(&$session,&$stripeCalls){
    $stripeCalls[]=[$method,$path,$params,$key];
    if($method==='POST') return ['id'=>'cs_test_fixture','url'=>'https://checkout.stripe.com/c/pay/cs_test_fixture'];
    return $session;
};
$ai=function($r,$j)use($report){
    ok(strpos($r,'candidate@example.com')===false && strpos($r,'+971 50 123 4567')===false,'Contact details redacted before provider');
    return $report+['paid_secret'=>'must be stripped'];
};
$svc=new Service($store,$config,$ai,$stripe,fn()=>$now);
$input=['resume'=>$resume,'job_description'=>$job,'consent'=>true,'adult'=>true];
$owner='owner-cookie-secret';
fails(fn()=>$svc->prepare(array_merge($input,['consent'=>false]),$owner),422,'Consent required before AI processing');
fails(fn()=>$svc->prepare(array_merge($input,['adult'=>false]),$owner),422,'Adult confirmation required');
fails(fn()=>$svc->prepare(array_merge($input,['resume'=>str_repeat('x',18001)]),$owner),422,'Oversized CV rejected');
$p=$svc->prepare($input,$owner); $id=$p['order_id'];
ok(!isset($p['package']) && !isset($p['preview']['interview_questions']) && !isset($p['preview']['original_cv']),'Preview contains no full package or original CV');
ok(!isset($store->data['orders'][$id]['package']['analysis']['paid_secret']),'Unknown provider keys discarded');
ok($p['price']['total']===9 && $p['price']['currency']==='USD','Price is exactly USD 9');
fails(fn()=>$svc->result($id,$owner),402,'Unpaid package cannot be downloaded');
fails(fn()=>$svc->status($id,'attacker'),404,'Another browser cannot inspect order');
fails(fn()=>$svc->result($id,'attacker'),404,'Another browser cannot download order');
$checkout=['order_id'=>$id,'billing_country'=>'AE','accept_terms'=>true,'accept_delivery'=>true,'amount'=>1,'currency'=>'eur'];
fails(fn()=>$svc->checkout($checkout,$owner),409,'Checkout requires preview acknowledgment');
$svc->acknowledgePreview($id,$owner);
fails(fn()=>$svc->checkout(array_merge($checkout,['accept_terms'=>false]),$owner),422,'Terms acceptance required');
fails(fn()=>$svc->checkout(array_merge($checkout,['billing_country'=>'US']),$owner),422,'Unreviewed billing jurisdiction blocked');
foreach(['checkout_enabled','licence_confirmed','privacy_confirmed','tax_confirmed','merchant_confirmed','production_tests_passed'] as $flag) {
    $blocked=new Service($store,array_merge($config,[$flag=>false]),$ai,$stripe,fn()=>$now);
    fails(fn()=>$blocked->checkout($checkout,$owner),503,'Launch gate '.$flag);
}
$url=$svc->checkout($checkout,$owner);
$call=end($stripeCalls); $params=$call[2];
ok($params['line_items'][0]['price_data']['unit_amount']===900 && $params['line_items'][0]['price_data']['currency']==='usd','Client amount and currency cannot override price');
ok($params['mode']==='payment' && $params['line_items'][0]['quantity']===1 && !isset($params['allow_promotion_codes']),'One-time fixed quantity, no discount or subscription');
ok($params['line_items'][0]['price_data']['tax_behavior']==='inclusive','Any configured tax is inside the 9 dollar total');
ok($call[3]==='applypilot-'.$id,'Order-bound Stripe idempotency key');
$calls=count($stripeCalls); $svc->checkout($checkout,$owner);
ok(count($stripeCalls)===$calls,'Repeated checkout reuses same session');
$session=['id'=>'cs_test_fixture','metadata'=>['order_id'=>$id,'product'=>'applypilot-v1'],
    'client_reference_id'=>$id,'payment_status'=>'paid','mode'=>'payment','status'=>'complete',
    'amount_total'=>900,'currency'=>'usd','livemode'=>false,'payment_intent'=>'pi_fixture',
    'customer_details'=>['address'=>['country'=>'AE']]];
function eventBody(string $id,string $type='checkout.session.completed',array $object=['id'=>'cs_test_fixture'],bool $live=false): string {
    return json_encode(['id'=>$id,'type'=>$type,'livemode'=>$live,'data'=>['object'=>$object]]);
}
function signedHeader(string $raw,int $timestamp): string {return 't='.$timestamp.',v1='.hash_hmac('sha256',$timestamp.'.'.$raw,'whsec_test_fixture');}
$raw=eventBody('evt_paid');
fails(fn()=>$svc->webhook($raw,'t='.$now.',v1=forged'),400,'Forged webhook rejected');
fails(fn()=>$svc->webhook($raw,signedHeader($raw,$now-301)),400,'Stale signed webhook rejected');
fails(fn()=>$svc->webhook($raw.' ',signedHeader($raw,$now)),400,'Raw-body modification rejected');
$live=eventBody('evt_live','checkout.session.completed',['id'=>'cs_test_fixture'],true);
fails(fn()=>$svc->webhook($live,signedHeader($live,$now)),400,'Live webhook rejected by test-mode service');
$original=$session;
foreach(['amount_total'=>901,'currency'=>'eur','mode'=>'subscription','status'=>'open','livemode'=>true,'client_reference_id'=>'other','id'=>'cs_test_other'] as $field=>$bad) {
    $session=array_merge($original,[$field=>$bad]);
    fails(fn()=>$svc->webhook($raw,signedHeader($raw,$now)),409,'Payment mismatch rejected: '.$field);
}
$session=$original; $session['metadata']['product']='another-product';
fails(fn()=>$svc->webhook($raw,signedHeader($raw,$now)),409,'Wrong product rejected');
$session=$original; $session['customer_details']['address']['country']='US';
fails(fn()=>$svc->webhook($raw,signedHeader($raw,$now)),409,'Actual billing country must match reviewed tax country');
$session=$original; $session['payment_status']='unpaid';
$svc->webhook($raw,signedHeader($raw,$now));
fails(fn()=>$svc->result($id,$owner),402,'Checkout completion with unpaid status cannot fulfill');
$session=$original; $svc->webhook($raw,signedHeader($raw,$now));
$result=$svc->result($id,$owner);
ok(isset($result['package']['analysis']['interview_questions']),'Verified payment unlocks full result');
$svc->webhook($raw,signedHeader($raw,$now));
$counts=$store->data['counts'][gmdate('Y-m-d',$now)];
ok($counts['payment_verified']===1,'Duplicate webhook does not duplicate fulfillment');
fails(fn()=>$svc->checkout($checkout,$owner),409,'Paid order cannot create another charge');
$refund=eventBody('evt_refund','charge.refunded',['payment_intent'=>'pi_fixture']);
$svc->webhook($refund,signedHeader($refund,$now));
fails(fn()=>$svc->result($id,$owner),402,'Refund revokes package access');
$later=eventBody('evt_later'); $svc->webhook($later,signedHeader($later,$now));
fails(fn()=>$svc->result($id,$owner),402,'Late payment event cannot restore refunded access');
$unknown=eventBody('evt_earlyrefund','charge.refunded',['payment_intent'=>'pi_unknown']);
fails(fn()=>$svc->webhook($unknown,signedHeader($unknown,$now)),409,'Refund arriving before binding requires webhook retry');
$p2=$svc->prepare($input,$owner); $id2=$p2['order_id'];
$svc->delete($id2,$owner);
ok($store->data['orders'][$id2]['package']===null && $store->data['orders'][$id2]['preview']===null,'Deletion removes personal package and preview');
fails(fn()=>$svc->result($id2,$owner),410,'Deleted package inaccessible');
$badReport=$report; $badReport['strengths'][0]['quote']='Invented ten years of banking experience.';
fails(fn()=>ApplyPilot\validateReport($badReport,ApplyPilot\redact($resume)),502,'Invented CV evidence rejected');
$small=new Service(new MemoryStore(),array_merge($config,['daily_ai_limit'=>1]),$ai,$stripe,fn()=>$now);
$small->prepare($input,$owner);
fails(fn()=>$small->prepare($input,$owner),429,'Daily AI credit-use cap enforced');
$disabled=new Service(new MemoryStore(),array_merge($config,['ai_enabled'=>false]),$ai,$stripe,fn()=>$now);
fails(fn()=>$disabled->prepare($input,$owner),503,'AI remains disabled until configured');
$p3=$svc->prepare($input,$owner); $id3=$p3['order_id'];
$future=new Service($store,$config,$ai,$stripe,fn()=>$now+86401);
fails(fn()=>$future->status($id3,$owner),410,'Package expires after 24 hours');
$purge=$future->cleanup();
ok($purge['packages_purged']>=1 && $store->data['orders'][$id3]['package']===null,'Cleanup actually removes expired CV data');
$store->data['jobs_at']=$now-43201; $store->data['jobs']=[['title'=>'Stale fake','location'=>'Dubai','employer'=>'Test']];
ok(count($svc->jobs('')['jobs'])===0,'Stale job listings are not returned as fresh');
$limits=new Service(new MemoryStore(),$config,$ai,$stripe,fn()=>$now);
for($i=0;$i<60;$i++) $limits->rateLimit('192.0.2.1');
fails(fn()=>$limits->rateLimit('192.0.2.1'),429,'IP abuse limit enforced');
require_once __DIR__.'/../private/Admin.php';
$adminConfig=array_merge($config,['admin_password_hash'=>password_hash('synthetic-owner-password-with-length',PASSWORD_DEFAULT)]);
$admin=new ApplyPilot\Admin($store,$adminConfig);
ok(!$admin->login('wrong','192.0.2.9'),'Wrong owner password rejected');
ok($admin->login('synthetic-owner-password-with-length','192.0.2.9'),'Owner password verified');
$snapshot=$admin->snapshot();$encoded=json_encode($snapshot);
ok(strpos($encoded,'candidate@example.com')===false && strpos($encoded,$resume)===false && strpos($encoded,$owner)===false,'Admin snapshot excludes CV and browser owner secret');
for($i=0;$i<3;$i++)$admin->login('wrong','192.0.2.9');
fails(fn()=>$admin->login('wrong','192.0.2.9'),429,'Owner login brute force limit enforced');
$freshStore=new MemoryStore();$fresh=new Service($freshStore,$config,$ai,$stripe,fn()=>$now);
$latePreview=$fresh->prepare($input,'late-owner');$lateId=$latePreview['order_id'];$fresh->acknowledgePreview($lateId,'late-owner');
$late=new Service($freshStore,$config,$ai,$stripe,fn()=>$now+86400-100);
fails(fn()=>$late->checkout(array_merge($checkout,['order_id'=>$lateId]),'late-owner'),409,'Expiring package cannot enter checkout');
echo 'RESULT '.$passed.' checks passed. MemoryStore and mocked provider calls only.'.PHP_EOL;
