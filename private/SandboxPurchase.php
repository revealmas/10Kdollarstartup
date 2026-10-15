<?php
declare(strict_types=1);
namespace ApplyPilot;
require_once __DIR__.'/SandboxCheck.php';
// Owner-only sandbox QA. Public launch settings are never changed. Synthetic
// orders use the normal encrypted store, customer authorization and webhook.
final class SandboxPurchase {
    private Store $store; private array $config; private $gateway;
    public function __construct(Store $store,array $config,?callable $gateway=null){
        $this->store=$store;$this->config=$config;$this->gateway=$gateway??stripe($config);
    }
    public function assertReady():void{(new SandboxCheck($this->config,$this->gateway))->assertReady();}
    private function settings():array{
        $c=$this->config;
        foreach(['ai_enabled','privacy_confirmed','checkout_enabled','licence_confirmed','tax_confirmed','merchant_confirmed','production_tests_passed']as$key)$c[$key]=true;
        $c['support_email']='test@example.invalid';$c['merchant_descriptor']='ApplyPilot sandbox QA';$c['country_tax_rates']=['AE'=>''];return $c;
    }
    public function prepare(string $owner):array{
        $this->assertReady();$memory=new SandboxMemoryStore();$c=$this->settings();
        $fixture=syntheticCvFixture();$resume=$fixture['resume'];
        $report=['summary'=>'Synthetic sandbox review: this fixture evidences coordination and reporting. It is not a real applicant or job posting.',
            'strengths'=>[['quote'=>'Coordinated supplier communication and weekly delivery schedules.','reason'=>'This is relevant to the synthetic coordination requirement.']],
            'gaps'=>['This synthetic fixture does not evidence SAP experience.'],
            'interview_questions'=>['How did you maintain delivery schedules and project records?'],
            'tips'=>['Synthetic QA only. Do not submit this fixture to an employer.']];
        $report['cv']=$fixture['cv'];$report['application_pack']=$fixture['application_pack'];
        $service=new Service($memory,$c,fn()=> $report,$this->gateway);
        $result=$service->prepare(['resume'=>$resume,'job_description'=>$fixture['job_description'],'consent'=>true,'adult'=>true],$owner);
        $id=$result['order_id'];$order=$memory->transaction(fn(&$s)=>$s['orders'][$id]);$order['source']='sandbox-qa';
        $this->store->transaction(function(&$s)use($id,$order){$s['orders'][$id]=$order;$day=gmdate('Y-m-d');$s['counts'][$day]['sandbox_preview_created']=($s['counts'][$day]['sandbox_preview_created']??0)+1;});
        // The fixture's mock AI allowance stays in memory. No provider call occurs.
        return $result+['notice'=>'Synthetic sandbox test only. No real applicant, job, AI call or live charge. Review the preview before opening test Checkout.'];
    }
    public function checkout(string $id,string $owner,bool $reviewed):array{
        $this->assertReady();if(!$reviewed)throw new Problem(422,'Review the synthetic preview and confirm this is a sandbox test.');
        $this->store->transaction(function(&$s)use($id,$owner){
            $o=$s['orders'][$id]??null;
            if(!$o||!hash_equals($o['owner'],hash('sha256',$owner))||($o['source']??'')!=='sandbox-qa')throw new Problem(404,'Synthetic test order not found.');
        });
        $service=new Service($this->store,$this->settings(),fn()=>throw new Problem(500,'No AI call allowed.'),$this->gateway);
        $service->acknowledgePreview($id,$owner);
        return $service->checkout(['order_id'=>$id,'billing_country'=>'AE','accept_terms'=>true,'accept_delivery'=>true],$owner);
    }
}

// Called only after an authenticated owner request has closed its owner session.
// This ordinary customer cookie makes the return/download use normal authorization.
function sandboxCustomerOwner(string $dir,array $config):string{
    session_name('applypilot_session');
    // PHP has already consumed the owner cookie in this request. Explicitly
    // select the customer cookie when switching session names after closing it.
    $customerId=$_COOKIE['applypilot_session']??'';
    session_id(is_string($customerId)&&preg_match('/^[A-Za-z0-9,-]{16,128}$/D',$customerId)?$customerId:'');
    session_save_path(privateSessionDirectory($dir,'customer'));ini_set('session.gc_maxlifetime','86400');
    session_set_cookie_params(['lifetime'=>86400,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);session_start();
    if(!isset($_SESSION['owner'])){session_regenerate_id(true);$_SESSION['owner']=bin2hex(random_bytes(32));$_SESSION['csrf']=bin2hex(random_bytes(32));}
    $owner=$_SESSION['owner'];session_write_close();return $owner;
}
