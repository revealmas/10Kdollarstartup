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
                'jobs'=>['count'=>count($s['jobs']),'checked_at'=>$s['jobs_at']?gmdate('c',$s['jobs_at']):null],
                'gates'=>$gates,'storage_backend'=>$this->config['storage_backend']??'file',
                'notice'=>'Operational counts, not a financial ledger. Reconcile fees, refunds and taxes in Stripe. CV text, provider keys and browser owner secrets are excluded.'];
        });
    }
}
