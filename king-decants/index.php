<?php
/**
 * King Decants — index.php (Homepage)
 * ---------------------------------------------------------------------------
 * Estrutura (de cima para baixo):
 *   1. Cabeçalho (inc/header.php)
 *   2. Banner principal em duas áreas: título editorial + frasco com luz dourada
 *   3. Faixa de coleções (5 ícones em círculos finos dourados + "Ver todas")
 *   4. Vitrine "Mais vendidos" (grid responsivo com os produtos do banco)
 *   5. "Descobrir" (benefícios) e "A marca" (bloco editorial)
 *
 * Filtros via GET (opcionais): ?q=termo (busca) e ?colecao=floral
 * ---------------------------------------------------------------------------
 */
require __DIR__ . '/inc/bootstrap.php';

$q = clean_string($_GET['q'] ?? '', 80);
$collection = clean_string($_GET['colecao'] ?? '', 30);
$allCollections = collections();
if (!isset($allCollections[$collection])) {
    $collection = '';
}

// Monta a consulta com prepared statements (filtros são sempre parâmetros).
$where = ['is_active = 1'];
$params = [];
if ($q !== '') {
    $where[] = '(name LIKE ? OR sku LIKE ? OR short_description LIKE ? OR tagline LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($collection !== '') {
    $where[] = 'collection = ?';
    $params[] = $collection;
}
$stmt = db()->prepare('SELECT * FROM products WHERE ' . implode(' AND ', $where) . ' ORDER BY sort_order ASC, sold_count DESC LIMIT 24');
$stmt->execute($params);
$products = $stmt->fetchAll();

$isFiltered = $q !== '' || $collection !== '';
$pageTitle = 'King Decants — Luxo em cada gota';
$activeNav = 'inicio';
require __DIR__ . '/inc/header.php';
?>

<!-- ======================= BANNER PRINCIPAL ======================= -->
<section class="relative overflow-hidden">
    <!-- Brilho de fundo que integra a imagem ao preto da página -->
    <div class="pointer-events-none absolute -right-40 top-0 h-[720px] w-[720px] rounded-full bg-gold-400/10 blur-[140px]"></div>

    <div class="mx-auto grid max-w-7xl items-center gap-6 px-5 pb-10 pt-10 sm:pt-16 lg:grid-cols-2 lg:gap-12 lg:px-8 lg:pb-20 lg:pt-20">
        <!-- Área esquerda: texto -->
        <div class="relative z-10 order-2 text-center lg:order-1 lg:text-left">
            <p class="eyebrow animate-fade-up">Decants de alta perfumaria</p>
            <h1 class="mt-6 font-serif text-5xl font-normal leading-[1.02] text-white animate-fade-up sm:text-6xl xl:text-7xl" style="animation-delay:.1s">
                Criado para<br>
                <em class="font-light text-gold-gradient">marcar presença.</em>
            </h1>
            <div class="mx-auto mt-8 h-px w-16 bg-gold-400/60 lg:mx-0"></div>
            <p class="mt-8 text-lg font-light tracking-wide text-ink-300 animate-fade-up" style="animation-delay:.2s">Luxo em cada gota.</p>
            <div class="mt-10 flex flex-col items-center gap-4 animate-fade-up sm:flex-row sm:justify-center lg:justify-start" style="animation-delay:.3s">
                <a href="#mais-vendidos" class="btn-gold">Comprar agora <?= icon('arrow', 'h-4 w-4') ?></a>
                <a href="#colecoes" class="text-xs uppercase tracking-[0.22em] text-ink-400 transition-colors hover:text-gold-300">Explorar coleções</a>
            </div>
        </div>

        <!-- Área direita: frasco preto cercado por luz dourada (sem "bloco") -->
        <div class="relative order-1 mx-auto w-full max-w-md lg:order-2 lg:max-w-none">
            <div class="hero-glow animate-glow"></div>
            <img src="<?= e(asset('img/hero-bottle.svg')) ?>"
                 alt="Frasco de perfume preto com tampa dourada envolto em luz dourada"
                 class="blend-edges relative mx-auto w-[78%] animate-fade-up lg:w-[88%]" width="600" height="720">
        </div>
    </div>
</section>

<div class="gold-divider mx-auto max-w-7xl"></div>

<!-- ======================= COLEÇÕES ======================= -->
<section id="colecoes" class="mx-auto max-w-7xl scroll-mt-24 px-5 py-14 lg:px-8">
    <div class="mb-10 flex items-end justify-between gap-4">
        <h2 class="text-xs font-medium uppercase tracking-[0.28em] text-white">Coleções</h2>
        <a href="<?= e(url('index.php#mais-vendidos')) ?>" class="group inline-flex items-center gap-2 text-xs uppercase tracking-[0.2em] text-gold-400 hover:text-gold-300">
            Ver todas <?= icon('arrow', 'h-4 w-4 transition-transform group-hover:translate-x-1') ?>
        </a>
    </div>
    <div class="grid grid-cols-3 gap-y-10 sm:grid-cols-5">
        <?php foreach ($allCollections as $slug => $c): ?>
            <a href="<?= e(url('index.php?colecao=' . $slug . '#mais-vendidos')) ?>" class="group flex flex-col items-center gap-4 text-center reveal">
                <span class="collection-ring <?= $collection === $slug ? 'is-active' : '' ?>"><?= icon($c['icon'], 'h-8 w-8') ?></span>
                <span class="text-[11px] uppercase tracking-[0.2em] <?= $collection === $slug ? 'text-gold-300' : 'text-ink-300 group-hover:text-white' ?>"><?= e($c['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<div class="gold-divider mx-auto max-w-7xl"></div>

<!-- ======================= MAIS VENDIDOS ======================= -->
<section id="mais-vendidos" class="mx-auto max-w-7xl scroll-mt-24 px-5 py-16 lg:px-8">
    <div class="mb-10 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="text-xs font-medium uppercase tracking-[0.28em] text-white">
                <?php if ($q !== ''): ?>
                    Resultados para “<?= e($q) ?>”
                <?php elseif ($collection !== ''): ?>
                    Coleção <?= e($allCollections[$collection]['label']) ?>
                <?php else: ?>
                    Mais vendidos
                <?php endif; ?>
            </h2>
            <p class="mt-3 font-serif text-3xl italic text-ink-300 sm:text-4xl">Escolhas que <span class="text-gold-gradient">ficam na memória</span></p>
        </div>
        <?php if ($isFiltered): ?>
            <a href="<?= e(url('index.php#mais-vendidos')) ?>" class="group inline-flex items-center gap-2 text-xs uppercase tracking-[0.2em] text-gold-400 hover:text-gold-300">
                Limpar filtros <?= icon('close', 'h-4 w-4') ?>
            </a>
        <?php else: ?>
            <a href="#mais-vendidos" class="group inline-flex items-center gap-2 text-xs uppercase tracking-[0.2em] text-gold-400 hover:text-gold-300">
                Visualizar todos <?= icon('arrow', 'h-4 w-4 transition-transform group-hover:translate-x-1') ?>
            </a>
        <?php endif; ?>
    </div>

    <?php if (!$products): ?>
        <div class="panel px-6 py-16 text-center">
            <p class="font-serif text-2xl text-white">Nenhum decant encontrado.</p>
            <p class="mt-2 text-sm text-ink-400">Tente outro termo ou explore todas as coleções.</p>
            <a href="<?= e(url('index.php')) ?>" class="btn-outline mt-6">Ver todos</a>
        </div>
    <?php else: ?>
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <?php foreach ($products as $index => $product): ?>
                <?php include __DIR__ . '/inc/product_card.php'; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<div class="gold-divider mx-auto max-w-7xl"></div>

<!-- ======================= DESCOBRIR ======================= -->
<section id="descobrir" class="mx-auto max-w-7xl scroll-mt-24 px-5 py-20 lg:px-8">
    <div class="grid gap-12 lg:grid-cols-3">
        <div class="reveal">
            <p class="eyebrow">Descobrir</p>
            <h2 class="mt-4 font-serif text-4xl leading-tight text-white">Experimente antes do <em class="text-gold-gradient">frasco inteiro.</em></h2>
        </div>
        <div class="grid gap-10 sm:grid-cols-3 lg:col-span-2">
            <?php
            $benefits = [
                ['shield', 'Originais e rastreáveis', 'Decants envasados a partir de frascos originais, com lote registrado.'],
                ['truck', 'Envio para todo o Brasil', 'Embalagem protegida e frete grátis acima de ' . money(FREE_SHIPPING_FROM) . '.'],
                ['gift', 'Pronto para presentear', 'Estojos elegantes e cartão personalizado sob encomenda.'],
            ];
            foreach ($benefits as $i => [$ic, $title, $text]): ?>
                <div class="reveal" style="transition-delay: <?= $i * 120 ?>ms">
                    <span class="text-gold-400"><?= icon($ic, 'h-7 w-7') ?></span>
                    <h3 class="mt-5 text-sm font-medium uppercase tracking-[0.16em] text-white"><?= e($title) ?></h3>
                    <p class="mt-3 text-sm leading-relaxed text-ink-400"><?= e($text) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ======================= A MARCA ======================= -->
<section id="marca" class="relative scroll-mt-24 overflow-hidden border-y border-gold-400/15">
    <div class="pointer-events-none absolute left-1/2 top-1/2 h-[480px] w-[480px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-gold-400/10 blur-[120px]"></div>
    <div class="relative mx-auto max-w-3xl px-5 py-24 text-center reveal">
        <span class="mx-auto inline-flex text-gold-400"><?= icon('crown', 'h-9 w-9') ?></span>
        <p class="eyebrow mt-6">A marca</p>
        <h2 class="mt-5 font-serif text-4xl leading-tight text-white sm:text-5xl">A realeza está nos <em class="text-gold-gradient">detalhes.</em></h2>
        <p class="mx-auto mt-6 max-w-xl leading-relaxed text-ink-400">
            A King Decants nasceu para tornar a alta perfumaria acessível sem abrir mão do ritual. Cada decant é envasado à mão,
            identificado por lote e embalado como um presente — para que você descubra sua assinatura olfativa com confiança.
        </p>
    </div>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
