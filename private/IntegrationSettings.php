<?php
declare(strict_types=1);
namespace ApplyPilot;
require_once __DIR__.'/Core.php';
final class IntegrationSettings {
    private Store $store; private string $defaultPaymentConfiguration;
    private const FIELDS=['deepseek_key','deepseek_model','stripe_key','stripe_webhook_secret','stripe_payment_method_configuration'];
    public function __construct(string $dir,array $config) {
        $this->defaultPaymentConfiguration=$config['stripe_payment_method_configuration']??'';
        $this->store=new EncryptedFileStore($dir.'/data/integrations.enc',$config['storage_key']);
    }
    public function read(): array {
        return $this->store->transaction(fn(&$s)=>array_intersect_key($s['settings']??[],array_flip(self::FIELDS)));
    }
    public function status(): array {
        $s=$this->read();return ['deepseek_configured'=>!empty($s['deepseek_key']),
            'deepseek_model'=>$s['deepseek_model']??'', 'stripe_test_configured'=>!empty($s['stripe_key']),
            'webhook_configured'=>!empty($s['stripe_webhook_secret']),
            'stripe_payment_method_configuration'=>$s['stripe_payment_method_configuration']??$this->defaultPaymentConfiguration];
    }
    public function save(array $input): array {
        $values=[];
        foreach($input as $name=>$value){
            if(!in_array($name,self::FIELDS,true))throw new Problem(422,'Unsupported setting.');
            if(!is_string($value)||strlen($value)>300)throw new Problem(422,'Invalid setting.');
            $value=trim($value);if($value==='')continue;
            $pattern=match($name){
                'deepseek_key'=>'/^[A-Za-z0-9_-]{20,240}$/D',
                'deepseek_model'=>'/^[A-Za-z0-9._-]{1,100}$/D',
                'stripe_key'=>'/^(rk|sk)_test_[A-Za-z0-9]{10,240}$/D',
                'stripe_webhook_secret'=>'/^whsec_[A-Za-z0-9]{10,240}$/D',
                'stripe_payment_method_configuration'=>'/^pmc_[A-Za-z0-9]{1,100}$/D',
            };
            if(!preg_match($pattern,$value))throw new Problem(422,'Invalid integration setting; only Stripe test credentials are accepted.');
            $values[$name]=$value;
        }
        $this->store->transaction(function(&$s)use($values){$s['settings']=array_merge($s['settings']??[],$values);});
        return $this->status();
    }
}
