# Checkout 360BH

Checkout central PHP/MySQL com links de cobrança e adaptador Asaas.

## Instalação

1. Copie `.env.example` para `.env` um nível acima de `public_html` e preencha os segredos.
2. Importe `database/schema.sql` no banco exclusivo.
3. Configure o webhook do Asaas para `https://pay.360bh.com.br/webhook/asaas`.
4. Aponte o document root do domínio para `public_html`.

