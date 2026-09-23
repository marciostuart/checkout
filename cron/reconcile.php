<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap/app.php';
require dirname(__DIR__).'/app/Gateway.php';
$pdo=db(); $pending=$pdo->query("SELECT * FROM payment_intents WHERE status IN ('PENDING','PROCESSING') AND gateway_id IS NOT NULL AND created_at < (NOW() - INTERVAL 10 MINUTE) LIMIT 50")->fetchAll();
foreach($pending as $intent) { try { $pdo->prepare("UPDATE payment_intents SET status='RECONCILIATION_PENDING' WHERE id=?")->execute([$intent['id']]); } catch(Throwable $e) { error_log($e->getMessage()); } }
$notifications=$pdo->query("SELECT n.*,s.webhook_url FROM notification_attempts n JOIN services s ON s.id=n.service_id WHERE n.status='PENDING' AND n.next_attempt_at<=NOW() ORDER BY n.id LIMIT 50")->fetchAll();
foreach($notifications as $n) { $ch=curl_init($n['webhook_url']); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$n['payload'],CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-Checkout-Signature: '.$n['signature']],CURLOPT_TIMEOUT=>15]); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch); if($code>=200&&$code<300) $pdo->prepare("UPDATE notification_attempts SET status='DELIVERED',attempts=attempts+1 WHERE id=?")->execute([$n['id']]); else $pdo->prepare("UPDATE notification_attempts SET attempts=attempts+1,next_attempt_at=DATE_ADD(NOW(),INTERVAL LEAST(POWER(2,attempts),60) MINUTE),last_error=? WHERE id=?")->execute(['HTTP '.$code,$n['id']]); }
echo "reconcile complete\n";

