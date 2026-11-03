<?php
/**
 * King Decants — cart.php (Sacola / Carrinho)
 * ---------------------------------------------------------------------------
 * GET  -> exibe a sacola (itens, quantidades, subtotal, frete estimado).
 * POST -> ações (todas com CSRF):
 *           action=add      product_id, quantity    (usado também via AJAX)
 *           action=update   product_id, quantity    (0 remove)
 *           action=remove   product_id
 *           action=clear
 *         Se a requisição for AJAX (Accept: application/json), responde JSON.
 *
 * "Finalizar compra" sem login abre um modal com 3 caminhos:
 *   Criar conta & pagar | Entrar | Continuar como convidado
 * ---------------------------------------------------------------------------
 */
require __DIR__ . '/inc/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $action = $_POST['action'] ?? '';
    $productId = int_param($_POST, 'product_id');
    $qty = int_param($_POST, 'quantity', 1);
    $result = ['ok' => true];
    $message = null;

    switch ($action) {
        case 'add':
            $result = cart_add($productId, max(1, $qty));
            $message = $result['ok'] ? ($result['notice'] ?? 'Adicionado à sacola.') : null;
            break;
        case 'update':
            $result = cart_set($productId, max(0, $qty));
            $message = $result['ok'] ? ($result['notice'] ?? 'Sacola atualizada.') : null;
            break;
        case 'remove':
            cart_remove($productId);
            $message = 'Item removido.';
            break;
        case 'clear':
            cart_clear();
            $message = 'Sacola esvaziada.';
            break;
        default:
            $result = ['ok' => false, 'error' => 'Ação inválida.'];
    }

    if (wants_json()) {
        $cart = cart_items_detailed();
        json_response([
            'ok'       => (bool) $result['ok'],
            'message'  => $message,
            'error'    => $result['error'] ?? null,
            'count'    => $cart['count'],
            'subtotal' => money($cart['subtotal']),
        ], $result['ok'] ? 200 : 422);
    }

    flash($result['ok'] ? 'success' : 'error', $result['ok'] ? (string) $message : (string) $result['error']);
    redirect(url('cart.php'));
}

// ------------------------------------------------------------------ GET
$cart = cart_items_detailed();
$user = current_user();

$pageTitle = 'Sacola — King Decants';
require __DIR__ . '/inc/header.php';
?>

<section class="mx-auto max-w-7xl px-5 py-12 lg:px-8 lg:py-16">
    <p class="eyebrow">Sua seleção</p>
    <h1 class="mt-3 font-serif text-5xl text-white">Sacola</h1>
    <div class="gold-divider my-10"></div>

    <?php if (!$cart['items']): ?>
        <div class="panel mx-auto max-w-xl px-6 py-16 text-center animate-fade-up">
            <span class="mx-auto inline-flex text-gold-400"><?= icon('bag', 'h-10 w-10') ?></span>
            <p class="mt-6 font-serif text-3xl text-white">Sua sacola está vazia</p>
            <p class="mt-2 text-sm text-ink-400">Descubra decants que marcam presença.</p>
            <a href="<?= e(url('index.php#mais-vendidos')) ?>" class="btn-gold mt-8">Explorar a loja</a>
        </div>
    <?php else: ?>
        <div class="grid gap-10 lg:grid-cols-3">
            <!-- Itens -->
            <div class="space-y-4 lg:col-span-2">
                <?php foreach ($cart['items'] as $item): ?>
                    <div class="panel flex gap-4 p-4 animate-fade-up sm:gap-6 sm:p-5">
                        <a href="<?= e(url('product.php?slug=' . urlencode($item['slug']))) ?>" class="h-28 w-24 shrink-0 overflow-hidden rounded-lg bg-ink-900 sm:h-32 sm:w-28">
                            <?= product_img($item, 'h-full w-full object-cover') ?>
                        </a>
                        <div class="flex flex-1 flex-col">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-[10px] uppercase tracking-[0.22em] text-gold-500"><?= e($item['tagline']) ?></p>
                                    <h2 class="mt-1 font-serif text-xl text-white sm:text-2xl"><?= e($item['name']) ?></h2>
                                    <p class="mt-1 text-xs text-ink-500">SKU <?= e($item['sku']) ?> · <?= money($item['price']) ?> / unid.</p>
                                </div>
                                <form action="<?= e(url('cart.php')) ?>" method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>">
                                    <button class="icon-btn text-ink-400 hover:text-red-300" aria-label="Remover <?= e($item['name']) ?>"><?= icon('trash', 'h-5 w-5') ?></button>
                                </form>
                            </div>

                            <div class="mt-auto flex items-end justify-between gap-3 pt-4">
                                <!-- Alterar quantidade: botões -/+ enviam o novo valor -->
                                <form action="<?= e(url('cart.php')) ?>" method="post" class="flex items-center rounded-md border border-white/10">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>">
                                    <button name="quantity" value="<?= (int) $item['quantity'] - 1 ?>" class="flex h-9 w-9 items-center justify-center text-gold-400 hover:bg-white/5" aria-label="Diminuir"><?= icon('minus', 'h-4 w-4') ?></button>
                                    <span class="w-10 text-center text-sm text-white"><?= (int) $item['quantity'] ?></span>
                                    <button name="quantity" value="<?= (int) $item['quantity'] + 1 ?>" class="flex h-9 w-9 items-center justify-center text-gold-400 hover:bg-white/5 disabled:opacity-30" aria-label="Aumentar" <?= $item['quantity'] >= min(CART_MAX_QTY_PER_ITEM, (int) $item['stock']) ? 'disabled' : '' ?>><?= icon('plus', 'h-4 w-4') ?></button>
                                </form>
                                <p class="whitespace-nowrap font-serif text-2xl text-gold-300"><?= money($item['line_total']) ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <form action="<?= e(url('cart.php')) ?>" method="post" class="pt-2 text-right">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="clear">
                    <button class="text-xs uppercase tracking-[0.2em] text-ink-500 hover:text-red-300" onclick="return confirm('Esvaziar a sacola?')">Esvaziar sacola</button>
                </form>
            </div>

            <!-- Resumo -->
            <aside class="panel h-fit p-6 lg:sticky lg:top-28">
                <h2 class="text-xs font-medium uppercase tracking-[0.24em] text-white">Resumo</h2>
                <dl class="mt-6 space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="text-ink-400">Subtotal (<?= (int) $cart['count'] ?> <?= $cart['count'] > 1 ? 'itens' : 'item' ?>)</dt><dd class="text-white"><?= money($cart['subtotal']) ?></dd></div>
                    <div class="flex justify-between"><dt class="text-ink-400">Frete</dt>
                        <dd class="text-white"><?= FREE_SHIPPING_FROM > 0 && $cart['subtotal'] >= FREE_SHIPPING_FROM ? 'Grátis' : 'Calculado no checkout' ?></dd></div>
                </dl>
                <?php if (FREE_SHIPPING_FROM > 0 && $cart['subtotal'] < FREE_SHIPPING_FROM): ?>
                    <p class="mt-4 rounded-md border border-gold-400/20 bg-gold-400/5 px-3 py-2 text-xs text-gold-200">
                        Faltam <?= money(FREE_SHIPPING_FROM - $cart['subtotal']) ?> para frete grátis.
                    </p>
                <?php endif; ?>
                <div class="gold-divider my-6"></div>
                <div class="flex items-baseline justify-between">
                    <span class="text-xs uppercase tracking-[0.2em] text-ink-400">Subtotal</span>
                    <span class="font-serif text-3xl text-white"><?= money($cart['subtotal']) ?></span>
                </div>

                <?php if ($user): ?>
                    <a href="<?= e(url('checkout.php')) ?>" class="btn-gold mt-6 w-full">Finalizar compra</a>
                <?php else: ?>
                    <!-- Sem login: abre o modal de escolha (sem JS, o link leva à tela de escolha no checkout) -->
                    <a href="<?= e(url('checkout.php')) ?>" data-open-modal="checkout-choice" class="btn-gold mt-6 w-full">Finalizar compra</a>
                <?php endif; ?>
                <a href="<?= e(url('index.php#mais-vendidos')) ?>" class="mt-4 block text-center text-xs uppercase tracking-[0.2em] text-ink-400 hover:text-gold-300">Continuar comprando</a>
            </aside>
        </div>
    <?php endif; ?>
</section>

<?php if (!$user && $cart['items']): ?>
<!-- Modal: como deseja finalizar? -->
<div id="checkout-choice" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="choice-title">
    <div class="modal-backdrop" data-close-modal></div>
    <div class="modal-panel">
        <button type="button" class="absolute right-4 top-4 text-ink-400 hover:text-white" data-close-modal aria-label="Fechar"><?= icon('close') ?></button>
        <p class="eyebrow">Quase lá</p>
        <h2 id="choice-title" class="mt-2 font-serif text-3xl text-white">Como deseja <em class="text-gold-gradient">finalizar?</em></h2>
        <div class="mt-6 space-y-3">
            <a href="<?= e(url('auth/register.php?redirect=checkout.php')) ?>" class="btn-gold w-full">Criar conta &amp; pagar</a>
            <button type="button" class="btn-outline w-full" onclick="document.querySelector('#login-modal input[name=redirect]').value='checkout.php';document.getElementById('checkout-choice').classList.add('hidden');document.getElementById('login-modal').classList.remove('hidden');">Já tenho conta — entrar</button>
            <a href="<?= e(url('checkout.php?guest=1')) ?>" class="block py-3 text-center text-xs uppercase tracking-[0.2em] text-ink-300 hover:text-gold-300">Continuar como convidado</a>
        </div>
        <p class="mt-4 text-center text-xs text-ink-500">Seu carrinho é mantido ao criar conta ou entrar.</p>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/inc/footer.php'; ?>
