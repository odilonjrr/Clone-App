<?php
/**
 * King Decants — admin/index.php (Dashboard)
 * ---------------------------------------------------------------------------
 * Cards: total de pedidos, pedidos pagos, faturamento (soma dos pagos),
 * pendentes de envio e aguardando pagamento.
 * Gráfico: faturamento dos últimos 14 dias (barras em CSS puro, sem libs).
 * Listas: pedidos recentes e atividade recente dos admins (admin_logs).
 *
 * Performance: todas as consultas são agregações simples apoiadas pelos
 * índices idx_orders_status_created e idx_orders_paid_at.
 * ---------------------------------------------------------------------------
 */
define('KD_ADMIN_AREA', true);
require dirname(__DIR__) . '/inc/bootstrap.php';
$admin = require_admin();

$pdo = db();
orders_expire_stale(); // libera reservas de pedidos pendentes há mais de 24h
$paidStatuses = "('paid','shipped','delivered')";

$metrics = $pdo->query(
    "SELECT
        COUNT(*)                                                         AS total_orders,
        SUM(status IN $paidStatuses)                                     AS paid_orders,
        COALESCE(SUM(CASE WHEN status IN $paidStatuses THEN total END), 0) AS revenue,
        SUM(status = 'paid')                                             AS to_ship,
        SUM(status = 'pending')                                          AS awaiting_payment
     FROM orders"
)->fetch();

// Últimos 14 dias de faturamento (pagos)
$stmt = $pdo->prepare(
    "SELECT DATE(paid_at) AS d, SUM(total) AS revenue, COUNT(*) AS qty
       FROM orders
      WHERE status IN $paidStatuses AND paid_at >= (CURDATE() - INTERVAL 13 DAY)
      GROUP BY DATE(paid_at)"
);
$stmt->execute();
$byDay = [];
foreach ($stmt->fetchAll() as $r) {
    $byDay[$r['d']] = $r;
}
$series = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $series[] = ['date' => $d, 'revenue' => (float) ($byDay[$d]['revenue'] ?? 0), 'qty' => (int) ($byDay[$d]['qty'] ?? 0)];
}
$maxRevenue = max(1, max(array_column($series, 'revenue')));
$revenue14 = array_sum(array_column($series, 'revenue'));
$avgTicket = $metrics['paid_orders'] ? $metrics['revenue'] / $metrics['paid_orders'] : 0;

$recentOrders = $pdo->query('SELECT id, customer_name, status, total, created_at FROM orders ORDER BY id DESC LIMIT 8')->fetchAll();
$recentLogs = $pdo->query(
    'SELECT l.action, l.entity_type, l.entity_id, l.details, l.created_at, a.name AS admin_name
       FROM admin_logs l LEFT JOIN admin_users a ON a.id = l.admin_id
      ORDER BY l.id DESC LIMIT 8'
)->fetchAll();
$lowStock = $pdo->query('SELECT name, sku, stock FROM products WHERE is_active = 1 AND stock <= 5 ORDER BY stock ASC LIMIT 5')->fetchAll();

$pageTitle = 'Dashboard';
$adminNav = 'dashboard';
require dirname(__DIR__) . '/inc/admin_header.php';

$cards = [
    ['Total de pedidos', number_format((int) $metrics['total_orders'], 0, ',', '.'), 'box', 'admin/orders.php'],
    ['Pedidos pagos', number_format((int) $metrics['paid_orders'], 0, ',', '.'), 'check', 'admin/orders.php?status=paid'],
    ['Total faturado', money($metrics['revenue']), 'chart', null],
    ['Pendentes de envio', number_format((int) $metrics['to_ship'], 0, ',', '.'), 'truck', 'admin/orders.php?status=paid'],
];
?>
<div class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="eyebrow">Visão geral</p>
        <h1 class="mt-2 font-serif text-4xl text-white">Dashboard</h1>
    </div>
    <p class="text-xs text-ink-500">Atualizado em <?= date('d/m/Y H:i') ?></p>
</div>
<div class="gold-divider my-8"></div>

<!-- Cards de métricas -->
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <?php foreach ($cards as $i => [$label, $value, $ic, $href]): ?>
        <<?= $href ? 'a href="' . e(url($href)) . '"' : 'div' ?> class="card-lux block p-6 animate-fade-up" style="animation-delay: <?= $i * 60 ?>ms">
            <div class="flex items-center justify-between">
                <span class="text-[11px] uppercase tracking-[0.2em] text-ink-400"><?= e($label) ?></span>
                <span class="text-gold-400"><?= icon($ic, 'h-5 w-5') ?></span>
            </div>
            <p class="mt-4 font-serif text-4xl text-white"><?= e($value) ?></p>
        </<?= $href ? 'a' : 'div' ?>>
    <?php endforeach; ?>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-3">
    <!-- Gráfico 14 dias -->
    <section class="panel p-6 xl:col-span-2">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="text-xs font-medium uppercase tracking-[0.22em] text-white">Faturamento · 14 dias</h2>
            <p class="text-sm text-ink-400"><?= money($revenue14) ?> · ticket médio <?= money($avgTicket) ?> · <?= (int) $metrics['awaiting_payment'] ?> aguardando pagamento</p>
        </div>
        <div class="mt-8 flex h-48 items-end gap-1.5 sm:gap-2" role="img" aria-label="Gráfico de barras do faturamento diário">
            <?php foreach ($series as $p): $h = $p['revenue'] > 0 ? max(4, round($p['revenue'] / $maxRevenue * 100)) : 2; ?>
                <div class="group relative flex h-full flex-1 flex-col justify-end">
                    <div class="w-full rounded-t-sm bg-gradient-to-t from-gold-600 to-gold-300 opacity-80 transition-opacity group-hover:opacity-100" style="height: <?= $h ?>%"></div>
                    <span class="pointer-events-none absolute -top-8 left-1/2 hidden -translate-x-1/2 whitespace-nowrap rounded bg-black px-2 py-1 text-[10px] text-gold-200 group-hover:block">
                        <?= money($p['revenue']) ?> · <?= $p['qty'] ?> ped.
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="mt-2 flex gap-1.5 text-[9px] text-ink-500 sm:gap-2">
            <?php foreach ($series as $i => $p): ?>
                <span class="flex-1 text-center"><?= $i % 2 === 0 ? date('d/m', strtotime($p['date'])) : '' ?></span>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Estoque baixo + atividade -->
    <section class="panel p-6">
        <h2 class="text-xs font-medium uppercase tracking-[0.22em] text-white">Estoque baixo</h2>
        <?php if (!$lowStock): ?>
            <p class="mt-4 text-sm text-ink-400">Nenhum produto com 5 unidades ou menos.</p>
        <?php else: ?>
            <ul class="mt-4 space-y-2 text-sm">
                <?php foreach ($lowStock as $p): ?>
                    <li class="flex justify-between"><span class="text-ink-300"><?= e($p['name']) ?> <span class="text-ink-500">· <?= e($p['sku']) ?></span></span><span class="<?= $p['stock'] <= 0 ? 'text-red-300' : 'text-amber-300' ?>"><?= (int) $p['stock'] ?></span></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <div class="gold-divider my-6"></div>
        <h2 class="text-xs font-medium uppercase tracking-[0.22em] text-white">Atividade recente</h2>
        <ul class="mt-4 space-y-3 text-sm">
            <?php foreach ($recentLogs as $l): ?>
                <li>
                    <p class="text-ink-300"><span class="text-gold-300"><?= e($l['admin_name'] ?? 'Sistema') ?></span> · <?= e($l['action']) ?><?= $l['entity_id'] ? ' #' . (int) $l['entity_id'] : '' ?></p>
                    <p class="text-xs text-ink-500"><?= date_br($l['created_at']) ?><?= $l['details'] ? ' · ' . e(mb_strimwidth($l['details'], 0, 60, '…')) : '' ?></p>
                </li>
            <?php endforeach; ?>
            <?php if (!$recentLogs): ?><li class="text-ink-500">Sem atividade.</li><?php endif; ?>
        </ul>
    </section>
</div>

<!-- Pedidos recentes -->
<section class="panel mt-6 overflow-x-auto">
    <div class="flex items-center justify-between px-6 pt-6">
        <h2 class="text-xs font-medium uppercase tracking-[0.22em] text-white">Pedidos recentes</h2>
        <a href="<?= e(url('admin/orders.php')) ?>" class="text-xs uppercase tracking-[0.18em] text-gold-400 hover:text-gold-300">Ver todos</a>
    </div>
    <table class="table-lux mt-4">
        <thead><tr><th>#</th><th>Cliente</th><th>Status</th><th>Data</th><th class="text-right">Total</th></tr></thead>
        <tbody>
        <?php foreach ($recentOrders as $o): ?>
            <tr class="cursor-pointer" onclick="location.href='<?= e(url('admin/order_view.php?id=' . $o['id'])) ?>'">
                <td class="text-white">#<?= (int) $o['id'] ?></td>
                <td><?= e($o['customer_name']) ?></td>
                <td><?= status_badge($o['status']) ?></td>
                <td class="text-ink-400"><?= date_br($o['created_at']) ?></td>
                <td class="text-right"><?= money($o['total']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$recentOrders): ?><tr><td colspan="5" class="py-10 text-center text-ink-500">Nenhum pedido ainda.</td></tr><?php endif; ?>
        </tbody>
    </table>
</section>

<?php require dirname(__DIR__) . '/inc/admin_footer.php'; ?>
