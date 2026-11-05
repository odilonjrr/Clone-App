<?php
/**
 * King Decants — inc/orders.php
 * ---------------------------------------------------------------------------
 * Regras de negócio de pedidos:
 *   - Criação do pedido (status "pending") ANTES de enviar ao Mercado Pago,
 *     para termos um external_reference (= id do pedido) que o MP devolve.
 *   - Máquina de estados de status + histórico (order_status_history).
 *   - Baixa/estorno de estoque idempotente (flag orders.stock_applied).
 *   - Processamento de um pagamento do MP (usado por webhook.php e callback.php).
 * ---------------------------------------------------------------------------
 */

/**
 * Transições permitidas. Evita, por exemplo, que um webhook atrasado
 * "volte" um pedido já enviado para "pago".
 */
function order_allowed_transitions(): array
{
    return [
        'pending'   => ['paid', 'cancelled'],
        'paid'      => ['shipped', 'cancelled'],
        'shipped'   => ['delivered'],
        'delivered' => [],
        // Pagamento aprovado após cancelamento por expiração (ex.: PIX pago no limite) reabre o pedido.
        'cancelled' => ['paid'],
    ];
}

function order_can_transition(string $from, string $to): bool
{
    return in_array($to, order_allowed_transitions()[$from] ?? [], true);
}

/**
 * Cria (ou reaproveita) o registro de cliente para o mini-CRM.
 * - Se já existe usuário com o e-mail, retorna o id dele (completa CPF/telefone apenas se for convidado).
 * - Senão, cria um registro "convidado" (is_guest = 1, sem senha).
 */
function customer_upsert(string $name, string $email, string $phone, string $cpf): int
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, is_guest FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user) {
        // Só completa dados de registros de convidado: um checkout como convidado não pode
        // gravar telefone/CPF numa conta registrada (quem digita o e-mail pode não ser o dono).
        if ((int) $user['is_guest']) {
            $pdo->prepare('UPDATE users SET phone = COALESCE(NULLIF(phone, \'\'), ?), cpf = COALESCE(cpf, ?) WHERE id = ?')
                ->execute([$phone, $cpf, $user['id']]);
        }
        return (int) $user['id'];
    }
    $pdo->prepare('INSERT INTO users (name, email, phone, cpf, is_guest) VALUES (?, ?, ?, ?, 1)')
        ->execute([$name, $email, $phone, $cpf]);
    return (int) $pdo->lastInsertId();
}

/**
 * Cria pedido + itens numa transação.
 *
 * @param array $customer  campos validados do checkout
 * @param array $cart      resultado de cart_items_detailed()
 * @param array $shipping  resultado de shipping_quote()
 * @param string $method   'pix' | 'card'
 * @param bool $isGuest    pedido feito sem login
 * @return array           ['id' => int, 'access_token' => string, 'total' => float]
 */
function order_create(array $customer, array $cart, array $shipping, string $method, bool $isGuest, ?string $couponCode = null): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        // RESERVA de estoque: revalida com lock de linha e já baixa a quantidade dentro da
        // mesma transação. Assim dois clientes não conseguem gerar pagamento para a última
        // unidade. A reserva é devolvida se o pedido for cancelado (inclusive por expiração,
        // ver orders_expire_stale()).
        // Com TAMANHOS: o estoque controlado é o do tamanho; o do produto (soma) acompanha.
        foreach ($cart['items'] as $item) {
            $qty = (int) $item['quantity'];
            $vid = (int) ($item['variant_id'] ?? 0);
            $label = $item['name'] . ($vid ? ' — ' . $item['variant_label'] : '');
            if ($vid) {
                $stmt = $pdo->prepare('SELECT stock FROM product_variants WHERE id = ? AND product_id = ? FOR UPDATE');
                $stmt->execute([$vid, $item['id']]);
            } else {
                $stmt = $pdo->prepare('SELECT stock FROM products WHERE id = ? FOR UPDATE');
                $stmt->execute([$item['id']]);
            }
            if ((int) $stmt->fetchColumn() < $qty) {
                throw new RuntimeException('Estoque insuficiente para "' . $label . '".');
            }
            stock_adjust((int) $item['id'], $vid, -$qty);
        }

        $userId = $isGuest
            ? customer_upsert($customer['name'], $customer['email'], $customer['phone'], $customer['cpf'])
            : (int) $_SESSION['user_id'];

        $subtotal = (float) $cart['subtotal'];
        $shippingPrice = (float) $shipping['price'];

        // Cupom: revalida com a linha travada (limite de usos não estoura em pedidos
        // simultâneos) e já conta o uso; o uso é devolvido se o pedido for cancelado.
        $discount = 0.0;
        $appliedCode = null;
        if ($couponCode) {
            $cs = $pdo->prepare('SELECT * FROM coupons WHERE code = ? FOR UPDATE');
            $cs->execute([coupon_normalize($couponCode)]);
            $coupon = $cs->fetch() ?: null;
            if ($err = coupon_error($coupon, $subtotal)) {
                throw new RuntimeException('Cupom: ' . $err);
            }
            $discount = coupon_discount($coupon, $subtotal);
            $appliedCode = $coupon['code'];
            $pdo->prepare('UPDATE coupons SET used_count = used_count + 1 WHERE id = ?')->execute([$coupon['id']]);
        }
        $total = round($subtotal - $discount + $shippingPrice, 2);
        $token = random_token(20);

        $pdo->prepare(
            'INSERT INTO orders (user_id, is_guest_order, access_token, status, payment_method,
                customer_name, customer_email, customer_phone, customer_cpf,
                cep, street, number, complement, district, city, state,
                subtotal, discount, coupon_code, shipping, shipping_service_id, shipping_service, shipping_package, total, stock_applied)
             VALUES (?, ?, ?, \'pending\', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)'
        )->execute([
            $userId, $isGuest ? 1 : 0, $token, $method,
            $customer['name'], $customer['email'], $customer['phone'], $customer['cpf'],
            $customer['cep'], $customer['street'], $customer['number'], $customer['complement'] ?: null,
            $customer['district'], $customer['city'], $customer['state'],
            $subtotal, $discount, $appliedCode, $shippingPrice,
            $shipping['service_id'] ?? null, isset($shipping['service_name']) ? mb_substr($shipping['service_name'], 0, 60) : null,
            isset($shipping['package']) ? json_encode($shipping['package']) : null, $total,
        ]);
        $orderId = (int) $pdo->lastInsertId();

        $itemStmt = $pdo->prepare(
            'INSERT INTO order_items (order_id, product_id, variant_id, sku, name, variant_label, unit_price, quantity, line_total)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($cart['items'] as $item) {
            $vid = (int) ($item['variant_id'] ?? 0);
            // O nome gravado já inclui o tamanho ("Decants Básicos — 10 ml"): aparece assim
            // no pedido, no e-mail, no admin e na página de pagamento.
            $name = $item['name'] . ($vid ? ' — ' . $item['variant_label'] : '');
            $itemStmt->execute([$orderId, $item['id'], $vid ?: null, $item['sku'], $name, $item['variant_label'] ?? null,
                                $item['price'], $item['quantity'], $item['line_total']]);
        }

        $pdo->prepare('INSERT INTO order_status_history (order_id, from_status, to_status, source, note) VALUES (?, NULL, \'pending\', \'system\', ?)')
            ->execute([$orderId, 'Pedido criado (' . ['pix' => 'PIX', 'card' => 'Cartão / Checkout Pro', 'infinitepay' => 'InfinitePay'][$method] ?? $method . ')']);

        $pdo->commit();
        return ['id' => $orderId, 'access_token' => $token, 'total' => $total];
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Ajusta estoque (delta negativo baixa, positivo devolve). Com tamanho, ajusta o
 * tamanho e também o total do produto; nunca fica abaixo de zero.
 */
function stock_adjust(int $productId, int $variantId, int $delta): void
{
    $pdo = db();
    if ($variantId) {
        $pdo->prepare('UPDATE product_variants SET stock = GREATEST(stock + ?, 0) WHERE id = ?')->execute([$delta, $variantId]);
    }
    $pdo->prepare('UPDATE products SET stock = GREATEST(stock + ?, 0) WHERE id = ?')->execute([$delta, $productId]);
}

function order_find(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/** Busca pedido pelo id + token secreto (acesso do convidado sem login). */
function order_find_by_token(int $id, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{40}$/', $token)) {
        return null;
    }
    $order = order_find($id);
    return ($order && hash_equals($order['access_token'], $token)) ? $order : null;
}

function order_items(int $orderId): array
{
    $stmt = db()->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id');
    $stmt->execute([$orderId]);
    return $stmt->fetchAll();
}

/**
 * Altera o status do pedido, registra histórico e ajusta estoque.
 *
 * @param string $source system | webhook | callback | admin
 * @return bool  false se a transição não for permitida (nada é alterado)
 */
function order_change_status(int $orderId, string $to, string $source, ?int $adminId = null, ?string $note = null): bool
{
    $pdo = db();
    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) {
        $pdo->beginTransaction();
    }
    try {
        $stmt = $pdo->prepare('SELECT id, status, stock_applied, coupon_code FROM orders WHERE id = ? FOR UPDATE');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order || $order['status'] === $to || !order_can_transition($order['status'], $to)) {
            if ($ownTransaction) {
                $pdo->commit();
            }
            return false;
        }

        $timestampColumn = [
            'paid' => 'paid_at', 'shipped' => 'shipped_at', 'delivered' => 'delivered_at', 'cancelled' => 'cancelled_at',
        ][$to] ?? null;

        $sql = 'UPDATE orders SET status = ?' . ($timestampColumn ? ", {$timestampColumn} = NOW()" : '') . ' WHERE id = ?';
        $pdo->prepare($sql)->execute([$to, $orderId]);

        // Estoque: reservado na criação do pedido (stock_applied = 1).
        //  - pago: contabiliza a venda (sold_count). Se a reserva tinha sido liberada
        //    (pedido cancelado e depois pago), reserva de novo e avisa se faltar estoque.
        //  - cancelado: devolve a reserva; se estava pago, desfaz a contagem de venda.
        $items = order_items($orderId);
        if ($to === 'paid') {
            foreach ($items as $it) {
                if (!$it['product_id']) {
                    continue;
                }
                if (!(int) $order['stock_applied']) {
                    $vid = (int) ($it['variant_id'] ?? 0);
                    $s = $vid
                        ? $pdo->prepare('SELECT stock FROM product_variants WHERE id = ? FOR UPDATE')
                        : $pdo->prepare('SELECT stock FROM products WHERE id = ? FOR UPDATE');
                    $s->execute([$vid ?: $it['product_id']]);
                    if ((int) $s->fetchColumn() < (int) $it['quantity']) {
                        $note = trim(($note ?? '') . ' · ATENÇÃO: estoque insuficiente para ' . $it['sku'] . ', verificar.');
                        app_log('payments', 'Pedido pago sem estoque reservado', ['order' => $orderId, 'sku' => $it['sku']]);
                    }
                    stock_adjust((int) $it['product_id'], $vid, -(int) $it['quantity']);
                }
                $pdo->prepare('UPDATE products SET sold_count = sold_count + ? WHERE id = ?')->execute([$it['quantity'], $it['product_id']]);
            }
            $pdo->prepare('UPDATE orders SET stock_applied = 1 WHERE id = ?')->execute([$orderId]);
        } elseif ($to === 'cancelled') {
            foreach ($items as $it) {
                if (!$it['product_id']) {
                    continue;
                }
                if ((int) $order['stock_applied']) {
                    stock_adjust((int) $it['product_id'], (int) ($it['variant_id'] ?? 0), (int) $it['quantity']);
                }
                if ($order['status'] === 'paid') {
                    $pdo->prepare('UPDATE products SET sold_count = GREATEST(CAST(sold_count AS SIGNED) - ?, 0) WHERE id = ?')->execute([$it['quantity'], $it['product_id']]);
                }
            }
            $pdo->prepare('UPDATE orders SET stock_applied = 0 WHERE id = ?')->execute([$orderId]);
        }

        // Uso do cupom: devolvido ao cancelar; contado de novo se um cancelado for pago.
        if (!empty($order['coupon_code'])) {
            if ($to === 'cancelled') {
                $pdo->prepare('UPDATE coupons SET used_count = GREATEST(CAST(used_count AS SIGNED) - 1, 0) WHERE code = ?')->execute([$order['coupon_code']]);
            } elseif ($to === 'paid' && $order['status'] === 'cancelled') {
                $pdo->prepare('UPDATE coupons SET used_count = used_count + 1 WHERE code = ?')->execute([$order['coupon_code']]);
            }
        }

        $pdo->prepare('INSERT INTO order_status_history (order_id, from_status, to_status, source, admin_id, note) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$orderId, $order['status'], $to, $source, $adminId, $note ? mb_substr($note, 0, 255) : null]);

        if ($ownTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($ownTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    // Notificações por e-mail (fora da transação; falha de e-mail não quebra o fluxo).
    try {
        $fresh = order_find($orderId);
        if ($fresh) {
            mail_order_status($fresh, $source, $note);
        }
    } catch (Throwable $e) {
        app_log('mail', 'Falha ao enviar e-mail de status', ['order' => $orderId, 'error' => $e->getMessage()]);
    }
    return true;
}

/**
 * Cancela pedidos "pending" mais antigos que $hours horas e libera a reserva de estoque.
 * 24h acompanha a expiração da preferência do Checkout Pro (o PIX expira em 30 min).
 * Executada de forma oportunista (início do checkout e dashboard do admin), sem cron.
 * Se um pagamento for aprovado depois, o pedido reabre (cancelled -> paid).
 */
function orders_expire_stale(int $hours = 24): int
{
    $stmt = db()->prepare("SELECT id FROM orders WHERE status = 'pending' AND created_at < (NOW() - INTERVAL ? HOUR) LIMIT 50");
    $stmt->execute([$hours]);
    $count = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
        if (order_change_status((int) $id, 'cancelled', 'system', null, "Expirado: sem pagamento em {$hours}h (reserva de estoque liberada)")) {
            $count++;
        }
    }
    return $count;
}

/** Converte status do Mercado Pago no status do pedido (ou null = não muda). */
function mp_status_to_order_status(string $mpStatus): ?string
{
    switch ($mpStatus) {
        case 'approved':
            return 'paid';
        case 'rejected':
        case 'cancelled':
        case 'refunded':
        case 'charged_back':
            return 'cancelled';
        default:
            // pending, in_process, in_mediation, authorized -> continua aguardando
            return null;
    }
}

/**
 * Processa um pagamento consultado na API do MP (/v1/payments/{id}).
 * Idempotente: pode ser chamado várias vezes para o mesmo pagamento.
 *
 * Regra importante: um pagamento "rejected" NÃO cancela o pedido se o cliente
 * ainda pode tentar outro meio (Checkout Pro permite nova tentativa). Só
 * cancelamos em cancelled/refunded/charged_back. Rejeições ficam registradas
 * em payments e o pedido segue "pending".
 *
 * @return array ['ok' => bool, 'message' => string, 'order_id' => ?int]
 */
function payment_process_mp(array $mp, string $source): array
{
    $orderId = (int) ($mp['external_reference'] ?? 0);
    $order = $orderId ? order_find($orderId) : null;
    if (!$order) {
        return ['ok' => false, 'message' => 'Pedido não encontrado para external_reference', 'order_id' => null];
    }

    $mpId = (string) ($mp['id'] ?? '');
    $status = (string) ($mp['status'] ?? 'unknown');
    $amount = (float) ($mp['transaction_amount'] ?? 0);

    // Segurança: o valor pago precisa bater com o total do pedido (tolerância de centavos).
    if ($status === 'approved' && abs($amount - (float) $order['total']) > 0.05) {
        app_log('payments', 'Valor divergente', ['order' => $orderId, 'mp_amount' => $amount, 'order_total' => $order['total']]);
        order_add_history_note($orderId, 'Pagamento aprovado com valor divergente (' . money($amount) . '). Verificar manualmente.', $source);
        payment_upsert($orderId, $mp);
        return ['ok' => false, 'message' => 'Valor divergente', 'order_id' => $orderId];
    }

    payment_upsert($orderId, $mp);

    $target = mp_status_to_order_status($status);
    if ($status === 'rejected') {
        $target = null; // ver regra acima
    }
    // Um pedido pode ter várias tentativas (PIX regenerado, troca cartão -> PIX).
    // Se OUTRA tentativa já foi aprovada, o cancelamento/expiração desta não cancela o pedido.
    // (Estorno/chargeback da tentativa aprovada atualiza a própria linha, então cancela normalmente.)
    if ($target === 'cancelled') {
        $other = db()->prepare("SELECT COUNT(*) FROM payments WHERE order_id = ? AND status = 'approved' AND mp_payment_id <> ?");
        $other->execute([$orderId, $mpId]);
        if ((int) $other->fetchColumn() > 0) {
            $target = null;
        }
    }
    $changed = false;
    if ($target) {
        $changed = order_change_status($orderId, $target, $source, null, "Mercado Pago: {$status}" . (!empty($mp['status_detail']) ? " ({$mp['status_detail']})" : '') . " · pagamento #{$mpId}");
    }
    return ['ok' => true, 'message' => $changed ? "Pedido atualizado para {$target}" : "Status MP {$status} registrado", 'order_id' => $orderId];
}

/** Insere/atualiza a linha de payments a partir do objeto do MP. */
function payment_upsert(int $orderId, array $mp): void
{
    $tx = $mp['point_of_interaction']['transaction_data'] ?? [];
    $expires = !empty($mp['date_of_expiration']) ? date('Y-m-d H:i:s', strtotime($mp['date_of_expiration'])) : null;

    db()->prepare(
        'INSERT INTO payments (order_id, mp_payment_id, method, status, status_detail, amount,
                               pix_qr_code, pix_qr_code_base64, pix_ticket_url, pix_expires_at, raw_response)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE status = VALUES(status), status_detail = VALUES(status_detail),
                                 method = VALUES(method), amount = VALUES(amount),
                                 pix_qr_code = COALESCE(VALUES(pix_qr_code), pix_qr_code),
                                 pix_qr_code_base64 = COALESCE(VALUES(pix_qr_code_base64), pix_qr_code_base64),
                                 pix_ticket_url = COALESCE(VALUES(pix_ticket_url), pix_ticket_url),
                                 pix_expires_at = COALESCE(VALUES(pix_expires_at), pix_expires_at),
                                 raw_response = VALUES(raw_response)'
    )->execute([
        $orderId,
        (string) ($mp['id'] ?? ''),
        $mp['payment_method_id'] ?? ($mp['payment_type_id'] ?? null),
        (string) ($mp['status'] ?? 'unknown'),
        $mp['status_detail'] ?? null,
        (float) ($mp['transaction_amount'] ?? 0),
        $tx['qr_code'] ?? null,
        $tx['qr_code_base64'] ?? null,
        $tx['ticket_url'] ?? null,
        $expires,
        json_encode($mp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
}

/** Registra uma linha informativa no histórico sem mudar o status. */
function order_add_history_note(int $orderId, string $note, string $source = 'system', ?int $adminId = null): void
{
    $order = order_find($orderId);
    if (!$order) {
        return;
    }
    db()->prepare('INSERT INTO order_status_history (order_id, from_status, to_status, source, admin_id, note) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$orderId, $order['status'], $order['status'], $source, $adminId, mb_substr($note, 0, 255)]);
}

/** Pagamento PIX mais recente de um pedido (para exibir QR code). */
function order_latest_pix(int $orderId): ?array
{
    $stmt = db()->prepare("SELECT * FROM payments WHERE order_id = ? AND pix_qr_code IS NOT NULL ORDER BY id DESC LIMIT 1");
    $stmt->execute([$orderId]);
    return $stmt->fetch() ?: null;
}
