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

// ---------------- Loja parceira: verificar todos / configuração
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'partner_sync') {
    csrf_require();
    @set_time_limit(120);
    $c = partner_sync_all();
    admin_log('partner_sync', 'products', null, json_encode($c));
    flash('success', "Estoque dos parceiros verificado: {$c['available']} disponível(is), {$c['out']} esgotado(s)" . ($c['unknown'] ? ", {$c['unknown']} sem confirmação" : '') . '.');
    redirect(url('admin/products.php') . '#parceiros');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'partner_settings') {
    csrf_require();
    setting_set('partner_fail_mode', ($_POST['partner_fail_mode'] ?? '') === 'block' ? 'block' : 'allow');
    setting_set('partner_cache_minutes', (string) max(1, min(1440, (int) ($_POST['partner_cache_minutes'] ?? 10))));
    if (!setting('partner_sync_key')) {
        setting_set('partner_sync_key', bin2hex(random_bytes(16)));
    }
    admin_log('partner_settings', 'settings', null, $_POST['partner_fail_mode'] ?? '');
    flash('success', 'Configuração da loja parceira salva.');
    redirect(url('admin/products.php') . '#parceiros');
}
$partnerCount = (int) db()->query("SELECT COUNT(*) FROM products WHERE partner_url IS NOT NULL AND partner_url <> ''")->fetchColumn();

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

<?php if ($partnerCount): $syncKey = setting('partner_sync_key');
    if (!$syncKey) { $syncKey = bin2hex(random_bytes(16)); setting_set('partner_sync_key', $syncKey); } ?>
<div id="parceiros" class="panel mt-6 flex flex-wrap items-end gap-4 p-4">
    <div class="min-w-[220px] flex-1">
        <p class="text-xs font-medium uppercase tracking-[0.22em] text-white">Loja parceira</p>
        <p class="mt-1 text-xs text-ink-400"><?= $partnerCount ?> produto(s) com link de parceiro. O estoque é conferido ao adicionar à sacola e guardado por alguns minutos.</p>
        <?php if ($syncKey): ?><p class="mt-1 break-all text-[11px] text-ink-500">Verificação automática (cron da Hostinger, a cada hora): <span class="font-mono text-ink-300"><?= e(absolute_url('partner-sync.php?key=' . $syncKey)) ?></span></p><?php endif; ?>
    </div>
    <form method="post" class="flex flex-wrap items-end gap-2">
        <?= csrf_field() ?><input type="hidden" name="action" value="partner_settings">
        <div><label class="field-label" for="pfm">Se o parceiro não responder</label>
            <select id="pfm" name="partner_fail_mode" class="field !py-2 !pr-9 text-sm">
                <option value="allow" <?= partner_fail_mode() === 'allow' ? 'selected' : '' ?>>Vender (com aviso no pedido)</option>
                <option value="block" <?= partner_fail_mode() === 'block' ? 'selected' : '' ?>>Não vender</option>
            </select></div>
        <div><label class="field-label" for="pcm">Guardar leitura (min)</label>
            <input id="pcm" name="partner_cache_minutes" type="number" min="1" max="1440" class="field !w-24 !py-2" value="<?= partner_cache_minutes() ?>"></div>
        <button class="btn-outline btn-sm">Salvar</button>
    </form>
    <form method="post" data-loading>
        <?= csrf_field() ?><input type="hidden" name="action" value="partner_sync">
        <button class="btn-gold btn-sm" data-loading-text="Buscando estoque…">Verificar estoque agora</button>
    </form>
</div>
<?php endif; ?>

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
                <td class="text-right <?= (int) $p['stock'] <= 0 ? 'text-red-300' : ((int) $p['stock'] <= 5 ? 'text-amber-300' : '') ?>"><?= (int) $p['stock'] ?>
                    <?php if (!empty($p['partner_url'])): ?><span class="block text-[10px] uppercase tracking-wider <?= $p['partner_status'] === 'out' ? 'text-red-300' : ($p['partner_status'] === 'available' ? 'text-emerald-300' : 'text-ink-500') ?>" title="<?= e(partner_status_label($p['partner_status'])) ?>">Parceiro: <?= $p['partner_status'] === 'out' ? 'esgotado' : ($p['partner_status'] === 'available' ? 'ok' : '?') ?></span><?php endif; ?></td>
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
