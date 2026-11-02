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
    'is_active' => 1, 'image_url' => '', 'concentration' => '',
    'notes_top' => '', 'notes_heart' => '', 'notes_base' => '',
    'notes_top_image' => '', 'notes_heart_image' => '', 'notes_base_image' => '',
];
$noteLevels = ['top' => 'Topo', 'heart' => 'Coração', 'base' => 'Fundo'];
const GALLERY_MAX = 12;

// postMaxSize excedido: o PHP descarta $_POST inteiro (inclusive o CSRF).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    flash('error', 'O envio excedeu o limite do servidor (post_max_size). Use uma imagem menor.');
    redirect(url('admin/product_edit.php' . ($id ? '?id=' . $id : '')));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = $_POST['action'] ?? 'save';

    // ------------------------------------------------------------ Avaliações: ocultar/mostrar
    if ($action === 'review_toggle' && $product) {
        $rid = int_param($_POST, 'review_id');
        db()->prepare('UPDATE product_reviews SET is_visible = 1 - is_visible WHERE id = ? AND product_id = ?')->execute([$rid, $id]);
        admin_log('review_toggle', 'product', $id, 'avaliação #' . $rid);
        flash('success', 'Visibilidade da avaliação atualizada.');
        redirect(url('admin/product_edit.php?id=' . $id . '#avaliacoes'));
    }

    // ------------------------------------------------------------ Excluir
    if ($action === 'delete' && $product) {
        $sold = db()->prepare('SELECT COUNT(*) FROM order_items WHERE product_id = ?');
        $sold->execute([$id]);
        if ((int) $sold->fetchColumn() > 0) {
            flash('error', 'Este produto já tem pedidos e não pode ser excluído. Use "Ocultar da loja" para tirá-lo da vitrine.');
            redirect(url('admin/product_edit.php?id=' . $id));
        }
        $extras = product_extra_images($id);
        db()->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
        product_image_delete($product['image_url']);
        foreach ($extras as $img) {
            product_image_delete($img['image_url']);
        }
        foreach (['top', 'heart', 'base'] as $lv) {
            product_image_delete($product['notes_' . $lv . '_image'] ?? null);
        }
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
        'concentration'     => clean_string($_POST['concentration'] ?? '', 60),
        'notes_top'         => clean_string($_POST['notes_top'] ?? '', 255),
        'notes_heart'       => clean_string($_POST['notes_heart'] ?? '', 255),
        'notes_base'        => clean_string($_POST['notes_base'] ?? '', 255),
        'notes_top_image'   => $product['notes_top_image'] ?? '',
        'notes_heart_image' => $product['notes_heart_image'] ?? '',
        'notes_base_image'  => $product['notes_base_image'] ?? '',
    ];
    // Galeria: ids marcados para remover (somente deste produto)
    $existingGallery = $product ? product_extra_images($id) : [];
    $removeIds = array_map('intval', (array) ($_POST['gallery_remove'] ?? []));
    $removeIds = array_values(array_intersect($removeIds, array_map('intval', array_column($existingGallery, 'id'))));
    $galleryFiles = uploaded_files_list($_FILES['gallery'] ?? null);
    if (count($existingGallery) - count($removeIds) + count($galleryFiles) > GALLERY_MAX) {
        $errors['gallery'] = 'A galeria aceita no máximo ' . GALLERY_MAX . ' fotos extras.';
    }
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
    // $uploaded guarda tudo que foi salvo nesta requisição, para limpar se houver erro.
    $newImage = null;
    $uploaded = [];
    $newGallery = [];
    $replacedNoteImages = [];
    if (!$errors) {
        try {
            $newImage = product_image_upload($_FILES['image'] ?? null);
            if ($newImage) {
                $uploaded[] = $newImage;
            }
        } catch (RuntimeException $e) {
            $errors['image'] = $e->getMessage();
        }
    }
    // Imagens da pirâmide olfativa (uma por nível; opcional; "remover" volta à ilustração padrão)
    foreach ($noteLevels as $lv => $label) {
        if ($errors) {
            break;
        }
        $col = 'notes_' . $lv . '_image';
        try {
            $file = product_image_upload($_FILES['notes_' . $lv . '_file'] ?? null, 120);
            if ($file) {
                $uploaded[] = $file;
                $replacedNoteImages[] = $old[$col];
                $old[$col] = $file;
            } elseif (isset($_POST['notes_' . $lv . '_remove'])) {
                $replacedNoteImages[] = $old[$col];
                $old[$col] = '';
            }
        } catch (RuntimeException $e) {
            $errors['notes_' . $lv . '_file'] = $e->getMessage();
        }
    }
    // Fotos extras da galeria
    foreach ($galleryFiles as $gf) {
        if ($errors) {
            break;
        }
        try {
            if ($path = product_image_upload($gf)) {
                $uploaded[] = $path;
                $newGallery[] = $path;
            }
        } catch (RuntimeException $e) {
            $errors['gallery'] = $e->getMessage();
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
            $old['concentration'] ?: null, $old['notes_top'] ?: null, $old['notes_heart'] ?: null, $old['notes_base'] ?: null,
            $old['notes_top_image'] ?: null, $old['notes_heart_image'] ?: null, $old['notes_base_image'] ?: null,
        ];
        if ($product) {
            db()->prepare('UPDATE products SET sku=?, name=?, slug=?, tagline=?, short_description=?, description=?, collection=?,
                           price=?, stock=?, image_url=?, volume_ml=?, sort_order=?, is_active=?,
                           concentration=?, notes_top=?, notes_heart=?, notes_base=?,
                           notes_top_image=?, notes_heart_image=?, notes_base_image=? WHERE id=?')
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
                           price, stock, image_url, volume_ml, sort_order, is_active,
                           concentration, notes_top, notes_heart, notes_base, notes_top_image, notes_heart_image, notes_base_image)
                           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute($data);
            $id = (int) db()->lastInsertId();
            admin_log('product_create', 'product', $id, $old['sku'] . ' · ' . $old['name']);
            flash('success', 'Produto cadastrado.');
        }

        // Galeria: remove marcadas e adiciona novas ao final
        foreach ($existingGallery as $img) {
            if (in_array((int) $img['id'], $removeIds, true)) {
                db()->prepare('DELETE FROM product_images WHERE id = ? AND product_id = ?')->execute([$img['id'], $id]);
                product_image_delete($img['image_url']);
            }
        }
        $maxOrder = db()->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM product_images WHERE product_id = ?');
        $maxOrder->execute([$id]);
        $order = (int) $maxOrder->fetchColumn();
        foreach ($newGallery as $path) {
            db()->prepare('INSERT INTO product_images (product_id, image_url, sort_order) VALUES (?, ?, ?)')->execute([$id, $path, ++$order]);
        }
        foreach ($replacedNoteImages as $oldPath) {
            product_image_delete($oldPath);
        }
        if ($newGallery || $removeIds) {
            admin_log('product_gallery', 'product', $id, '+' . count($newGallery) . ' / -' . count($removeIds) . ' fotos');
        }
        redirect(url('admin/product_edit.php?id=' . $id));
    }
    // Se deu erro depois de uploads bem-sucedidos, descarta os arquivos órfãos.
    foreach ($uploaded as $path) {
        product_image_delete($path);
    }
}

$err = fn(string $k) => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
$inv = fn(string $k) => isset($errors[$k]) ? ' is-invalid' : '';
$v = fn(string $k) => e($old[$k] ?? '');
$priceDisplay = is_numeric($old['price']) ? number_format((float) $old['price'], 2, ',', '') : $old['price'];
$isExternal = preg_match('#^https?://#i', (string) $old['image_url']);
$extraImages = $product ? product_extra_images((int) $product['id']) : [];
$productReviews = [];
if ($product) {
    $rv = db()->prepare('SELECT r.id, r.rating, r.comment, r.is_visible, r.created_at, u.name, u.email
                           FROM product_reviews r JOIN users u ON u.id = r.user_id WHERE r.product_id = ? ORDER BY r.id DESC LIMIT 50');
    $rv->execute([$product['id']]);
    $productReviews = $rv->fetchAll();
}

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
            <div>
                <label class="field-label" for="tagline">Subtítulo (cards da vitrine)</label>
                <input id="tagline" name="tagline" class="field" placeholder="Ex.: Coleção Noir · 10 ml" value="<?= $v('tagline') ?>">
            </div>
            <div>
                <label class="field-label" for="concentration">Concentração</label>
                <input id="concentration" name="concentration" list="concentrations" class="field" placeholder="Ex.: Eau de Parfum" value="<?= $v('concentration') ?>">
                <datalist id="concentrations">
                    <option value="Eau de Cologne"><option value="Eau de Toilette"><option value="Eau de Parfum"><option value="Parfum"><option value="Extrait de Parfum">
                </datalist>
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

        <!-- Pirâmide olfativa -->
        <div class="gold-divider"></div>
        <div>
            <h2 class="text-xs font-medium uppercase tracking-[0.22em] text-white">Pirâmide olfativa</h2>
            <p class="mt-1 text-xs text-ink-500">Separe as notas por vírgula. Níveis em branco não aparecem na loja. Sem imagem, a loja usa uma ilustração dourada padrão.</p>
            <div class="mt-4 space-y-4">
                <?php foreach ($noteLevels as $lv => $label): $imgCol = 'notes_' . $lv . '_image'; ?>
                    <div class="grid gap-3 rounded-lg border border-white/5 p-4 sm:grid-cols-[110px_1fr_auto] sm:items-center">
                        <span class="note-label !m-0 !h-10 !w-[110px]"><?= e($label) ?></span>
                        <div>
                            <input name="notes_<?= $lv ?>" class="field" maxlength="255" placeholder="Ex.: Bergamota, Pimenta-preta" value="<?= $v('notes_' . $lv) ?>" aria-label="Notas de <?= e($label) ?>">
                            <div class="mt-2 flex flex-wrap items-center gap-3">
                                <input type="file" name="notes_<?= $lv ?>_file" accept="image/jpeg,image/png,image/webp" aria-label="Imagem das notas de <?= e($label) ?>"
                                       class="text-xs text-ink-400 file:mr-2 file:rounded file:border-0 file:bg-white/10 file:px-2 file:py-1 file:text-xs file:text-ink-100">
                                <?php if (!empty($old[$imgCol])): ?>
                                    <label class="flex items-center gap-2 text-xs text-ink-400"><input type="checkbox" name="notes_<?= $lv ?>_remove" value="1" class="accent-[#d4af37]"> usar ilustração padrão</label>
                                <?php endif; ?>
                            </div>
                            <?= $err('notes_' . $lv . '_file') ?>
                        </div>
                        <img src="<?= e(!empty($old[$imgCol]) ? product_image_src($old[$imgCol]) : url('assets/img/notes/' . $lv . '.svg')) ?>" alt="" class="h-12 w-24 object-contain">
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="mt-2 text-xs text-ink-500">Dica: imagens com fundo transparente (PNG/WebP) ficam melhores na faixa escura.</p>
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

        <!-- Galeria (miniaturas na lateral da página do produto) -->
        <div class="panel p-6">
            <h2 class="text-xs font-medium uppercase tracking-[0.22em] text-white">Galeria · fotos extras</h2>
            <p class="mt-1 text-xs text-ink-500">Aparecem como miniaturas ao lado da foto principal (máx. <?= GALLERY_MAX ?>).</p>
            <?php if ($extraImages): ?>
                <div class="mt-4 grid grid-cols-3 gap-3">
                    <?php foreach ($extraImages as $img): ?>
                        <label class="group relative block cursor-pointer overflow-hidden rounded-md border border-white/10">
                            <img src="<?= e(product_image_src($img['image_url'])) ?>" alt="" class="aspect-[4/5] w-full object-cover">
                            <span class="absolute inset-x-0 bottom-0 flex items-center gap-1 bg-black/75 px-2 py-1 text-[10px] uppercase tracking-wider text-ink-200">
                                <input type="checkbox" name="gallery_remove[]" value="<?= (int) $img['id'] ?>" class="accent-[#d4af37]"> remover
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <label class="field-label mt-5" for="gallery">Adicionar fotos</label>
            <input id="gallery" name="gallery[]" type="file" multiple accept="image/jpeg,image/png,image/webp"
                   class="block w-full text-sm text-ink-300 file:mr-3 file:rounded-md file:border-0 file:bg-gold-400 file:px-3 file:py-2 file:text-xs file:font-semibold file:uppercase file:tracking-wider file:text-black<?= $inv('gallery') ?>">
            <?= $err('gallery') ?>
        </div>

        <button type="submit" class="btn-gold w-full" data-loading-text="Salvando…"><?= $product ? 'Salvar alterações' : 'Cadastrar produto' ?></button>
    </div>
</form>

<?php if ($product): ?>
    <!-- Avaliações (moderação) -->
    <section id="avaliacoes" class="panel mt-8 overflow-x-auto">
        <h2 class="px-6 pt-6 text-xs font-medium uppercase tracking-[0.22em] text-white">Avaliações de clientes</h2>
        <p class="px-6 pt-1 text-xs text-ink-500">Somente compradores verificados podem avaliar. Você pode ocultar avaliações impróprias (nunca editar a nota).</p>
        <table class="table-lux mt-3">
            <thead><tr><th>Cliente</th><th>Nota</th><th>Comentário</th><th>Data</th><th class="text-right">Ação</th></tr></thead>
            <tbody>
            <?php foreach ($productReviews as $r): ?>
                <tr class="<?= (int) $r['is_visible'] ? '' : 'opacity-50' ?>">
                    <td><?= e($r['name']) ?><p class="text-xs text-ink-500"><?= e($r['email']) ?></p></td>
                    <td class="whitespace-nowrap"><?= stars_html((float) $r['rating'], 'h-4 w-4') ?></td>
                    <td class="max-w-md text-ink-300"><?= e(mb_strimwidth((string) $r['comment'], 0, 160, '…')) ?></td>
                    <td class="whitespace-nowrap text-ink-400"><?= date_br($r['created_at'], false) ?></td>
                    <td class="text-right">
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="review_toggle">
                            <input type="hidden" name="review_id" value="<?= (int) $r['id'] ?>">
                            <button class="btn-outline btn-sm"><?= (int) $r['is_visible'] ? 'Ocultar' : 'Mostrar' ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$productReviews): ?><tr><td colspan="5" class="py-8 text-center text-ink-500">Nenhuma avaliação ainda.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </section>

    <form method="post" class="mt-10 border-t border-white/5 pt-6"
          onsubmit="return confirm('Excluir definitivamente este produto? Esta ação não pode ser desfeita.')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete">
        <button class="text-xs uppercase tracking-[0.18em] text-red-300/80 hover:text-red-300">Excluir produto</button>
        <p class="mt-1 text-xs text-ink-500">Só é possível excluir produtos que nunca foram vendidos. Para os demais, desmarque "Visível na loja".</p>
    </form>
<?php endif; ?>

<?php require dirname(__DIR__) . '/inc/admin_footer.php'; ?>
