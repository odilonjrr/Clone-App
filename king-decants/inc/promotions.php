<?php
/**
 * King Decants — inc/promotions.php
 * ---------------------------------------------------------------------------
 * FRETE GRÁTIS (Admin -> Frete) e CUPONS DE DESCONTO (Admin -> Cupons).
 *
 * Frete grátis — modos (tabela settings):
 *   off    -> sem frete grátis (tabela de frete normal)
 *   all    -> frete grátis em TODOS os pedidos
 *   above  -> frete grátis quando o valor dos produtos (já com desconto do
 *             cupom) for igual ou maior que "free_shipping_from"
 *   Sem configuração salva, vale o FREE_SHIPPING_FROM do config (padrão antigo).
 *
 * Cupons: código, tipo (percent | fixed), valor e, opcionalmente, pedido
 * mínimo, limite de usos e validade. O cliente aplica no checkout; o desconto é
 * SEMPRE recalculado e revalidado no servidor ao criar o pedido.
 * ---------------------------------------------------------------------------
 */

// ===========================================================================
// FRETE GRÁTIS
// ===========================================================================

/** Regra atual: ['mode' => off|all|above, 'from' => float] */
function free_shipping_rule(): array
{
    $mode = setting('free_shipping_mode');
    if ($mode === null) {
        // Ainda não configurado no admin: mantém o comportamento do config.php.
        return FREE_SHIPPING_FROM > 0 ? ['mode' => 'above', 'from' => (float) FREE_SHIPPING_FROM] : ['mode' => 'off', 'from' => 0.0];
    }
    $from = (float) setting('free_shipping_from', '0');
    if (!in_array($mode, ['off', 'all', 'above'], true) || ($mode === 'above' && $from <= 0)) {
        $mode = 'off';
    }
    return ['mode' => $mode, 'from' => $from];
}

/** O pedido com este valor de produtos tem frete grátis? */
function free_shipping_applies(float $productsTotal): bool
{
    $r = free_shipping_rule();
    return $r['mode'] === 'all' || ($r['mode'] === 'above' && $productsTotal >= $r['from']);
}

/** Texto para a loja ("Frete grátis em todos os pedidos" / "... acima de R$ X") ou null. */
function free_shipping_text(): ?string
{
    $r = free_shipping_rule();
    if ($r['mode'] === 'all') {
        return 'Frete grátis em todos os pedidos';
    }
    if ($r['mode'] === 'above') {
        return 'Frete grátis acima de ' . money($r['from']);
    }
    return null;
}

/** Quanto falta para o frete grátis (null se não se aplica). */
function free_shipping_missing(float $productsTotal): ?float
{
    $r = free_shipping_rule();
    if ($r['mode'] !== 'above' || $productsTotal >= $r['from']) {
        return null;
    }
    return round($r['from'] - $productsTotal, 2);
}

// ===========================================================================
// CUPONS
// ===========================================================================

/** Normaliza o código digitado: maiúsculas, só letras, números, - e _. */
function coupon_normalize(string $code): string
{
    return strtoupper(substr(preg_replace('/[^A-Za-z0-9_\-]/', '', $code) ?? '', 0, 30));
}

function coupon_find(string $code): ?array
{
    $code = coupon_normalize($code);
    if ($code === '') {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM coupons WHERE code = ?');
    $stmt->execute([$code]);
    return $stmt->fetch() ?: null;
}

/**
 * Valida o cupom para um subtotal de produtos.
 * @return string|null  mensagem de erro amigável, ou null se válido
 */
function coupon_error(?array $coupon, float $subtotal): ?string
{
    if (!$coupon || !(int) $coupon['is_active']) {
        return 'Cupom inválido ou inativo.';
    }
    $now = date('Y-m-d H:i:s');
    if ($coupon['starts_at'] && $now < $coupon['starts_at']) {
        return 'Este cupom ainda não está valendo.';
    }
    if ($coupon['expires_at'] && $now > $coupon['expires_at']) {
        return 'Este cupom expirou.';
    }
    if ($coupon['max_uses'] !== null && (int) $coupon['used_count'] >= (int) $coupon['max_uses']) {
        return 'Este cupom atingiu o limite de usos.';
    }
    if ($coupon['min_subtotal'] !== null && $subtotal < (float) $coupon['min_subtotal']) {
        return 'Este cupom vale para compras a partir de ' . money($coupon['min_subtotal']) . '.';
    }
    return null;
}

/** Valor do desconto (nunca maior que o subtotal dos produtos). */
function coupon_discount(array $coupon, float $subtotal): float
{
    $d = $coupon['type'] === 'percent'
        ? round($subtotal * min(100, (float) $coupon['value']) / 100, 2)
        : (float) $coupon['value'];
    return max(0.0, min(round($d, 2), $subtotal));
}

/** Descrição curta do cupom ("10% OFF" / "R$ 20,00 OFF"). */
function coupon_label(array $coupon): string
{
    return $coupon['type'] === 'percent'
        ? rtrim(rtrim(number_format((float) $coupon['value'], 2, ',', ''), '0'), ',') . '% OFF'
        : money($coupon['value']) . ' OFF';
}

/**
 * Totais do pedido a partir da sacola, do cupom aplicado (sessão) e do CEP.
 * Se o cupom da sessão deixou de valer, ele é removido e o motivo retornado.
 * @return array subtotal, discount, coupon (array|null), coupon_error, products_total, shipping (quote), total
 */
function order_totals(array $cart, string $cep = ''): array
{
    $subtotal = (float) $cart['subtotal'];
    $coupon = null;
    $error = null;
    $code = $_SESSION['coupon_code'] ?? '';
    if ($code !== '') {
        $c = coupon_find($code);
        $error = coupon_error($c, $subtotal);
        if ($error) {
            unset($_SESSION['coupon_code']);
        } else {
            $coupon = $c;
        }
    }
    $discount = $coupon ? coupon_discount($coupon, $subtotal) : 0.0;
    $productsTotal = round($subtotal - $discount, 2);
    $shipping = shipping_quote($cep, $productsTotal);
    return [
        'subtotal'       => $subtotal,
        'discount'       => $discount,
        'coupon'         => $coupon,
        'coupon_error'   => $error,
        'products_total' => $productsTotal,
        'shipping'       => $shipping,
        'total'          => round($productsTotal + (float) $shipping['price'], 2),
    ];
}
