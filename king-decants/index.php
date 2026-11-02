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
$where = ['p.is_active = 1'];
$params = [];
if ($q !== '') {
    $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.short_description LIKE ? OR p.tagline LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($collection !== '') {
    $where[] = 'p.collection = ?';
    $params[] = $collection;
}
// Inclui média e total de avaliações reais (visíveis) para as estrelas dos cards.
$stmt = db()->prepare(
    'SELECT p.*, r.rating_avg, COALESCE(r.rating_count, 0) AS rating_count
       FROM products p
       LEFT JOIN (SELECT product_id, AVG(rating) AS rating_avg, COUNT(*) AS rating_count
                    FROM product_reviews WHERE is_visible = 1 GROUP BY product_id) r ON r.product_id = p.id
      WHERE ' . implode(' AND ', $where) . ' ORDER BY p.sort_order ASC, p.sold_count DESC LIMIT 24'
);
$stmt->execute($params);
$products = $stmt->fetchAll();

$isFiltered = $q !== '' || $collection !== '';
$pageTitle = 'King Decants — Luxo em cada gota';
$activeNav = 'inicio';
require __DIR__ . '/inc/header.php';
?>

<!-- ======================= BANNER PRINCIPAL ======================= -->
<!-- Banner de largura total: raios de luz dourada à direita com o frasco ao centro
     dessa luz; texto editorial à esquerda. Troque assets/img/hero-bottle.svg por
     uma fotografia (PNG/WebP com fundo preto) quando tiver. -->
<section class="hero relative overflow-hidden border-b border-white/10">
    <div class="hero-rays pointer-events-none absolute inset-y-0 right-0 w-full lg:w-[68%]" style="background-image:url('<?= e(asset('img/hero-rays.svg')) ?>')"></div>
    <div class="pointer-events-none absolute inset-0 bg-gradient-to-r from-ink-950 via-ink-950/70 to-transparent lg:via-ink-950/40"></div>

    <div class="relative mx-auto grid min-h-[520px] max-w-7xl items-center gap-4 px-5 py-12 lg:grid-cols-[1.1fr_1fr] lg:px-8 lg:py-16">
        <div class="relative z-10 order-2 text-center lg:order-1 lg:text-left">
            <h1 class="font-serif leading-[.95] text-white animate-fade-up">
                <span class="block text-5xl sm:text-6xl xl:text-7xl">Criado para</span>
                <em class="block text-6xl sm:text-7xl xl:text-[5.6rem]">marcar presença.</em>
            </h1>
            <p class="mt-6 text-xl font-light text-ink-100 animate-fade-up sm:text-2xl" style="animation-delay:.15s">Luxo em cada gota.</p>
            <div class="mt-9 animate-fade-up" style="animation-delay:.3s">
                <a href="#mais-vendidos" class="btn-gold btn-hero">Comprar agora</a>
            </div>
        </div>
        <div class="relative order-1 mx-auto w-full max-w-[320px] lg:order-2 lg:max-w-[400px]">
            <img src="<?= e(asset('img/hero-bottle.svg')) ?>" alt="Frasco de perfume preto com tampa dourada envolto em luz dourada"
                 class="blend-edges relative mx-auto w-full animate-fade-up" width="600" height="720">
        </div>
    </div>
</section>

<!-- ======================= EXPLORAR COLEÇÕES ======================= -->
<section id="colecoes" class="scroll-mt-24 border-b border-white/10">
    <div class="mx-auto max-w-7xl px-5 py-8 lg:px-8">
        <div class="mb-6 flex items-center justify-between gap-4">
            <h2 class="text-sm font-medium uppercase tracking-[0.22em] text-white">Explorar coleções</h2>
            <a href="<?= e(url('index.php#mais-vendidos')) ?>" class="group inline-flex items-center gap-1 text-sm text-ink-200 hover:text-gold-300">
                Ver todas <?= icon('chevron', 'h-4 w-4 transition-transform group-hover:translate-x-0.5') ?>
            </a>
        </div>
        <div class="grid grid-cols-3 gap-y-8 sm:grid-cols-5">
            <?php foreach ($allCollections as $slug => $c): ?>
                <a href="<?= e(url('index.php?colecao=' . $slug . '#mais-vendidos')) ?>" class="group flex flex-col items-center gap-3 text-center">
                    <span class="collection-ring <?= $collection === $slug ? 'is-active' : '' ?>"><?= icon($c['icon'], 'h-9 w-9') ?></span>
                    <span class="text-[15px] <?= $collection === $slug ? 'text-gold-300' : 'text-ink-100 group-hover:text-white' ?>"><?= e($c['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ======================= MAIS VENDIDOS ======================= -->
<section id="mais-vendidos" class="mx-auto max-w-7xl scroll-mt-24 px-5 py-10 lg:px-8">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="text-sm font-medium uppercase tracking-[0.22em] text-white">
                <?php if ($q !== ''): ?>
                    Resultados para “<?= e($q) ?>”
                <?php elseif ($collection !== ''): ?>
                    Coleção <?= e($allCollections[$collection]['label']) ?>
                <?php else: ?>
                    Mais vendidos
                <?php endif; ?>
            </h2>
        </div>
        <?php if ($isFiltered): ?>
            <a href="<?= e(url('index.php#mais-vendidos')) ?>" class="group inline-flex items-center gap-2 text-xs uppercase tracking-[0.2em] text-gold-400 hover:text-gold-300">
                Limpar filtros <?= icon('close', 'h-4 w-4') ?>
            </a>
        <?php else: ?>
            <a href="#mais-vendidos" class="group inline-flex items-center gap-1 text-sm text-ink-200 hover:text-gold-300">
                Ver todos <?= icon('chevron', 'h-4 w-4 transition-transform group-hover:translate-x-0.5') ?>
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
        <div class="grid gap-5 lg:grid-cols-2">
            <?php foreach ($products as $index => $product): ?>
                <?php include __DIR__ . '/inc/product_card_wide.php'; ?>
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
