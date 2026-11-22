<?php
/**
 * King Decants — admin/orders.php (Listagem de pedidos)
 * ---------------------------------------------------------------------------
 * - Filtro por status (abas), busca (nº, nome, e-mail, CPF, telefone) e período
 * - Paginação (20 por página, LIMIT/OFFSET) + contagem com o mesmo WHERE
 * - Ações rápidas por linha (Enviado / Entregue / Cancelar) com CSRF,
 *   processadas por order_view.php (mesma regra e mesmo log de auditoria)
 * - Botão "Exportar CSV" respeitando os filtros atuais
 * ---------------------------------------------------------------------------
 */
define('KD_ADMIN_AREA', true);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/admin_filters.php';
$admin = require_admin();

[$whereSql, $params] = admin_orders_filter($_GET);

$count = db()->prepare("SELECT COUNT(*) FROM orders o WHERE $whereSql");
$count->execute($params);
$total = (int) $count->fetchColumn();
[$page, $perPage, $offset, $pages] = paginate($total, 20);

$stmt = db()->prepare(
    "SELECT o.id, o.customer_name, o.customer_email, o.status, o.payment_method, o.total, o.created_at, o.is_guest_order,
            (SELECT COALESCE(SUM(quantity), 0) FROM order_items WHERE order_id = o.id) AS items_count
       FROM orders o
      WHERE $whereSql
      ORDER BY o.id DESC
      LIMIT $perPage OFFSET $offset"   // inteiros já validados por paginate()
);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Contadores por status para as abas
$tabs = ['' => 'Todos'] + order_statuses();
$statusCounts = [];
foreach (db()->query('SELECT status, COUNT(*) c FROM orders GROUP BY status')->fetchAll() as $r) {
    $statusCounts[$r['status']] = (int) $r['c'];
}
$statusCounts[''] = array_sum($statusCounts);
$currentStatus = $_GET['status'] ?? '';
$backUrl = $_SERVER['REQUEST_URI'] ?? url('admin/orders.php');

$pageTitle = 'Pedidos';
$adminNav = 'orders';
require dirname(__DIR__) . '/inc/admin_header.php';
?>
<div class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="eyebrow">Gestão</p>
        <h1 class="mt-2 font-serif text-4xl text-white">Pedidos</h1>
    </div>
    <a href="<?= e(url('admin/export.php') . query_with(['type' => 'orders', 'page' => null])) ?>" class="btn-outline btn-sm"><?= icon('download', 'h-4 w-4') ?> Exportar CSV</a>
</div>
<div class="gold-divider my-8"></div>

<!-- Abas de status -->
<div class="-mx-1 flex gap-2 overflow-x-auto pb-2">
    <?php foreach ($tabs as $key => $label): ?>
        <a href="<?= e(query_with(['status' => $key, 'page' => null])) ?>"
           class="whitespace-nowrap rounded-full border px-4 py-1.5 text-xs uppercase tracking-[0.14em] transition-colors <?= $currentStatus === $key ? 'border-gold-400 bg-gold-400/10 text-gold-300' : 'border-white/10 text-ink-400 hover:border-gold-400/40 hover:text-white' ?>">
            <?= e($label) ?> <span class="ml-1 text-ink-500"><?= (int) ($statusCounts[$key] ?? 0) ?></span>
        </a>
    <?php endforeach; ?>
</div>

<!-- Busca -->
<form method="get" class="panel mt-4 grid gap-3 p-4 sm:grid-cols-[1fr_auto_auto_auto]">
    <input type="hidden" name="status" value="<?= e($currentStatus) ?>">
    <input name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Nº do pedido, nome, e-mail, CPF ou telefone" class="field">
    <input type="date" name="from" value="<?= e($_GET['from'] ?? '') ?>" class="field" aria-label="De">
    <input type="date" name="to" value="<?= e($_GET['to'] ?? '') ?>" class="field" aria-label="Até">
    <button class="btn-gold btn-sm"><?= icon('search', 'h-4 w-4') ?> Filtrar</button>
</form>

<div class="panel mt-6 overflow-x-auto">
    <table class="table-lux">
        <thead>
        <tr><th>#</th><th>Cliente</th><th>Itens</th><th>Pagamento</th><th>Status</th><th>Data</th><th class="text-right">Total</th><th class="text-right">Ações</th></tr>
        </thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
            <tr>
                <td><a class="text-white hover:text-gold-300" href="<?= e(url('admin/order_view.php?id=' . $o['id'])) ?>">#<?= (int) $o['id'] ?></a></td>
                <td>
                    <p class="text-ink-100"><?= e($o['customer_name']) ?> <?= $o['is_guest_order'] ? '<span class="text-[10px] uppercase tracking-wider text-ink-500">convidado</span>' : '' ?></p>
                    <p class="text-xs text-ink-500"><?= e($o['customer_email']) ?></p>
                </td>
                <td class="text-ink-400"><?= (int) $o['items_count'] ?></td>
                <td class="text-ink-400"><?= e(payment_method_label($o['payment_method'])) ?></td>
                <td><?= status_badge($o['status']) ?></td>
                <td class="whitespace-nowrap text-ink-400"><?= date_br($o['created_at']) ?></td>
                <td class="whitespace-nowrap text-right text-white"><?= money($o['total']) ?></td>
                <td class="whitespace-nowrap text-right">
                    <div class="inline-flex items-center gap-1">
                        <?php foreach (['shipped' => ['truck', 'Marcar como enviado'], 'delivered' => ['check', 'Marcar como entregue'], 'cancelled' => ['close', 'Cancelar pedido']] as $to => [$ic, $title]): ?>
                            <?php if (order_can_transition($o['status'], $to)): ?>
                                <form method="post" action="<?= e(url('admin/order_view.php?id=' . $o['id'])) ?>"
                                      <?= $to === 'cancelled' ? 'onsubmit="return confirm(\'Cancelar o pedido #' . (int) $o['id'] . '?\')"' : '' ?>>
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="status">
                                    <input type="hidden" name="to" value="<?= $to ?>">
                                    <input type="hidden" name="back" value="<?= e($backUrl) ?>">
                                    <button class="icon-btn h-8 w-8 <?= $to === 'cancelled' ? 'text-red-300' : 'text-gold-400' ?>" title="<?= e($title) ?>" aria-label="<?= e($title) ?>"><?= icon($ic, 'h-4 w-4') ?></button>
                                </form>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <a class="icon-btn h-8 w-8 text-ink-300" href="<?= e(url('admin/order_view.php?id=' . $o['id'])) ?>" title="Ver detalhes" aria-label="Ver detalhes"><?= icon('eye', 'h-4 w-4') ?></a>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$orders): ?>
            <tr><td colspan="8" class="py-14 text-center text-ink-500">Nenhum pedido encontrado com esses filtros.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include dirname(__DIR__) . '/inc/admin_pagination.php'; ?>

<?php require dirname(__DIR__) . '/inc/admin_footer.php'; ?>
