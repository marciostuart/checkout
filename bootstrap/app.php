<?php
declare(strict_types=1);
$root = dirname(__DIR__);
foreach ([$root.'/.env', dirname($root).'/.env'] as $envFile) {
    if (is_file($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value, " \t\"");
        }
        break;
    }
}
date_default_timezone_set('America/Sao_Paulo');
function envv(string $key, ?string $default = null): ?string { return $_ENV[$key] ?? $default; }
function db(): PDO { static $pdo; if ($pdo instanceof PDO) return $pdo; $pdo = new PDO('mysql:host='.envv('DB_HOST','localhost').';port='.envv('DB_PORT','3306').';dbname='.envv('DB_DATABASE').';charset=utf8mb4', envv('DB_USERNAME'), envv('DB_PASSWORD',''), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]); return $pdo; }
function json_out(array $data, int $status = 200): never { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function request_json(): array { return json_decode(file_get_contents('php://input'), true) ?: []; }
function public_token(): string { return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='); }
function token_hash(string $value): string { return hash('sha256', $value); }
function money(int $cents): string { return number_format($cents / 100, 2, '.', ''); }
function service_from_request(): array { $key = $_SERVER['HTTP_X_CHECKOUT_API_KEY'] ?? ''; if ($key === '') json_out(['error'=>'missing api key'],401); $stmt=db()->prepare('SELECT * FROM services WHERE api_key_hash=? AND active=1'); $stmt->execute([hash('sha256',$key)]); $service=$stmt->fetch(); if (!$service) json_out(['error'=>'invalid api key'],401); return $service; }
function admin_required(): void { if (session_status() !== PHP_SESSION_ACTIVE) session_start(); if (empty($_SESSION['admin_authenticated'])) { header('Location: /admin/login'); exit; } }
function csrf_token(): string { if (session_status() !== PHP_SESSION_ACTIVE) session_start(); return $_SESSION['csrf'] ??= bin2hex(random_bytes(24)); }
function csrf_check(): void { if (!hash_equals((string)($_SESSION['csrf']??''), (string)($_POST['_csrf']??''))) { http_response_code(419); exit('Sessão expirada.'); } }
function notify_service(array $service, string $event, array $payload, ?int $intentId=null): void { if (empty($service['webhook_url']) || empty($service['webhook_secret_hash'])) return; $secret = envv('SERVICE_WEBHOOK_SECRET_'.$service['id']); if (!$secret) return; $body=json_encode(['event'=>$event,'data'=>$payload,'occurred_at'=>gmdate('c')],JSON_UNESCAPED_UNICODE); $sig=hash_hmac('sha256',$body,$secret); db()->prepare('INSERT INTO notification_attempts(service_id,payment_intent_id,event_name,payload,signature) VALUES(?,?,?,?,?)')->execute([$service['id'],$intentId,$event,$body,$sig]); }
