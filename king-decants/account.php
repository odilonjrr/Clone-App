<?php
/**
 * King Decants — account.php (Minha conta)
 * ---------------------------------------------------------------------------
 * Área do cliente logado: dados cadastrais e histórico de pedidos feitos
 * com a conta (pedidos de convidado não aparecem — ver nota em register.php).
 * ---------------------------------------------------------------------------
 */
require __DIR__ . '/inc/bootstrap.php';

$user = current_user();
if (!$user) {
    flash('info', 'Entre na sua conta para continuar.');
    redirect(url('auth/login.php?redirect=account.php'));
}

$stmt = db()->prepare('SELECT id, access_token, status, total, created_at FROM orders WHERE user_id = ? AND is_guest_order = 0 ORDER BY id DESC LIMIT 50');
$stmt->execute([$user['id']]);
$orders = $stmt->fetchAll();

$fav = db()->prepare('SELECT p.* FROM wishlists w JOIN products p ON p.id = w.product_id WHERE w.user_id = ? AND p.is_active = 1 ORDER BY w.created_at DESC');
$fav->execute([$user['id']]);
$favorites = $fav->fetchAll();

$pageTitle = 'Minha conta — King Decants';
require __DIR__ . '/inc/header.php';
?>
<section class="mx-auto max-w-6xl px-5 py-12 lg:py-16">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="eyebrow">Minha conta</p>
            <h1 class="mt-3 font-serif text-5xl text-white">Olá, <em class="text-gold-gradient"><?= e(explode(' ', $user['name'])[0]) ?></em></h1>
        </div>
        <a href="<?= e(url('auth/logout.php')) ?>" class="btn-outline btn-sm"><?= icon('logout', 'h-4 w-4') ?> Sair</a>
    </div>
    <div class="gold-divider my-10"></div>

    <div class="grid gap-8 lg:grid-cols-3">
        <aside class="panel h-fit p-6">
            <h2 class="text-xs font-medium uppercase tracking-[0.24em] text-white">Seus dados</h2>
            <dl class="mt-5 space-y-3 text-sm">
                <div><dt class="text-ink-500">Nome</dt><dd class="text-ink-200"><?= e($user['name']) ?></dd></div>
                <div><dt class="text-ink-500">E-mail</dt><dd class="text-ink-200"><?= e($user['email']) ?></dd></div>
                <div><dt class="text-ink-500">Telefone</dt><dd class="text-ink-200"><?= $user['phone'] ? e(format_phone($user['phone'])) : '—' ?></dd></div>
                <div><dt class="text-ink-500">CPF</dt><dd class="text-ink-200"><?= $user['cpf'] ? e(format_cpf($user['cpf'])) : '—' ?></dd></div>
            </dl>
        </aside>

        <div class="panel overflow-x-auto lg:col-span-2">
            <h2 class="px-6 pt-6 text-xs font-medium uppercase tracking-[0.24em] text-white">Pedidos</h2>
            <?php if (!$orders): ?>
                <p class="px-6 py-10 text-sm text-ink-400">Você ainda não fez pedidos. <a class="text-gold-400" href="<?= e(url('index.php#mais-vendidos')) ?>">Explorar a loja</a></p>
            <?php else: ?>
                <table class="table-lux mt-4">
                    <thead><tr><th>Pedido</th><th>Data</th><th>Status</th><th class="text-right">Total</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td class="text-white">#<?= (int) $o['id'] ?></td>
                            <td class="text-ink-400"><?= date_br($o['created_at']) ?></td>
                            <td><?= status_badge($o['status']) ?></td>
                            <td class="text-right text-ink-200"><?= money($o['total']) ?></td>
                            <td class="text-right"><a class="text-xs uppercase tracking-[0.18em] text-gold-400 hover:text-gold-300" href="<?= e(url('callback.php?order=' . $o['id'] . '&token=' . $o['access_token'])) ?>">Ver</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Favoritos -->
    <div id="favoritos" class="mt-12 scroll-mt-28">
        <h2 class="text-xs font-medium uppercase tracking-[0.24em] text-white">Favoritos</h2>
        <?php if (!$favorites): ?>
            <p class="mt-4 text-sm text-ink-400">Você ainda não salvou favoritos. Use o coração na página de cada decant.</p>
        <?php else: ?>
            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <?php foreach ($favorites as $p): ?>
                    <div class="panel flex gap-4 p-4">
                        <a href="<?= e(url('product.php?slug=' . urlencode($p['slug']))) ?>" class="h-24 w-20 shrink-0 overflow-hidden rounded-md bg-ink-900"><?= product_img($p, 'h-full w-full object-cover') ?></a>
                        <div class="flex min-w-0 flex-1 flex-col">
                            <a href="<?= e(url('product.php?slug=' . urlencode($p['slug']))) ?>" class="truncate font-serif text-lg text-white hover:text-gold-300"><?= e($p['name']) ?></a>
                            <span class="text-sm text-gold-300"><?= money($p['price']) ?></span>
                            <form action="<?= e(url('wishlist.php')) ?>" method="post" class="mt-auto">
                                <?= csrf_field() ?>
                                <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
                                <input type="hidden" name="back" value="account">
                                <button class="text-[11px] uppercase tracking-[0.16em] text-ink-500 hover:text-red-300">Remover</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/inc/footer.php'; ?>
