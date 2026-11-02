<?php
/**
 * King Decants — inc/header.php
 * ---------------------------------------------------------------------------
 * Layout superior da loja: <head>, Tailwind CDN (com tema dourado), fontes,
 * cabeçalho horizontal (logo | menu central | busca, sacola, conta) e
 * cards de notificação (flash messages).
 *
 * Variáveis opcionais definidas pela página ANTES do include:
 *   $pageTitle  (string) título da aba
 *   $activeNav  (string) 'inicio' | 'descobrir' | 'colecoes' | 'mais-vendidos' | 'marca'
 *   $metaDescription (string)
 * ---------------------------------------------------------------------------
 */
$pageTitle = $pageTitle ?? APP_NAME;
$activeNav = $activeNav ?? '';
$metaDescription = $metaDescription ?? 'King Decants — decants de perfumes de luxo. Luxo em cada gota.';
$user = current_user();
$cartCount = cart_count();

$navItems = [
    'inicio'        => ['Início',        url('index.php')],
    'descobrir'     => ['Descobrir',     url('index.php#descobrir')],
    'colecoes'      => ['Coleções',      url('index.php#colecoes')],
    'mais-vendidos' => ['Mais vendidos', url('index.php#mais-vendidos')],
    'marca'         => ['A marca',       url('index.php#marca')],
];
?>
<!DOCTYPE html>
<html lang="pt-BR" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($metaDescription) ?>">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="base-url" content="<?= e(base_path()) ?>">
    <link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">

    <!-- Fontes: serifa elegante (títulos) + sem serifa (interface) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,500&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">

    <!-- Tailwind via CDN (sem build). O objeto tailwind.config estende o tema com a paleta da marca. -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?= e(asset('js/tailwind.config.js')) ?>"></script>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="min-h-screen bg-ink-950 font-sans text-ink-200 antialiased selection:bg-gold-400/30 selection:text-white">

<!-- ======================= CABEÇALHO ======================= -->
<header id="site-header" class="sticky top-0 z-40 border-b border-gold-400/10 bg-ink-950/80 backdrop-blur-md transition-colors">
    <div class="mx-auto flex h-20 max-w-7xl items-center justify-between gap-4 px-5 lg:px-8">

        <!-- Logo dourada à esquerda -->
        <a href="<?= e(url('index.php')) ?>" class="group flex items-center gap-2.5" aria-label="King Decants — início">
            <span class="text-gold-400 transition-transform duration-500 group-hover:-translate-y-0.5"><?= icon('crown', 'h-6 w-6') ?></span>
            <span class="font-serif text-xl tracking-[0.28em] text-gold-gradient sm:text-2xl">KING<span class="ml-2 hidden font-light sm:inline">DECANTS</span></span>
        </a>

        <!-- Menu central (desktop) -->
        <nav class="hidden items-center gap-9 lg:flex" aria-label="Principal">
            <?php foreach ($navItems as $key => [$label, $href]): ?>
                <a href="<?= e($href) ?>"
                   class="nav-link relative py-2 text-[13px] uppercase tracking-[0.18em] transition-colors <?= $activeNav === $key ? 'is-active text-white' : 'text-ink-300 hover:text-white' ?>">
                    <?= e($label) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <!-- Ações à direita: busca, sacola, conta -->
        <div class="flex items-center gap-1 text-gold-400 sm:gap-2">
            <button type="button" data-toggle="search" class="icon-btn" aria-label="Buscar"><?= icon('search') ?></button>

            <a href="<?= e(url('cart.php')) ?>" class="icon-btn relative" aria-label="Sacola">
                <?= icon('bag') ?>
                <span id="cart-count"
                      class="absolute -right-0.5 -top-0.5 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-gold-400 px-1 text-[10px] font-semibold text-black transition-transform <?= $cartCount ? '' : 'scale-0' ?>">
                    <?= (int) $cartCount ?>
                </span>
            </a>

            <?php if ($user): ?>
                <a href="<?= e(url('account.php')) ?>" class="icon-btn" aria-label="Minha conta" title="<?= e($user['name']) ?>"><?= icon('user') ?></a>
            <?php else: ?>
                <button type="button" data-open-modal="login-modal" class="icon-btn" aria-label="Entrar"><?= icon('user') ?></button>
            <?php endif; ?>

            <button type="button" data-toggle="mobile-nav" class="icon-btn lg:hidden" aria-label="Abrir menu"><?= icon('menu') ?></button>
        </div>
    </div>

    <!-- Busca simples (expande abaixo do cabeçalho) -->
    <div id="search-panel" class="hidden border-t border-gold-400/10">
        <form action="<?= e(url('index.php')) ?>" method="get" class="mx-auto flex max-w-3xl items-center gap-3 px-5 py-4" role="search">
            <span class="text-gold-400"><?= icon('search') ?></span>
            <input type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Buscar decants, coleções, SKU…"
                   class="w-full border-0 bg-transparent text-white placeholder:text-ink-400 focus:ring-0 focus:outline-none" autocomplete="off">
            <button class="text-xs uppercase tracking-[0.2em] text-gold-400 hover:text-gold-300">Buscar</button>
        </form>
    </div>

    <!-- Menu mobile -->
    <nav id="mobile-nav" class="hidden border-t border-gold-400/10 lg:hidden" aria-label="Principal (mobile)">
        <div class="flex flex-col px-5 py-3">
            <?php foreach ($navItems as $key => [$label, $href]): ?>
                <a href="<?= e($href) ?>" class="border-b border-white/5 py-3 text-sm uppercase tracking-[0.18em] <?= $activeNav === $key ? 'text-gold-400' : 'text-ink-300' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
            <?php if ($user): ?>
                <a href="<?= e(url('auth/logout.php')) ?>" class="py-3 text-sm uppercase tracking-[0.18em] text-ink-400">Sair</a>
            <?php endif; ?>
        </div>
    </nav>
</header>

<!-- ======================= NOTIFICAÇÕES (cards) ======================= -->
<div id="toast-stack" class="pointer-events-none fixed right-4 top-24 z-50 flex w-[calc(100%-2rem)] max-w-sm flex-col gap-3" aria-live="polite">
    <?php foreach (flash_pull() as $f): ?>
        <div class="toast pointer-events-auto" data-type="<?= e($f['type']) ?>">
            <span class="toast-icon"><?= icon($f['type'] === 'success' ? 'check' : ($f['type'] === 'error' ? 'alert' : 'info')) ?></span>
            <p><?= e($f['message']) ?></p>
        </div>
    <?php endforeach; ?>
</div>

<main id="main">
