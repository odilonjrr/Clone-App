<?php
/**
 * King Decants — inc/product_card.php
 * ---------------------------------------------------------------------------
 * Card de produto reutilizável (vitrine). Espera a variável $product
 * (linha da tabela products) e opcionalmente $index (para escalonar animação).
 * O botão "Adicionar" envia para cart.php via AJAX (data-add-to-cart) e, sem
 * JavaScript, funciona como POST normal.
 * ---------------------------------------------------------------------------
 */
$index = $index ?? 0;
$inStock = (int) $product['stock'] > 0;
$productUrl = url('product.php?slug=' . urlencode($product['slug']));
?>
<article class="card-lux group reveal flex flex-col overflow-hidden" style="transition-delay: <?= (int) $index * 90 ?>ms">
    <a href="<?= e($productUrl) ?>" class="skeleton relative block aspect-[4/5] overflow-hidden">
        <?= str_replace('<img ', '<img data-skeleton ', product_img($product, 'h-full w-full object-cover transition-transform duration-[1.2s] ease-out group-hover:scale-105')) ?>
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-transparent"></div>
        <?php if (!$inStock): ?>
            <span class="absolute left-4 top-4 rounded-full border border-white/20 bg-black/70 px-3 py-1 text-[10px] uppercase tracking-[0.2em] text-ink-300">Esgotado</span>
        <?php elseif ((int) $product['stock'] <= 10): ?>
            <span class="absolute left-4 top-4 rounded-full border border-gold-400/40 bg-black/60 px-3 py-1 text-[10px] uppercase tracking-[0.2em] text-gold-300">Últimas <?= (int) $product['stock'] ?> unid.</span>
        <?php endif; ?>
    </a>

    <div class="flex flex-1 flex-col gap-3 p-6">
        <p class="text-[10px] uppercase tracking-[0.24em] text-gold-500"><?= e($product['tagline'] ?? '') ?></p>
        <h3 class="font-serif text-2xl leading-tight text-white">
            <a href="<?= e($productUrl) ?>" class="transition-colors hover:text-gold-300"><?= e($product['name']) ?></a>
        </h3>
        <p class="line-clamp-2 text-sm leading-relaxed text-ink-400"><?= e($product['short_description']) ?></p>
        <p class="text-[11px] tracking-wider text-ink-500">SKU <?= e($product['sku']) ?> · <?= $inStock ? (int) $product['stock'] . ' em estoque' : 'Sem estoque' ?></p>

        <div class="mt-auto flex items-center justify-between gap-3 pt-4">
            <span class="whitespace-nowrap font-serif text-2xl text-gold-300"><?= product_price_label($product) ?></span>
            <?php if (product_variant_count((int) $product['id']) > 0): ?>
                <!-- Produto com tamanhos: a escolha é feita na página do produto -->
                <a href="<?= e($productUrl) ?>" class="btn-outline btn-sm" <?= $inStock ? '' : 'aria-disabled="true"' ?>>Escolher tamanho</a>
            <?php else: ?>
            <form action="<?= e(url('cart.php')) ?>" method="post" data-add-to-cart>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                <input type="hidden" name="quantity" value="1">
                <button type="submit" class="btn-outline btn-sm" <?= $inStock ? '' : 'disabled' ?> data-loading-text="…" aria-label="Adicionar <?= e($product['name']) ?> à sacola">
                    <?= icon('bag', 'h-4 w-4') ?> <span class="hidden sm:inline">Adicionar</span>
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</article>
