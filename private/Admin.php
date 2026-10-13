<?php
declare(strict_types=1);
namespace ApplyPilot;
final class Admin {
    private Store $store; private array $config;
    public function __construct(Store $store,array $config){$this->store=$store;$this->config=$config;}
    public function login(string $password,string $ip): bool {
        $key='admin:'.gmdate('Y-m-d-H').':'.hash_hmac('sha256',$ip,$this->config['storage_key']);
        $allowed=$this->store->transaction(function(&$s)use($key){$s['admin_attempts'][$key]=($s['admin_attempts'][$key]??0)+1;return $s['admin_attempts'][$key]<=5;});
        if(!$allowed)throw new Problem(429,'Too many sign-in attempts. Try later.');
        $hash=$this->config['admin_password_hash']??'';
        if($hash==='')throw new Problem(503,'Owner access has not been configured.');
        return strlen($password)<=200 && password_verify($password,$hash);
    }
    public function reserveSandboxCheck():void {
        $hour=intdiv(time(),3600);
        $allowed=$this->store->transaction(function(&$s)use($hour){
            foreach(array_keys($s['sandbox_checks']??[])as$old)if((int)$old<$hour-24)unset($s['sandbox_checks'][$old]);
            $s['sandbox_checks'][$hour]=($s['sandbox_checks'][$hour]??0)+1;return $s['sandbox_checks'][$hour]<=3;
        });
        if(!$allowed)throw new Problem(429,'Sandbox checks are limited to three per hour.');
    }
    public function recordSandboxCheck(bool $passed):void {
        $this->store->transaction(function(&$s)use($passed){$day=gmdate('Y-m-d');$event=$passed?'sandbox_check_passed':'sandbox_check_failed';$s['counts'][$day][$event]=($s['counts'][$day][$event]??0)+1;});
    }
    public function reserveAiCheck():void {
        $hour=intdiv(time(),3600);$day=gmdate('Y-m-d');
        $allowed=$this->store->transaction(function(&$s)use($hour,$day){
            foreach(array_keys($s['ai_checks']??[])as$old)if((int)$old<=$hour-24)unset($s['ai_checks'][$old]);
            $dayChecks=array_sum($s['ai_checks']??[]);
            $settings=$this->config;$settings['daily_ai_limit']=min(20,max(0,(int)($settings['daily_ai_limit']??0)));
            if(($s['ai_checks'][$hour]??0)>=1||$dayChecks>=3||!reserveAiAllowance($s,$settings,$day))return false;
            $s['ai_checks'][$hour]=($s['ai_checks'][$hour]??0)+1;
            return true;
        });
        if(!$allowed)throw new Problem(429,'AI checks are limited to one per hour, three per 24 hours and the shared daily/cumulative AI allowance. Failed calls count.');
    }
    public function recordAiCheck(bool $passed):void {
        $this->store->transaction(function(&$s)use($passed){$day=gmdate('Y-m-d');$event=$passed?'ai_check_passed':'ai_check_failed';$s['counts'][$day][$event]=($s['counts'][$day][$event]??0)+1;});
    }
    public function snapshot(): array {
        return $this->store->transaction(function(&$s){
            $orders=[];$paid=0;$revoked=0;$active=0;
            foreach($s['orders'] as $o){
                if($o['paid'])$paid++;if($o['paid']&&$o['revoked'])$revoked++;
                if($o['package']&&$o['expires']>time())$active++;
                $orders[]=['id'=>$o['id'],'state'=>$o['state'],'created'=>gmdate('c',$o['created']),
                    'expires'=>gmdate('c',$o['expires']),'paid'=>$o['paid'],'revoked'=>$o['revoked'],
                    'stripe_session'=>$o['checkout']['id']??null,'payment_intent'=>$o['payment_intent'],
                    'package_present'=>(bool)$o['package']];
            }
            usort($orders,fn($a,$b)=>strcmp($b['created'],$a['created']));
            $gates=[];foreach(['ai_enabled','checkout_enabled','licence_confirmed','privacy_confirmed','tax_confirmed','merchant_confirmed','production_tests_passed']as$key)$gates[$key]=(bool)($this->config[$key]??false);
            return ['totals'=>['paid_orders'=>$paid,'revoked_orders'=>$revoked,'active_packages'=>$active,'gross_verified_usd'=>$paid*9],
                'counts'=>$s['counts'],'orders'=>array_slice($orders,0,100),'ai_calls'=>$s['ai'],
                'ai_allowance'=>['total_used'=>$s['ai_total']??array_sum($s['ai']??[]),'total_limit'=>max(0,(int)($this->config['total_ai_limit']??30))],
                'jobs'=>['count'=>count($s['jobs']),'checked_at'=>$s['jobs_at']?gmdate('c',$s['jobs_at']):null],
                'payments_environment'=>($this->config['stripe_live']??false)?'live':'test',
                'gates'=>$gates,'storage_backend'=>$this->config['storage_backend']??'file',
                'maintenance'=>isset($s['maintenance'])?array_intersect_key($s['maintenance'],array_flip(['checked_at','ok','packages_purged','sessions_purged'])):null,
                'notice'=>'Operational counts, not a financial ledger. Reconcile fees, refunds and taxes in Stripe. CV text, provider keys and browser owner secrets are excluded.'];
        });
    }
}
