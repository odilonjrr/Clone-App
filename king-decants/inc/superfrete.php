<?php
/**
 * King Decants — inc/superfrete.php
 * ---------------------------------------------------------------------------
 * Integração com a SuperFrete (https://superfrete.readme.io), conforme a
 * documentação oficial:
 *   POST api/v0/calculator      cotação (checkout)
 *   POST api/v0/cart            cria a etiqueta (status pending)
 *   POST api/v0/checkout        paga a etiqueta com o saldo -> tracking + PDF
 *   POST api/v0/tag/print       link do PDF da etiqueta
 *   GET  api/v0/order/info/{id} situação da etiqueta (pending/released/posted/delivered/canceled)
 *   POST api/v0/order/cancel    cancela (antes da postagem; valor volta ao saldo)
 *   GET  api/v0/user            saldo da conta
 *   POST/GET/DELETE api/v0/webhook  avisos automáticos (postado/entregue)
 *
 * Cabeçalhos obrigatórios: Authorization: Bearer <token> e
 * User-Agent: "Nome versão (email de contato)".
 *
 * Token em SUPERFRETE_TOKEN (kingdecants-config.php). O resto (CEP de origem,
 * remetente, medidas por decant, transportadoras, margem) em Admin -> Frete.
 * Se a SuperFrete não responder, o checkout usa a tabela de frete por CEP.
 * ---------------------------------------------------------------------------
 */

const SF_SERVICES = [
    1  => 'PAC',
    2  => 'SEDEX',
    17 => 'Mini Envios',
    3  => 'Jadlog',
    33 => 'J&T',
    31 => 'Loggi',
];

/** Configurações (Admin -> Frete), com valores padrão. */
function sf_settings(): array
{
    $g = fn(string $k, string $d = '') => (string) setting($k, $d);
    return [
        'mode'        => $g('shipping_calc', 'table'),   // table | superfrete
        'origin_cep'  => only_digits($g('sf_origin_cep')),
        'services'    => array_values(array_filter(array_map('intval', explode(',', $g('sf_services', '1,2,17'))), fn($s) => isset(SF_SERVICES[$s]))),
        'height'      => (float) $g('sf_item_height', '0'),   // cm, por unidade
        'width'       => (float) $g('sf_item_width', '0'),
        'length'      => (float) $g('sf_item_length', '0'),
        'weight_g'    => (float) $g('sf_item_weight_g', '0'), // gramas, por unidade
        'extra_fee'   => (float) $g('sf_extra_fee', '0'),     // R$ somado ao frete
        'extra_days'  => (int) $g('sf_extra_days', '0'),      // dias de preparo somados ao prazo
        'insurance'   => $g('sf_insurance', '0') === '1',     // declarar valor dos produtos
        'sender'      => [
            'name'       => $g('sf_from_name', 'Loja King Decants'),
            'address'    => $g('sf_from_address'),
            'number'     => $g('sf_from_number'),
            'complement' => $g('sf_from_complement'),
            'district'   => $g('sf_from_district'),
            'city'       => $g('sf_from_city'),
            'state_abbr' => strtoupper($g('sf_from_state')),
            'document'   => only_digits($g('sf_from_document')),
        ],
        'webhook_id'  => $g('sf_webhook_id'),
    ];
}

function sf_token_ok(): bool
{
    $t = (string) SUPERFRETE_TOKEN;
    return $t !== '' && !preg_match('/COLOQUE|COLE_AQUI|SEU_TOKEN/i', $t);
}

/** O que falta para a cotação funcionar ([] = tudo certo). */
function sf_missing(?array $s = null): array
{
    $s = $s ?? sf_settings();
    $m = [];
    if (!sf_token_ok()) {
        $m[] = 'token SUPERFRETE_TOKEN no kingdecants-config.php';
    }
    if (!validate_cep($s['origin_cep'])) {
        $m[] = 'CEP de origem';
    }
    if ($s['height'] <= 0 || $s['width'] <= 0 || $s['length'] <= 0 || $s['weight_g'] <= 0) {
        $m[] = 'medidas e peso do decant embalado';
    }
    if (!$s['services']) {
        $m[] = 'ao menos uma transportadora';
    }
    return $m;
}

/** A cotação pela SuperFrete está ligada e configurada? */
function sf_enabled(): bool
{
    $s = sf_settings();
    return $s['mode'] === 'superfrete' && !sf_missing($s);
}

/** Etiquetas exigem também o endereço do remetente. */
function sf_sender_missing(?array $s = null): array
{
    $s = $s ?? sf_settings();
    $m = [];
    foreach (['name' => 'nome do remetente', 'address' => 'rua', 'district' => 'bairro', 'city' => 'cidade'] as $k => $label) {
        if (trim($s['sender'][$k]) === '') {
            $m[] = $label;
        }
    }
    if (!preg_match('/^[A-Z]{2}$/', $s['sender']['state_abbr'])) {
        $m[] = 'UF';
    }
    return $m;
}

function sf_base_url(): string
{
    if ((string) SUPERFRETE_API_BASE !== '') {
        return rtrim((string) SUPERFRETE_API_BASE, '/') . '/';
    }
    return SUPERFRETE_SANDBOX ? 'https://sandbox.superfrete.com/' : 'https://api.superfrete.com/';
}

/** Painel web da SuperFrete (para links no admin). */
function sf_panel_url(): string
{
    return SUPERFRETE_SANDBOX ? 'https://sandbox.superfrete.com/#/' : 'https://web.superfrete.com/#/';
}

/**
 * Chamada à API. Retorna o JSON decodificado; lança RuntimeException com a
 * mensagem da SuperFrete em caso de erro (HTTP >= 400).
 */
function sf_request(string $method, string $path, ?array $body = null, int $timeout = 15)
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('A extensão cURL do PHP é necessária para a SuperFrete.');
    }
    if (!sf_token_ok()) {
        throw new RuntimeException('Token da SuperFrete não configurado (SUPERFRETE_TOKEN).');
    }
    $contact = defined('MAIL_FROM') && validate_email((string) MAIL_FROM) ? MAIL_FROM : 'contato@localhost';
    $ch = curl_init(sf_base_url() . ltrim($path, '/'));
    $opts = [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . SUPERFRETE_TOKEN,
            'User-Agent: King Decants 1.0 (' . $contact . ')',
            'Accept: application/json',
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_SSL_VERIFYPEER => true,
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        app_log('superfrete', 'Sem conexão', ['path' => $path, 'error' => $err]);
        throw new RuntimeException('Não foi possível conectar à SuperFrete (' . $err . ').');
    }
    $json = json_decode((string) $raw, true);
    if ($status >= 400 || $status === 0) {
        $msg = is_array($json) ? (string) ($json['message'] ?? $json['error_description'] ?? $json['error'] ?? '') : '';
        if (is_array($json) && $msg === '' && isset($json['errors'])) {
            $msg = is_array($json['errors']) ? implode('; ', array_map(fn($e) => is_array($e) ? implode(', ', array_map('strval', $e)) : (string) $e, $json['errors'])) : (string) $json['errors'];
        }
        app_log('superfrete', "Erro HTTP {$status}", ['path' => $path, 'body' => mb_substr((string) $raw, 0, 600)]);
        $hint = $status === 401 || $status === 403 ? ' Confira o token (produção x sandbox).' : '';
        throw new RuntimeException('SuperFrete recusou (' . $status . ')' . ($msg !== '' ? ': ' . $msg : '.') . $hint);
    }
    return $json;
}

// ===========================================================================
// COTAÇÃO
// ===========================================================================

/** Quantidade de itens (decants) na sacola. */
function sf_cart_quantity(array $cart): int
{
    $q = 0;
    foreach ($cart['items'] ?? [] as $it) {
        $q += (int) ($it['quantity'] ?? 0);
    }
    return max(1, $q);
}

/**
 * Cota o frete para o CEP. Retorna as opções (mais barata primeiro) ou null se
 * a SuperFrete falhar. Cada opção:
 *   id, name, company, price (já com margem), days_min, days_max, package
 * Resultado guardado na sessão por 20 min (mesmo CEP + mesma sacola = mesma cotação,
 * inclusive na hora de criar o pedido).
 */
function sf_quote(string $cep, int $quantity, float $productsValue): ?array
{
    $s = sf_settings();
    $cep = only_digits($cep);
    if (!validate_cep($cep) || sf_missing($s)) {
        return null;
    }
    $body = [
        'from'     => ['postal_code' => $s['origin_cep']],
        'to'       => ['postal_code' => $cep],
        'services' => implode(',', $s['services']),
        'options'  => [
            'own_hand'            => false,
            'receipt'             => false,
            'insurance_value'     => $s['insurance'] ? round($productsValue, 2) : 0,
            'use_insurance_value' => $s['insurance'],
        ],
        'products' => [[
            'quantity' => $quantity,
            'height'   => $s['height'],
            'width'    => $s['width'],
            'length'   => $s['length'],
            'weight'   => round($s['weight_g'] / 1000, 4),
        ]],
    ];
    $key = md5(json_encode($body) . '|' . $s['extra_fee'] . '|' . $s['extra_days'] . '|' . sf_base_url());
    $cache = $_SESSION['sf_quotes'][$key] ?? null;
    if ($cache && $cache['t'] > time() - 1200) {
        return $cache['o'];
    }

    try {
        $res = sf_request('POST', 'api/v0/calculator', $body, 10);
    } catch (Throwable $e) {
        return null;
    }
    if (!is_array($res)) {
        return null;
    }
    $options = [];
    foreach ($res as $r) {
        if (!is_array($r) || !empty($r['has_error']) || (float) ($r['price'] ?? 0) <= 0 || !isset($r['id'])) {
            continue;
        }
        $pkg = $r['packages'][0] ?? [];
        $min = (int) ($r['delivery_range']['min'] ?? $r['delivery_time'] ?? 0);
        $max = (int) ($r['delivery_range']['max'] ?? $r['delivery_time'] ?? $min);
        $options[] = [
            'id'       => (int) $r['id'],
            'name'     => (string) ($r['name'] ?? (SF_SERVICES[(int) $r['id']] ?? 'Envio')),
            'company'  => (string) ($r['company']['name'] ?? ''),
            'price'    => round((float) $r['price'] + $s['extra_fee'], 2),
            'days_min' => $min + $s['extra_days'],
            'days_max' => max($min, $max) + $s['extra_days'],
            'package'  => [
                'height' => (float) ($pkg['dimensions']['height'] ?? $s['height']),
                'width'  => (float) ($pkg['dimensions']['width'] ?? $s['width']),
                'length' => (float) ($pkg['dimensions']['length'] ?? $s['length']),
                'weight' => (float) ($pkg['weight'] ?? ($s['weight_g'] * $quantity / 1000)),
            ],
        ];
    }
    usort($options, fn($a, $b) => $a['price'] <=> $b['price'] ?: $a['days_max'] <=> $b['days_max']);

    // Guarda só as 5 cotações mais recentes na sessão
    $_SESSION['sf_quotes'] = array_slice(($_SESSION['sf_quotes'] ?? []), -4, null, true);
    $_SESSION['sf_quotes'][$key] = ['t' => time(), 'o' => $options];
    return $options;
}

function sf_days_label(int $min, int $max): string
{
    if ($max <= 0) {
        return '';
    }
    return $min > 0 && $min !== $max ? "{$min} a {$max} dias úteis" : "{$max} " . ($max === 1 ? 'dia útil' : 'dias úteis');
}

/**
 * Frete pela SuperFrete para o checkout. Retorna o mesmo formato de
 * shipping_quote() + 'options' e 'service_id', ou null para cair na tabela.
 * Frete grátis: a opção mais barata sai grátis e as demais cobram só a diferença.
 */
function sf_shipping(string $cep, array $cart, float $productsTotal, ?int $serviceId): ?array
{
    if (!sf_enabled()) {
        return null;
    }
    $options = sf_quote($cep, sf_cart_quantity($cart), $productsTotal);
    if (!$options) {
        return null;
    }
    $free = free_shipping_applies($productsTotal);
    $cheapest = $options[0]['price'];
    foreach ($options as &$o) {
        $o['charge'] = $free ? round(max(0, $o['price'] - $cheapest), 2) : $o['price'];
        $o['days'] = sf_days_label($o['days_min'], $o['days_max']);
    }
    unset($o);

    $selected = $options[0];
    foreach ($options as $o) {
        if ($serviceId !== null && $o['id'] === $serviceId) {
            $selected = $o;
        }
    }
    return [
        'price'        => $selected['charge'],
        'label'        => $selected['charge'] > 0 ? $selected['name'] : 'Frete grátis',
        'days'         => $selected['days'],
        'service_id'   => $selected['id'],
        'service_name' => $selected['name'],
        'package'      => $selected['package'],
        'options'      => $options,
        'source'       => 'superfrete',
        'free'         => $free,
    ];
}

// ===========================================================================
// ETIQUETAS (admin)
// ===========================================================================

function sf_order_package(array $order): array
{
    $pkg = json_decode((string) ($order['shipping_package'] ?? ''), true);
    if (is_array($pkg) && !empty($pkg['weight'])) {
        return $pkg;
    }
    // Pedido sem pacote salvo: estima pelas medidas cadastradas
    $s = sf_settings();
    $qty = 0;
    foreach (order_items((int) $order['id']) as $it) {
        $qty += (int) $it['quantity'];
    }
    return ['height' => $s['height'] * max(1, $qty), 'width' => $s['width'], 'length' => $s['length'], 'weight' => round($s['weight_g'] * max(1, $qty) / 1000, 4)];
}

/** Corta texto no limite de caracteres da SuperFrete. */
function sf_cut(?string $v, int $max): string
{
    return mb_substr(trim((string) $v), 0, $max);
}

/**
 * Cria a etiqueta (carrinho) e paga com o saldo. Atualiza o pedido com
 * sf_order_id, status, valor, rastreio e link do PDF.
 */
function sf_create_and_pay_label(array $order, ?int $serviceId = null): array
{
    $s = sf_settings();
    if ($miss = array_merge(sf_missing($s), sf_sender_missing($s))) {
        throw new RuntimeException('Complete em Admin → Frete: ' . implode(', ', $miss) . '.');
    }
    $service = $serviceId ?: (int) ($order['shipping_service_id'] ?? 0) ?: $s['services'][0];
    $pkg = sf_order_package($order);

    // 1) Cria a etiqueta (status pending), se ainda não existir uma ativa
    if (empty($order['sf_order_id']) || in_array($order['sf_status'], ['canceled', 'cancelled'], true)) {
        $products = [];
        foreach (order_items((int) $order['id']) as $it) {
            $products[] = ['name' => sf_cut($it['name'], 80), 'quantity' => (int) $it['quantity'], 'unitary_value' => round((float) $it['unit_price'], 2)];
        }
        $phone = only_digits((string) $order['customer_phone']);
        $productsValue = round((float) $order['subtotal'] - (float) ($order['discount'] ?? 0), 2);
        $body = [
            'from' => [
                'name'        => sf_cut($s['sender']['name'], 50),
                'address'     => sf_cut($s['sender']['address'], 50),
                'complement'  => sf_cut($s['sender']['complement'], 20),
                'number'      => sf_cut($s['sender']['number'], 10),
                'district'    => sf_cut($s['sender']['district'], 60) ?: 'NA',
                'city'        => sf_cut($s['sender']['city'], 50),
                'state_abbr'  => $s['sender']['state_abbr'],
                'postal_code' => $s['origin_cep'],
            ] + ($s['sender']['document'] !== '' ? ['document' => $s['sender']['document']] : []),
            'to' => [
                'name'        => sf_cut($order['customer_name'], 50),
                'address'     => sf_cut($order['street'], 50),
                'complement'  => sf_cut($order['complement'], 20),
                'number'      => sf_cut($order['number'], 10),
                'district'    => sf_cut($order['district'], 50) ?: 'NA',
                'city'        => sf_cut($order['city'], 50),
                'state_abbr'  => strtoupper((string) $order['state']),
                'postal_code' => only_digits((string) $order['cep']),
                'email'       => (string) $order['customer_email'],
                'document'    => only_digits((string) $order['customer_cpf']),
            ] + (strlen($phone) === 11 ? ['phone' => $phone] : []),
            'service'  => $service,
            'products' => $products,
            'volumes'  => [
                'height' => (float) $pkg['height'], 'width' => (float) $pkg['width'],
                'length' => (float) $pkg['length'], 'weight' => (float) $pkg['weight'],
            ],
            'options'  => [
                'insurance_value' => $s['insurance'] ? $productsValue : null,
                'receipt'         => false,
                'own_hand'        => false,
                'non_commercial'  => true, // declaração de conteúdo (sem nota fiscal)
                'tags'            => [['tag' => 'KD-' . $order['id'], 'url' => absolute_url('admin/order_view.php?id=' . $order['id'])]],
            ],
            'platform' => 'King Decants',
        ];
        $cart = sf_request('POST', 'api/v0/cart', $body, 25);
        if (empty($cart['id'])) {
            throw new RuntimeException('A SuperFrete não devolveu o número da etiqueta.');
        }
        db()->prepare('UPDATE orders SET sf_order_id = ?, sf_status = ?, sf_price = ?, shipping_service_id = COALESCE(shipping_service_id, ?) WHERE id = ?')
            ->execute([(string) $cart['id'], (string) ($cart['status'] ?? 'pending'), $cart['price'] ?? null, $service, $order['id']]);
        order_add_history_note((int) $order['id'], 'Etiqueta SuperFrete criada (' . $cart['id'] . ', ' . money((float) ($cart['price'] ?? 0)) . ').', 'admin');
        $order = order_find((int) $order['id']);
    }

    // 2) Paga com o saldo -> rastreio e PDF
    if ($order['sf_status'] === 'pending' || $order['sf_status'] === null) {
        $paid = sf_request('POST', 'api/v0/checkout', ['orders' => [(string) $order['sf_order_id']]], 25);
        $o = $paid['purchase']['orders'][0] ?? [];
        if (empty($paid['success'])) {
            throw new RuntimeException('Pagamento da etiqueta não confirmado (confira o saldo na SuperFrete).');
        }
        $tracking = trim((string) ($o['tracking'] ?? ''));
        db()->prepare('UPDATE orders SET sf_status = ?, sf_price = COALESCE(?, sf_price), sf_label_url = NULLIF(?, \'\'), tracking_code = COALESCE(NULLIF(?, \'\'), tracking_code) WHERE id = ?')
            ->execute(['released', $o['price'] ?? null, (string) ($o['print']['url'] ?? ''), $tracking, $order['id']]);
        order_add_history_note((int) $order['id'], 'Etiqueta paga com o saldo SuperFrete' . ($tracking ? " · rastreio {$tracking}" : '') . '.', 'admin');
    }
    return order_find((int) $order['id']);
}

/** Link do PDF da etiqueta (gera de novo se preciso). */
function sf_print_url(array $order): string
{
    if (empty($order['sf_order_id'])) {
        throw new RuntimeException('Este pedido ainda não tem etiqueta.');
    }
    $res = sf_request('POST', 'api/v0/tag/print', ['orders' => [(string) $order['sf_order_id']]]);
    $url = (string) ($res['url'] ?? '');
    if ($url === '') {
        throw new RuntimeException('A SuperFrete não devolveu o link da etiqueta.');
    }
    db()->prepare('UPDATE orders SET sf_label_url = ? WHERE id = ?')->execute([$url, $order['id']]);
    return $url;
}

/** Cancela a etiqueta (só antes da postagem; o valor volta ao saldo). */
function sf_cancel_label(array $order, string $reason = 'Cancelado pela loja'): void
{
    if (empty($order['sf_order_id'])) {
        throw new RuntimeException('Este pedido não tem etiqueta.');
    }
    $res = sf_request('POST', 'api/v0/order/cancel', ['order' => ['id' => (string) $order['sf_order_id'], 'description' => sf_cut($reason, 120) ?: 'Cancelado pela loja']]);
    $ok = is_array($res) && !empty($res[$order['sf_order_id']]['canceled']);
    if (!$ok) {
        throw new RuntimeException('A SuperFrete não confirmou o cancelamento.');
    }
    db()->prepare("UPDATE orders SET sf_status = 'canceled' WHERE id = ?")->execute([$order['id']]);
    if (!empty($order['tracking_code']) && $order['status'] === 'paid') {
        db()->prepare('UPDATE orders SET tracking_code = NULL WHERE id = ?')->execute([$order['id']]);
    }
    order_add_history_note((int) $order['id'], 'Etiqueta SuperFrete cancelada (valor devolvido ao saldo).', 'admin');
}

/**
 * Consulta a situação da etiqueta na SuperFrete e atualiza o pedido:
 *   posted    -> pedido "Enviado" (cliente recebe e-mail com rastreio)
 *   delivered -> pedido "Entregue"
 * Usada pelo botão "Atualizar" e pelo webhook (que nunca confia no conteúdo do aviso).
 */
function sf_sync(array $order, string $source = 'superfrete'): array
{
    if (empty($order['sf_order_id'])) {
        return $order;
    }
    $info = sf_request('GET', 'api/v0/order/info/' . rawurlencode((string) $order['sf_order_id']));
    if (!is_array($info)) {
        return $order;
    }
    $status = (string) ($info['status'] ?? '');
    $tracking = trim((string) ($info['tracking'] ?? ''));
    $label = (string) ($info['print']['url'] ?? '');
    db()->prepare('UPDATE orders SET sf_status = ?, tracking_code = COALESCE(NULLIF(?, \'\'), tracking_code), sf_label_url = COALESCE(NULLIF(?, \'\'), sf_label_url) WHERE id = ?')
        ->execute([$status ?: $order['sf_status'], $tracking, $label, $order['id']]);
    $order = order_find((int) $order['id']);

    if (in_array($status, ['posted', 'delivered'], true) && $order['status'] === 'paid') {
        order_change_status((int) $order['id'], 'shipped', $source, null, 'SuperFrete: postado' . ($tracking ? " · rastreio {$tracking}" : ''));
        $order = order_find((int) $order['id']);
    }
    if ($status === 'delivered' && $order['status'] === 'shipped') {
        order_change_status((int) $order['id'], 'delivered', $source, null, 'SuperFrete: entregue');
        $order = order_find((int) $order['id']);
    }
    return $order;
}

/** Saldo da conta SuperFrete (ou null). */
function sf_balance(): ?float
{
    try {
        $u = sf_request('GET', 'api/v0/user', null, 8);
        return isset($u['balance']) ? (float) $u['balance'] : null;
    } catch (Throwable $e) {
        return null;
    }
}

/** Cadastra (ou recria) o webhook apontando para webhook-superfrete.php. */
function sf_register_webhook(): array
{
    $url = absolute_url('webhook-superfrete.php');
    if (!str_starts_with($url, 'https://')) {
        throw new RuntimeException('O site precisa estar em https para receber avisos (APP_URL atual: ' . APP_URL . ').');
    }
    $old = setting('sf_webhook_id');
    if ($old) {
        try {
            sf_request('DELETE', 'api/v0/webhook/' . rawurlencode($old));
        } catch (Throwable $e) {
            // já removido: segue
        }
    }
    $res = sf_request('POST', 'api/v0/webhook', [
        'name'   => 'King Decants',
        'url'    => $url,
        'events' => ['order.released', 'order.posted', 'order.delivered', 'order.cancelled'],
    ]);
    if (empty($res['id'])) {
        throw new RuntimeException('A SuperFrete não confirmou o cadastro do webhook.');
    }
    setting_set('sf_webhook_id', (string) $res['id']);
    return $res;
}

/** Nome do status da etiqueta em português. */
function sf_status_label(?string $s): string
{
    return [
        'pending'   => 'Aguardando pagamento',
        'released'  => 'Paga · aguardando postagem',
        'posted'    => 'Postada',
        'delivered' => 'Entregue',
        'canceled'  => 'Cancelada',
        'cancelled' => 'Cancelada',
    ][(string) $s] ?? ((string) $s ?: '—');
}
