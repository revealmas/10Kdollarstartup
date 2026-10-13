<?php
declare(strict_types=1);
namespace ApplyPilot;

final class Problem extends \RuntimeException {
    public int $status;
    public function __construct(int $status, string $message) {
        parent::__construct($message); $this->status = $status;
    }
}
interface Store { public function transaction(callable $fn); }

// One encrypted state file, one advisory lock: sufficient for a small pilot.
// All writers and cleanup use this same transaction. No public file storage.
final class EncryptedFileStore implements Store {
    private string $file; private string $key;
    public function __construct(string $file, string $encodedKey) {
        $key = base64_decode($encodedKey, true);
        if (!is_string($key) || strlen($key) !== 32 || !function_exists('openssl_encrypt'))
            throw new Problem(503, 'Private storage is not configured.');
        $this->file = $file; $this->key = $key;
        if (!is_dir(dirname($file)) && !mkdir(dirname($file), 0700, true))
            throw new Problem(503, 'Private storage is unavailable.');
    }
    public function transaction(callable $fn) {
        $lock = fopen($this->file . '.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX)) throw new Problem(503, 'Please retry.');
        chmod($this->file . '.lock', 0600);
        try {
            $state = ['orders'=>[], 'counts'=>[], 'limits'=>[], 'jobs'=>[], 'jobs_at'=>0, 'ai'=>[]];
            if (is_file($this->file)) {
                $blob = file_get_contents($this->file);
                if ($blob === false || strlen($blob) < 28) throw new Problem(503, 'Storage error.');
                $plain = openssl_decrypt(substr($blob,28), 'aes-256-gcm', $this->key,
                    OPENSSL_RAW_DATA, substr($blob,0,12), substr($blob,12,16), 'applypilot-v1');
                if ($plain === false) throw new Problem(503, 'Storage error.');
                $state = json_decode($plain, true, 512, JSON_THROW_ON_ERROR);
            }
            $result = $fn($state);
            $iv = random_bytes(12); $tag = '';
            $cipher = openssl_encrypt(json_encode($state, JSON_THROW_ON_ERROR), 'aes-256-gcm',
                $this->key, OPENSSL_RAW_DATA, $iv, $tag, 'applypilot-v1');
            if ($cipher === false) throw new Problem(503, 'Storage error.');
            $tmp = $this->file . '.' . bin2hex(random_bytes(8)) . '.tmp';
            if (file_put_contents($tmp, $iv . $tag . $cipher) === false) throw new Problem(503,'Storage error.');
            chmod($tmp,0600);
            if (!rename($tmp,$this->file)) { @unlink($tmp); throw new Problem(503,'Storage error.'); }
            return $result;
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }
}

function verifySignature(string $raw, string $header, string $secret, int $now): array {
    if ($secret === '' || strlen($raw) > 262144) throw new Problem(400, 'Invalid webhook.');
    $t = null; $signatures = [];
    foreach (explode(',', $header) as $part) {
        $pair = explode('=', trim($part), 2);
        if (count($pair) !== 2) continue;
        if ($pair[0] === 't') {
            if ($t !== null || !ctype_digit($pair[1])) throw new Problem(400,'Invalid webhook.');
            $t = (int)$pair[1];
        }
        if ($pair[0] === 'v1') $signatures[] = $pair[1];
    }
    if ($t === null || abs($now - $t) > 300) throw new Problem(400,'Invalid webhook.');
    $expected = hash_hmac('sha256', $t . '.' . $raw, $secret);
    $valid = false;
    foreach ($signatures as $sig) if (hash_equals($expected,$sig)) $valid = true;
    if (!$valid) throw new Problem(400,'Invalid webhook.');
    try { $event = json_decode($raw,true,512,JSON_THROW_ON_ERROR); }
    catch (\Throwable $e) { throw new Problem(400,'Invalid webhook.'); }
    if (!is_array($event) || !is_string($event['id'] ?? null) || !is_string($event['type'] ?? null))
        throw new Problem(400,'Invalid webhook.');
    return $event;
}

function boundedText($v, int $min, int $max): string {
    if (!is_string($v) || strlen(trim($v)) < $min || strlen($v) > $max || preg_match('//u',$v) !== 1)
        throw new Problem(422,'Please check the text length and format.');
    return trim($v);
}
function redact(string $text): string {
    $text=preg_replace('/^(?:date of birth|dob|passport(?: number)?|emirates id|nationality|citizenship|visa status|marital status|religion|gender)\s*:.*$/mi','[sensitive detail removed]',$text);
    $text=preg_replace('/(?:sk|rk)_(?:test|live)_[A-Za-z0-9]+|sk-[A-Za-z0-9_-]{20,}/','[credential removed]',$text);
    $text = preg_replace('/[\w.+-]+@[\w.-]+\.[a-z]{2,}/i','[email removed]',$text);
    $text = preg_replace('~https?://\S+~i','[link removed]',$text);
    return preg_replace_callback('/(?<!\w)\+?\d[\d ().-]{7,}\d(?!\w)/',fn($m)=>strlen(preg_replace('/\D/','',$m[0]))<9?$m[0]:'[number removed]',$text);
}
require_once __DIR__.'/Cv.php';
function validateReport(array $report, string $resume): array {
    $out = ['summary'=>boundedText($report['summary'] ?? null,20,1000)];
    $strengths = $report['strengths'] ?? null;
    if (!is_array($strengths) || count($strengths) < 1 || count($strengths)>8)
        throw new Problem(502,'The analysis could not be validated. No payment is required.');
    $out['strengths']=[];
    foreach ($strengths as $item) {
        $quote=boundedText($item['quote'] ?? null,10,500);
        if (strpos($resume,$quote) === false)
            throw new Problem(502,'The analysis cited experience not in your CV. No payment is required.');
        $out['strengths'][]=['quote'=>$quote,'reason'=>boundedText($item['reason'] ?? null,10,700)];
    }
    foreach (['gaps','interview_questions','tips'] as $field) {
        if (!is_array($report[$field] ?? null) || count($report[$field])<1 || count($report[$field])>12)
            throw new Problem(502,'The analysis could not be validated. No payment is required.');
        $out[$field]=array_map(fn($s)=>boundedText($s,10,1000),$report[$field]);
    }
    if(isset($report['cv'])&&is_array($report['cv']))$out['cv']=validateCv($report['cv'],$resume);
    return $out; // Explicit allowlist strips provider output / hidden keys.
}

// Reserve atomically before the provider call. Failures consume the reservation.
// This counter persists when daily history is purged; it never resets at midnight.
function reserveAiAllowance(array &$state,array $config,string $day): bool {
    $used=$state['ai_total']??array_sum($state['ai']??[]);
    $totalLimit=max(0,(int)($config['total_ai_limit']??30));
    if($used>=$totalLimit||($state['ai'][$day]??0)>=max(0,(int)($config['daily_ai_limit']??0)))return false;
    $state['ai_total']=$used+1;
    $state['ai'][$day]=($state['ai'][$day]??0)+1;
    return true;
}

final class Service {
    private Store $store; private array $config; private $ai; private $stripe; private $clock;
    public function __construct(Store $store, array $config, callable $ai, callable $stripe, ?callable $clock=null) {
        $this->store=$store; $this->config=$config; $this->ai=$ai; $this->stripe=$stripe;
        $this->clock=$clock ?? fn()=>time();
    }
    private function now(): int { return ($this->clock)(); }
    private function count(array &$s,string $name): void {
        $day=gmdate('Y-m-d',$this->now());
        $s['counts'][$day][$name]=($s['counts'][$day][$name] ?? 0)+1;
    }
    public function rateLimit(string $ip): void {
        $key=hash_hmac('sha256',$ip,$this->config['storage_key']);
        $hour=intdiv($this->now(),3600);
        $ok=$this->store->transaction(function(&$s)use($key,$hour){
            $bucket=$hour . ':' . $key;
            $s['limits'][$bucket]=($s['limits'][$bucket] ?? 0)+1;
            return $s['limits'][$bucket]<=60;
        });
        if (!$ok) throw new Problem(429,'Too many requests. Please try later.');
    }
    private function authorize(array $s,string $id,string $owner): array {
        $o=$s['orders'][$id] ?? null;
        if (!$o || !hash_equals($o['owner'],hash('sha256',$owner)))
            throw new Problem(404,'Package not found.');
        if ($o['expires'] <= $this->now() || !$o['package']) throw new Problem(410,'Package has expired or was deleted.');
        return $o;
    }
    public function prepare(array $input,string $owner): array {
        if (!($this->config['ai_enabled'] ?? false) || !($this->config['privacy_confirmed'] ?? false))
            throw new Problem(503,'AI preparation is not enabled yet.');
        if (($input['consent'] ?? false) !== true || ($input['adult'] ?? false) !== true)
            throw new Problem(422,'Confirm consent and that you are at least 18.');
        $resume=redact(boundedText($input['resume'] ?? null,100,18000));
        $job=redact(boundedText($input['job_description'] ?? null,80,12000));
        $allowed=$this->store->transaction(function(&$s)use($owner){
            $day=gmdate('Y-m-d',$this->now());
            $ownerKey=$day.':'.hash('sha256',$owner);
            $s['owner_ai'][$ownerKey]=($s['owner_ai'][$ownerKey] ?? 0)+1;
            if($s['owner_ai'][$ownerKey]>3)return false;
            return reserveAiAllowance($s,$this->config,$day);
        });
        if (!$allowed) throw new Problem(429,'The preparation allowance has been reached. Free jobs remain available.');
        $report=validateReport(($this->ai)($resume,$job),$resume);
        if(!isset($report['cv']))throw new Problem(502,'The provider did not produce a validated CV draft. No payment is required.');
        $id=bin2hex(random_bytes(16));
        // Build before checkout; a failed AI call can never leave a paid empty order.
        $cv=$report['cv'];unset($report['cv']);
        $package=['original_cv'=>$resume, 'cv'=>$cv,'analysis'=>$report,
            'cover_letter_template'=>"Dear Hiring Team,\n\nI am interested in the role described in your posting. My CV includes:\n" .
                implode("\n",array_map(fn($x)=>'• '.$x['quote'],$report['strengths'])) .
                "\n\nI would welcome an opportunity to discuss how this experience relates to your requirements.\n\nKind regards,\n[Add your name locally]",
            'notice'=>'AI assistance can contain mistakes. Review every statement before applying. This is digital CV assistance, not recruitment or a promise of employment.'];
        $preview=['summary'=>$report['summary'],'strengths'=>array_slice($report['strengths'],0,2),
            'gaps'=>array_slice($report['gaps'],0,2),'label'=>'AI guidance, not an ATS score or verified hiring assessment','cv_example'=>isset($cv['experience'][0]['bullets'][0])?['original'=>$cv['experience'][0]['bullets'][0]['quote'],'draft'=>$cv['experience'][0]['bullets'][0]['text']]:null,'cv_readiness'=>['ready'=>!$cv['missing_details'],'missing_details'=>$cv['missing_details'],'experience_entries'=>count($cv['experience']),'education_entries'=>count($cv['education'])]];
        $this->store->transaction(function(&$s)use($id,$owner,$package,$preview){
            $s['orders'][$id]=['id'=>$id,'owner'=>hash('sha256',$owner),'created'=>$this->now(),
                'expires'=>$this->now()+86400,'package'=>$package,'preview'=>$preview,'preview_seen'=>false,
                'state'=>'ready','checkout'=>null,'checkout_started'=>null,'country'=>null,'paid'=>false,'revoked'=>false,
                'payment_intent'=>null,'events'=>[],'consent_version'=>'2026-10-13'];
            $this->count($s,'preview_created');
        });
        return ['order_id'=>$id,'preview'=>$preview,'price'=>['currency'=>'USD','total'=>9],
            'expires_in_hours'=>24,'paid_contents'=>['Job-specific CV draft','CV Word/PDF/text exports','Full analysis','Cover-letter template','Interview prompts']];
    }
    public function acknowledgePreview(string $id,string $owner): array {
        return $this->store->transaction(function(&$s)use($id,$owner){
            $this->authorize($s,$id,$owner); $s['orders'][$id]['preview_seen']=true;
            $this->count($s,'preview_acknowledged'); return ['ok'=>true];
        });
    }
    private function gate(): void {
        foreach (['checkout_enabled','licence_confirmed','privacy_confirmed','tax_confirmed','merchant_confirmed','production_tests_passed'] as $flag)
            if (!($this->config[$flag] ?? false)) throw new Problem(503,'Checkout is not enabled yet.');
        if (!filter_var($this->config['support_email'] ?? '',FILTER_VALIDATE_EMAIL)
            || ($this->config['merchant_descriptor'] ?? '') === '') throw new Problem(503,'Merchant setup is incomplete.');
    }
    public function checkout(array $input,string $owner): array {
        $this->gate();
        if (($input['accept_terms'] ?? false) !== true || ($input['accept_delivery'] ?? false) !== true)
            throw new Problem(422,'Please accept the terms and immediate digital delivery.');
        $id=boundedText($input['order_id'] ?? null,32,32);
        $country=$input['billing_country'] ?? '';
        if (!is_string($country) || !array_key_exists($country,$this->config['country_tax_rates']))
            throw new Problem(422,'Sales are not enabled in this billing country yet.');
        $o=$this->store->transaction(function(&$s)use($id,$owner,$country){
            $o=$this->authorize($s,$id,$owner);
            if (!$o['preview_seen']) throw new Problem(409,'Review your free preview first.');
            if(!empty($o['package']['cv']['missing_details']))throw new Problem(422,'Add missing experience details or skills to your source CV and prepare a fresh preview before purchasing.');
            if ($o['expires'] < $this->now()+1800) throw new Problem(409,'This preview is expiring. Prepare a fresh preview before paying.');
            if ($o['paid'] || $o['revoked']) throw new Problem(409,'This order cannot be charged again.');
            if ($o['country'] !== null && $o['country'] !== $country)
                throw new Problem(409,'Create a new preview to change billing country.');
            $s['orders'][$id]['country']=$country;
            $s['orders'][$id]['checkout_started']=$o['checkout_started'] ?? $this->now();
            $s['orders'][$id]['state']='checkout';
            $s['orders'][$id]['terms_version']='2026-10-13';
            return $s['orders'][$id];
        });
        if ($o['checkout']) return ['url'=>$o['checkout']['url']];
        $pmc=$this->config['stripe_payment_method_configuration'] ?? '';
        if(!preg_match('/^pmc_[A-Za-z0-9]+$/',$pmc)) throw new Problem(503,'Payment method configuration is incomplete.');
        $methods=($this->stripe)('GET','/v1/payment_method_configurations/'.$pmc,[],null);
        if(($methods['id'] ?? '')!==$pmc || ($methods['active'] ?? false)!==true
            || ($methods['livemode'] ?? null)!==(bool)$this->config['stripe_live']
            || ($methods['card']['available'] ?? false)!==true)
            throw new Problem(503,'Payment method configuration is unavailable.');
        // Packages expire in 24 hours: refuse methods with delayed settlement.
        foreach($methods as $name=>$setting)
            if(is_array($setting) && (($setting['available'] ?? false)===true
                || ($setting['display_preference']['value'] ?? '')==='on')
                && !in_array($name,['card','apple_pay','google_pay','link'],true))
                throw new Problem(503,'Payment methods need review before checkout.');
        $line=['price_data'=>['currency'=>'usd','unit_amount'=>900,'tax_behavior'=>'inclusive',
            'product_data'=>['name'=>'ApplyPilot digital application assistance bundle']], 'quantity'=>1];
        $tax=$this->config['country_tax_rates'][$country];
        if ($tax !== '') {
            $rate=($this->stripe)('GET','/v1/tax_rates/'.rawurlencode($tax),[],null);
            if (($rate['inclusive'] ?? false)!==true || ($rate['active'] ?? false)!==true)
                throw new Problem(503,'Inclusive tax rate is not configured.');
            $line['tax_rates']=[$tax];
        }
        $base=rtrim($this->config['base_url'],'/');
        $session=($this->stripe)('POST','/v1/checkout/sessions',[
            'mode'=>'payment','payment_method_configuration'=>$pmc,'line_items'=>[$line],
            'integration_identifier'=>$this->config['stripe_integration_identifier'],
            'client_reference_id'=>$id,'metadata'=>['order_id'=>$id,'product'=>'applypilot-v1','billing_country'=>$country],
            'payment_intent_data'=>['metadata'=>['order_id'=>$id,'product'=>'applypilot-v1']],
            'billing_address_collection'=>'required','consent_collection'=>['terms_of_service'=>'required'],
            'success_url'=>$base.'/payment-return.html','cancel_url'=>$base.'/payment-return.html?cancelled=1',
            'expires_at'=>$o['checkout_started']+1800,
            'custom_text'=>['submit'=>['message'=>'One-time USD $9 total. Digital assistance only; no job or interview guarantee.']]
        ],'applypilot-'.$id);
        if (!preg_match('/^cs_(test_|live_)?[A-Za-z0-9]+$/',$session['id'] ?? '')
            || !str_starts_with($session['url'] ?? '', 'https://checkout.stripe.com/'))
            throw new Problem(502,'Checkout is temporarily unavailable.');
        $this->store->transaction(function(&$s)use($id,$session){
            if (!isset($s['orders'][$id])) throw new Problem(410,'Package was deleted.');
            $s['orders'][$id]['checkout']=['id'=>$session['id'],'url'=>$session['url']];
            $this->count($s,'checkout_created');
        });
        return ['url'=>$session['url']];
    }
    public function webhook(string $raw,string $header): array {
        $e=verifySignature($raw,$header,$this->config['stripe_webhook_secret'],$this->now());
        if (($e['livemode'] ?? null) !== (bool)$this->config['stripe_live']) throw new Problem(400,'Wrong payment mode.');
        $type=$e['type']; $object=$e['data']['object'] ?? [];
        if (in_array($type,['checkout.session.completed','checkout.session.async_payment_succeeded'],true)) {
            // Fresh Stripe retrieval: signed webhook / redirect alone is not fulfillment proof.
            $sid=$object['id'] ?? '';
            if (!is_string($sid) || !preg_match('/^cs_[A-Za-z0-9_]+$/',$sid)) throw new Problem(400,'Invalid session.');
            $session=($this->stripe)('GET','/v1/checkout/sessions/'.rawurlencode($sid),[],null);
            return $this->store->transaction(function(&$s)use($session,$e){
                $id=$session['metadata']['order_id'] ?? '';
                $o=$s['orders'][$id] ?? null;
                if (!$o) {
                    if(($session['metadata']['product']??'')!=='applypilot-v1')return ['ok'=>true,'ignored'=>true];
                    throw new Problem(409,'Order not found; investigate payment before fulfillment.');
                }
                if (in_array($e['id'],$o['events'],true)) return ['ok'=>true];
                // The event may race our Checkout response; retry until session binding is persisted.
                if (($o['checkout']['id'] ?? null) !== ($session['id'] ?? null)) throw new Problem(409,'Session mismatch; retry.');
                if (($session['payment_status'] ?? '') !== 'paid') return ['ok'=>true];
                if (($session['mode'] ?? '')!=='payment' || ($session['status'] ?? '')!=='complete'
                    || ($session['amount_total'] ?? null)!==900 || ($session['currency'] ?? '')!=='usd'
                    || ($session['livemode'] ?? null)!==(bool)$this->config['stripe_live']
                    || ($session['client_reference_id'] ?? '')!==$id
                    || ($session['metadata']['product'] ?? '')!=='applypilot-v1'
                    || ($session['customer_details']['address']['country'] ?? '')!==$o['country']
                    || !is_string($session['payment_intent']??null)
                    || !preg_match('/^pi_[A-Za-z0-9]+$/D',$session['payment_intent']))
                    throw new Problem(409,'Payment details need review.');
                if (!$o['paid']) $this->count($s,'payment_verified');
                $s['orders'][$id]['paid']=true;
                $s['orders'][$id]['payment_intent']=$session['payment_intent'];
                $s['orders'][$id]['events'][]=$e['id'];
                // Never reinstate an order revoked by a refund/dispute/deletion.
                $revoked=$o['revoked']||isset($s['pending_revocations'][$session['payment_intent']]);
                if($revoked&&!$o['revoked'])$this->count($s,'access_revoked');
                $s['orders'][$id]['revoked']=$revoked;
                $s['orders'][$id]['state']=$revoked?'revoked':'paid';
                unset($s['pending_revocations'][$session['payment_intent']]);
                return ['ok'=>true];
            });
        }
        if (in_array($type,['charge.refunded','charge.dispute.created'],true)) {
            $pi=$object['payment_intent'] ?? '';
            if(!is_string($pi)||!preg_match('/^pi_[A-Za-z0-9]+$/D',$pi))throw new Problem(400,'Payment reference missing.');
            return $this->store->transaction(function(&$s)use($pi,$e){
                $found=false;
                foreach ($s['orders'] as &$o) if ($o['payment_intent']===$pi) {
                    $found=true;
                    if (!in_array($e['id'],$o['events'],true)) {
                        $o['revoked']=true; $o['state']='revoked'; $o['events'][]=$e['id'];
                        $this->count($s,'access_revoked');
                    }
                }
                if(!$found){
                    // Shared merchant events can concern another product. Keep a bounded,
                    // private tombstone so an earlier refund still revokes a later binding.
                    foreach($s['pending_revocations']??[]as$key=>$item)
                        if(($item['at']??0)<$this->now()-604800)unset($s['pending_revocations'][$key]);
                    if(!isset($s['pending_revocations'][$pi])&&count($s['pending_revocations']??[])>=1000)
                        throw new Problem(503,'Payment events need review; retry.');
                    $s['pending_revocations'][$pi]=['at'=>$this->now()];
                }
                return ['ok'=>true];
            });
        }
        return ['ok'=>true];
    }
    public function result(string $id,string $owner): array {
        return $this->store->transaction(function(&$s)use($id,$owner){
            $o=$this->authorize($s,$id,$owner);
            if (!$o['paid'] || $o['revoked']) throw new Problem(402,'Verified payment is required.');
            $this->count($s,'package_download'); return ['package'=>$o['package']];
        });
    }
    public function status(string $id,string $owner): array {
        return $this->store->transaction(function(&$s)use($id,$owner){
            $o=$this->authorize($s,$id,$owner); return ['state'=>$o['state'],'paid'=>$o['paid']&&!$o['revoked']];
        });
    }
    public function delete(string $id,string $owner): array {
        $existing=$this->store->transaction(fn(&$s)=>$this->authorize($s,$id,$owner));
        if ($existing['checkout'] && !$existing['paid']) {
            $path='/v1/checkout/sessions/'.$existing['checkout']['id'];
            $session=($this->stripe)('GET',$path,[],null);
            if (($session['status'] ?? '')==='open') {
                $session=($this->stripe)('POST',$path.'/expire',[],'applypilot-expire-'.$id);
            }
            if (($session['status'] ?? '')!=='expired')
                throw new Problem(409,'Payment is processing. Wait for confirmation before deleting this package.');
        }
        return $this->store->transaction(function(&$s)use($id,$owner){
            $this->authorize($s,$id,$owner);
            $s['orders'][$id]['package']=null; $s['orders'][$id]['preview']=null;
            $s['orders'][$id]['revoked']=true; $s['orders'][$id]['state']='deleted';
            $this->count($s,'package_deleted'); return ['ok'=>true];
        });
    }
    public function jobs(string $query): array {
        return $this->store->transaction(function(&$s)use($query){
            $this->count($s,'job_search');
            if ($s['jobs_at'] < $this->now()-43200) return ['jobs'=>[],'notice'=>'No fresh verified listings are available.'];
            $q=strtolower(substr($query,0,100));
            $jobs=array_values(array_filter($s['jobs'],fn($j)=>$q==='' || strpos(strtolower($j['title'].' '.$j['location'].' '.$j['employer']),$q)!==false));
            return ['jobs'=>array_slice($jobs,0,100),'checked_at'=>gmdate('c',$s['jobs_at'])];
        });
    }
    public function cleanup(): array {
        return $this->store->transaction(function(&$s){
            $n=0;
            foreach($s['orders'] as &$o) {
                if($o['expires']<=$this->now() && $o['package']) { $o['package']=null; $o['preview']=null; $n++; }
            }
            unset($o);
            foreach($s['orders'] as $id=>$o) if(!$o['paid'] && !$o['checkout'] && $o['expires']<$this->now()-604800) unset($s['orders'][$id]);
            foreach($s['limits'] as $key=>$v) if((int)explode(':',$key)[0]<intdiv($this->now(),3600)-1) unset($s['limits'][$key]);
            foreach($s['ai'] as $day=>$v) if($day<gmdate('Y-m-d',$this->now()-604800)) unset($s['ai'][$day]);
            foreach($s['owner_ai'] ?? [] as $key=>$v)if(substr($key,0,10)<gmdate('Y-m-d',$this->now()-86400))unset($s['owner_ai'][$key]);
            foreach($s['admin_attempts'] ?? [] as $key=>$v)if(substr($key,6,13)<gmdate('Y-m-d-H',$this->now()-3600))unset($s['admin_attempts'][$key]);
            foreach($s['pending_revocations']??[]as$key=>$item)if(($item['at']??0)<$this->now()-604800)unset($s['pending_revocations'][$key]);
            return ['packages_purged'=>$n];
        });
    }
}
