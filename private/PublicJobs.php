<?php
declare(strict_types=1);
namespace ApplyPilot;
require_once __DIR__.'/Transport.php';
// Public API usage reviewed 2026-10-15: https://jobicy.com/jobs-rss-feed
// Attribution + canonical links retained. No applicant data or API credentials sent.
function normalizePublicJobs(array $data):array {
    if(($data['success']??true)!==true||!isset($data['jobs'])||!is_array($data['jobs']))throw new Problem(502,'Job source response is invalid.');
    $jobs=[];
    foreach(array_slice($data['jobs'],0,100) as $job){
        if(!is_array($job))continue;
        $id=$job['id']??null;$url=$job['url']??null;
        if(!is_int($id)||$id<1||!is_string($url))continue;
        $parts=parse_url($url);
        if(!$parts||($parts['scheme']??'')!=='https'||($parts['host']??'')!=='jobicy.com'||isset($parts['user'])||isset($parts['pass'])||isset($parts['port'])||!str_starts_with($parts['path']??'','/jobs/'))continue;
        $clean=function($value,int $limit):string{return is_string($value)?trim(substr(html_entity_decode(strip_tags($value),ENT_QUOTES|ENT_HTML5,'UTF-8'),0,$limit)):'';};
        $title=$clean($job['jobTitle']??null,300);$company=$clean($job['companyName']??null,200);$geo=$clean($job['jobGeo']??null,200);
        if($title===''||$company===''||$geo==='')continue;
        $jobs[$id]=['id'=>'jobicy:'.$id,'title'=>$title,'employer'=>$company,'location'=>$geo,'apply_url'=>$url,'source'=>'Jobicy','checked_at'=>gmdate('c'),'notice'=>'Remote eligibility varies. View the original Jobicy listing.'];
    }
    return array_values($jobs);
}
function refreshPublicJobs(Store $store,?callable $fetch=null,?int $now=null):array {
    $now=$now??time();
    $reserved=$store->transaction(function(&$s)use($now){if(($s['public_jobs_attempt']??0)>$now-21600)return false;$s['public_jobs_attempt']=$now;return true;});
    if(!$reserved)return ['skipped'=>true];
    try{
        $fetch=$fetch??fn()=>requestJson('https://jobicy.com/api/v2/remote-jobs?count=100','GET',['Accept: application/json','User-Agent: ApplyPilot/1.0 (+https://mybillflow.online)'],null,2097152);
        $jobs=normalizePublicJobs($fetch());
        $store->transaction(function(&$s)use($jobs,$now){$s['jobs']=$jobs;$s['jobs_at']=$now;$s['public_jobs_health']=['ok'=>true,'checked_at'=>$now,'count'=>count($jobs)];});
        return ['ok'=>true,'source'=>'Jobicy','count'=>count($jobs)];
    }catch(\Throwable $e){
        $store->transaction(function(&$s)use($now){$s['public_jobs_health']=['ok'=>false,'checked_at'=>$now];});
        return ['ok'=>false,'source'=>'Jobicy','notice'=>'Refresh failed; previous freshness was not renewed.'];
    }
}
