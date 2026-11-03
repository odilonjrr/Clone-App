<?php
/**
 * King Decants — callback.php (Retorno do Mercado Pago + página do pedido)
 * ---------------------------------------------------------------------------
 * Usado como:
 *   1. back_urls do Checkout Pro (success | failure | pending). O MP acrescenta
 *      parâmetros como payment_id, collection_status, external_reference.
 *   2. Página do pedido para o cliente (inclusive convidado), protegida pelo
 *      token secreto do pedido: callback.php?order=ID&token=TOKEN
 *   3. Página do PIX: exibe QR code + "copia e cola" e faz polling do status.
 *   4. ?format=json -> {"status": "..."} para o polling do PIX.
 *
 * IMPORTANTE: os parâmetros da URL de retorno podem ser forjados. Por isso,
 * quando há payment_id, CONSULTAMOS a API do MP para obter o status real.
 * A fonte principal de atualização continua sendo o webhook.php.
 * ---------------------------------------------------------------------------
 */
require __DIR__ . '/inc/bootstrap.php';

$orderId = int_param($_GET, 'order');
$token = (string) ($_GET['token'] ?? '');

// Retorno do MP sem nossos parâmetros (raro): tenta pelo external_reference + sessão.
if (!$orderId && !empty($_GET['external_reference']) && !empty($_SESSION['last_order'])) {
    if ((int) $_GET['external_reference'] === (int) $_SESSION['last_order']['id']) {
        $orderId = (int) $_SESSION['last_order']['id'];
        $token = $_SESSION['last_order']['token'];
    }
}

$order = $orderId ? order_find_by_token($orderId, $token) : null;
if (!$order) {
    http_response_code(404);
    if (($_GET['format'] ?? '') === 'json') {
        json_response(['ok' => false], 404);
    }
    $pageTitle = 'Pedido não encontrado — King Decants';
    require __DIR__ . '/inc/header.php';
    echo '<section class="mx-auto max-w-xl px-5 py-32 text-center"><p class="eyebrow">Pedido</p><h1 class="mt-4 font-serif text-4xl text-white">Pedido não encontrado</h1><p class="mt-3 text-ink-400">Confira o link recebido por e-mail.</p><a class="btn-gold mt-8" href="' . e(url('index.php')) . '">Voltar à loja</a></section>';
    require __DIR__ . '/inc/footer.php';
    exit;
}

// ---------------------------------------------------- Polling (JSON)
if (($_GET['format'] ?? '') === 'json') {
    json_response(['ok' => true, 'status' => $order['status']]);
}

// ---------------------------------------------------- Gerar novo PIX (POST)
$isInfinitePay = ($order['payment_provider'] ?? '') === 'infinitepay';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'new_pix') {
    csrf_require();
    if ($order['status'] === 'pending' && !$isInfinitePay) {
        try {
            payment_upsert($order['id'], mp_create_pix_payment($order));
            db()->prepare("UPDATE orders SET payment_method = 'pix' WHERE id = ?")->execute([$order['id']]);
            flash('success', 'Novo código PIX gerado.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
    }
    redirect(url('callback.php?order=' . $order['id'] . '&token=' . $order['access_token']));
}

// ---------------------------------------------------- Retorno da InfinitePay
// A InfinitePay volta com order_nsu, transaction_nsu, slug, capture_method e
// receipt_url. Esses parâmetros podem ser forjados: confirmamos com payment_check.
if ($isInfinitePay && $order['status'] === 'pending' && ip_is_configured()
    && (isset($_GET['transaction_nsu']) || isset($_GET['slug']) || isset($_GET['order_nsu']))) {
    try {
        $check = ip_payment_check(ip_order_nsu($order), (string) ($_GET['transaction_nsu'] ?? ''), (string) ($_GET['slug'] ?? ''));
        if ($check) {
            ip_process_payment($order, $check, [
                'transaction_nsu' => (string) ($_GET['transaction_nsu'] ?? ''),
                'slug'            => (string) ($_GET['slug'] ?? ''),
                'receipt_url'     => (string) ($_GET['receipt_url'] ?? ''),
            ], 'callback');
            $order = order_find($order['id']);
        }
    } catch (Throwable $e) {
        app_log('callback', 'InfinitePay: erro ao confirmar no retorno', ['error' => $e->getMessage()]);
    }
}

// ---------------------------------------------------- Retorno do Checkout Pro (Mercado Pago)
$mpPaymentId = (string) ($_GET['payment_id'] ?? ($_GET['collection_id'] ?? ''));
if (!$isInfinitePay && $mpPaymentId !== '' && $mpPaymentId !== 'null' && mp_is_configured()) {
    try {
        $payment = mp_get_payment($mpPaymentId);
        // Só processa se o pagamento pertence a ESTE pedido.
        if ($payment && (int) ($payment['external_reference'] ?? 0) === (int) $order['id']) {
            payment_process_mp($payment, 'callback');
            $order = order_find($order['id']);
        }
    } catch (Throwable $e) {
        app_log('callback', 'Erro ao consultar pagamento no retorno', ['error' => $e->getMessage()]);
    }
}

$items = order_items($order['id']);
$pix = ($order['status'] === 'pending') ? order_latest_pix($order['id']) : null;
$pixExpired = $pix && $pix['pix_expires_at'] && strtotime($pix['pix_expires_at']) < time();
$returnResult = $_GET['result'] ?? '';

$headline = [
    'pending'   => ['Aguardando pagamento', 'Assim que o pagamento for confirmado, você receberá um e-mail.'],
    'paid'      => ['Pagamento confirmado', 'Obrigado! Seu pedido já está sendo preparado.'],
    'shipped'   => ['Pedido enviado', 'Seu pedido está a caminho.'],
    'delivered' => ['Pedido entregue', 'Esperamos que cada gota seja memorável.'],
    'cancelled' => ['Pedido cancelado', 'Se acredita que isso é um erro, fale com a gente.'],
][$order['status']];

if ($order['status'] === 'pending' && $returnResult === 'failure') {
    $headline = ['Pagamento não concluído', 'O pagamento foi recusado ou cancelado. Você pode tentar novamente abaixo.'];
}

$selfUrl = url('callback.php?order=' . $order['id'] . '&token=' . $order['access_token']);
$pageTitle = 'Pedido #' . $order['id'] . ' — King Decants';
require __DIR__ . '/inc/header.php';
?>

<section class="mx-auto max-w-5xl px-5 py-12 lg:py-16">
    <div class="text-center animate-fade-up">
        <span class="mx-auto inline-flex h-16 w-16 items-center justify-center rounded-full border border-gold-400/40 text-gold-400">
            <?= icon(in_array($order['status'], ['paid', 'shipped', 'delivered'], true) ? 'check' : ($order['status'] === 'cancelled' ? 'close' : 'info'), 'h-8 w-8') ?>
        </span>
        <p class="eyebrow mt-6">Pedido #<?= (int) $order['id'] ?></p>
        <h1 class="mt-3 font-serif text-4xl text-white sm:text-5xl"><?= e($headline[0]) ?></h1>
        <p class="mt-3 text-ink-400"><?= e($headline[1]) ?></p>
        <?php
        // Comprovante da InfinitePay (quando disponível)
        $receiptStmt = db()->prepare('SELECT receipt_url FROM payments WHERE order_id = ? AND receipt_url IS NOT NULL AND status = \'approved\' ORDER BY id DESC LIMIT 1');
        $receiptStmt->execute([$order['id']]);
        $receiptUrl = $receiptStmt->fetchColumn();
        ?>
        <?php if ($receiptUrl): ?>
            <a href="<?= e($receiptUrl) ?>" target="_blank" rel="noopener" class="mt-3 inline-block text-xs uppercase tracking-[0.2em] text-gold-400 hover:text-gold-300">Ver comprovante</a>
        <?php endif; ?>
        <div class="mt-4"><?= status_badge($order['status']) ?></div>
    </div>

    <?php if ($order['status'] === 'pending'): ?>
        <!-- ======================= PIX: QR CODE + COPIA E COLA ======================= -->
        <?php if ($pix && !$pixExpired): ?>
            <div class="panel mx-auto mt-12 max-w-xl p-8 text-center animate-fade-up" data-poll-status="<?= e($selfUrl . '&format=json') ?>">
                <h2 class="text-xs font-medium uppercase tracking-[0.24em] text-white">Pague com PIX</h2>
                <?php if ($pix['pix_qr_code_base64']): ?>
                    <div class="mx-auto mt-6 w-fit rounded-xl bg-white p-3">
                        <img src="data:image/png;base64,<?= e($pix['pix_qr_code_base64']) ?>" alt="QR code PIX" class="h-56 w-56">
                    </div>
                <?php endif; ?>
                <ol class="mx-auto mt-6 max-w-sm space-y-1 text-left text-sm text-ink-400">
                    <li>1. Abra o app do seu banco e escolha <strong class="text-ink-200">Pagar com PIX</strong>.</li>
                    <li>2. Escaneie o QR code ou use o <strong class="text-ink-200">copia e cola</strong> abaixo.</li>
                    <li>3. Confirme o valor de <strong class="text-gold-300"><?= money($order['total']) ?></strong>.</li>
                </ol>
                <textarea id="pix-code" readonly rows="3" class="field mt-6 resize-none font-mono text-xs"><?= e($pix['pix_qr_code']) ?></textarea>
                <button type="button" class="btn-gold mt-4 w-full" data-copy="pix-code"><?= icon('copy', 'h-4 w-4') ?> Copiar código PIX</button>
                <?php if ($pix['pix_ticket_url']): ?>
                    <a href="<?= e($pix['pix_ticket_url']) ?>" target="_blank" rel="noopener" class="mt-4 inline-block text-xs uppercase tracking-[0.2em] text-gold-400 hover:text-gold-300">Abrir instruções no Mercado Pago</a>
                <?php endif; ?>
                <p class="mt-6 flex items-center justify-center gap-2 text-xs text-ink-500">
                    <span class="spinner text-gold-400"></span> Aguardando confirmação · expira <?= date_br($pix['pix_expires_at']) ?>
                </p>
            </div>
        <?php elseif ($pix && $pixExpired): ?>
            <div class="panel mx-auto mt-12 max-w-xl p-8 text-center">
                <p class="text-ink-300">O código PIX expirou.</p>
                <form method="post" class="mt-6" data-loading>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="new_pix">
                    <button class="btn-gold" data-loading-text="Gerando…">Gerar novo PIX</button>
                </form>
            </div>
        <?php elseif ($order['mp_init_point']): ?>
            <!-- ======================= CARTÃO: tentar novamente no Checkout Pro ======================= -->
            <div class="mx-auto mt-10 flex max-w-xl flex-col items-center gap-3 sm:flex-row sm:justify-center" data-poll-status="<?= e($selfUrl . '&format=json') ?>">
                <a href="<?= e($order['mp_init_point']) ?>" class="btn-gold">Ir para o pagamento</a>
                <?php if (!$isInfinitePay): ?>
                <form method="post" data-loading>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="new_pix">
                    <button class="btn-outline" data-loading-text="Gerando…">Pagar com PIX</button>
                </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- ======================= RESUMO ======================= -->
    <div class="mt-14 grid gap-6 md:grid-cols-2">
        <div class="panel p-6">
            <h2 class="text-xs font-medium uppercase tracking-[0.24em] text-white">Itens</h2>
            <ul class="mt-5 space-y-3 text-sm">
                <?php foreach ($items as $it): ?>
                    <li class="flex justify-between gap-3"><span class="text-ink-300"><?= (int) $it['quantity'] ?>× <?= e($it['name']) ?></span><span class="text-white"><?= money($it['line_total']) ?></span></li>
                <?php endforeach; ?>
            </ul>
            <div class="gold-divider my-5"></div>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-ink-400">Subtotal</dt><dd><?= money($order['subtotal']) ?></dd></div>
                <div class="flex justify-between"><dt class="text-ink-400">Frete</dt><dd><?= (float) $order['shipping'] > 0 ? money($order['shipping']) : 'Grátis' ?></dd></div>
                <div class="flex justify-between text-base"><dt class="text-white">Total</dt><dd class="font-serif text-2xl text-gold-300"><?= money($order['total']) ?></dd></div>
            </dl>
        </div>
        <div class="panel p-6">
            <h2 class="text-xs font-medium uppercase tracking-[0.24em] text-white">Entrega</h2>
            <address class="mt-5 space-y-1 text-sm not-italic text-ink-300">
                <p class="text-white"><?= e($order['customer_name']) ?></p>
                <p><?= e($order['street']) ?>, <?= e($order['number']) ?><?= $order['complement'] ? ' — ' . e($order['complement']) : '' ?></p>
                <p><?= e($order['district']) ?> · <?= e($order['city']) ?>/<?= e($order['state']) ?></p>
                <p>CEP <?= e(format_cep($order['cep'])) ?></p>
            </address>
            <?php if ($order['tracking_code']): ?>
                <p class="mt-5 text-sm text-ink-400">Rastreio: <span class="text-gold-300"><?= e($order['tracking_code']) ?></span></p>
            <?php endif; ?>
            <p class="mt-5 text-xs text-ink-500">Comprovante enviado para <?= e($order['customer_email']) ?>.</p>
        </div>
    </div>

    <div class="mt-10 text-center">
        <a href="<?= e(url('index.php')) ?>" class="text-xs uppercase tracking-[0.2em] text-ink-400 hover:text-gold-300">Voltar à loja</a>
    </div>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
