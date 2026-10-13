<?php
declare(strict_types=1);
namespace ApplyPilot;
require_once __DIR__.'/Transport.php';
require_once __DIR__.'/SyntheticCv.php';
final class AiCheckMemoryStore implements Store {
    private array $data=['orders'=>[],'counts'=>[],'limits'=>[],'jobs'=>[],'jobs_at'=>0,'ai'=>[]];
    public function transaction(callable $fn){$copy=$this->data;$out=$fn($copy);$this->data=$copy;return $out;}
}
final class AiCheck {
    private array $config;private $provider;private ?Store $diagnostics;
    public function __construct(array $config,?callable $provider=null,?Store $diagnostics=null){$this->config=$config;$this->provider=$provider??deepseek($config);$this->diagnostics=$diagnostics;}
    public function assertReady():void {
        if(empty($this->config['deepseek_key'])||empty($this->config['deepseek_model']))
            throw new Problem(503,'Save the DeepSeek key and account-available model first.');
    }
    public function run(bool $revalidate=false):array {
        $this->assertReady();$settings=$this->config;
        // Synthetic-only overrides are never saved to the customer configuration.
        $settings['ai_enabled']=true;$settings['privacy_confirmed']=true;$settings['daily_ai_limit']=1;
        $memory=new AiCheckMemoryStore();$fixture=syntheticCvFixture();
        $fixtureHash=hash('sha256',json_encode([$fixture['resume'],$fixture['job_description']]));
        if($revalidate){
            $cached=$this->diagnostics?->transaction(fn(&$s)=>$s['synthetic_ai_report']??null);
            if(!is_array($cached)||($cached['expires_at']??0)<time()||($cached['fixture_hash']??'')!==$fixtureHash)
                throw new Problem(409,'No recent synthetic provider draft is available. Run one connection check first.');
            $provider=fn()=> $cached['report'];
        }else{
            $provider=function($resume,$job)use($fixtureHash){
                $report=($this->provider)($resume,$job);
                $safe=array_intersect_key($report,array_flip(['summary','strengths','gaps','interview_questions','tips','cv']));
                if($this->diagnostics&&strlen(json_encode($safe))<=65536)$this->diagnostics->transaction(function(&$s)use($safe,$fixtureHash){$s['synthetic_ai_report']=['report'=>$safe,'fixture_hash'=>$fixtureHash,'expires_at'=>time()+86400];});
                return $report;
            };
        }
        $service=new Service($memory,$settings,$provider,
            fn()=>throw new Problem(500,'AI connection checks cannot call payments.'));
        try {
            $preview=$service->prepare([
                'resume'=>$fixture['resume'],
                'job_description'=>$fixture['job_description'],
                'consent'=>true,'adult'=>true],bin2hex(random_bytes(32)));
            return ['ok'=>true,'model'=>$this->config['deepseek_model'],
                'checks'=>[$revalidate?'Saved actual provider response revalidated without a new API call':'Actual provider response','Structured JSON validated','Every strength quote matched the synthetic CV','CV employers, dates, skills and numeric claims checked','Useful preview and structured CV prepared'],
                'preview'=>$preview['preview'],'synthetic_cv'=>$memory->transaction(fn(&$s)=>$s['orders'][$preview['order_id']]['package']['cv']),
                'revalidated'=>$revalidate,'notice'=>'Fixed fictional CV only. The synthetic provider draft is encrypted for up to 24 hours to recheck validation without spending credits. No real applicant data, customer order, payment or launch flag was created. New provider calls, including failures, consume the allowance. This checks connection and schema, not general output quality or privacy clearance.'];
        }catch(\Throwable $e){
            $known=['Please check the text length and format.','The provider is unavailable. Please retry later.','AI preparation was incomplete. No payment is required.','AI preparation was invalid. No payment is required.','The analysis could not be validated. No payment is required.','The analysis cited experience not in your CV. No payment is required.','The CV draft has invalid text or structure. No payment is required.','The CV draft cited facts absent from your supplied CV. No payment is required.','A CV employer, qualification or date could not be verified. No payment is required.','CV evidence is incomplete. No payment is required.','The CV draft added a number not supported by its evidence. No payment is required.','The CV headline must use a role or phrase in your supplied CV.','The CV summary could not be validated.','The CV structure could not be validated.','Invalid CV skill.','A CV skill was not present in its source evidence.','CV experience could not be validated.','Invalid CV bullet.','A CV bullet was attributed to a different source role. No payment is required.','Invalid CV education.'];
            $reason=$e instanceof Problem&&in_array($e->getMessage(),$known,true)?$e->getMessage():'Check model, credits and provider settings.';
            throw new Problem(502,'Synthetic AI check failed: '.$reason.' No provider response, key or headers are exposed.');
        }
    }
}
