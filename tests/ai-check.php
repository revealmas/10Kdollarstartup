<?php
declare(strict_types=1);
require_once __DIR__.'/../private/AiCheck.php';
require_once __DIR__.'/../private/Admin.php';
use ApplyPilot\AiCheck;use ApplyPilot\Problem;use ApplyPilot\Admin;use ApplyPilot\AiCheckMemoryStore;
$passed=0;
function check(bool $value,string $name):void{global $passed;if(!$value)throw new RuntimeException($name);$passed++;echo 'PASS '.$name.PHP_EOL;}
function rejects(callable $fn,int $status,string $name):void{try{$fn();}catch(Problem $e){check($e->status===$status,$name);return;}throw new RuntimeException($name);}
$config=require __DIR__.'/../private/config.example.php';$config['deepseek_key']='synthetic-key-for-tests-only';$config['deepseek_model']='deepseek-flash';
$cvFixture=ApplyPilot\syntheticCvFixture();
$calls=0;$request=[];$invented=false;$finish='stop';
$provider=ApplyPilot\deepseek($config,function($url,$method,$headers,$body)use(&$calls,&$request,&$invented,&$finish,$cvFixture){
 $calls++;$request=['url'=>$url,'method'=>$method,'body'=>json_decode($body,true)];
 return ['choices'=>[['finish_reason'=>$finish,'message'=>['reasoning_content'=>'not for the client',
  'content'=>json_encode(['summary'=>'The synthetic CV supports coordination and reporting; SAP experience is not evidenced.',
   'strengths'=>[['quote'=>$invented?'Invented supplier history never present in the CV':'Coordinated supplier communication and weekly delivery schedules.',
    'reason'=>'This supports the synthetic supplier communication requirement.']],
   'gaps'=>['SAP experience is not evidenced by this fixture.'],
   'interview_questions'=>['How did you maintain delivery schedules and project records?'],
   'tips'=>['Review requirements and avoid claiming SAP experience without evidence.'],
   'application_pack'=>$cvFixture['application_pack'],'cv'=>$cvFixture['cv'],'unexpected'=>'must not appear in the returned preview'])]]]];
});
$result=(new AiCheck($config,$provider))->run();
check($calls===1&&$result['ok']===true,'AI diagnostic uses one fixed synthetic provider call');
check($request['url']==='https://api.deepseek.com/chat/completions'&&$request['method']==='POST','AI adapter calls the fixed HTTPS provider endpoint');
check($request['body']['thinking']['type']==='disabled'&&$request['body']['max_tokens']===6000&&$request['body']['stream']===false,'Bounded non-thinking JSON request avoids default reasoning cost');
check($request['body']['response_format']['type']==='json_object','Provider request requires structured JSON');
check(str_contains($request['body']['messages'][1]['content'],'Synthetic CV fixture only'),'Diagnostic input contains synthetic fixture only');
check(!str_contains(json_encode($result),'synthetic-key-for-tests-only')&&!str_contains(json_encode($result),'reasoning_content')&&!str_contains(json_encode($result),'must not appear'),'Diagnostic response excludes credentials, reasoning and unexpected provider fields');
check(!isset($result['order_id'])&&$config['ai_enabled']===false&&$config['privacy_confirmed']===false,'Diagnostic exposes no customer order and leaves launch gates unchanged');
$invented=true;rejects(fn()=>(new AiCheck($config,$provider))->run(),502,'Invented CV evidence rejects the diagnostic');
$invented=false;$finish='length';rejects(fn()=>(new AiCheck($config,$provider))->run(),502,'Truncated provider output rejects the diagnostic without retries');
$before=$calls;rejects(fn()=>(new AiCheck(array_merge($config,['deepseek_key'=>'']),$provider))->run(),503,'Missing AI configuration fails before the provider');check($calls===$before,'Missing settings consume no provider request');
$store=new AiCheckMemoryStore();$admin=new Admin($store,$config);$admin->reserveAiCheck();$admin->reserveAiCheck();
rejects(fn()=>$admin->reserveAiCheck(),429,'Repeated AI diagnostics cannot consume more than two hourly calls');
check(array_sum($admin->snapshot()['ai_calls'])===2,'Diagnostic credit reservation shares customer daily AI counts');
$config['daily_ai_limit']=0;$zero=new Admin(new AiCheckMemoryStore(),$config);rejects(fn()=>$zero->reserveAiCheck(),429,'Zero daily credit allowance prevents diagnostic calls');
$config['daily_ai_limit']=20;$config['total_ai_limit']=0;$closed=new Admin(new AiCheckMemoryStore(),$config);
rejects(fn()=>$closed->reserveAiCheck(),429,'Owner diagnostics also respect the cumulative pilot allowance');
$config['total_ai_limit']=30;$finish='stop';$cache=new AiCheckMemoryStore();$cachedChecker=new AiCheck($config,$provider,$cache);$cachedChecker->run();$before=$calls;$rechecked=$cachedChecker->run(true);check($calls===$before&&$rechecked['revalidated']===true,'Cached synthetic draft revalidates without another provider call');
$missing=new AiCheck($config,$provider,new AiCheckMemoryStore());rejects(fn()=> $missing->run(true),409,'No cached draft cannot trigger an implicit provider retry');
$unsafe=new AiCheck($config,fn()=>throw new Problem(502,'sk-secret-untrusted-provider-error'));try{$unsafe->run();}catch(Problem $e){check(!str_contains($e->getMessage(),'sk-secret'),'Diagnostic errors use fixed allowlisted messages only');}
echo 'AI CHECK RESULT '.$passed.' mocked checks passed; no external provider call.'.PHP_EOL;
