<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
function respond(int $status,array $body): void {
    http_response_code($status); echo json_encode($body,JSON_UNESCAPED_SLASHES); exit;
}
$dir=getenv('APPLYPILOT_PRIVATE_DIR') ?: '';
if($dir==='' || !is_file($dir.'/Transport.php')) respond(503,['error'=>'Private server setup is incomplete.']);
try {
    require_once $dir.'/Transport.php';
    [$config,$store,$service]=\ApplyPilot\boot($dir);
    if(($_SERVER['HTTPS'] ?? '')!=='on') throw new \ApplyPilot\Problem(503,'HTTPS is required.');
    $method=$_SERVER['REQUEST_METHOD'] ?? '';
    $action=$_GET['action'] ?? '';
    if(!is_string($action)) throw new \ApplyPilot\Problem(400,'Invalid action.');
    // Read one byte past cap rather than trusting Content-Length.
    $raw=file_get_contents('php://input',false,null,0,262145);
    if($raw===false || strlen($raw)>262144) throw new \ApplyPilot\Problem(413,'Request too large.');
    if($action==='webhook') {
        if($method!=='POST') throw new \ApplyPilot\Problem(405,'Use POST.');
        respond(200,$service->webhook($raw,$_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''));
    }
    $service->rateLimit($_SERVER['REMOTE_ADDR'] ?? 'unknown'); // never trust forwarded IP headers
    ini_set('session.use_strict_mode','1'); ini_set('session.use_only_cookies','1');
    ini_set('session.gc_maxlifetime','86400');
    session_save_path(\ApplyPilot\privateSessionDirectory($dir,'customer'));
    $path=parse_url($config['base_url'],PHP_URL_PATH) ?: '/';
    session_name('applypilot_session');
    session_set_cookie_params(['lifetime'=>86400,'path'=>rtrim($path,'/').'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
    session_start();
    if(!isset($_SESSION['owner'])) { session_regenerate_id(true); $_SESSION['owner']=bin2hex(random_bytes(32)); $_SESSION['csrf']=bin2hex(random_bytes(32)); }
    $owner=$_SESSION['owner']; $csrf=$_SESSION['csrf']; session_write_close();
    if($action==='bootstrap' && $method==='GET') respond(200,[
        'csrf'=>$csrf,'ai_enabled'=>(bool)$config['ai_enabled'],'checkout_enabled'=>(bool)$config['checkout_enabled'],
        'billing_countries'=>array_keys($config['country_tax_rates']),'price'=>['currency'=>'USD','total'=>9],
        'support_email'=>$config['support_email'],'merchant_descriptor'=>$config['merchant_descriptor']]);
    if($action==='jobs' && $method==='GET') {
        $q=$_GET['q'] ?? ''; if(!is_string($q)) throw new \ApplyPilot\Problem(400,'Invalid query.');
        respond(200,$service->jobs($q));
    }
    if($method!=='POST') throw new \ApplyPilot\Problem(405,'Use POST.');
    $origin=parse_url($config['base_url']);
    $expected=$origin['scheme'].'://'.$origin['host'].(isset($origin['port'])?':'.$origin['port']:'');
    if(($_SERVER['HTTP_ORIGIN'] ?? '')!==$expected || !hash_equals($csrf,$_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))
        throw new \ApplyPilot\Problem(403,'Reload the page and try again.');
    if(!str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) throw new \ApplyPilot\Problem(415,'Use JSON.');
    try { $input=json_decode($raw,true,32,JSON_THROW_ON_ERROR); }
    catch(\Throwable $e){ throw new \ApplyPilot\Problem(400,'Invalid JSON.'); }
    if(!is_array($input)) throw new \ApplyPilot\Problem(400,'Invalid JSON.');
    $id=$input['order_id'] ?? '';
    if(!is_string($id)) throw new \ApplyPilot\Problem(400,'Invalid order.');
    switch($action) {
        case 'prepare': $result=$service->prepare($input,$owner); break;
        case 'preview-seen': $result=$service->acknowledgePreview($id,$owner); break;
        case 'checkout': $result=$service->checkout($input,$owner); break;
        case 'result': $result=$service->result($id,$owner); break;
        case 'status': $result=$service->status($id,$owner); break;
        case 'delete': $result=$service->delete($id,$owner); break;
        default: throw new \ApplyPilot\Problem(404,'Unknown action.');
    }
    respond(200,$result);
} catch(\ApplyPilot\Problem $e) { respond($e->status,['error'=>$e->getMessage()]); }
catch(\Throwable $e) { respond(503,['error'=>'Service temporarily unavailable. No package can be released without verified payment.']); }
