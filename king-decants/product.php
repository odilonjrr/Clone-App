<?php
/**
 * King Decants — product.php (Detalhe do produto)
 * ---------------------------------------------------------------------------
 * Acesso: product.php?slug=decants-basicos  (ou ?id=1)
 * Mostra imagem, descrição completa, SKU, preço, estoque e seletor de
 * quantidade. "Adicionar à sacola" usa AJAX (contador atualiza sem recarregar);
 * "Comprar agora" adiciona e leva direto para o carrinho.
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

// Relacionados: outros produtos ativos
$rel = db()->prepare('SELECT * FROM products WHERE is_active = 1 AND id <> ? ORDER BY sold_count DESC LIMIT 3');
$rel->execute([$product['id']]);
$related = $rel->fetchAll();

$inStock = (int) $product['stock'] > 0;
$maxQty = min(CART_MAX_QTY_PER_ITEM, (int) $product['stock']);
$collectionLabel = collections()[$product['collection']]['label'] ?? '';

$pageTitle = $product['name'] . ' — King Decants';
$metaDescription = $product['short_description'];
$activeNav = 'descobrir';
require __DIR__ . '/inc/header.php';
?>

<section class="mx-auto max-w-7xl px-5 py-10 lg:px-8 lg:py-16">
    <nav class="mb-8 text-[11px] uppercase tracking-[0.2em] text-ink-500" aria-label="Breadcrumb">
        <a href="<?= e(url('index.php')) ?>" class="hover:text-gold-300">Início</a>
        <span class="mx-2 text-gold-600">/</span>
        <a href="<?= e(url('index.php?colecao=' . $product['collection'] . '#mais-vendidos')) ?>" class="hover:text-gold-300"><?= e($collectionLabel) ?></a>
        <span class="mx-2 text-gold-600">/</span>
        <span class="text-ink-300"><?= e($product['name']) ?></span>
    </nav>

    <div class="grid gap-12 lg:grid-cols-2 lg:gap-16">
        <!-- Imagem -->
        <div class="relative animate-fade-up">
            <div class="pointer-events-none absolute inset-10 rounded-full bg-gold-400/15 blur-[90px]"></div>
            <div class="skeleton relative overflow-hidden rounded-2xl border border-white/5">
                <?= str_replace('<img ', '<img data-skeleton ', product_img($product, 'aspect-[4/5] w-full object-cover')) ?>
            </div>
        </div>

        <!-- Informações -->
        <div class="flex flex-col justify-center animate-fade-up" style="animation-delay:.1s">
            <p class="eyebrow"><?= e($product['tagline']) ?></p>
            <h1 class="mt-4 font-serif text-5xl leading-tight text-white"><?= e($product['name']) ?></h1>
            <p class="mt-6 font-serif text-4xl text-gold-300"><?= money($product['price']) ?></p>
            <p class="mt-1 text-sm text-ink-500">em até 12x no cartão ou à vista no PIX via Mercado Pago</p>

            <div class="gold-divider my-8"></div>

            <p class="leading-relaxed text-ink-300"><?= nl2br(e($product['description'] ?: $product['short_description'])) ?></p>

            <dl class="mt-8 grid grid-cols-3 gap-4 text-sm">
                <div class="panel px-4 py-3"><dt class="text-[10px] uppercase tracking-[0.2em] text-ink-500">SKU</dt><dd class="mt-1 text-white"><?= e($product['sku']) ?></dd></div>
                <div class="panel px-4 py-3"><dt class="text-[10px] uppercase tracking-[0.2em] text-ink-500">Volume</dt><dd class="mt-1 text-white"><?= (int) $product['volume_ml'] ?> ml</dd></div>
                <div class="panel px-4 py-3"><dt class="text-[10px] uppercase tracking-[0.2em] text-ink-500">Estoque</dt><dd class="mt-1 <?= $inStock ? 'text-white' : 'text-red-300' ?>"><?= $inStock ? (int) $product['stock'] . ' unid.' : 'Esgotado' ?></dd></div>
            </dl>

            <?php if ($inStock): ?>
                <form action="<?= e(url('cart.php')) ?>" method="post" class="mt-10 flex flex-col gap-4 sm:flex-row" data-add-to-cart>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                    <label class="sr-only" for="qty">Quantidade</label>
                    <select id="qty" name="quantity" class="field sm:w-28">
                        <?php for ($i = 1; $i <= $maxQty; $i++): ?>
                            <option value="<?= $i ?>"><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                    <button type="submit" class="btn-gold flex-1" data-loading-text="Adicionando…"><?= icon('bag', 'h-4 w-4') ?> Adicionar à sacola</button>
                </form>
                <!-- Comprar agora: POST normal (sem AJAX) que adiciona e redireciona ao carrinho -->
                <form action="<?= e(url('cart.php')) ?>" method="post" class="mt-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                    <input type="hidden" name="quantity" value="1">
                    <input type="hidden" name="go" value="cart">
                    <button type="submit" class="btn-outline w-full">Comprar agora</button>
                </form>
            <?php else: ?>
                <div class="alert mt-10">Este decant está esgotado no momento. Volte em breve.</div>
            <?php endif; ?>

            <ul class="mt-10 space-y-3 text-sm text-ink-400">
                <li class="flex items-center gap-3"><span class="text-gold-400"><?= icon('shield', 'h-5 w-5') ?></span> Pagamento seguro via Mercado Pago (PIX e cartão)</li>
                <li class="flex items-center gap-3"><span class="text-gold-400"><?= icon('truck', 'h-5 w-5') ?></span> Frete grátis acima de <?= money(FREE_SHIPPING_FROM) ?></li>
            </ul>
        </div>
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
