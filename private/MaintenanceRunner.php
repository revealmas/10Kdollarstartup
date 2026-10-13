<?php
declare(strict_types=1);
namespace ApplyPilot;
require_once __DIR__.'/Core.php';
function runMaintenance(Store $store,callable $cleanup,string $privateDir):array {
    try {
        $purged=$cleanup();
        if(!is_int($purged['packages_purged']??null)||$purged['packages_purged']<0)
            throw new Problem(503,'Invalid cleanup result.');
        $sessions=0;
        foreach(['customer'=>86400,'owner'=>1800]as$kind=>$ttl)
            foreach(glob($privateDir.'/data/sessions/'.$kind.'/sess_*')?:[]as$file)
                if(is_file($file)&&!is_link($file)&&filemtime($file)<time()-$ttl){
                    if(!unlink($file))throw new Problem(503,'Session cleanup failed.');
                    $sessions++;
                }
        $result=['checked_at'=>gmdate('c'),'ok'=>true,'packages_purged'=>$purged['packages_purged'],'sessions_purged'=>$sessions];
        $store->transaction(function(&$s)use($result){$s['maintenance']=$result;});
        return $result;
    }catch(\Throwable $e){
        $result=['checked_at'=>gmdate('c'),'ok'=>false,'packages_purged'=>null,'sessions_purged'=>null];
        try{$store->transaction(function(&$s)use($result){$s['maintenance']=$result;});}catch(\Throwable $ignored){}
        throw new Problem(503,'Scheduled cleanup failed. Review private server operation; no sensitive details are exposed.');
    }
}
