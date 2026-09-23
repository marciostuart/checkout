<?php
interface Gateway { public function createCustomer(array $customer): string; public function createPayment(array $data): array; public function createSubscription(array $data): array; }
final class AsaasGateway implements Gateway {
    private string $base; private string $key;
    public function __construct() { $this->base = envv('ASAAS_ENVIRONMENT','sandbox') === 'production' ? 'https://api.asaas.com/v3' : 'https://api-sandbox.asaas.com/v3'; $this->key = envv('ASAAS_API_KEY',''); }
    private function call(string $method, string $path, array $body = []): array { if (!$this->key) throw new RuntimeException('ASAAS_API_KEY não configurada'); $ch=curl_init($this->base.$path); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>['Content-Type: application/json','access_token: '.$this->key],CURLOPT_POSTFIELDS=>$body?json_encode($body):null,CURLOPT_TIMEOUT=>30]); $raw=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch); $data=json_decode((string)$raw,true) ?: []; if($code<200||$code>=300) throw new RuntimeException($data['errors'][0]['description']??'Erro no gateway', $code); return $data; }
    public function createCustomer(array $c): string { return $this->call('POST','/customers',['name'=>$c['name'],'email'=>$c['email'],'cpfCnpj'=>$c['cpf_cnpj']??null,'mobilePhone'=>$c['phone']??null])['id']; }
    public function createPayment(array $d): array { return $this->call('POST','/payments',['customer'=>$d['gateway_customer_id'],'billingType'=>$d['billing_type']??'UNDEFINED','value'=>money($d['amount_cents']),'dueDate'=>$d['due_date']??date('Y-m-d'),'description'=>$d['description']??'Cobrança 360BH','externalReference'=>$d['external_reference']??null]); }
    public function createSubscription(array $d): array { return $this->call('POST','/subscriptions',['customer'=>$d['gateway_customer_id'],'billingType'=>$d['billing_type']??'UNDEFINED','value'=>money($d['amount_cents']),'nextDueDate'=>$d['due_date']??date('Y-m-d'),'cycle'=>$d['cycle']??'MONTHLY','description'=>$d['description']??'Assinatura 360BH','externalReference'=>$d['external_reference']??null]); }
}

