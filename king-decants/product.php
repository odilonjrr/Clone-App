<?php
/**
 * King Decants — product.php (Detalhe do produto)
 * ---------------------------------------------------------------------------
 * Acesso: product.php?slug=decants-basicos  (ou ?id=1)
 *
 * Layout (desktop):  [miniaturas] [foto principal com luz dourada] [informações]
 *   Informações: nome, concentração – volume, estrelas (avaliações reais),
 *   preço, descrição, PIRÂMIDE OLFATIVA (topo / coração / fundo com imagem por
 *   nível) e os botões "Favoritos" e "Adicionar à sacola".
 * Abaixo: descrição completa e avaliações de compradores verificados.
 * No celular: foto, miniaturas em linha e informações empilhadas.
 *
 * POST action=review (CSRF): cria/atualiza a avaliação do cliente logado,
 * somente se ele comprou o produto com a conta (user_can_review()).
 * ---------------------------------------------------------------------------
 */
require __DIR__ . '/inc/bootstrap.php';

$slug = clean_string($_GET['slug'] ?? '', 160);
$id = int_param($_GET, 'id');

if ($slug !== '') {
    $stmt = db()->prepare('SELECT * FROM products WHERE slug = ? AND is_active = 1');
    $stmt->execute([$slug]);
} else {
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ? AND is_active = 1');
    $stmt->execute([$id]);
}
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    $pageTitle = 'Produto não encontrado — King Decants';
    require __DIR__ . '/inc/header.php';
    echo '<section class="mx-auto max-w-xl px-5 py-32 text-center"><p class="eyebrow">404</p><h1 class="mt-4 font-serif text-4xl text-white">Produto não encontrado</h1><a class="btn-gold mt-8" href="' . e(url('index.php')) . '">Voltar à loja</a></section>';
    require __DIR__ . '/inc/footer.php';
    exit;
}

$productId = (int) $product['id'];
$selfUrl = url('product.php?slug=' . urlencode($product['slug']));
$user = current_user();

// ------------------------------------------------------------------ Avaliação (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'review') {
    csrf_require();
    if (!$user) {
        flash('error', 'Entre na sua conta para avaliar.');
    } elseif (!user_can_review((int) $user['id'], $productId)) {
        flash('error', 'Apenas clientes que compraram este decant podem avaliá-lo.');
    } else {
        $rating = int_param($_POST, 'rating');
        $comment = clean_text($_POST['comment'] ?? '', 1000);
        if ($rating < 1 || $rating > 5) {
            flash('error', 'Escolha de 1 a 5 estrelas.');
        } else {
            db()->prepare('INSERT INTO product_reviews (product_id, user_id, rating, comment) VALUES (?, ?, ?, ?)
                           ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), is_visible = 1')
                ->execute([$productId, $user['id'], $rating, $comment !== '' ? $comment : null]);
            flash('success', 'Obrigado pela sua avaliação!');
        }
    }
    redirect($selfUrl . '#avaliacoes');
}

// ------------------------------------------------------------------ Dados
$gallery = product_gallery($product);
$pyramid = product_pyramid($product);
$rating = product_rating($productId);
$reviews = product_reviews($productId, 10);
$inStock = (int) $product['stock'] > 0;
$collectionLabel = collections()[$product['collection']]['label'] ?? '';
$inWishlist = $user ? wishlist_has((int) $user['id'], $productId) : false;
$canReview = $user ? user_can_review((int) $user['id'], $productId) : false;
$myReview = ($user && $canReview) ? user_review((int) $user['id'], $productId) : null;

// "Eau de Parfum – 10 ml" (cai no subtítulo se não houver concentração)
// Tamanhos (variações). O primeiro com estoque já vem selecionado.
$variants = product_variants($productId);
$selected = null;
foreach ($variants as $v) {
    if ((int) $v['stock'] > 0) {
        $selected = $v;
        break;
    }
}
$selected = $selected ?? ($variants[0] ?? null);
if ($variants) {
    $inStock = $selected && (int) $selected['stock'] > 0;
}
$displayPrice = $selected ? (float) $selected['price'] : (float) $product['price'];
$displaySku = $selected['sku'] ?? $product['sku'];
$displayStock = $selected ? (int) $selected['stock'] : (int) $product['stock'];
$stockText = fn(int $n) => $n > 0 ? ($n <= 10 ? 'Últimas ' . $n . ' unidades' : 'Em estoque') : 'Sem estoque no momento';

// Com tamanhos, o volume aparece no seletor; o subtítulo mostra só a concentração.
$subtitleParts = array_filter([trim((string) $product['concentration']), (!$variants && $product['volume_ml']) ? (int) $product['volume_ml'] . ' ml' : '']);
$subtitle = $subtitleParts ? implode(' – ', $subtitleParts) : (string) $product['tagline'];

$rel = db()->prepare('SELECT * FROM products WHERE is_active = 1 AND id <> ? ORDER BY sold_count DESC LIMIT 3');
$rel->execute([$productId]);
$related = $rel->fetchAll();

$pageTitle = $product['name'] . ' — King Decants';
$metaDescription = $product['short_description'];
$activeNav = 'descobrir';
require __DIR__ . '/inc/header.php';
?>

<section class="mx-auto max-w-7xl px-5 py-8 lg:px-8 lg:py-12">
    <nav class="mb-6 text-[11px] uppercase tracking-[0.2em] text-ink-500" aria-label="Breadcrumb">
        <a href="<?= e(url('index.php')) ?>" class="hover:text-gold-300">Início</a>
        <span class="mx-2 text-gold-600">/</span>
        <a href="<?= e(url('index.php?colecao=' . $product['collection'] . '#mais-vendidos')) ?>" class="hover:text-gold-300"><?= e($collectionLabel) ?></a>
        <span class="mx-2 text-gold-600">/</span>
        <span class="text-ink-300"><?= e($product['name']) ?></span>
    </nav>

    <div class="grid gap-6 lg:grid-cols-[96px_minmax(0,1.05fr)_minmax(0,1fr)] lg:gap-8">

        <!-- ======================= MINIATURAS (lateral esquerda) ======================= -->
        <?php if (count($gallery) > 1): ?>
            <div class="order-2 flex gap-3 overflow-x-auto pb-1 lg:order-1 lg:max-h-[640px] lg:flex-col lg:overflow-y-auto lg:overflow-x-visible lg:pb-0" role="tablist" aria-label="Fotos do produto">
                <?php foreach ($gallery as $i => $src): ?>
                    <button type="button" role="tab" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" aria-label="Foto <?= $i + 1 ?>"
                            data-gallery-thumb="<?= e($src) ?>"
                            class="gallery-thumb aspect-[4/5] w-20 shrink-0 overflow-hidden rounded-lg border bg-ink-900 transition lg:w-full <?= $i === 0 ? 'is-active' : '' ?>">
                        <img src="<?= e($src) ?>" alt="" loading="lazy" class="h-full w-full object-cover"
                             onerror="this.onerror=null;this.src='<?= e(url('assets/img/product-placeholder.svg')) ?>'">
                    </button>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="hidden lg:order-1 lg:block"></div>
        <?php endif; ?>

        <!-- ======================= FOTO PRINCIPAL ======================= -->
        <div class="relative order-1 lg:order-2">
            <div class="product-stage skeleton relative overflow-hidden rounded-2xl border border-white/10">
                <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_center,rgba(212,175,55,.28),transparent_65%)]"></div>
                <img id="gallery-main" data-skeleton src="<?= e($gallery[0]) ?>" alt="<?= e($product['name']) ?>"
                     class="relative aspect-[4/5] w-full object-cover transition-opacity duration-300"
                     onerror="this.onerror=null;this.src='<?= e(url('assets/img/product-placeholder.svg')) ?>'">
            </div>
        </div>

        <!-- ======================= INFORMAÇÕES ======================= -->
        <div class="order-3 flex flex-col animate-fade-up">
            <h1 class="font-serif text-4xl leading-[1.05] text-white sm:text-5xl"><?= e($product['name']) ?></h1>
            <?php if ($subtitle !== ''): ?>
                <p class="mt-3 text-lg text-ink-200"><?= e($subtitle) ?></p>
            <?php endif; ?>

            <!-- Avaliações (somente reais) -->
            <a href="#avaliacoes" class="mt-4 inline-flex w-fit items-center gap-3 text-ink-200 hover:text-white">
                <?= stars_html($rating['avg']) ?>
                <?php if ($rating['count'] > 0): ?>
                    <span><?= e(number_format($rating['avg'], 1, ',', '')) ?> <span class="text-ink-400">(<?= (int) $rating['count'] ?> <?= $rating['count'] === 1 ? 'avaliação' : 'avaliações' ?>)</span></span>
                <?php else: ?>
                    <span class="text-sm text-ink-400">Ainda sem avaliações</span>
                <?php endif; ?>
            </a>

            <p class="mt-5 font-serif text-5xl font-semibold text-gold-gradient" data-variant-price><?= money($displayPrice) ?></p>
            <p class="mt-1 text-xs text-ink-500">em até 12x no cartão ou à vista no PIX via <?= e(payment_provider_name()) ?></p>

            <p class="mt-6 max-w-md text-lg leading-relaxed text-ink-200"><?= e($product['short_description']) ?></p>

            <!-- Pirâmide olfativa -->
            <?php if ($pyramid): ?>
                <h2 class="mt-8 text-lg font-medium uppercase tracking-[0.06em] text-white">Notas olfativas</h2>
                <ul class="mt-4 space-y-2.5">
                    <?php foreach ($pyramid as $level): ?>
                        <li class="note-row">
                            <span class="note-label"><?= e($level['label']) ?></span>
                            <span class="note-body">
                                <span class="flex-1 text-[15px] text-ink-100"><?= e($level['notes']) ?></span>
                                <img src="<?= e($level['image']) ?>" alt="" loading="lazy" class="h-14 w-28 shrink-0 object-contain">
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <!-- Escolha do tamanho (antes dos botões). Os rádios pertencem ao formulário
                 da sacola pelo atributo form="", então funcionam até sem JavaScript. -->
            <?php if ($variants): ?>
                <fieldset class="mt-8">
                    <legend class="text-sm uppercase tracking-[0.16em] text-white">Tamanho: <span class="text-gold-300" data-variant-label><?= e($selected['label']) ?></span></legend>
                    <div class="mt-3 flex flex-wrap gap-3">
                        <?php foreach ($variants as $v): $out = (int) $v['stock'] <= 0; ?>
                            <label class="variant-pill <?= $out ? 'is-out' : '' ?>">
                                <input type="radio" name="variant_id" value="<?= (int) $v['id'] ?>" form="add-to-cart-form"
                                       data-price="<?= e(money($v['price'])) ?>" data-sku="<?= e($v['sku']) ?>" data-label="<?= e($v['label']) ?>"
                                       data-stock="<?= (int) $v['stock'] ?>" data-stock-text="<?= e($stockText((int) $v['stock'])) ?>"
                                       <?= $selected && (int) $selected['id'] === (int) $v['id'] ? 'checked' : '' ?> <?= $out ? 'disabled' : '' ?> required>
                                <span class="variant-pill-label"><?= e($v['label']) ?></span>
                                <span class="variant-pill-price"><?= $out ? 'Esgotado' : e(money($v['price'])) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            <?php endif; ?>

            <!-- Ações -->
            <div class="mt-8 grid gap-3 sm:grid-cols-2">
                <?php if ($user): ?>
                    <form action="<?= e(url('wishlist.php')) ?>" method="post" data-wishlist>
                        <?= csrf_field() ?>
                        <input type="hidden" name="product_id" value="<?= $productId ?>">
                        <button type="submit" class="btn-wishlist w-full <?= $inWishlist ? 'is-active' : '' ?>" aria-pressed="<?= $inWishlist ? 'true' : 'false' ?>">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" fill="none" aria-hidden="true"><path d="M12 20s-7-4.4-9.2-8.6C1.3 8.4 3.2 5 6.6 5c2 0 3.4 1.1 4.4 2.5C12 6.1 13.4 5 15.4 5c3.4 0 5.3 3.4 3.8 6.4C19 15.6 12 20 12 20z"/></svg>
                            <span data-wishlist-label><?= $inWishlist ? 'Nos favoritos' : 'Adicionar aos favoritos' ?></span>
                        </button>
                    </form>
                <?php else: ?>
                    <button type="button" class="btn-wishlist w-full" data-open-modal="login-modal">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" fill="none" aria-hidden="true"><path d="M12 20s-7-4.4-9.2-8.6C1.3 8.4 3.2 5 6.6 5c2 0 3.4 1.1 4.4 2.5C12 6.1 13.4 5 15.4 5c3.4 0 5.3 3.4 3.8 6.4C19 15.6 12 20 12 20z"/></svg>
                        Adicionar aos favoritos
                    </button>
                <?php endif; ?>

                <form id="add-to-cart-form" action="<?= e(url('cart.php')) ?>" method="post" data-add-to-cart>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?= $productId ?>">
                    <input type="hidden" name="quantity" value="1">
                    <button type="submit" class="btn-gold btn-lg w-full" <?= $inStock ? '' : 'disabled' ?> data-loading-text="Adicionando…" data-add-button>
                        <?= $inStock ? 'Adicionar à sacola' : 'Esgotado' ?>
                    </button>
                </form>
            </div>
            <p class="mt-4 text-xs tracking-wider text-ink-500">
                SKU <span data-variant-sku><?= e($displaySku) ?></span> ·
                <span data-variant-stock><?= e($stockText($displayStock)) ?></span>
                · Frete grátis acima de <?= money(FREE_SHIPPING_FROM) ?>
            </p>
        </div>
    </div>
</section>

<div class="gold-divider mx-auto max-w-7xl"></div>

<!-- ======================= DESCRIÇÃO + AVALIAÇÕES ======================= -->
<section class="mx-auto grid max-w-7xl gap-12 px-5 py-14 lg:grid-cols-2 lg:px-8">
    <div>
        <h2 class="text-xs font-medium uppercase tracking-[0.28em] text-white">Sobre este decant</h2>
        <p class="mt-5 leading-relaxed text-ink-300"><?= nl2br(e($product['description'] ?: $product['short_description'])) ?></p>
        <ul class="mt-8 space-y-3 text-sm text-ink-400">
            <li class="flex items-center gap-3"><span class="text-gold-400"><?= icon('shield', 'h-5 w-5') ?></span> Envasado a partir do frasco original, com lote registrado</li>
            <li class="flex items-center gap-3"><span class="text-gold-400"><?= icon('truck', 'h-5 w-5') ?></span> Envio para todo o Brasil</li>
            <li class="flex items-center gap-3"><span class="text-gold-400"><?= icon('card', 'h-5 w-5') ?></span> Pagamento seguro via <?= e(payment_provider_name()) ?> (PIX e cartão)</li>
        </ul>
    </div>

    <div id="avaliacoes" class="scroll-mt-28">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <h2 class="text-xs font-medium uppercase tracking-[0.28em] text-white">Avaliações</h2>
            <?php if ($rating['count'] > 0): ?>
                <p class="flex items-center gap-2 text-sm text-ink-300"><?= stars_html($rating['avg'], 'h-4 w-4') ?> <?= e(number_format($rating['avg'], 1, ',', '')) ?> de 5 · <?= (int) $rating['count'] ?></p>
            <?php endif; ?>
        </div>

        <?php if ($reviews): ?>
            <ul class="mt-6 space-y-5">
                <?php foreach ($reviews as $rv): ?>
                    <li class="panel p-5">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-sm text-white"><?= e(explode(' ', trim($rv['name']))[0]) ?> <span class="ml-1 text-[10px] uppercase tracking-wider text-gold-500">compra verificada</span></span>
                            <?= stars_html((float) $rv['rating'], 'h-4 w-4') ?>
                        </div>
                        <?php if ($rv['comment']): ?><p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-ink-300"><?= e($rv['comment']) ?></p><?php endif; ?>
                        <p class="mt-2 text-xs text-ink-500"><?= date_br($rv['created_at'], false) ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="mt-6 text-sm text-ink-400">Este decant ainda não recebeu avaliações.</p>
        <?php endif; ?>

        <!-- Formulário: somente compradores verificados -->
        <?php if ($canReview): ?>
            <form method="post" class="panel mt-8 space-y-4 p-5" data-loading>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="review">
                <p class="text-sm text-white"><?= $myReview ? 'Editar sua avaliação' : 'Avalie este decant' ?></p>
                <fieldset class="star-input" aria-label="Nota">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <input type="radio" id="rate-<?= $i ?>" name="rating" value="<?= $i ?>" <?= (int) ($myReview['rating'] ?? 0) === $i ? 'checked' : '' ?> required>
                        <label for="rate-<?= $i ?>" title="<?= $i ?> estrela<?= $i > 1 ? 's' : '' ?>">★</label>
                    <?php endfor; ?>
                </fieldset>
                <textarea name="comment" rows="3" maxlength="1000" class="field" placeholder="Conte como foi sua experiência (opcional)"><?= e($myReview['comment'] ?? '') ?></textarea>
                <button class="btn-gold btn-sm" data-loading-text="Enviando…">Publicar avaliação</button>
            </form>
        <?php elseif ($user): ?>
            <p class="mt-8 text-xs text-ink-500">Somente clientes que compraram este decant com a conta podem avaliá-lo.</p>
        <?php else: ?>
            <p class="mt-8 text-xs text-ink-500">Comprou este decant? <button type="button" class="text-gold-400 hover:text-gold-300" data-open-modal="login-modal">Entre na sua conta</button> para avaliar.</p>
        <?php endif; ?>
    </div>
</section>

<?php if ($related): ?>
<div class="gold-divider mx-auto max-w-7xl"></div>
<section class="mx-auto max-w-7xl px-5 py-16 lg:px-8">
    <h2 class="mb-10 text-xs font-medium uppercase tracking-[0.28em] text-white">Você também pode gostar</h2>
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($related as $index => $product): ?>
            <?php include __DIR__ . '/inc/product_card.php'; ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/inc/footer.php'; ?>
