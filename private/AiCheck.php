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
    private array $config;private $provider;
    public function __construct(array $config,?callable $provider=null){$this->config=$config;$this->provider=$provider??deepseek($config);}
    public function assertReady():void {
        if(empty($this->config['deepseek_key'])||empty($this->config['deepseek_model']))
            throw new Problem(503,'Save the DeepSeek key and account-available model first.');
    }
    public function run():array {
        $this->assertReady();$settings=$this->config;
        // Synthetic-only overrides are never saved to the customer configuration.
        $settings['ai_enabled']=true;$settings['privacy_confirmed']=true;$settings['daily_ai_limit']=1;
        $memory=new AiCheckMemoryStore();$fixture=syntheticCvFixture();
        $service=new Service($memory,$settings,$this->provider,
            fn()=>throw new Problem(500,'AI connection checks cannot call payments.'));
        try {
            $preview=$service->prepare([
                'resume'=>$fixture['resume'],
                'job_description'=>$fixture['job_description'],
                'consent'=>true,'adult'=>true],bin2hex(random_bytes(32)));
            return ['ok'=>true,'model'=>$this->config['deepseek_model'],
                'checks'=>['Actual provider response','Structured JSON validated','Every strength quote matched the synthetic CV','CV employers, dates, skills and numeric claims checked','Useful preview and structured CV prepared'],
                'preview'=>$preview['preview'],'synthetic_cv'=>$memory->transaction(fn(&$s)=>$s['orders'][$preview['order_id']]['package']['cv']),
                'notice'=>'Synthetic CV only. One bounded AI call; failed calls also consume the allowance. No customer package, payment, personal data or saved launch flag was created. This checks connection and schema, not general output quality or privacy clearance.'];
        }catch(\Throwable $e){
            throw new Problem(502,'AI connection check failed. Check model, credits and provider settings. No provider response, key or headers are exposed.');
        }
    }
}
