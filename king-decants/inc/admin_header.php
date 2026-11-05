<?php
/**
 * King Decants — inc/admin_header.php
 * ---------------------------------------------------------------------------
 * Layout do painel /admin (mesma identidade da loja: preto, dourado, serifa).
 * Barra lateral no desktop e barra superior com menu no mobile.
 *
 * Variáveis esperadas: $admin (array do admin logado), $pageTitle, $adminNav
 * ('dashboard' | 'orders' | 'customers').
 * ---------------------------------------------------------------------------
 */
$pageTitle = $pageTitle ?? 'Painel';
$adminNav = $adminNav ?? '';
$adminLinks = [
    'dashboard' => ['Dashboard', 'admin/index.php', 'chart'],
    'orders'    => ['Pedidos', 'admin/orders.php', 'box'],
    'customers' => ['Clientes', 'admin/customers.php', 'users'],
    'products'  => ['Produtos', 'admin/products.php', 'gift'],
    'shipping'  => ['Frete', 'admin/shipping.php', 'truck'],
    'coupons'   => ['Cupons', 'admin/coupons.php', 'tag'],
    'email'     => ['E-mails', 'admin/email.php', 'mail'],
    'appearance'=> ['Aparência', 'admin/appearance.php', 'crown'],
    'account'   => ['Minha conta', 'admin/account.php', 'user'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($pageTitle) ?> · Admin King Decants</title>
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?= e(asset('js/tailwind.config.js')) ?>"></script>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="min-h-screen bg-ink-950 font-sans text-ink-200 antialiased">
<div class="lg:flex">
    <!-- Barra lateral -->
    <aside class="border-b border-gold-400/15 bg-ink-900 lg:sticky lg:top-0 lg:flex lg:h-screen lg:w-64 lg:shrink-0 lg:flex-col lg:border-b-0 lg:border-r">
        <div class="flex h-16 items-center justify-between px-5 lg:h-20">
            <a href="<?= e(url('admin/index.php')) ?>" class="flex items-center gap-2 text-gold-400">
                <?= icon('crown', 'h-5 w-5') ?>
                <span class="font-serif text-lg tracking-[0.25em] text-gold-gradient">KING</span>
                <span class="text-[10px] uppercase tracking-[0.25em] text-ink-500">Admin</span>
            </a>
            <button type="button" data-toggle="admin-nav" class="icon-btn text-gold-400 lg:hidden" aria-label="Menu"><?= icon('menu') ?></button>
        </div>
        <nav id="admin-nav" class="hidden flex-1 flex-col gap-1 px-3 pb-4 lg:flex">
            <?php foreach ($adminLinks as $key => [$label, $href, $ic]): ?>
                <a href="<?= e(url($href)) ?>"
                   class="flex items-center gap-3 rounded-md px-3 py-2.5 text-sm transition-colors <?= $adminNav === $key ? 'bg-gold-400/10 text-gold-300' : 'text-ink-300 hover:bg-white/5 hover:text-white' ?>">
                    <span class="text-gold-400"><?= icon($ic, 'h-5 w-5') ?></span> <?= e($label) ?>
                </a>
            <?php endforeach; ?>
            <div class="gold-divider my-3"></div>
            <a href="<?= e(url('index.php')) ?>" target="_blank" class="flex items-center gap-3 rounded-md px-3 py-2.5 text-sm text-ink-400 hover:text-white">
                <span class="text-gold-400"><?= icon('eye', 'h-5 w-5') ?></span> Ver loja
            </a>
            <div class="mt-auto px-3 pt-6 text-xs text-ink-500">
                <p class="text-ink-300"><?= e($admin['name']) ?></p>
                <p class="truncate"><?= e($admin['email']) ?></p>
                <a href="<?= e(url('admin/logout.php')) ?>" class="mt-3 inline-flex items-center gap-2 text-gold-400 hover:text-gold-300"><?= icon('logout', 'h-4 w-4') ?> Sair</a>
            </div>
        </nav>
    </aside>

    <!-- Conteúdo -->
    <main class="min-w-0 flex-1 px-5 py-8 lg:px-10 lg:py-10">
        <?php if ($admin['email'] === 'admin@kingdecants.local' && $adminNav !== 'account'): ?>
            <a href="<?= e(url('admin/account.php')) ?>" class="alert alert-error mb-6 block">
                Segurança: você está usando o acesso padrão de instalação. Clique aqui para trocar e-mail e senha.
            </a>
        <?php endif; ?>
        <div id="toast-stack" class="pointer-events-none fixed right-4 top-4 z-50 flex w-[calc(100%-2rem)] max-w-sm flex-col gap-3" aria-live="polite">
            <?php foreach (flash_pull() as $f): ?>
                <div class="toast pointer-events-auto" data-type="<?= e($f['type']) ?>">
                    <span class="toast-icon"><?= icon($f['type'] === 'success' ? 'check' : ($f['type'] === 'error' ? 'alert' : 'info')) ?></span>
                    <p><?= e($f['message']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
