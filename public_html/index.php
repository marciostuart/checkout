<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap/app.php';
require dirname(__DIR__).'/app/Gateway.php';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'];
if ($path === '/health') json_out(['ok'=>true,'app'=>'checkout-360bh']);
if ($path === '/api/v1/payment-links' && $method === 'POST') {
    $service = service_from_request();
    $in = request_json();
    if (empty($in['amount_cents']) || empty($in['description'])) json_out(['error'=>'amount_cents e description são obrigatórios'],422);
    $raw = public_token();
    db()->prepare('INSERT INTO payment_links(token_hash,amount_cents,currency,description,payment_type,expires_at,max_uses,created_by,service_id) VALUES(?,?,?,?,?,?,?,?,?)')->execute([token_hash($raw),(int)$in['amount_cents'],$in['currency']??'BRL',$in['description'],$in['payment_type']??'ONE_TIME',$in['expires_at']??null,$in['max_uses']??1,$service['name'],$service['id']]);
    json_out(['url'=>rtrim((string)envv('APP_URL'),' /').'/pagar/'.$raw],201);
}
if (preg_match('#^/pagar/([A-Za-z0-9_-]+)$#',$path,$matches)) {
    $stmt=db()->prepare('SELECT * FROM payment_links WHERE token_hash=?'); $stmt->execute([token_hash($matches[1])]); $link=$stmt->fetch();
    if (!$link || $link['status'] !== 'ACTIVE' || ($link['expires_at'] && strtotime($link['expires_at']) < time())) { http_response_code(404); exit('Link inválido, expirado ou cancelado.'); }
    if ($method === 'POST') {
        $in=$_POST; if(empty($in['name'])||empty($in['email'])) exit('Nome e e-mail são obrigatórios.');
        $pdo=db(); $pdo->prepare('INSERT INTO customers(name,email,cpf_cnpj,phone) VALUES(?,?,?,?)')->execute([$in['name'],$in['email'],$in['cpf_cnpj']??null,$in['phone']??null]); $gateway=new AsaasGateway(); $gatewayId=$gateway->createCustomer($in);
        $result=$link['payment_type']==='SUBSCRIPTION'?$gateway->createSubscription(['gateway_customer_id'=>$gatewayId,'amount_cents'=>$link['amount_cents'],'cycle'=>'MONTHLY','description'=>$link['description'],'external_reference'=>'link:'.$link['id']]):$gateway->createPayment(['gateway_customer_id'=>$gatewayId,'amount_cents'=>$link['amount_cents'],'description'=>$link['description'],'external_reference'=>'link:'.$link['id']]);
        $pdo->prepare("UPDATE payment_links SET uses_count=uses_count+1,status=IF(max_uses IS NOT NULL AND uses_count+1>=max_uses,'USED',status) WHERE id=?")->execute([$link['id']]); if(!empty($result['invoiceUrl'])) header('Location: '.$result['invoiceUrl'],true,303); exit('Cobrança criada.');
    }
    ?><!doctype html><meta charset="utf-8"><title>Pagamento 360BH</title><h1><?=htmlspecialchars($link['description'])?></h1><p>Valor: R$ <?=money((int)$link['amount_cents'])?></p><form method="post"><input name="name" placeholder="Nome" required><input name="email" type="email" placeholder="E-mail" required><input name="cpf_cnpj" placeholder="CPF/CNPJ"><input name="phone" placeholder="Telefone"><button>Pagar</button></form><?php exit;
}
if ($path === '/webhook/asaas' && $method === 'POST') {
    $provided=$_SERVER['HTTP_ASAAS_ACCESS_TOKEN']??''; if(!hash_equals((string)envv('ASAAS_WEBHOOK_TOKEN',''),$provided)) json_out(['error'=>'unauthorized'],401); $payload=json_decode(file_get_contents('php://input'),true) ?: []; $event=$payload['id']??hash('sha256',json_encode($payload));
    try { db()->prepare('INSERT INTO webhook_events(gateway,event_id,event_name,payload,processed_at) VALUES(?,?,?,?,NOW())')->execute(['asaas',$event,$payload['event']??'unknown',json_encode($payload)]); } catch(PDOException $e) { if(($e->errorInfo[1]??0)!==1062) throw $e; }
    json_out(['received'=>true]);
}
http_response_code(404); echo 'Not found';
