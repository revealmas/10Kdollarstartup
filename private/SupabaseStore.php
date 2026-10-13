<?php
declare(strict_types=1);
namespace ApplyPilot;
// Server-side service key only. Encrypted payload, optimistic version check.
// No publishable/anon key grants access to the state or compare-and-swap RPC.
final class SupabaseStore implements Store {
    private array $config; private string $key;
    public function __construct(array $config){
        $this->config=$config;$key=base64_decode($config['storage_key'],true);
        if(!is_string($key)||strlen($key)!==32||!function_exists('openssl_encrypt')
            ||($config['supabase_service_key']??'')===''
            ||!preg_match('~^https://[a-z]{20}\.supabase\.co$~',$config['supabase_url']??''))
            throw new Problem(503,'Private database configuration is incomplete.');
        $this->key=$key;
    }
    private function request(string $path,string $method,?array $body=null):array{
        return requestJson($this->config['supabase_url'].'/rest/v1/'.$path,$method,
            ['apikey: '.$this->config['supabase_service_key'],
             'Authorization: Bearer '.$this->config['supabase_service_key'],'Content-Type: application/json'],
            $body===null?null:json_encode($body,JSON_THROW_ON_ERROR),10485760);
    }
    public function transaction(callable $fn){
        for($attempt=0;$attempt<6;$attempt++){
            $rows=$this->request('applypilot_state?id=eq.1&select=version,sealed_payload','GET');
            $row=$rows[0]??null;if(!$row)throw new Problem(503,'Private database state is unavailable.');
            $state=['orders'=>[],'counts'=>[],'limits'=>[],'jobs'=>[],'jobs_at'=>0,'ai'=>[]];
            if($row['sealed_payload']!==null){
                $blob=base64_decode($row['sealed_payload'],true);
                if($blob===false||strlen($blob)<28)throw new Problem(503,'Private database state is invalid.');
                $plain=openssl_decrypt(substr($blob,28),'aes-256-gcm',$this->key,OPENSSL_RAW_DATA,substr($blob,0,12),substr($blob,12,16),'applypilot-v1');
                if($plain===false)throw new Problem(503,'Private database state could not be decrypted.');
                $state=json_decode($plain,true,512,JSON_THROW_ON_ERROR);
            }
            $result=$fn($state);$iv=random_bytes(12);$tag='';
            $cipher=openssl_encrypt(json_encode($state,JSON_THROW_ON_ERROR),'aes-256-gcm',$this->key,OPENSSL_RAW_DATA,$iv,$tag,'applypilot-v1');
            if($cipher===false)throw new Problem(503,'Private storage encryption failed.');
            // requestJson accepts arrays; the RPC wraps its boolean in JSON object.
            $commit=$this->request('rpc/applypilot_compare_swap_json','POST',[
                'expected_version'=>(int)$row['version'],'new_payload'=>base64_encode($iv.$tag.$cipher)]);
            if(($commit['committed']??false)===true)return $result;
        }
        throw new Problem(503,'The service is busy. Please retry.');
    }
}
