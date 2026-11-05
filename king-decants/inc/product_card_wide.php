<?php
/**
 * King Decants — inc/product_card_wide.php
 * ---------------------------------------------------------------------------
 * Card horizontal da vitrine "Mais vendidos" da home:
 *   [foto com luz dourada] | NOME · concentração · preço · estrelas · botão
 * Espera $product (com rating_avg e rating_count vindos da consulta da home)
 * e opcionalmente $index (atraso da animação).
 * As estrelas só aparecem quando há avaliações reais.
 * ---------------------------------------------------------------------------
 */
$index = $index ?? 0;
$inStock = (int) $product['stock'] > 0;
$productUrl = url('product.php?slug=' . urlencode($product['slug']));
$ratingCount = (int) ($product['rating_count'] ?? 0);
$subtitle = trim((string) ($product['concentration'] ?? '')) ?: (string) $product['tagline'];
?>
<article class="wide-card group reveal" style="transition-delay: <?= (int) $index * 90 ?>ms">
    <a href="<?= e($productUrl) ?>" class="wide-card-media skeleton" aria-label="<?= e($product['name']) ?>">
        <span class="pointer-events-none absolute inset-0 bg-cover bg-center opacity-70 transition-opacity duration-700 group-hover:opacity-100" style="background-image:url('<?= e(url('assets/img/hero-rays.svg')) ?>')"></span>
        <?= str_replace('<img ', '<img data-skeleton ', product_img($product, 'relative h-full w-full object-cover mix-blend-normal transition-transform duration-[1.2s] ease-out group-hover:scale-105')) ?>
        <?php if (!$inStock): ?>
            <span class="absolute left-3 top-3 rounded-full border border-white/20 bg-black/70 px-3 py-1 text-[10px] uppercase tracking-[0.2em] text-ink-300">Esgotado</span>
        <?php endif; ?>
    </a>
    <div class="flex flex-1 flex-col justify-center gap-1.5 p-6 sm:p-7">
        <h3 class="font-serif text-2xl uppercase leading-tight tracking-[0.04em] text-gold-300">
            <a href="<?= e($productUrl) ?>" class="hover:text-gold-200"><?= e($product['name']) ?></a>
        </h3>
        <?php if ($subtitle !== ''): ?><p class="text-ink-200"><?= e($subtitle) ?></p><?php endif; ?>
        <p class="mt-1 text-2xl font-medium text-gold-300"><?= product_price_label($product) ?></p>
        <?php if ($ratingCount > 0): ?>
            <p class="flex items-center gap-2 text-ink-200"><?= stars_html((float) $product['rating_avg'], 'h-4 w-4') ?> <?= e(number_format((float) $product['rating_avg'], 1, ',', '')) ?></p>
        <?php endif; ?>
        <?php if (product_variant_count((int) $product['id']) > 0): ?>
            <!-- Produto com tamanhos: leva à página para escolher -->
            <a href="<?= e($productUrl) ?>" class="btn-gold mt-4 w-full"><?= $inStock ? 'Escolher tamanho' : 'Esgotado' ?></a>
        <?php else: ?>
        <form action="<?= e(url('cart.php')) ?>" method="post" class="mt-4" data-add-to-cart>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
            <input type="hidden" name="quantity" value="1">
            <button type="submit" class="btn-gold w-full" <?= $inStock ? '' : 'disabled' ?> data-loading-text="Adicionando…">
                <?= $inStock ? 'Adicionar à sacola' : 'Esgotado' ?>
            </button>
        </form>
        <?php endif; ?>
    </div>
</article>
