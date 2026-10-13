<?php
declare(strict_types=1);
namespace ApplyPilot;
require_once __DIR__.'/Transport.php';
require_once __DIR__.'/SyntheticCv.php';
// Synthetic diagnostic state never enters the customer store or changes launch flags.
final class SandboxMemoryStore implements Store {
    private array $data=['orders'=>[],'counts'=>[],'limits'=>[],'jobs'=>[],'jobs_at'=>0,'ai'=>[]];
    public function transaction(callable $fn){$copy=$this->data;$out=$fn($copy);$this->data=$copy;return $out;}
}
final class SandboxCheck {
    private array $config; private $gateway;
    public function __construct(array $config,?callable $gateway=null){$this->config=$config;$this->gateway=$gateway??stripe($config);}
    public function assertReady():void {
        if(($this->config['stripe_live']??false)!==false
            ||!preg_match('/^(rk|sk)_test_[A-Za-z0-9]{10,240}$/D',$this->config['stripe_key']??'')
            ||!str_starts_with($this->config['stripe_webhook_secret']??'','whsec_')
            ||!preg_match('/^pmc_[A-Za-z0-9]+$/D',$this->config['stripe_payment_method_configuration']??''))
            throw new Problem(503,'Configure sandbox credentials, webhook and payment configuration first. Live mode cannot run this check.');
    }
    public function run():array {
        $this->assertReady();$phase='synthetic preparation';$gateway=$this->gateway;$sessionId=null;
        $settings=$this->config;
        // These test-only values exist in memory; no licence/privacy launch assertion is saved.
        foreach(['ai_enabled','privacy_confirmed','checkout_enabled','licence_confirmed','tax_confirmed','merchant_confirmed','production_tests_passed']as$key)$settings[$key]=true;
        $settings['support_email']='test@example.invalid';$settings['merchant_descriptor']='ApplyPilot sandbox diagnostic';$settings['country_tax_rates']=['AE'=>''];
        $fixture=syntheticCvFixture();$resume=$fixture['resume'];
        $report=['summary'=>'Synthetic diagnostic review only. No real applicant or AI provider is involved.',
            'strengths'=>[['quote'=>'Coordinated supplier communication and weekly delivery schedules.','reason'=>'Synthetic evidence for a deterministic diagnostic.']],
            'gaps'=>['This is a synthetic test, not applicant guidance.'],
            'interview_questions'=>['Synthetic interview question for test data only.'],
            'tips'=>['Do not use this fixture as a job application.']];
        $report['cv']=$fixture['cv'];$report['application_pack']=$fixture['application_pack'];
        $service=new Service(new SandboxMemoryStore(),$settings,fn($r,$j)=>$report,
            function($method,$path,$params,$idempotency)use($gateway,&$phase,&$sessionId){
                $phase=$method==='POST'?'sandbox Checkout creation':'sandbox payment configuration';
                $result=$gateway($method,$path,$params,$idempotency);
                if($method==='POST'&&$path==='/v1/checkout/sessions')$sessionId=$result['id']??null;
                return $result;
            });
        try {
            $owner=bin2hex(random_bytes(32));
            $preview=$service->prepare(['resume'=>$resume,'job_description'=>$fixture['job_description'],'consent'=>true,'adult'=>true],$owner);
            $service->acknowledgePreview($preview['order_id'],$owner);
            $service->checkout(['order_id'=>$preview['order_id'],'billing_country'=>'AE','accept_terms'=>true,'accept_delivery'=>true],$owner);
            if(!is_string($sessionId)||!preg_match('/^cs_test_[A-Za-z0-9]+$/D',$sessionId))throw new Problem(502,'Unexpected sandbox session.');
            $phase='sandbox Checkout retrieval';$path='/v1/checkout/sessions/'.$sessionId;
            $session=$gateway('GET',$path,[],null);
            if(($session['livemode']??null)!==false||($session['amount_total']??null)!==900
                ||($session['currency']??'')!=='usd'||($session['mode']??'')!=='payment'
                ||($session['status']??'')!=='open'||($session['payment_status']??'')!=='unpaid'
                ||($session['client_reference_id']??'')!==$preview['order_id'])throw new Problem(502,'Unexpected sandbox session.');
            $phase='sandbox Checkout expiration';
            $expired=$gateway('POST',$path.'/expire',[],'applypilot-diagnostic-expire-'.$preview['order_id']);
            if(($expired['status']??'')!=='expired'||($expired['livemode']??null)!==false)throw new Problem(502,'Expiration not confirmed.');
            return ['ok'=>true,'mode'=>'test','price'=>['currency'=>'USD','total'=>9],
                'checks'=>['Synthetic preview','Reviewed payment configuration','Server-created Checkout','Retrieved unpaid USD 9 session','Confirmed session expiration'],
                'notice'=>'No payment was taken. No customer order, real CV, AI call or launch flag was saved. Paid fulfillment/refund testing remains separate.'];
        }catch(\Throwable $e){
            // A failed validation must not leave a usable test Checkout where possible.
            if(is_string($sessionId)&&preg_match('/^cs_test_[A-Za-z0-9]+$/D',$sessionId))
                try{$gateway('POST','/v1/checkout/sessions/'.$sessionId.'/expire',[],'applypilot-diagnostic-cleanup-'.$sessionId);}catch(\Throwable $ignored){}
            throw new Problem(502,'Sandbox check failed at '.$phase.'. Review sandbox account settings and key permissions. No provider details are exposed.');
        }
    }
}
