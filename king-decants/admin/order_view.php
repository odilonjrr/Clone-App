<?php
/**
 * King Decants — admin/order_view.php (Detalhe do pedido)
 * ---------------------------------------------------------------------------
 * Mostra: itens, cliente, endereço, pagamentos (Mercado Pago), histórico de
 * status e notas internas.
 *
 * Ações (POST + CSRF + log em admin_logs):
 *   action=status  to=shipped|delivered|cancelled [tracking_code] [reason]
 *   action=note    note=texto
 *   action=sync    reconsulta o último pagamento na API do MP
 * As transições respeitam order_allowed_transitions() (inc/orders.php).
 * ---------------------------------------------------------------------------
 */
define('KD_ADMIN_AREA', true);
require dirname(__DIR__) . '/inc/bootstrap.php';
$admin = require_admin();

$id = int_param($_GET, 'id');
$order = order_find($id);
if (!$order) {
    flash('error', 'Pedido não encontrado.');
    redirect(url('admin/orders.php'));
}
$selfUrl = url('admin/order_view.php?id=' . $id);

// ------------------------------------------------------------------ Ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = $_POST['action'] ?? '';
    // Volta para a página de origem somente se for uma página do admin.
    $back = (string) ($_POST['back'] ?? '');
    $back = str_starts_with($back, base_path() . '/admin/') && !str_contains($back, '//') ? $back : $selfUrl;

    if ($action === 'status') {
        $to = (string) ($_POST['to'] ?? '');
        $reason = clean_string($_POST['reason'] ?? '', 200);
        if ($to === 'shipped') {
            $tracking = clean_string($_POST['tracking_code'] ?? '', 60);
            if ($tracking !== '') {
                db()->prepare('UPDATE orders SET tracking_code = ? WHERE id = ?')->execute([$tracking, $id]);
                $reason = trim('Rastreio: ' . $tracking . ' ' . $reason);
            }
        }
        if (order_change_status($id, $to, 'admin', (int) $admin['id'], $reason ?: null)) {
            admin_log('order_status', 'order', $id, "{$order['status']} → {$to}" . ($reason ? " ({$reason})" : ''));
            flash('success', "Pedido #{$id} marcado como " . mb_strtolower(status_label($to)) . '.');
        } else {
            flash('error', 'Transição de status não permitida.');
        }
        redirect($back);
    }

    if ($action === 'note') {
        $note = clean_text($_POST['note'] ?? '', 2000);
        if ($note !== '') {
            db()->prepare('INSERT INTO order_notes (order_id, admin_id, note) VALUES (?, ?, ?)')->execute([$id, $admin['id'], $note]);
            admin_log('order_note', 'order', $id, mb_substr($note, 0, 120));
            flash('success', 'Nota adicionada.');
        }
        redirect($selfUrl . '#notas');
    }

    if ($action === 'sync') {
        $stmt = db()->prepare('SELECT mp_payment_id FROM payments WHERE order_id = ? AND mp_payment_id IS NOT NULL ORDER BY id DESC LIMIT 1');
        $stmt->execute([$id]);
        $pid = (string) $stmt->fetchColumn();
        try {
            $payment = ($pid && mp_is_configured()) ? mp_get_payment($pid) : null;
            if ($payment) {
                $res = payment_process_mp($payment, 'admin');
                admin_log('order_sync', 'order', $id, $res['message']);
                flash('success', 'Sincronizado: ' . $res['message']);
            } else {
                flash('error', 'Nenhum pagamento para sincronizar (ou credenciais não configuradas).');
            }
        } catch (Throwable $e) {
            flash('error', 'Mercado Pago indisponível no momento. Tente novamente em instantes.');
        }
        redirect($selfUrl);
    }
    redirect($selfUrl);
}

// ------------------------------------------------------------------ Dados
$items = order_items($id);
$payments = db()->prepare('SELECT id, mp_payment_id, method, status, status_detail, amount, created_at, updated_at FROM payments WHERE order_id = ? ORDER BY id DESC');
$payments->execute([$id]);
$payments = $payments->fetchAll();

$history = db()->prepare('SELECT h.*, a.name AS admin_name FROM order_status_history h LEFT JOIN admin_users a ON a.id = h.admin_id WHERE h.order_id = ? ORDER BY h.id DESC');
$history->execute([$id]);
$history = $history->fetchAll();

$notes = db()->prepare('SELECT n.*, a.name AS admin_name FROM order_notes n LEFT JOIN admin_users a ON a.id = n.admin_id WHERE n.order_id = ? ORDER BY n.id DESC');
$notes->execute([$id]);
$notes = $notes->fetchAll();

$pageTitle = 'Pedido #' . $id;
$adminNav = 'orders';
require dirname(__DIR__) . '/inc/admin_header.php';
?>
<a href="<?= e(url('admin/orders.php')) ?>" class="text-xs uppercase tracking-[0.18em] text-ink-500 hover:text-gold-300">← Pedidos</a>
<div class="mt-3 flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="eyebrow"><?= date_br($order['created_at']) ?> · <?= $order['payment_method'] === 'pix' ? 'PIX' : 'Cartão' ?><?= $order['is_guest_order'] ? ' · convidado' : '' ?></p>
        <h1 class="mt-2 flex flex-wrap items-center gap-4 font-serif text-4xl text-white">Pedido #<?= $id ?> <?= status_badge($order['status']) ?></h1>
    </div>
    <p class="font-serif text-4xl text-gold-300"><?= money($order['total']) ?></p>
</div>
<div class="gold-divider my-8"></div>

<!-- Ações rápidas -->
<div class="panel flex flex-wrap items-end gap-3 p-5">
    <?php if (order_can_transition($order['status'], 'shipped')): ?>
        <form method="post" class="flex flex-wrap items-end gap-2">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="status"><input type="hidden" name="to" value="shipped">
            <div>
                <label class="field-label" for="tracking">Código de rastreio (opcional)</label>
                <input id="tracking" name="tracking_code" class="field !py-2" placeholder="AA123456789BR">
            </div>
            <button class="btn-gold btn-sm"><?= icon('truck', 'h-4 w-4') ?> Marcar como enviado</button>
        </form>
    <?php endif; ?>
    <?php if (order_can_transition($order['status'], 'delivered')): ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="status"><input type="hidden" name="to" value="delivered">
            <button class="btn-gold btn-sm"><?= icon('check', 'h-4 w-4') ?> Marcar como entregue</button>
        </form>
    <?php endif; ?>
    <?php if (order_can_transition($order['status'], 'cancelled')): ?>
        <form method="post" class="flex flex-wrap items-end gap-2" onsubmit="return confirm('Cancelar este pedido? Se já estava pago, o estoque será devolvido (o estorno no Mercado Pago deve ser feito no painel do MP).')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="status"><input type="hidden" name="to" value="cancelled">
            <input name="reason" class="field !py-2" placeholder="Motivo (opcional)">
            <button class="btn-outline btn-sm !border-red-400/40 !text-red-300"><?= icon('close', 'h-4 w-4') ?> Cancelar pedido</button>
        </form>
    <?php endif; ?>
    <form method="post" class="ml-auto">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="sync">
        <button class="btn-outline btn-sm" title="Reconsulta o status do pagamento na API do Mercado Pago">Sincronizar pagamento</button>
    </form>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-3">
    <div class="space-y-6 xl:col-span-2">
        <!-- Itens -->
        <section class="panel overflow-x-auto">
            <h2 class="px-6 pt-6 text-xs font-medium uppercase tracking-[0.22em] text-white">Itens</h2>
            <table class="table-lux mt-3">
                <thead><tr><th>Produto</th><th>SKU</th><th class="text-right">Qtd.</th><th class="text-right">Unitário</th><th class="text-right">Total</th></tr></thead>
                <tbody>
                <?php foreach ($items as $it): ?>
                    <tr><td class="text-white"><?= e($it['name']) ?></td><td class="text-ink-400"><?= e($it['sku']) ?></td><td class="text-right"><?= (int) $it['quantity'] ?></td><td class="text-right"><?= money($it['unit_price']) ?></td><td class="text-right"><?= money($it['line_total']) ?></td></tr>
                <?php endforeach; ?>
                <tr><td colspan="4" class="text-right text-ink-400">Subtotal</td><td class="text-right"><?= money($order['subtotal']) ?></td></tr>
                <tr><td colspan="4" class="text-right text-ink-400">Frete</td><td class="text-right"><?= money($order['shipping']) ?></td></tr>
                <tr><td colspan="4" class="text-right text-white">Total</td><td class="text-right font-serif text-xl text-gold-300"><?= money($order['total']) ?></td></tr>
                </tbody>
            </table>
        </section>

        <!-- Pagamentos -->
        <section class="panel overflow-x-auto">
            <h2 class="px-6 pt-6 text-xs font-medium uppercase tracking-[0.22em] text-white">Pagamentos (Mercado Pago)</h2>
            <table class="table-lux mt-3">
                <thead><tr><th>ID MP</th><th>Método</th><th>Status</th><th class="text-right">Valor</th><th>Atualizado</th></tr></thead>
                <tbody>
                <?php foreach ($payments as $p): ?>
                    <tr>
                        <td class="font-mono text-xs text-ink-300"><?= e($p['mp_payment_id']) ?></td>
                        <td><?= e($p['method'] ?? '—') ?></td>
                        <td><span class="text-ink-100"><?= e($p['status']) ?></span><?= $p['status_detail'] ? '<span class="block text-xs text-ink-500">' . e($p['status_detail']) . '</span>' : '' ?></td>
                        <td class="text-right"><?= money($p['amount']) ?></td>
                        <td class="text-ink-400"><?= date_br($p['updated_at'] ?: $p['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$payments): ?><tr><td colspan="5" class="py-8 text-center text-ink-500">Nenhum pagamento registrado ainda<?= $order['mp_preference_id'] ? ' (preferência ' . e($order['mp_preference_id']) . ')' : '' ?>.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </section>

        <!-- Notas internas -->
        <section id="notas" class="panel p-6">
            <h2 class="text-xs font-medium uppercase tracking-[0.22em] text-white">Notas internas</h2>
            <form method="post" class="mt-4 space-y-3">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="note">
                <textarea name="note" rows="3" required class="field" placeholder="Ex.: cliente pediu embalagem para presente…"></textarea>
                <button class="btn-gold btn-sm">Adicionar nota</button>
            </form>
            <ul class="mt-6 space-y-4">
                <?php foreach ($notes as $n): ?>
                    <li class="border-l border-gold-400/40 pl-4">
                        <p class="whitespace-pre-line text-sm text-ink-200"><?= e($n['note']) ?></p>
                        <p class="mt-1 text-xs text-ink-500"><?= e($n['admin_name'] ?? 'Admin removido') ?> · <?= date_br($n['created_at']) ?></p>
                    </li>
                <?php endforeach; ?>
                <?php if (!$notes): ?><li class="text-sm text-ink-500">Nenhuma nota.</li><?php endif; ?>
            </ul>
        </section>
    </div>

    <div class="space-y-6">
        <!-- Cliente -->
        <section class="panel p-6">
            <h2 class="text-xs font-medium uppercase tracking-[0.22em] text-white">Cliente</h2>
            <dl class="mt-4 space-y-2 text-sm">
                <div><dt class="text-ink-500">Nome</dt><dd class="text-white"><?= e($order['customer_name']) ?></dd></div>
                <div><dt class="text-ink-500">E-mail</dt><dd><a class="text-gold-300 hover:underline" href="mailto:<?= e($order['customer_email']) ?>"><?= e($order['customer_email']) ?></a></dd></div>
                <div><dt class="text-ink-500">Telefone</dt><dd><a class="text-gold-300 hover:underline" href="https://wa.me/55<?= e(only_digits($order['customer_phone'])) ?>" target="_blank" rel="noopener"><?= e(format_phone($order['customer_phone'])) ?></a></dd></div>
                <div><dt class="text-ink-500">CPF</dt><dd><?= e(format_cpf($order['customer_cpf'])) ?></dd></div>
            </dl>
            <?php if ($order['user_id']): ?>
                <a href="<?= e(url('admin/customer_view.php?id=' . $order['user_id'])) ?>" class="btn-outline btn-sm mt-5 w-full">Ver cliente no CRM</a>
            <?php endif; ?>
        </section>

        <!-- Endereço -->
        <section class="panel p-6">
            <h2 class="text-xs font-medium uppercase tracking-[0.22em] text-white">Endereço de entrega</h2>
            <address class="mt-4 space-y-1 text-sm not-italic text-ink-300">
                <p><?= e($order['street']) ?>, <?= e($order['number']) ?></p>
                <?php if ($order['complement']): ?><p><?= e($order['complement']) ?></p><?php endif; ?>
                <p><?= e($order['district']) ?></p>
                <p><?= e($order['city']) ?>/<?= e($order['state']) ?></p>
                <p>CEP <?= e(format_cep($order['cep'])) ?></p>
            </address>
            <?php if ($order['tracking_code']): ?><p class="mt-4 text-sm">Rastreio: <span class="text-gold-300"><?= e($order['tracking_code']) ?></span></p><?php endif; ?>
        </section>

        <!-- Histórico -->
        <section class="panel p-6">
            <h2 class="text-xs font-medium uppercase tracking-[0.22em] text-white">Histórico</h2>
            <ol class="mt-4 space-y-4">
                <?php foreach ($history as $h): ?>
                    <li class="relative border-l border-gold-400/30 pl-4">
                        <span class="absolute -left-[4px] top-1.5 h-2 w-2 rounded-full bg-gold-400"></span>
                        <p class="text-sm text-ink-100">
                            <?= $h['from_status'] && $h['from_status'] !== $h['to_status'] ? e(status_label($h['from_status'])) . ' → ' : '' ?><?= e(status_label($h['to_status'])) ?>
                        </p>
                        <?php if ($h['note']): ?><p class="text-xs text-ink-400"><?= e($h['note']) ?></p><?php endif; ?>
                        <p class="text-xs text-ink-500"><?= date_br($h['created_at']) ?> · <?= e($h['admin_name'] ?: $h['source']) ?></p>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>
    </div>
</div>

<?php require dirname(__DIR__) . '/inc/admin_footer.php'; ?>
