<?php
/**
 * King Decants — inc/mercadopago.php
 * ---------------------------------------------------------------------------
 * Integração com o Mercado Pago (Checkout Pro + PIX direto).
 *
 * DUAS OPÇÕES DE INTEGRAÇÃO (escolha automática):
 *
 *  Opção A (recomendada) — SDK oficial via Composer:
 *      composer require mercadopago/dx-php
 *    Se vendor/autoload.php existir e a classe do SDK estiver disponível,
 *    mp_create_preference() usa o SDK.
 *
 *  Opção B (fallback, sem dependências) — cURL direto na REST API:
 *    createPreferenceCurl() chama POST https://api.mercadopago.com/checkout/preferences
 *    com o header "Authorization: Bearer MP_ACCESS_TOKEN".
 *
 * Pagamentos PIX com QR code na própria loja usam POST /v1/payments (cURL),
 * que devolve point_of_interaction.transaction_data.{qr_code, qr_code_base64, ticket_url}.
 *
 * SEGURANÇA: o ACCESS_TOKEN só é usado no servidor. Nunca o envie ao navegador.
 * A PUBLIC_KEY é a única chave que pode ir para o front-end (Bricks/SDK JS).
 * ---------------------------------------------------------------------------
 */

// Base da API. Pode ser sobrescrita por variável de ambiente apenas para testes locais (mock).
if (!defined('MP_API_BASE')) {
    define('MP_API_BASE', getenv('MP_API_BASE') ?: 'https://api.mercadopago.com');
}

/** True quando as credenciais deixaram de ser placeholders. */
function mp_is_configured(): bool
{
    return MP_ACCESS_TOKEN !== '' && !str_starts_with(MP_ACCESS_TOKEN, 'COLOQUE_');
}

/** O SDK oficial (Opção A) está instalado? */
function mp_sdk_available(): bool
{
    return class_exists('MercadoPago\\MercadoPagoConfig')
        && class_exists('MercadoPago\\Client\\Preference\\PreferenceClient');
}

/**
 * Requisição HTTP genérica para a API do MP.
 * @return array [int $httpStatus, array|null $json, string $rawBody]
 */
function mp_request(string $method, string $path, ?array $body = null, array $extraHeaders = []): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('A extensão cURL do PHP é necessária para falar com o Mercado Pago.');
    }
    $headers = array_merge([
        'Authorization: Bearer ' . MP_ACCESS_TOKEN,
        'Content-Type: application/json',
        'Accept: application/json',
    ], $extraHeaders);

    $ch = curl_init(MP_API_BASE . $path);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,   // nunca desative em produção
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException('Falha de comunicação com o Mercado Pago: ' . $error);
    }
    $json = json_decode($raw, true);
    return [$status, is_array($json) ? $json : null, (string) $raw];
}

/** Divide "Nome Completo Sobrenome" em [nome, sobrenome] (o MP pede separados). */
function mp_split_name(string $fullName): array
{
    $parts = preg_split('/\s+/', trim($fullName));
    $first = array_shift($parts) ?: $fullName;
    return [$first, implode(' ', $parts)];
}

/**
 * Monta o payload da preferência (Checkout Pro) a partir do pedido.
 * O frete entra como um item separado para que o total cobrado seja exatamente orders.total.
 */
function mp_build_preference_payload(array $order, array $items): array
{
    [$first, $last] = mp_split_name($order['customer_name']);
    $phone = only_digits($order['customer_phone']);

    $mpItems = [];
    foreach ($items as $it) {
        $mpItems[] = [
            'id'          => $it['sku'],
            'title'       => $it['name'],
            'quantity'    => (int) $it['quantity'],
            'currency_id' => 'BRL',
            'unit_price'  => round((float) $it['unit_price'], 2),
        ];
    }
    if ((float) $order['shipping'] > 0) {
        $mpItems[] = [
            'id' => 'FRETE', 'title' => 'Frete', 'quantity' => 1,
            'currency_id' => 'BRL', 'unit_price' => round((float) $order['shipping'], 2),
        ];
    }

    $returnBase = 'callback.php?order=' . $order['id'] . '&token=' . $order['access_token'];

    $payload = [
        'items' => $mpItems,
        'payer' => [
            'name'           => $first,
            'surname'        => $last,
            'email'          => $order['customer_email'],
            'phone'          => ['area_code' => substr($phone, 0, 2), 'number' => substr($phone, 2)],
            'identification' => ['type' => 'CPF', 'number' => $order['customer_cpf']],
            'address'        => [
                'zip_code'      => $order['cep'],
                'street_name'   => $order['street'],
                'street_number' => $order['number'],
            ],
        ],
        'back_urls' => [
            'success' => absolute_url($returnBase . '&result=success'),
            'failure' => absolute_url($returnBase . '&result=failure'),
            'pending' => absolute_url($returnBase . '&result=pending'),
        ],
        'auto_return'          => 'approved',
        'external_reference'   => (string) $order['id'],
        'statement_descriptor' => MP_STATEMENT_DESCRIPTOR,
        // Mantém cartão de crédito/débito, saldo MP e PIX; exclui boleto (compensação lenta).
        'payment_methods' => [
            'installments'           => 12,
            'excluded_payment_types' => [['id' => 'ticket']],
        ],
        // Preferência expira em 24h.
        'expires'            => true,
        'expiration_date_to' => date('Y-m-d\TH:i:s.vP', time() + 86400),
        'metadata'           => ['order_id' => $order['id']],
    ];

    // O MP só aceita notification_url pública (https). Em localhost ela é omitida:
    // use a URL de webhook cadastrada no painel ou um túnel (ngrok/cloudflared).
    if (str_starts_with(APP_URL, 'https://')) {
        $payload['notification_url'] = absolute_url('webhook.php?source_news=webhooks');
    }
    return $payload;
}

/**
 * Cria a preferência usando SDK (Opção A) ou cURL (Opção B).
 * @return array ['id' => string, 'init_point' => string, 'sandbox_init_point' => ?string]
 */
function mp_create_preference(array $payload): array
{
    if (mp_sdk_available()) {
        // ----- Opção A: SDK oficial (mercadopago/dx-php v3) -----
        try {
            \MercadoPago\MercadoPagoConfig::setAccessToken(MP_ACCESS_TOKEN);
            $client = new \MercadoPago\Client\Preference\PreferenceClient();
            $pref = $client->create($payload);
            return [
                'id'                 => (string) $pref->id,
                'init_point'         => (string) $pref->init_point,
                'sandbox_init_point' => $pref->sandbox_init_point ?? null,
            ];
        } catch (\Throwable $e) {
            $detail = method_exists($e, 'getApiResponse') && $e->getApiResponse()
                ? json_encode($e->getApiResponse()->getContent())
                : $e->getMessage();
            app_log('mercadopago', 'SDK: erro ao criar preferência', ['error' => $detail]);
            throw new RuntimeException('Não foi possível iniciar o pagamento (SDK).');
        }
    }
    // ----- Opção B: cURL -----
    return createPreferenceCurl($payload);
}

/**
 * Opção B — cria a preferência via cURL (sem Composer).
 * Endpoint: POST https://api.mercadopago.com/checkout/preferences
 */
function createPreferenceCurl(array $payload): array
{
    [$status, $json, $raw] = mp_request('POST', '/checkout/preferences', $payload);
    if ($status < 200 || $status >= 300 || empty($json['id'])) {
        app_log('mercadopago', 'cURL: erro ao criar preferência', ['status' => $status, 'body' => mb_substr($raw, 0, 2000)]);
        throw new RuntimeException('Não foi possível iniciar o pagamento. Tente novamente em instantes.');
    }
    return [
        'id'                 => (string) $json['id'],
        'init_point'         => (string) ($json['init_point'] ?? ''),
        'sandbox_init_point' => $json['sandbox_init_point'] ?? null,
    ];
}

/** URL de checkout correta para o ambiente (sandbox ou produção). */
function mp_checkout_url(array $preference): string
{
    if (MP_SANDBOX && !empty($preference['sandbox_init_point'])) {
        return $preference['sandbox_init_point'];
    }
    return $preference['init_point'];
}

/**
 * Cria um pagamento PIX direto (QR code exibido na loja).
 * Endpoint: POST /v1/payments  — exige header X-Idempotency-Key.
 * Requisito: a conta MP precisa ter uma chave PIX cadastrada.
 */
function mp_create_pix_payment(array $order): array
{
    [$first, $last] = mp_split_name($order['customer_name']);
    $payload = [
        'transaction_amount' => round((float) $order['total'], 2),
        'description'        => 'Pedido #' . $order['id'] . ' - ' . APP_NAME,
        'payment_method_id'  => 'pix',
        'external_reference' => (string) $order['id'],
        // PIX válido por 30 minutos
        'date_of_expiration' => date('Y-m-d\TH:i:s.vP', time() + 1800),
        'payer' => [
            'email'          => $order['customer_email'],
            'first_name'     => $first,
            'last_name'      => $last ?: $first,
            'identification' => ['type' => 'CPF', 'number' => $order['customer_cpf']],
        ],
    ];
    if (str_starts_with(APP_URL, 'https://')) {
        $payload['notification_url'] = absolute_url('webhook.php?source_news=webhooks');
    }

    // Chave de idempotência: evita cobrança duplicada se a requisição for repetida.
    $idempotency = 'kd-order-' . $order['id'] . '-pix-' . random_token(8);
    [$status, $json, $raw] = mp_request('POST', '/v1/payments', $payload, ['X-Idempotency-Key: ' . $idempotency]);

    if ($status < 200 || $status >= 300 || empty($json['id'])) {
        app_log('mercadopago', 'Erro ao criar pagamento PIX', ['status' => $status, 'body' => mb_substr($raw, 0, 2000)]);
        throw new RuntimeException('Não foi possível gerar o PIX. Verifique se sua conta Mercado Pago possui chave PIX cadastrada.');
    }
    return $json;
}

/** Consulta um pagamento (fonte da verdade para o status). */
function mp_get_payment(string $paymentId): ?array
{
    if (!preg_match('/^\d{1,20}$/', $paymentId)) {
        return null;
    }
    [$status, $json, $raw] = mp_request('GET', '/v1/payments/' . $paymentId);
    if ($status !== 200 || !$json) {
        app_log('mercadopago', 'Erro ao consultar pagamento', ['id' => $paymentId, 'status' => $status, 'body' => mb_substr($raw, 0, 1000)]);
        return null;
    }
    return $json;
}

/** Consulta uma merchant_order (tópico legado do IPN) e retorna os ids de pagamento. */
function mp_get_merchant_order_payment_ids(string $merchantOrderId): array
{
    if (!preg_match('/^\d{1,20}$/', $merchantOrderId)) {
        return [];
    }
    [$status, $json] = mp_request('GET', '/merchant_orders/' . $merchantOrderId);
    if ($status !== 200 || !$json) {
        return [];
    }
    return array_map(fn($p) => (string) $p['id'], $json['payments'] ?? []);
}

/**
 * Valida o header x-signature enviado pelo Mercado Pago nos Webhooks.
 *
 * Formato do header:  x-signature: ts=1704908010,v1=618c8534...
 * Manifesto assinado: "id:{data.id};request-id:{x-request-id};ts:{ts};"
 * Algoritmo:          HMAC-SHA256 com a "assinatura secreta" (MP_WEBHOOK_SECRET).
 *
 * Retorna null se não houver segredo configurado (validação desativada).
 */
function mp_verify_webhook_signature(string $dataId): ?bool
{
    if (MP_WEBHOOK_SECRET === '') {
        return null;
    }
    $signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
    $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';
    if ($signature === '') {
        return false;
    }
    $ts = $v1 = null;
    foreach (explode(',', $signature) as $part) {
        $kv = explode('=', trim($part), 2);
        if (count($kv) === 2) {
            if ($kv[0] === 'ts') {
                $ts = $kv[1];
            } elseif ($kv[0] === 'v1') {
                $v1 = $kv[1];
            }
        }
    }
    if (!$ts || !$v1) {
        return false;
    }
    // Ids alfanuméricos devem ir em minúsculas no manifesto.
    $id = ctype_digit($dataId) ? $dataId : strtolower($dataId);
    $manifest = '';
    if ($id !== '') {
        $manifest .= "id:{$id};";
    }
    if ($requestId !== '') {
        $manifest .= "request-id:{$requestId};";
    }
    $manifest .= "ts:{$ts};";
    $expected = hash_hmac('sha256', $manifest, MP_WEBHOOK_SECRET);
    return hash_equals($expected, $v1);
}
