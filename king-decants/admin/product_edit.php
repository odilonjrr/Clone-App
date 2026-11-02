<?php
/**
 * King Decants — admin/product_edit.php (Cadastrar / editar produto)
 * ---------------------------------------------------------------------------
 *   product_edit.php          -> novo produto
 *   product_edit.php?id=3     -> editar
 *
 * Campos: nome, SKU, slug (gerado do nome se vazio), subtítulo, descrição curta
 * e completa, coleção, preço (aceita "49,90"), estoque, volume, ordem de
 * exibição, ativo/oculto e FOTO (upload JPG/PNG/WebP ou URL externa).
 *
 * Ações (POST + CSRF + admin_logs):
 *   action=save    salva (cria ou atualiza)
 *   action=delete  exclui — só se o produto nunca foi vendido; caso contrário
 *                  oriente a OCULTAR (preserva histórico de pedidos e relatórios)
 *
 * O formulário usa enctype="multipart/form-data". Limite de upload: 5 MB
 * (inc/uploads.php) e também upload_max_filesize/post_max_size do PHP.
 * ---------------------------------------------------------------------------
 */
define('KD_ADMIN_AREA', true);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/uploads.php';
$admin = require_admin();

$id = int_param($_GET, 'id');
$product = null;
if ($id) {
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    if (!$product) {
        flash('error', 'Produto não encontrado.');
        redirect(url('admin/products.php'));
    }
}
$collections = collections();

/** Gera slug ASCII a partir do nome ("Decants Básicos" -> "decants-basicos"). */
function slugify(string $text): string
{
    $text = function_exists('transliterator_transliterate')
        ? transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text)
        : strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text));
    $text = preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $text)) ?? '';
    return trim(substr($text, 0, 150), '-');
}

$errors = [];
$old = $product ?: [
    'name' => '', 'sku' => '', 'slug' => '', 'tagline' => '', 'short_description' => '', 'description' => '',
    'collection' => 'assinatura', 'price' => '', 'stock' => '0', 'volume_ml' => '', 'sort_order' => '0',
    'is_active' => 1, 'image_url' => '',
];

// postMaxSize excedido: o PHP descarta $_POST inteiro (inclusive o CSRF).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    flash('error', 'O envio excedeu o limite do servidor (post_max_size). Use uma imagem menor.');
    redirect(url('admin/product_edit.php' . ($id ? '?id=' . $id : '')));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = $_POST['action'] ?? 'save';

    // ------------------------------------------------------------ Excluir
    if ($action === 'delete' && $product) {
        $sold = db()->prepare('SELECT COUNT(*) FROM order_items WHERE product_id = ?');
        $sold->execute([$id]);
        if ((int) $sold->fetchColumn() > 0) {
            flash('error', 'Este produto já tem pedidos e não pode ser excluído. Use "Ocultar da loja" para tirá-lo da vitrine.');
            redirect(url('admin/product_edit.php?id=' . $id));
        }
        db()->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
        product_image_delete($product['image_url']);
        admin_log('product_delete', 'product', $id, $product['sku'] . ' · ' . $product['name']);
        flash('success', 'Produto excluído.');
        redirect(url('admin/products.php'));
    }

    // ------------------------------------------------------------ Salvar
    // Aceita "49,90", "1.249,90" (formato BR) e "49.90".
    $priceRaw = str_replace(['R$', ' '], '', (string) ($_POST['price'] ?? ''));
    if (str_contains($priceRaw, ',')) {
        $priceRaw = str_replace(',', '.', str_replace('.', '', $priceRaw));
    }
    $old = [
        'name'              => clean_string($_POST['name'] ?? '', 150),
        'sku'               => strtoupper(preg_replace('/[^A-Za-z0-9\-_]/', '', (string) ($_POST['sku'] ?? '')) ?? ''),
        'slug'              => slugify(clean_string($_POST['slug'] ?? '', 160)),
        'tagline'           => clean_string($_POST['tagline'] ?? '', 160),
        'short_description' => clean_string($_POST['short_description'] ?? '', 255),
        'description'       => clean_text($_POST['description'] ?? '', 5000),
        'collection'        => array_key_exists($_POST['collection'] ?? '', $collections) ? $_POST['collection'] : 'assinatura',
        'price'             => $priceRaw,
        'stock'             => (string) ($_POST['stock'] ?? '0'),
        'volume_ml'         => (string) ($_POST['volume_ml'] ?? ''),
        'sort_order'        => (string) ($_POST['sort_order'] ?? '0'),
        'is_active'         => isset($_POST['is_active']) ? 1 : 0,
        'image_url'         => $product['image_url'] ?? '',
    ];
    if ($old['slug'] === '') {
        $old['slug'] = slugify($old['name']);
    }
    $imageUrlInput = trim((string) ($_POST['image_url_external'] ?? ''));

    if (mb_strlen($old['name']) < 2) {
        $errors['name'] = 'Informe o nome.';
    }
    if (strlen($old['sku']) < 3 || strlen($old['sku']) > 40) {
        $errors['sku'] = 'SKU com 3 a 40 caracteres (letras, números, - e _).';
    }
    if ($old['slug'] === '') {
        $errors['slug'] = 'Não foi possível gerar o endereço (slug). Preencha manualmente.';
    }
    if ($old['short_description'] === '') {
        $errors['short_description'] = 'Escreva uma descrição curta (aparece no card).';
    }
    if (!is_numeric($old['price']) || (float) $old['price'] <= 0 || (float) $old['price'] > 99999) {
        $errors['price'] = 'Preço inválido. Ex.: 49,90';
    }
    if (filter_var($old['stock'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100000]]) === false) {
        $errors['stock'] = 'Estoque deve ser um número inteiro (0 ou mais).';
    }
    if ($old['volume_ml'] !== '' && filter_var($old['volume_ml'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]) === false) {
        $errors['volume_ml'] = 'Volume entre 1 e 1000 ml.';
    }
    if (filter_var($old['sort_order'], FILTER_VALIDATE_INT) === false) {
        $errors['sort_order'] = 'Ordem deve ser um número inteiro.';
    }
    if ($imageUrlInput !== '' && (!filter_var($imageUrlInput, FILTER_VALIDATE_URL) || !preg_match('#^https://#i', $imageUrlInput))) {
        $errors['image_url_external'] = 'Use uma URL https:// válida.';
    }
    // SKU e slug únicos
    foreach (['sku' => 'SKU', 'slug' => 'Endereço (slug)'] as $col => $label) {
        if (!isset($errors[$col])) {
            $dup = db()->prepare("SELECT COUNT(*) FROM products WHERE {$col} = ? AND id <> ?");
            $dup->execute([$old[$col], $id]);
            if ((int) $dup->fetchColumn() > 0) {
                $errors[$col] = "{$label} já usado por outro produto.";
            }
        }
    }

    // Imagem: upload tem prioridade; depois URL externa; senão mantém a atual.
    $newImage = null;
    if (!$errors) {
        try {
            $newImage = product_image_upload($_FILES['image'] ?? null);
        } catch (RuntimeException $e) {
            $errors['image'] = $e->getMessage();
        }
    }
    if (!$errors) {
        if ($newImage) {
            $old['image_url'] = $newImage;
        } elseif ($imageUrlInput !== '') {
            $old['image_url'] = $imageUrlInput;
        }
        if ($old['image_url'] === '') {
            $old['image_url'] = 'assets/img/product-placeholder.svg';
        }

        $data = [
            $old['sku'], $old['name'], $old['slug'], $old['tagline'] ?: null, $old['short_description'],
            $old['description'] ?: null, $old['collection'], round((float) $old['price'], 2), (int) $old['stock'],
            $old['image_url'], $old['volume_ml'] !== '' ? (int) $old['volume_ml'] : null, (int) $old['sort_order'], $old['is_active'],
        ];
        if ($product) {
            db()->prepare('UPDATE products SET sku=?, name=?, slug=?, tagline=?, short_description=?, description=?, collection=?,
                           price=?, stock=?, image_url=?, volume_ml=?, sort_order=?, is_active=? WHERE id=?')
                ->execute([...$data, $id]);
            if ($old['image_url'] !== $product['image_url']) {
                product_image_delete($product['image_url']);
            }
            $changes = [];
            foreach (['price' => 'preço', 'stock' => 'estoque', 'is_active' => 'visibilidade', 'image_url' => 'foto'] as $k => $label) {
                if ((string) $product[$k] !== (string) ($k === 'price' ? number_format((float) $old[$k], 2, '.', '') : $old[$k])) {
                    $changes[] = $label;
                }
            }
            admin_log('product_update', 'product', $id, $old['sku'] . ($changes ? ' · ' . implode(', ', $changes) : ''));
            flash('success', 'Produto atualizado.');
        } else {
            db()->prepare('INSERT INTO products (sku, name, slug, tagline, short_description, description, collection,
                           price, stock, image_url, volume_ml, sort_order, is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute($data);
            $id = (int) db()->lastInsertId();
            admin_log('product_create', 'product', $id, $old['sku'] . ' · ' . $old['name']);
            flash('success', 'Produto cadastrado.');
        }
        redirect(url('admin/product_edit.php?id=' . $id));
    }
    // Se deu erro depois de um upload bem-sucedido, descarta o arquivo órfão.
    if ($newImage) {
        product_image_delete($newImage);
    }
}

$err = fn(string $k) => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
$inv = fn(string $k) => isset($errors[$k]) ? ' is-invalid' : '';
$v = fn(string $k) => e($old[$k] ?? '');
$priceDisplay = is_numeric($old['price']) ? number_format((float) $old['price'], 2, ',', '') : $old['price'];
$isExternal = preg_match('#^https?://#i', (string) $old['image_url']);

$pageTitle = $product ? 'Editar ' . $product['name'] : 'Novo produto';
$adminNav = 'products';
require dirname(__DIR__) . '/inc/admin_header.php';
?>
<a href="<?= e(url('admin/products.php')) ?>" class="text-xs uppercase tracking-[0.18em] text-ink-500 hover:text-gold-300">← Produtos</a>
<div class="mt-3 flex flex-wrap items-end justify-between gap-4">
    <h1 class="font-serif text-4xl text-white"><?= $product ? e($product['name']) : 'Novo produto' ?></h1>
    <?php if ($product && (int) $product['is_active']): ?>
        <a href="<?= e(url('product.php?slug=' . urlencode($product['slug']))) ?>" target="_blank" class="btn-outline btn-sm"><?= icon('eye', 'h-4 w-4') ?> Ver na loja</a>
    <?php endif; ?>
</div>
<div class="gold-divider my-8"></div>

<?php if ($errors): ?>
    <div class="alert alert-error mb-6">Revise os campos destacados.</div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="grid gap-6 xl:grid-cols-3" data-loading novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="MAX_FILE_SIZE" value="<?= PRODUCT_IMAGE_MAX_BYTES ?>">

    <div class="panel space-y-5 p-6 xl:col-span-2">
        <div class="grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="field-label" for="name">Nome *</label>
                <input id="name" name="name" class="field<?= $inv('name') ?>" required value="<?= $v('name') ?>">
                <?= $err('name') ?>
            </div>
            <div>
                <label class="field-label" for="sku">SKU *</label>
                <input id="sku" name="sku" class="field uppercase<?= $inv('sku') ?>" required placeholder="KD-XXX-010" value="<?= $v('sku') ?>">
                <?= $err('sku') ?>
            </div>
            <div>
                <label class="field-label" for="slug">Endereço (slug)</label>
                <input id="slug" name="slug" class="field<?= $inv('slug') ?>" placeholder="gerado a partir do nome" value="<?= $v('slug') ?>">
                <?= $err('slug') ?>
            </div>
            <div class="sm:col-span-2">
                <label class="field-label" for="tagline">Subtítulo</label>
                <input id="tagline" name="tagline" class="field" placeholder="Ex.: Coleção Noir · 10 ml" value="<?= $v('tagline') ?>">
            </div>
            <div class="sm:col-span-2">
                <label class="field-label" for="short_description">Descrição curta * <span class="normal-case tracking-normal text-ink-500">(aparece no card)</span></label>
                <input id="short_description" name="short_description" maxlength="255" class="field<?= $inv('short_description') ?>" required value="<?= $v('short_description') ?>">
                <?= $err('short_description') ?>
            </div>
            <div class="sm:col-span-2">
                <label class="field-label" for="description">Descrição completa</label>
                <textarea id="description" name="description" rows="6" class="field"><?= $v('description') ?></textarea>
            </div>
        </div>

        <div class="gold-divider"></div>
        <div class="grid gap-5 sm:grid-cols-4">
            <div>
                <label class="field-label" for="price">Preço (R$) *</label>
                <input id="price" name="price" inputmode="decimal" class="field<?= $inv('price') ?>" required placeholder="49,90" value="<?= e($priceDisplay) ?>">
                <?= $err('price') ?>
            </div>
            <div>
                <label class="field-label" for="stock">Estoque *</label>
                <input id="stock" name="stock" type="number" min="0" class="field<?= $inv('stock') ?>" required value="<?= $v('stock') ?>">
                <?= $err('stock') ?>
            </div>
            <div>
                <label class="field-label" for="volume_ml">Volume (ml)</label>
                <input id="volume_ml" name="volume_ml" type="number" min="1" class="field<?= $inv('volume_ml') ?>" value="<?= $v('volume_ml') ?>">
                <?= $err('volume_ml') ?>
            </div>
            <div>
                <label class="field-label" for="sort_order">Ordem</label>
                <input id="sort_order" name="sort_order" type="number" class="field<?= $inv('sort_order') ?>" value="<?= $v('sort_order') ?>">
                <?= $err('sort_order') ?>
            </div>
            <div class="sm:col-span-2">
                <label class="field-label" for="collection">Coleção</label>
                <select id="collection" name="collection" class="field">
                    <?php foreach ($collections as $slug => $c): ?>
                        <option value="<?= e($slug) ?>" <?= $old['collection'] === $slug ? 'selected' : '' ?>><?= e($c['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <label class="flex items-center gap-3 self-end pb-3 text-sm text-ink-200 sm:col-span-2">
                <input type="checkbox" name="is_active" value="1" class="accent-[#d4af37]" <?= (int) $old['is_active'] ? 'checked' : '' ?>>
                Visível na loja
            </label>
        </div>
    </div>

    <div class="space-y-6">
        <div class="panel p-6">
            <h2 class="text-xs font-medium uppercase tracking-[0.22em] text-white">Foto</h2>
            <div class="mt-4 overflow-hidden rounded-lg border border-white/5 bg-ink-900">
                <?= product_img(['image_url' => $old['image_url'] ?: '', 'name' => $old['name'] ?: 'Prévia'], 'aspect-[4/5] w-full object-cover') ?>
            </div>
            <label class="field-label mt-5" for="image">Enviar nova foto</label>
            <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp"
                   class="block w-full text-sm text-ink-300 file:mr-3 file:rounded-md file:border-0 file:bg-gold-400 file:px-3 file:py-2 file:text-xs file:font-semibold file:uppercase file:tracking-wider file:text-black<?= $inv('image') ?>">
            <?= $err('image') ?>
            <p class="mt-2 text-xs text-ink-500">JPG, PNG ou WebP até 5 MB. Recomendado: vertical 4:5 (ex.: 1200×1500) com fundo escuro. A imagem é otimizada automaticamente.</p>
            <label class="field-label mt-5" for="image_url_external">Ou URL de imagem (https)</label>
            <input id="image_url_external" name="image_url_external" class="field<?= $inv('image_url_external') ?>" placeholder="https://…" value="<?= $isExternal ? $v('image_url') : '' ?>">
            <?= $err('image_url_external') ?>
        </div>

        <button type="submit" class="btn-gold w-full" data-loading-text="Salvando…"><?= $product ? 'Salvar alterações' : 'Cadastrar produto' ?></button>
    </div>
</form>

<?php if ($product): ?>
    <form method="post" class="mt-10 border-t border-white/5 pt-6"
          onsubmit="return confirm('Excluir definitivamente este produto? Esta ação não pode ser desfeita.')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete">
        <button class="text-xs uppercase tracking-[0.18em] text-red-300/80 hover:text-red-300">Excluir produto</button>
        <p class="mt-1 text-xs text-ink-500">Só é possível excluir produtos que nunca foram vendidos. Para os demais, desmarque "Visível na loja".</p>
    </form>
<?php endif; ?>

<?php require dirname(__DIR__) . '/inc/admin_footer.php'; ?>
