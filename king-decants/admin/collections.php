<?php
/**
 * King Decants — admin/collections.php (Coleções)
 * ---------------------------------------------------------------------------
 * Criar / editar / reordenar / ocultar / excluir coleções da home.
 *   - Imagem do círculo: envio de arquivo (quadrada, mín. 200 px). Sem imagem,
 *     usa a ilustração padrão (assets/img/collections/<slug>.svg) ou o ícone.
 *   - Coleção com produtos não é excluída: é ocultada (os produtos continuam
 *     com a coleção e voltam a aparecer se ela for reativada).
 * ---------------------------------------------------------------------------
 */
define('KD_ADMIN_AREA', true);
require dirname(__DIR__) . '/inc/bootstrap.php';
require_once dirname(__DIR__) . '/inc/uploads.php';
$admin = require_admin();

/** "Gourmand Doce" -> "gourmand-doce" (endereço da coleção). */
function collection_slug(string $text): string
{
    $t = mb_strtolower(trim($text));
    $t = strtr($t, ['á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ü' => 'u', 'ç' => 'c']);
    return trim(preg_replace('/[^a-z0-9]+/', '-', $t), '-');
}

$icons = ['crown' => 'Coroa', 'flower' => 'Flor', 'tree' => 'Árvore', 'drop' => 'Gota', 'moon' => 'Lua', 'heart' => 'Coração', 'gift' => 'Presente', 'tag' => 'Etiqueta'];
$errors = [];
$editId = int_param($_GET, 'editar');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    flash('error', 'O envio excedeu o limite do servidor. Use uma imagem menor.');
    redirect(url('admin/collections.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = $_POST['action'] ?? 'save';
    $id = int_param($_POST, 'id');
    $row = null;
    if ($id) {
        $st = db()->prepare('SELECT * FROM collections WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch() ?: null;
    }

    if ($action === 'toggle' && $row) {
        db()->prepare('UPDATE collections SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
        admin_log('collection_toggle', 'collection', $id, $row['slug']);
        flash('success', '"' . $row['label'] . '" ' . ((int) $row['is_active'] ? 'foi ocultada da loja.' : 'voltou a aparecer na loja.'));
        redirect(url('admin/collections.php'));
    }

    if ($action === 'move' && $row) {
        $dir = ($_POST['dir'] ?? '') === 'up' ? -1 : 1;
        $all = db()->query('SELECT id FROM collections ORDER BY sort_order, id')->fetchAll(PDO::FETCH_COLUMN);
        $pos = array_search($id, array_map('intval', $all), true);
        $swap = $pos + $dir;
        if ($pos !== false && isset($all[$swap])) {
            [$all[$pos], $all[$swap]] = [$all[$swap], $all[$pos]];
            $upd = db()->prepare('UPDATE collections SET sort_order = ? WHERE id = ?');
            foreach ($all as $i => $cid) {
                $upd->execute([$i, $cid]);
            }
        }
        redirect(url('admin/collections.php'));
    }

    if ($action === 'delete' && $row) {
        $used = db()->prepare('SELECT COUNT(*) FROM products WHERE collection = ?');
        $used->execute([$row['slug']]);
        $n = (int) $used->fetchColumn();
        if ($n > 0) {
            db()->prepare('UPDATE collections SET is_active = 0 WHERE id = ?')->execute([$id]);
            flash('info', "\"{$row['label']}\" tem {$n} produto(s): foi ocultada (não excluída). Mude os produtos de coleção para poder excluir.");
        } else {
            db()->prepare('DELETE FROM collections WHERE id = ?')->execute([$id]);
            product_image_delete($row['image']);
            flash('success', "Coleção \"{$row['label']}\" excluída.");
        }
        admin_log('collection_delete', 'collection', $id, $row['slug']);
        redirect(url('admin/collections.php'));
    }

    if ($action === 'remove_image' && $row) {
        db()->prepare('UPDATE collections SET image = NULL WHERE id = ?')->execute([$id]);
        product_image_delete($row['image']);
        flash('success', 'Imagem removida (volta a ilustração padrão ou o ícone).');
        redirect(url('admin/collections.php?editar=' . $id));
    }

    // ---------------- salvar
    $label = clean_string($_POST['label'] ?? '', 60);
    $slug = collection_slug(clean_string($_POST['slug'] ?? '', 30) ?: $label);
    $slug = substr($slug, 0, 30);
    $icon = array_key_exists($_POST['icon'] ?? '', $icons) ? $_POST['icon'] : 'crown';
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $editId = $id;
    if (mb_strlen($label) < 2) {
        $errors['label'] = 'Informe o nome da coleção.';
    }
    if ($slug === '') {
        $errors['slug'] = 'Endereço inválido.';
    } else {
        $dup = db()->prepare('SELECT COUNT(*) FROM collections WHERE slug = ? AND id <> ?');
        $dup->execute([$slug, $id]);
        if ((int) $dup->fetchColumn() > 0) {
            $errors['slug'] = 'Já existe uma coleção com esse endereço.';
        }
    }
    $newImage = null;
    if (!$errors) {
        try {
            $newImage = product_image_upload($_FILES['image'] ?? null);
        } catch (RuntimeException $e) {
            $errors['image'] = $e->getMessage();
        }
    }
    if (!$errors) {
        if ($row) {
            db()->prepare('UPDATE collections SET label = ?, slug = ?, icon = ?, is_active = ?' . ($newImage ? ', image = ?' : '') . ' WHERE id = ?')
                ->execute($newImage ? [$label, $slug, $icon, $isActive, $newImage, $id] : [$label, $slug, $icon, $isActive, $id]);
            if ($newImage) {
                product_image_delete($row['image']);
            }
            // Endereço mudou: leva os produtos junto
            if ($row['slug'] !== $slug) {
                db()->prepare('UPDATE products SET collection = ? WHERE collection = ?')->execute([$slug, $row['slug']]);
            }
            admin_log('collection_update', 'collection', $id, $slug);
            flash('success', "Coleção \"{$label}\" atualizada.");
        } else {
            $next = (int) db()->query('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM collections')->fetchColumn();
            db()->prepare('INSERT INTO collections (slug, label, icon, image, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$slug, $label, $icon, $newImage, $next, $isActive]);
            admin_log('collection_create', 'collection', (int) db()->lastInsertId(), $slug);
            flash('success', "Coleção \"{$label}\" criada. Agora escolha-a no cadastro dos produtos.");
        }
        redirect(url('admin/collections.php'));
    }
}

$rows = db()->query('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.collection = c.slug) AS products
                       FROM collections c ORDER BY c.sort_order, c.id')->fetchAll();
$edit = null;
foreach ($rows as $r) {
    if ((int) $r['id'] === $editId) {
        $edit = $r;
    }
}
$f = [
    'label'     => $_POST['label'] ?? ($edit['label'] ?? ''),
    'slug'      => $_POST['slug'] ?? ($edit['slug'] ?? ''),
    'icon'      => $_POST['icon'] ?? ($edit['icon'] ?? 'crown'),
    'is_active' => $_POST ? isset($_POST['is_active']) : (int) ($edit['is_active'] ?? 1) === 1,
];
$err = fn(string $k) => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
$inv = fn(string $k) => isset($errors[$k]) ? ' is-invalid' : '';

$pageTitle = 'Coleções';
$adminNav = 'collections';
require dirname(__DIR__) . '/inc/admin_header.php';
?>
<p class="eyebrow">Catálogo</p>
<h1 class="mt-2 font-serif text-4xl text-white">Coleções</h1>
<div class="gold-divider my-8"></div>

<div class="grid gap-6 2xl:grid-cols-[380px_1fr]">
    <!-- Formulário -->
    <form method="post" enctype="multipart/form-data" action="<?= e(url('admin/collections.php')) ?>" class="panel h-fit w-full max-w-xl space-y-4 p-6" data-loading novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
        <input type="hidden" name="MAX_FILE_SIZE" value="<?= PRODUCT_IMAGE_MAX_BYTES ?>">
        <h2 class="text-xs font-medium uppercase tracking-[0.22em] text-white"><?= $edit ? 'Editar coleção' : 'Adicionar coleção' ?></h2>
        <div>
            <label class="field-label" for="label">Nome *</label>
            <input id="label" name="label" maxlength="60" class="field<?= $inv('label') ?>" placeholder="Ex.: Oriental" value="<?= e($f['label']) ?>" required>
            <?= $err('label') ?>
        </div>
        <div>
            <label class="field-label" for="slug">Endereço na loja</label>
            <input id="slug" name="slug" maxlength="30" class="field<?= $inv('slug') ?>" placeholder="gerado pelo nome (ex.: oriental)" value="<?= e($f['slug']) ?>">
            <?= $err('slug') ?>
            <p class="mt-1 text-[11px] text-ink-500">Aparece no link: index.php?colecao=<strong><?= e($f['slug'] ?: 'oriental') ?></strong></p>
        </div>
        <div>
            <label class="field-label" for="image">Imagem do círculo</label>
            <?php if ($edit && ($img = collection_image_url($edit['slug'], ['image' => $edit['image']]))): ?>
                <div class="mb-3 flex items-center gap-3">
                    <img src="<?= e($img) ?>" alt="" class="h-16 w-16 rounded-full border border-gold-400/40 object-cover">
                    <span class="text-xs text-ink-400"><?= $edit['image'] ? 'Imagem enviada' : 'Ilustração padrão' ?></span>
                </div>
            <?php endif; ?>
            <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp"
                   class="block w-full text-sm text-ink-300 file:mr-3 file:rounded-md file:border-0 file:bg-gold-400 file:px-3 file:py-2 file:text-xs file:font-semibold file:uppercase file:tracking-wider file:text-black<?= $inv('image') ?>">
            <?= $err('image') ?>
            <p class="mt-1 text-[11px] text-ink-500">Quadrada (ex.: 600×600), JPG/PNG/WebP, fundo escuro combina melhor. Opcional.</p>
        </div>
        <div>
            <label class="field-label" for="icon">Ícone (usado se não houver imagem)</label>
            <select id="icon" name="icon" class="field !pr-9">
                <?php foreach ($icons as $k => $name): ?>
                    <option value="<?= e($k) ?>" <?= $f['icon'] === $k ? 'selected' : '' ?>><?= e($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <label class="flex items-center gap-3 text-sm text-ink-200">
            <input type="checkbox" name="is_active" value="1" class="accent-[#d4af37]" <?= $f['is_active'] ? 'checked' : '' ?>> Mostrar na loja
        </label>
        <div class="flex flex-wrap gap-3">
            <button class="btn-gold btn-sm" data-loading-text="Salvando…"><?= $edit ? 'Salvar alterações' : 'Adicionar coleção' ?></button>
            <?php if ($edit): ?><a href="<?= e(url('admin/collections.php')) ?>" class="btn-outline btn-sm">Cancelar</a><?php endif; ?>
        </div>
    </form>
    <?php if ($edit && $edit['image']): ?>
        <form method="post" action="<?= e(url('admin/collections.php')) ?>" class="-mt-4 2xl:col-start-1">
            <?= csrf_field() ?><input type="hidden" name="action" value="remove_image"><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
            <button class="text-xs uppercase tracking-wider text-red-300 hover:text-red-200">Remover imagem enviada</button>
        </form>
    <?php endif; ?>

    <!-- Lista -->
    <div class="panel overflow-x-auto 2xl:col-start-2 2xl:row-start-1">
        <table class="table-lux">
            <thead><tr><th>Ordem</th><th>Coleção</th><th class="text-right">Produtos</th><th>Situação</th><th class="text-right"><span class="sr-only">Ações</span></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $i => $r): $img = collection_image_url($r['slug'], ['image' => $r['image']]); ?>
                <tr class="<?= (int) $r['is_active'] ? '' : 'opacity-50' ?>">
                    <td class="whitespace-nowrap">
                        <?php foreach (['up' => '↑', 'down' => '↓'] as $dir => $arrow): $disabled = ($dir === 'up' && $i === 0) || ($dir === 'down' && $i === count($rows) - 1); ?>
                            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="move"><input type="hidden" name="dir" value="<?= $dir ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <button class="rounded border border-white/10 px-2 py-0.5 text-ink-300 hover:border-gold-400/50 hover:text-gold-300 disabled:opacity-30" <?= $disabled ? 'disabled' : '' ?> aria-label="Mover <?= $dir === 'up' ? 'para cima' : 'para baixo' ?>"><?= $arrow ?></button></form>
                        <?php endforeach; ?>
                    </td>
                    <td>
                        <div class="flex items-center gap-3">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-full border border-gold-400/40 text-gold-400">
                                <?php if ($img): ?><img src="<?= e($img) ?>" alt="" class="h-full w-full object-cover"><?php else: ?><?= icon($r['icon'], 'h-6 w-6') ?><?php endif; ?>
                            </span>
                            <div><p class="text-white"><?= e($r['label']) ?></p><p class="text-xs text-ink-500"><?= e($r['slug']) ?></p></div>
                        </div>
                    </td>
                    <td class="text-right"><?= (int) $r['products'] ?></td>
                    <td><?= (int) $r['is_active'] ? '<span class="text-xs uppercase tracking-wider text-emerald-300">Na loja</span>' : '<span class="text-xs uppercase tracking-wider text-ink-500">Oculta</span>' ?></td>
                    <td class="whitespace-nowrap text-right !pr-6">
                        <div class="inline-flex items-center gap-1">
                            <a class="icon-btn h-8 w-8 text-gold-400" href="<?= e(url('admin/collections.php?editar=' . $r['id'])) ?>" aria-label="Editar" title="Editar"><?= icon('edit', 'h-4 w-4') ?></a>
                            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <button class="rounded border border-gold-400/30 px-2 py-1 text-[11px] uppercase tracking-wider text-gold-300 hover:bg-gold-400/10"><?= (int) $r['is_active'] ? 'Ocultar' : 'Mostrar' ?></button></form>
                            <form method="post" onsubmit="return confirm('Excluir a coleção <?= e($r['label']) ?>?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <button class="icon-btn h-8 w-8 text-red-300" aria-label="Excluir" title="Excluir"><?= icon('trash', 'h-4 w-4') ?></button></form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require dirname(__DIR__) . '/inc/admin_footer.php'; ?>
