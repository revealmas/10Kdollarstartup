<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/Transport.php';
[$config,$store,$service]=\ApplyPilot\boot(__DIR__);
$jobs=[]; $failures=0; $approved=0;
foreach($config['feeds'] as $feed) {
    if(($feed['permission_reviewed'] ?? false)!==true) continue;
    $board=$feed['board'] ?? '';
    if(!is_string($board) || !preg_match('/^[a-zA-Z0-9_-]{1,100}$/',$board)) { $failures++; continue; }
    $kind=$feed['provider'] ?? '';
    if(!in_array($kind,['greenhouse','lever','lever-eu'],true)) { $failures++; continue; }
    if(empty($feed['employer']) || empty($feed['terms_url']) || empty($feed['reviewed_at'])) { $failures++; continue; }
    $approved++;
    $url=$kind==='greenhouse'?'https://boards-api.greenhouse.io/v1/boards/'.$board.'/jobs?content=true':
        'https://api'.($kind==='lever-eu'?'.eu':'').'.lever.co/v0/postings/'.$board.'?mode=json';
    try {
        $data=\ApplyPilot\requestJson($url,'GET',['Accept: application/json','User-Agent: ApplyPilot/1.0']);
        $items=$kind==='greenhouse'?($data['jobs'] ?? []):$data;
        foreach($items as $j) {
            $href=$kind==='greenhouse'?($j['absolute_url'] ?? ''):($j['hostedUrl'] ?? '');
            $host=parse_url($href,PHP_URL_HOST);
            if(parse_url($href,PHP_URL_SCHEME)!=='https' || !in_array($host,['boards.greenhouse.io','job-boards.greenhouse.io','jobs.lever.co','jobs.eu.lever.co'],true)) continue;
            $title=$kind==='greenhouse'?($j['title'] ?? ''):($j['text'] ?? '');
            $location=$kind==='greenhouse'?($j['location']['name'] ?? ''):($j['categories']['location'] ?? '');
            if(!is_string($title) || !is_string($location)) continue;
            $jobs[]=['id'=>$kind.':'.$board.':'.(string)$j['id'],'title'=>strip_tags($title),
                'location'=>strip_tags($location),'employer'=>$feed['employer'],'apply_url'=>$href,
                'source'=>$kind,'checked_at'=>gmdate('c'),'notice'=>'Apply directly with the employer. Availability may change.'];
        }
    } catch(\Throwable $e) { $failures++; }
}
// Fail closed on partial refresh; don't silently label stale listings as fresh.
if($failures>0 || $approved===0) { fwrite(STDERR,'Job refresh incomplete; freshness was not renewed.'.PHP_EOL); exit(1); }
$store->transaction(function(&$s)use($jobs){$s['jobs']=array_slice($jobs,0,2000);$s['jobs_at']=time();});
echo json_encode(['jobs'=>count($jobs),'feeds'=>$approved]).PHP_EOL;
