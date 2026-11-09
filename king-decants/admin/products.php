<?php
/**
 * King Decants — admin/products.php (Catálogo)
 * ---------------------------------------------------------------------------
 * Lista produtos com busca (nome/SKU), filtro ativo/oculto e paginação.
 * Ação rápida (POST + CSRF + log): action=toggle -> ativa/oculta na loja.
 * Cadastro e edição ficam em product_edit.php.
 * ---------------------------------------------------------------------------
 */
define('KD_ADMIN_AREA', true);
require dirname(__DIR__) . '/inc/bootstrap.php';
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    csrf_require();
    $id = int_param($_POST, 'id');
    $stmt = db()->prepare('SELECT name, is_active FROM products WHERE id = ?');
    $stmt->execute([$id]);
    if ($p = $stmt->fetch()) {
        $new = (int) $p['is_active'] ? 0 : 1;
        db()->prepare('UPDATE products SET is_active = ? WHERE id = ?')->execute([$new, $id]);
        admin_log($new ? 'product_activate' : 'product_hide', 'product', $id, $p['name']);
        flash('success', '"' . $p['name'] . '" ' . ($new ? 'agora aparece na loja.' : 'foi ocultado da loja.'));
    }
    redirect(url('admin/products.php') . query_with([]));
}

$where = ['1=1'];
$params = [];
$term = clean_string($_GET['q'] ?? '', 100);
if ($term !== '') {
    $where[] = '(name LIKE ? OR sku LIKE ?)';
    $params[] = '%' . $term . '%';
    $params[] = '%' . $term . '%';
}
$status = $_GET['status'] ?? '';
if ($status === 'active') {
    $where[] = 'is_active = 1';
} elseif ($status === 'hidden') {
    $where[] = 'is_active = 0';
}
$whereSql = implode(' AND ', $where);

$count = db()->prepare("SELECT COUNT(*) FROM products WHERE $whereSql");
$count->execute($params);
$total = (int) $count->fetchColumn();
[$page, $perPage, $offset, $pages] = paginate($total, 20);

$stmt = db()->prepare("SELECT * FROM products WHERE $whereSql ORDER BY sort_order ASC, id ASC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$products = $stmt->fetchAll();
$collections = collections();

$pageTitle = 'Produtos';
$adminNav = 'products';
require dirname(__DIR__) . '/inc/admin_header.php';
?>
<div class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="eyebrow">Catálogo</p>
        <h1 class="mt-2 font-serif text-4xl text-white">Produtos</h1>
    </div>
    <a href="<?= e(url('admin/product_edit.php')) ?>" class="btn-gold btn-sm"><?= icon('plus', 'h-4 w-4') ?> Novo produto</a>
</div>
<div class="gold-divider my-8"></div>

<form method="get" class="panel grid gap-3 p-4 sm:grid-cols-[1fr_200px_auto]">
    <input name="q" value="<?= e($term) ?>" placeholder="Nome ou SKU" class="field">
    <select name="status" class="field">
        <option value="">Todos</option>
        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Ativos na loja</option>
        <option value="hidden" <?= $status === 'hidden' ? 'selected' : '' ?>>Ocultos</option>
    </select>
    <button class="btn-gold btn-sm"><?= icon('search', 'h-4 w-4') ?> Buscar</button>
</form>

<div class="panel mt-6 overflow-x-auto">
    <table class="table-lux">
        <thead><tr><th>Produto</th><th>Coleção</th><th class="text-right">Preço</th><th class="text-right">Estoque</th><th class="text-right">Vendidos</th><th>Loja</th><th class="text-right">Ações</th></tr></thead>
        <tbody>
        <?php foreach ($products as $p): ?>
            <tr class="<?= (int) $p['is_active'] ? '' : 'opacity-60' ?>">
                <td>
                    <div class="flex items-center gap-3">
                        <div class="h-14 w-11 shrink-0 overflow-hidden rounded-md bg-ink-900"><?= product_img($p, 'h-full w-full object-cover') ?></div>
                        <div>
                            <a class="text-white hover:text-gold-300" href="<?= e(url('admin/product_edit.php?id=' . $p['id'])) ?>"><?= e($p['name']) ?></a>
                            <p class="text-xs text-ink-500">SKU <?= e($p['sku']) ?><?php $nv = product_variant_count((int) $p['id']); ?><?= $nv ? ' · ' . $nv . ' tamanho' . ($nv > 1 ? 's' : '') : '' ?></p>
                        </div>
                    </div>
                </td>
                <td class="text-ink-400"><?= e($collections[$p['collection']]['label'] ?? $p['collection']) ?></td>
                <td class="whitespace-nowrap text-right text-gold-300"><?= money($p['price']) ?></td>
                <td class="text-right <?= (int) $p['stock'] <= 0 ? 'text-red-300' : ((int) $p['stock'] <= 5 ? 'text-amber-300' : '') ?>"><?= (int) $p['stock'] ?></td>
                <td class="text-right text-ink-400"><?= (int) $p['sold_count'] ?></td>
                <td><?= (int) $p['is_active']
                        ? '<span class="text-xs uppercase tracking-wider text-emerald-300">Ativo</span>'
                        : '<span class="text-xs uppercase tracking-wider text-ink-500">Oculto</span>' ?></td>
                <td class="whitespace-nowrap text-right">
                    <div class="inline-flex items-center gap-1">
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                            <button class="icon-btn h-8 w-8 text-gold-400" title="<?= (int) $p['is_active'] ? 'Ocultar da loja' : 'Mostrar na loja' ?>" aria-label="<?= (int) $p['is_active'] ? 'Ocultar da loja' : 'Mostrar na loja' ?>"><?= icon((int) $p['is_active'] ? 'eye' : 'close', 'h-4 w-4') ?></button>
                        </form>
                        <a class="btn-outline btn-sm" href="<?= e(url('admin/product_edit.php?id=' . $p['id'])) ?>">Editar</a>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$products): ?><tr><td colspan="7" class="py-14 text-center text-ink-500">Nenhum produto encontrado.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php include dirname(__DIR__) . '/inc/admin_pagination.php'; ?>
<?php require dirname(__DIR__) . '/inc/admin_footer.php'; ?>
