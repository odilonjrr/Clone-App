<?php
/**
 * King Decants — inc/infinitepay.php
 * ---------------------------------------------------------------------------
 * Integração com o Checkout Integrado da InfinitePay (link de pagamento).
 *
 * Fluxo:
 *   1. checkout.php cria o pedido "pending" e chama ip_create_checkout_link():
 *      POST {INFINITEPAY_API_BASE}/links  com handle (InfiniteTag), itens em
 *      CENTAVOS, order_nsu (nosso número do pedido), redirect_url e webhook_url.
 *      A InfinitePay devolve a URL do checkout (PIX e cartão) -> redirecionamos.
 *   2. Após pagar, o cliente volta para callback.php (redirect_url) com
 *      parâmetros como order_nsu, transaction_nsu, slug e receipt_url.
 *   3. A InfinitePay também avisa webhook-infinitepay.php (webhook_url).
 *   4. Em ambos os casos NÃO confiamos nos parâmetros recebidos: confirmamos com
 *      POST {INFINITEPAY_API_BASE}/payment_check e só então marcamos o pedido
 *      como pago (ip_process_payment()).
 *
 * Pontos a confirmar na documentação oficial (as fontes públicas divergem):
 *   - endereço base da API (INFINITEPAY_API_BASE);
 *   - se a conta exige chave (INFINITEPAY_API_KEY -> cabeçalho X-Api-Key);
 *   - nome do campo da URL na resposta (tratamos "url", "checkout_url" e "link").
 * Por isso esses pontos são configuráveis e o código é tolerante.
 * ---------------------------------------------------------------------------
 */

/** A InfiniteTag foi preenchida? */
function ip_is_configured(): bool
{
    return INFINITEPAY_HANDLE !== '' && !str_starts_with(INFINITEPAY_HANDLE, 'COLOQUE_');
}

/** Provedor de pagamento ativo. */
function payment_provider(): string
{
    return PAYMENT_PROVIDER === 'mercadopago' ? 'mercadopago' : 'infinitepay';
}

/** Nome amigável do provedor (textos da loja). */
function payment_provider_name(): string
{
    return payment_provider() === 'mercadopago' ? 'Mercado Pago' : 'InfinitePay';
}

/** InfiniteTag sem "$" e espaços. */
function ip_handle(): string
{
    return ltrim(trim(INFINITEPAY_HANDLE), '$');
}

/** Número do pedido enviado à InfinitePay (volta no webhook e no redirect). */
function ip_order_nsu(array $order): string
{
    return 'KD-' . (int) $order['id'];
}

/** Extrai o id do pedido de um order_nsu "KD-123" (ou "123"). */
function ip_order_id_from_nsu(string $orderNsu): int
{
    return preg_match('/^(?:KD-)?(\d{1,10})$/', trim($orderNsu), $m) ? (int) $m[1] : 0;
}

/**
 * Requisição JSON para a API da InfinitePay.
 * @return array [int $status, ?array $json, string $raw]
 */
function ip_request(string $path, array $body): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('A extensão cURL do PHP é necessária para a InfinitePay.');
    }
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if (INFINITEPAY_API_KEY !== '') {
        $headers[] = 'X-Api-Key: ' . INFINITEPAY_API_KEY;
    }
    $ch = curl_init(rtrim(INFINITEPAY_API_BASE, '/') . $path);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($raw === false) {
        throw new RuntimeException('Falha de comunicação com a InfinitePay: ' . $error);
    }
    $json = json_decode((string) $raw, true);
    return [$status, is_array($json) ? $json : null, (string) $raw];
}

/** Telefone no formato internacional (+55DDDNÚMERO). */
function ip_phone(string $phone): string
{
    $d = only_digits($phone);
    return $d !== '' ? '+55' . $d : '';
}

/**
 * Cria o link de pagamento do pedido.
 * @return array ['url' => string, 'order_nsu' => string, 'raw' => array]
 */
function ip_create_checkout_link(array $order, array $items): array
{
    $mpItems = [];
    foreach ($items as $it) {
        $mpItems[] = [
            'quantity'    => (int) $it['quantity'],
            'price'       => (int) round((float) $it['unit_price'] * 100), // centavos
            'description' => mb_substr($it['name'], 0, 120),
        ];
    }
    // Com cupom: a InfinitePay não tem campo de desconto documentado, então os
    // produtos vão numa única linha com o valor já descontado (total exato).
    if ((float) ($order['discount'] ?? 0) > 0) {
        $mpItems = [[
            'quantity'    => 1,
            'price'       => (int) round(((float) $order['subtotal'] - (float) $order['discount']) * 100),
            'description' => 'Pedido KD-' . (int) $order['id'] . ' (cupom ' . $order['coupon_code'] . ')',
        ]];
    }
    if ((float) $order['shipping'] > 0) {
        $mpItems[] = ['quantity' => 1, 'price' => (int) round((float) $order['shipping'] * 100), 'description' => 'Frete'];
    }

    $orderNsu = ip_order_nsu($order);
    $body = [
        'handle'       => ip_handle(),
        'order_nsu'    => $orderNsu,
        'items'        => $mpItems,
        'redirect_url' => absolute_url('callback.php?order=' . $order['id'] . '&token=' . $order['access_token']),
        // Dados opcionais: pré-preenchem o checkout para o cliente.
        'customer'     => array_filter([
            'name'         => $order['customer_name'],
            'email'        => $order['customer_email'],
            'phone_number' => ip_phone($order['customer_phone']),
        ]),
        'address'      => array_filter([
            'cep'        => $order['cep'],
            'number'     => $order['number'],
            'complement' => $order['complement'] ?? '',
        ]),
    ];
    // O webhook só é útil com URL pública HTTPS (em localhost a InfinitePay não alcança).
    if (str_starts_with(APP_URL, 'https://')) {
        $body['webhook_url'] = absolute_url('webhook-infinitepay.php');
    }

    [$status, $json, $raw] = ip_request('/links', $body);
    $url = $json['url'] ?? $json['checkout_url'] ?? $json['link'] ?? null;
    if ($status < 200 || $status >= 300 || !is_string($url) || !preg_match('#^https://#i', $url)) {
        app_log('infinitepay', 'Erro ao criar link de pagamento', ['status' => $status, 'body' => mb_substr($raw, 0, 2000)]);
        throw new RuntimeException('Não foi possível iniciar o pagamento na InfinitePay. Tente novamente em instantes.');
    }
    return ['url' => $url, 'order_nsu' => $orderNsu, 'raw' => $json];
}

/**
 * Consulta o pagamento na InfinitePay (fonte da verdade).
 * @return array|null  null se a InfinitePay não encontrar o pagamento (4xx).
 * @throws RuntimeException em falha transitória (rede, 429, 5xx).
 */
function ip_payment_check(string $orderNsu, string $transactionNsu = '', string $slug = ''): ?array
{
    $body = array_filter([
        'handle'          => ip_handle(),
        'order_nsu'       => $orderNsu,
        'transaction_nsu' => $transactionNsu,
        'slug'            => $slug,
    ], fn($v) => $v !== '');
    [$status, $json, $raw] = ip_request('/payment_check', $body);

    if ($status === 429 || $status >= 500 || $status === 0) {
        app_log('infinitepay', 'payment_check: falha transitória', ['status' => $status, 'body' => mb_substr($raw, 0, 1000)]);
        throw new RuntimeException("Falha transitória ao consultar a InfinitePay (HTTP {$status})");
    }
    if ($status >= 400 || !$json) {
        app_log('infinitepay', 'payment_check: não encontrado/recusado', ['status' => $status, 'body' => mb_substr($raw, 0, 1000)]);
        return null;
    }
    return [
        'paid'           => filter_var($json['paid'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'amount'         => isset($json['amount']) ? (int) $json['amount'] : null,         // centavos
        'paid_amount'    => isset($json['paid_amount']) ? (int) $json['paid_amount'] : null, // centavos
        'installments'   => $json['installments'] ?? null,
        'capture_method' => $json['capture_method'] ?? null,                                  // pix / credit_card
        'raw'            => $json,
    ];
}

/**
 * Registra a confirmação e, se pago, marca o pedido como pago.
 * Idempotente (pode ser chamado pelo webhook e pelo retorno ao mesmo tempo).
 *
 * @param array $check  resultado de ip_payment_check()
 * @param array $ref    ['transaction_nsu','slug','receipt_url'] recebidos
 * @return array ['ok' => bool, 'message' => string]
 */
function ip_process_payment(array $order, array $check, array $ref, string $source): array
{
    $orderId = (int) $order['id'];
    $tx = substr(preg_replace('/[^A-Za-z0-9_\-]/', '', (string) ($ref['transaction_nsu'] ?? '')), 0, 40);
    $slug = substr(preg_replace('/[^A-Za-z0-9_\-]/', '', (string) ($ref['slug'] ?? '')), 0, 80);
    $receipt = (string) ($ref['receipt_url'] ?? '');
    $receipt = preg_match('#^https://#i', $receipt) ? mb_substr($receipt, 0, 500) : null;

    $paid = (bool) $check['paid'];
    $amountCents = $check['amount'] ?? $check['paid_amount'];
    $amount = $amountCents !== null ? $amountCents / 100 : (float) $order['total'];

    // Não pago: nada é gravado (os parâmetros recebidos podem ter sido forjados,
    // inclusive o link do comprovante). Só registramos o que a InfinitePay confirmou.
    if (!$paid) {
        app_log('infinitepay', 'Pagamento ainda não confirmado', ['order' => $orderId, 'source' => $source]);
        return ['ok' => true, 'message' => 'Pagamento ainda não confirmado'];
    }

    // Linha de pagamento (uma por transação; sem transaction_nsu usa o order_nsu).
    $externalId = $tx !== '' ? $tx : ip_order_nsu($order);
    db()->prepare(
        'INSERT INTO payments (order_id, provider, mp_payment_id, external_slug, method, status, status_detail, amount, receipt_url, raw_response)
         VALUES (?, \'infinitepay\', ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE status = VALUES(status), status_detail = VALUES(status_detail), method = COALESCE(VALUES(method), method),
                                 amount = VALUES(amount), external_slug = COALESCE(VALUES(external_slug), external_slug),
                                 receipt_url = COALESCE(VALUES(receipt_url), receipt_url), raw_response = VALUES(raw_response)'
    )->execute([
        $orderId, $externalId, $slug ?: null, $check['capture_method'], 'approved',
        $check['installments'] ? $check['installments'] . 'x' : null, $amount, $receipt,
        json_encode($check['raw'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    // Segurança: o valor cobrado (sem juros de parcelamento) precisa bater com o pedido.
    if ($check['amount'] !== null && abs($check['amount'] - (int) round((float) $order['total'] * 100)) > 5) {
        app_log('infinitepay', 'Valor divergente', ['order' => $orderId, 'amount' => $check['amount'], 'total' => $order['total']]);
        order_add_history_note($orderId, 'InfinitePay: pagamento com valor divergente (' . money($amount) . '). Verificar manualmente.', $source);
        return ['ok' => false, 'message' => 'Valor divergente'];
    }
    $note = 'InfinitePay: pago' . ($check['capture_method'] ? " via {$check['capture_method']}" : '')
          . ($check['installments'] ? " em {$check['installments']}x" : '') . ($tx ? " · transação {$tx}" : '');
    $changed = order_change_status($orderId, 'paid', $source, null, $note);
    return ['ok' => true, 'message' => $changed ? 'Pedido atualizado para paid' : 'Pagamento já registrado'];
}

/** Rótulo da forma de pagamento do pedido (listas do admin). */
function payment_method_label(string $method): string
{
    return ['pix' => 'PIX', 'card' => 'Cartão', 'infinitepay' => 'InfinitePay'][$method] ?? $method;
}
