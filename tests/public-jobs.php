<?php
declare(strict_types=1);
require_once __DIR__.'/../private/PublicJobs.php';
require_once __DIR__.'/../private/AiCheck.php';
use ApplyPilot\AiCheckMemoryStore;
$n=0;function check($v,$name){global $n;if(!$v)throw new RuntimeException($name);$n++;echo 'PASS '.$name.PHP_EOL;}
$j=['id'=>12,'url'=>'https://jobicy.com/jobs/12-engineer','jobTitle'=>'<b>Engineer</b> &amp; Developer','companyName'=>'Example','jobGeo'=>'USA only','jobDescription'=>'Not stored'];
$out=ApplyPilot\normalizePublicJobs(['jobs'=>[$j,$j]]);check(count($out)===1&&$out[0]['source']==='Jobicy','Source attribution and deduplication');check($out[0]['title']==='Engineer & Developer'&&!isset($out[0]['jobDescription']),'Safe compact metadata only');check($out[0]['location']==='USA only','Remote location restrictions preserved');
foreach(['https://evil.test/jobs/12','javascript:alert(1)','https://jobicy.com@evil.test/jobs/12','https://jobicy.com:999/jobs/12','https://jobicy.com/not-jobs']as$url)check(ApplyPilot\normalizePublicJobs(['jobs'=>[array_merge($j,['url'=>$url])]])===[],'Unsafe destination rejected');
$store=new AiCheckMemoryStore();$calls=0;$fetch=function()use(&$calls,$j){$calls++;return ['jobs'=>[$j]];};
$r=ApplyPilot\refreshPublicJobs($store,$fetch,100000);check($r['ok']&&$calls===1,'Successful feed refresh');ApplyPilot\refreshPublicJobs($store,$fetch,100001);check($calls===1,'Six-hour request reservation');
$r=ApplyPilot\refreshPublicJobs($store,fn()=>throw new RuntimeException('secret'),121601);check(!$r['ok']&&!str_contains(json_encode($r),'secret'),'Failed source body stays private');check($store->transaction(fn(&$s)=>$s['jobs_at'])===100000,'Failed refresh never renews freshness');
check(ApplyPilot\normalizePublicJobs(['jobs'=>[]])===[],'Empty successful feed clears withdrawn results');
echo 'PUBLIC JOBS RESULT '.$n.' checks passed'.PHP_EOL;
