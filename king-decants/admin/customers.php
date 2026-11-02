<?php
/**
 * King Decants — admin/customers.php (Mini-CRM: lista de clientes)
 * ---------------------------------------------------------------------------
 * - Busca por nome, e-mail, CPF ou telefone (prefixo/LIKE com índices)
 * - Filtro: todos | com conta | convidados
 * - Paginação 20/página; agregados (nº de pedidos, total gasto) por subquery
 *   limitada à página atual
 * - Exportar CSV com os mesmos filtros
 * ---------------------------------------------------------------------------
 */
define('KD_ADMIN_AREA', true);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/admin_filters.php';
$admin = require_admin();

[$whereSql, $params] = admin_customers_filter($_GET);

$count = db()->prepare("SELECT COUNT(*) FROM users u WHERE $whereSql");
$count->execute($params);
$total = (int) $count->fetchColumn();
[$page, $perPage, $offset, $pages] = paginate($total, 20);

$stmt = db()->prepare(
    "SELECT u.id, u.name, u.email, u.phone, u.cpf, u.is_guest, u.created_at,
            (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS orders_count,
            (SELECT COALESCE(SUM(o.total), 0) FROM orders o WHERE o.user_id = u.id AND o.status IN ('paid','shipped','delivered')) AS total_spent,
            (SELECT MAX(o.created_at) FROM orders o WHERE o.user_id = u.id) AS last_order_at
       FROM users u
      WHERE $whereSql
      ORDER BY u.id DESC
      LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$customers = $stmt->fetchAll();
$type = $_GET['type'] ?? '';

$pageTitle = 'Clientes';
$adminNav = 'customers';
require dirname(__DIR__) . '/inc/admin_header.php';
?>
<div class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="eyebrow">Mini-CRM</p>
        <h1 class="mt-2 font-serif text-4xl text-white">Clientes</h1>
    </div>
    <a href="<?= e(url('admin/export.php') . query_with(['type' => 'customers', 'ctype' => $type ?: null, 'page' => null])) ?>" class="btn-outline btn-sm"><?= icon('download', 'h-4 w-4') ?> Exportar CSV</a>
</div>
<div class="gold-divider my-8"></div>

<form method="get" class="panel grid gap-3 p-4 sm:grid-cols-[1fr_200px_auto]">
    <input name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Nome, e-mail, CPF ou telefone" class="field">
    <select name="type" class="field">
        <option value="">Todos</option>
        <option value="account" <?= $type === 'account' ? 'selected' : '' ?>>Com conta</option>
        <option value="guest" <?= $type === 'guest' ? 'selected' : '' ?>>Convidados</option>
    </select>
    <button class="btn-gold btn-sm"><?= icon('search', 'h-4 w-4') ?> Buscar</button>
</form>

<div class="panel mt-6 overflow-x-auto">
    <table class="table-lux">
        <thead><tr><th>Cliente</th><th>CPF</th><th>Telefone</th><th class="text-right">Pedidos</th><th class="text-right">Total pago</th><th>Último pedido</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($customers as $c): ?>
            <tr>
                <td>
                    <a href="<?= e(url('admin/customer_view.php?id=' . $c['id'])) ?>" class="text-white hover:text-gold-300"><?= e($c['name']) ?></a>
                    <?= $c['is_guest'] ? '<span class="ml-1 text-[10px] uppercase tracking-wider text-ink-500">convidado</span>' : '' ?>
                    <p class="text-xs text-ink-500"><?= e($c['email']) ?></p>
                </td>
                <td class="whitespace-nowrap text-ink-400"><?= $c['cpf'] ? e(format_cpf($c['cpf'])) : '—' ?></td>
                <td class="whitespace-nowrap text-ink-400"><?= $c['phone'] ? e(format_phone($c['phone'])) : '—' ?></td>
                <td class="text-right"><?= (int) $c['orders_count'] ?></td>
                <td class="whitespace-nowrap text-right text-gold-300"><?= money($c['total_spent']) ?></td>
                <td class="whitespace-nowrap text-ink-400"><?= date_br($c['last_order_at'], false) ?></td>
                <td class="text-right"><a class="icon-btn h-8 w-8 text-gold-400" href="<?= e(url('admin/customer_view.php?id=' . $c['id'])) ?>" aria-label="Abrir cliente"><?= icon('eye', 'h-4 w-4') ?></a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$customers): ?><tr><td colspan="7" class="py-14 text-center text-ink-500">Nenhum cliente encontrado.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php include dirname(__DIR__) . '/inc/admin_pagination.php'; ?>
<?php require dirname(__DIR__) . '/inc/admin_footer.php'; ?>
