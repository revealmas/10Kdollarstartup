<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/Transport.php';
[$config,$store,$service]=\ApplyPilot\boot(__DIR__);
echo $store->transaction(function(&$s){
    $paid=0; $revoked=0;
    foreach($s['orders'] as $o) { if($o['paid']) $paid++; if($o['paid']&&$o['revoked']) $revoked++; }
    return json_encode(['daily_counts'=>$s['counts'],'paid_orders'=>$paid,'paid_orders_revoked'=>$revoked,
        'gross_verified_usd'=>$paid*9,'note'=>'Not a financial ledger: reconcile fees, taxes, refunds and disputes in Stripe.'],JSON_PRETTY_PRINT);
}).PHP_EOL;
