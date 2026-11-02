<?php
/**
 * King Decants — admin/customer_view.php (Mini-CRM: ficha do cliente)
 * ---------------------------------------------------------------------------
 * Dados básicos, métricas (pedidos, total pago, ticket médio), histórico de
 * pedidos e "últimos contatos" (customer_notes: nota, telefone, WhatsApp, e-mail).
 * Ação POST (CSRF + log): action=contact  channel, note
 * Export: CSV dos pedidos deste cliente.
 * ---------------------------------------------------------------------------
 */
define('KD_ADMIN_AREA', true);
require dirname(__DIR__) . '/inc/bootstrap.php';
$admin = require_admin();

$id = int_param($_GET, 'id');
$stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$id]);
$customer = $stmt->fetch();
if (!$customer) {
    flash('error', 'Cliente não encontrado.');
    redirect(url('admin/customers.php'));
}
$selfUrl = url('admin/customer_view.php?id=' . $id);
$channels = ['nota' => 'Nota', 'telefone' => 'Telefone', 'whatsapp' => 'WhatsApp', 'email' => 'E-mail'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'contact') {
    csrf_require();
    $channel = array_key_exists($_POST['channel'] ?? '', $channels) ? $_POST['channel'] : 'nota';
    $note = clean_text($_POST['note'] ?? '', 2000);
    if ($note !== '') {
        db()->prepare('INSERT INTO customer_notes (user_id, admin_id, channel, note) VALUES (?, ?, ?, ?)')->execute([$id, $admin['id'], $channel, $note]);
        admin_log('customer_contact', 'customer', $id, "[{$channel}] " . mb_substr($note, 0, 120));
        flash('success', 'Contato registrado.');
    }
    redirect($selfUrl . '#contatos');
}

$orders = db()->prepare('SELECT id, status, total, payment_method, created_at, is_guest_order FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 100');
$orders->execute([$id]);
$orders = $orders->fetchAll();

$paid = array_filter($orders, fn($o) => in_array($o['status'], ['paid', 'shipped', 'delivered'], true));
$totalSpent = array_sum(array_column($paid, 'total'));
$avg = $paid ? $totalSpent / count($paid) : 0;

$notes = db()->prepare('SELECT n.*, a.name AS admin_name FROM customer_notes n LEFT JOIN admin_users a ON a.id = n.admin_id WHERE n.user_id = ? ORDER BY n.id DESC LIMIT 50');
$notes->execute([$id]);
$notes = $notes->fetchAll();

// Último endereço usado
$addr = db()->prepare('SELECT street, number, complement, district, city, state, cep FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 1');
$addr->execute([$id]);
$addr = $addr->fetch();

$pageTitle = $customer['name'];
$adminNav = 'customers';
require dirname(__DIR__) . '/inc/admin_header.php';
?>
<a href="<?= e(url('admin/customers.php')) ?>" class="text-xs uppercase tracking-[0.18em] text-ink-500 hover:text-gold-300">← Clientes</a>
<div class="mt-3 flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="eyebrow"><?= $customer['is_guest'] ? 'Convidado' : 'Cliente com conta' ?> · desde <?= date_br($customer['created_at'], false) ?></p>
        <h1 class="mt-2 font-serif text-4xl text-white"><?= e($customer['name']) ?></h1>
    </div>
    <a href="<?= e(url('admin/export.php?type=orders&customer=' . $id)) ?>" class="btn-outline btn-sm"><?= icon('download', 'h-4 w-4') ?> Exportar pedidos (CSV)</a>
</div>
<div class="gold-divider my-8"></div>

<div class="grid gap-4 sm:grid-cols-3">
    <div class="card-lux p-6"><p class="text-[11px] uppercase tracking-[0.2em] text-ink-400">Pedidos</p><p class="mt-3 font-serif text-3xl text-white"><?= count($orders) ?></p></div>
    <div class="card-lux p-6"><p class="text-[11px] uppercase tracking-[0.2em] text-ink-400">Total pago</p><p class="mt-3 font-serif text-3xl text-gold-300"><?= money($totalSpent) ?></p></div>
    <div class="card-lux p-6"><p class="text-[11px] uppercase tracking-[0.2em] text-ink-400">Ticket médio</p><p class="mt-3 font-serif text-3xl text-white"><?= money($avg) ?></p></div>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-3">
    <div class="space-y-6 xl:col-span-2">
        <section class="panel overflow-x-auto">
            <h2 class="px-6 pt-6 text-xs font-medium uppercase tracking-[0.22em] text-white">Histórico de pedidos</h2>
            <table class="table-lux mt-3">
                <thead><tr><th>#</th><th>Data</th><th>Pagamento</th><th>Status</th><th class="text-right">Total</th></tr></thead>
                <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr class="cursor-pointer" onclick="location.href='<?= e(url('admin/order_view.php?id=' . $o['id'])) ?>'">
                        <td class="text-white">#<?= (int) $o['id'] ?></td>
                        <td class="text-ink-400"><?= date_br($o['created_at']) ?></td>
                        <td class="text-ink-400"><?= $o['payment_method'] === 'pix' ? 'PIX' : 'Cartão' ?></td>
                        <td><?= status_badge($o['status']) ?></td>
                        <td class="text-right"><?= money($o['total']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$orders): ?><tr><td colspan="5" class="py-10 text-center text-ink-500">Sem pedidos.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </section>

        <section id="contatos" class="panel p-6">
            <h2 class="text-xs font-medium uppercase tracking-[0.22em] text-white">Últimos contatos</h2>
            <form method="post" class="mt-4 grid gap-3 sm:grid-cols-[160px_1fr]">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="contact">
                <select name="channel" class="field">
                    <?php foreach ($channels as $k => $label): ?><option value="<?= $k ?>"><?= $label ?></option><?php endforeach; ?>
                </select>
                <textarea name="note" rows="2" required class="field" placeholder="Resumo do contato…"></textarea>
                <div class="sm:col-span-2"><button class="btn-gold btn-sm">Registrar contato</button></div>
            </form>
            <ul class="mt-6 space-y-4">
                <?php foreach ($notes as $n): ?>
                    <li class="border-l border-gold-400/40 pl-4">
                        <p class="text-[10px] uppercase tracking-[0.2em] text-gold-400"><?= e($channels[$n['channel']] ?? $n['channel']) ?></p>
                        <p class="mt-1 whitespace-pre-line text-sm text-ink-200"><?= e($n['note']) ?></p>
                        <p class="mt-1 text-xs text-ink-500"><?= e($n['admin_name'] ?? 'Admin removido') ?> · <?= date_br($n['created_at']) ?></p>
                    </li>
                <?php endforeach; ?>
                <?php if (!$notes): ?><li class="text-sm text-ink-500">Nenhum contato registrado.</li><?php endif; ?>
            </ul>
        </section>
    </div>

    <section class="panel h-fit p-6">
        <h2 class="text-xs font-medium uppercase tracking-[0.22em] text-white">Dados</h2>
        <dl class="mt-4 space-y-3 text-sm">
            <div><dt class="text-ink-500">E-mail</dt><dd><a class="text-gold-300 hover:underline" href="mailto:<?= e($customer['email']) ?>"><?= e($customer['email']) ?></a></dd></div>
            <div><dt class="text-ink-500">Telefone</dt><dd><?= $customer['phone'] ? '<a class="text-gold-300 hover:underline" target="_blank" rel="noopener" href="https://wa.me/55' . e(only_digits($customer['phone'])) . '">' . e(format_phone($customer['phone'])) . '</a>' : '—' ?></dd></div>
            <div><dt class="text-ink-500">CPF</dt><dd><?= $customer['cpf'] ? e(format_cpf($customer['cpf'])) : '—' ?></dd></div>
            <div><dt class="text-ink-500">Último login</dt><dd><?= date_br($customer['last_login_at']) ?></dd></div>
            <?php if ($addr): ?>
                <div><dt class="text-ink-500">Último endereço</dt>
                    <dd class="text-ink-300"><?= e($addr['street']) ?>, <?= e($addr['number']) ?><?= $addr['complement'] ? ' — ' . e($addr['complement']) : '' ?><br><?= e($addr['district']) ?> · <?= e($addr['city']) ?>/<?= e($addr['state']) ?><br>CEP <?= e(format_cep($addr['cep'])) ?></dd></div>
            <?php endif; ?>
        </dl>
    </section>
</div>

<?php require dirname(__DIR__) . '/inc/admin_footer.php'; ?>
