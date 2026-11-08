<?php
/**
 * King Decants — admin/appearance.php (Aparência da loja)
 * ---------------------------------------------------------------------------
 * Troca a imagem do frasco no banner da página inicial, sem mexer em código.
 *   - Upload JPG/PNG/WebP (PNG/WebP com fundo transparente fica melhor sobre as
 *     luzes animadas). A imagem passa pelo mesmo tratamento seguro dos produtos
 *     (inc/uploads.php): validação pelo conteúdo, recodificação e nome aleatório.
 *   - Texto alternativo (acessibilidade/SEO).
 *   - Link opcional: ao clicar no frasco, abre a página de um produto.
 *   - "Voltar ao padrão" restaura o frasco ilustrado.
 *
 * IMPORTANTE: use fotos próprias ou com autorização. Fotos oficiais de marcas
 * (campanhas, sites de fabricantes) têm direitos autorais.
 * ---------------------------------------------------------------------------
 */
define('KD_ADMIN_AREA', true);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/uploads.php';
$admin = require_admin();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    flash('error', 'O envio excedeu o limite do servidor (post_max_size). Use uma imagem menor.');
    redirect(url('admin/appearance.php'));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $current = setting('hero_image');

    // ---------------- Botão flutuante do WhatsApp
    if (($_POST['action'] ?? '') === 'whatsapp') {
        $waNumber = whatsapp_normalize((string) ($_POST['whatsapp_number'] ?? ''));
        $waOn = isset($_POST['whatsapp_enabled']);
        $waMsg = clean_string($_POST['whatsapp_message'] ?? '', 300);
        if ($waOn && (strlen($waNumber) < 12 || strlen($waNumber) > 15)) {
            $errors['whatsapp_number'] = 'Número inválido. Use DDD + número, ex.: (11) 91234-5678.';
        } else {
            setting_set('whatsapp_enabled', $waOn ? '1' : '0');
            setting_set('whatsapp_number', $waNumber !== '' ? $waNumber : null);
            setting_set('whatsapp_message', $waMsg !== '' ? $waMsg : null);
            admin_log('appearance_whatsapp', 'settings', null, ($waOn ? 'ativado ' : 'desativado ') . $waNumber);
            flash('success', $waOn ? 'Botão do WhatsApp ativado.' : 'Botão do WhatsApp desativado.');
            redirect(url('admin/appearance.php#whatsapp'));
        }
    }

    if (($_POST['action'] ?? '') === 'reset') {
        setting_set('hero_image', null);
        product_image_delete($current);
        admin_log('appearance_hero_reset', 'settings', null, 'Banner voltou ao padrão');
        flash('success', 'Banner voltou ao frasco ilustrado padrão.');
        redirect(url('admin/appearance.php'));
    }

    if (($_POST['action'] ?? '') !== 'whatsapp') {
    $alt = clean_string($_POST['hero_image_alt'] ?? '', 150);
    $productId = int_param($_POST, 'hero_product');
    $link = null;
    if ($productId && ($p = cart_find_product($productId))) {
        $link = 'product.php?slug=' . rawurlencode($p['slug']);
    }
    try {
        $new = product_image_upload($_FILES['hero_image'] ?? null);
        if ($new) {
            setting_set('hero_image', $new);
            product_image_delete($current);
        }
        setting_set('hero_image_alt', $alt ?: null);
        setting_set('hero_link', $link);
        setting_set('hero_product_id', $productId ? (string) $productId : null);
        admin_log('appearance_hero_update', 'settings', null, ($new ? 'nova imagem; ' : '') . 'alt/link atualizados');
        flash('success', $new ? 'Imagem do banner atualizada.' : 'Configurações do banner salvas.');
        redirect(url('admin/appearance.php'));
    } catch (RuntimeException $e) {
        $errors['hero_image'] = $e->getMessage();
    }
    }
}
$waNumberShow = (string) ($_POST['whatsapp_number'] ?? setting('whatsapp_number', ''));
if (!isset($_POST['whatsapp_number']) && preg_match('/^55(\d{2})(\d{4,5})(\d{4})$/', $waNumberShow, $wm)) {
    $waNumberShow = "({$wm[1]}) {$wm[2]}-{$wm[3]}";
}
$waOnShow = isset($_POST['action']) && $_POST['action'] === 'whatsapp' ? isset($_POST['whatsapp_enabled']) : setting('whatsapp_enabled', '0') === '1';

$heroImage = setting('hero_image');
$products = db()->query('SELECT id, name FROM products WHERE is_active = 1 ORDER BY sort_order, name')->fetchAll();
$heroProduct = (int) setting('hero_product_id', '0');

$pageTitle = 'Aparência';
$adminNav = 'appearance';
require dirname(__DIR__) . '/inc/admin_header.php';
?>
<p class="eyebrow">Loja</p>
<h1 class="mt-2 font-serif text-4xl text-white">Aparência</h1>
<div class="gold-divider my-8"></div>

<div class="grid gap-6 xl:grid-cols-[1fr_380px]">
    <form method="post" enctype="multipart/form-data" class="panel space-y-5 p-6" data-loading novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="MAX_FILE_SIZE" value="<?= PRODUCT_IMAGE_MAX_BYTES ?>">
        <h2 class="text-xs font-medium uppercase tracking-[0.22em] text-white">Imagem do banner da página inicial</h2>
        <p class="text-sm text-ink-400">Aparece no lugar do frasco ilustrado, com as luzes douradas animadas atrás.
            Recomendado: <strong class="text-ink-200">PNG ou WebP com fundo transparente</strong>, frasco centralizado, vertical (ex.: 1000×1200), até 5 MB.</p>

        <div>
            <label class="field-label" for="hero_image">Enviar imagem</label>
            <input id="hero_image" name="hero_image" type="file" accept="image/jpeg,image/png,image/webp"
                   class="block w-full text-sm text-ink-300 file:mr-3 file:rounded-md file:border-0 file:bg-gold-400 file:px-3 file:py-2 file:text-xs file:font-semibold file:uppercase file:tracking-wider file:text-black<?= isset($errors['hero_image']) ? ' is-invalid' : '' ?>">
            <?php if (isset($errors['hero_image'])): ?><p class="field-error"><?= e($errors['hero_image']) ?></p><?php endif; ?>
        </div>
        <div>
            <label class="field-label" for="hero_image_alt">Descrição da imagem (acessibilidade)</label>
            <input id="hero_image_alt" name="hero_image_alt" class="field" maxlength="150" placeholder="Ex.: Frasco de perfume floral com luz dourada" value="<?= e(setting('hero_image_alt', '')) ?>">
        </div>
        <div>
            <label class="field-label" for="hero_product">Ao clicar no frasco, abrir o produto (opcional)</label>
            <select id="hero_product" name="hero_product" class="field">
                <option value="0">— Nenhum —</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= (int) $p['id'] ?>" <?= $heroProduct === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="alert text-xs text-ink-300">Use fotos <strong>próprias</strong> (ex.: você fotografando o frasco que decanta) ou com autorização. Imagens de campanhas e sites oficiais de marcas têm direitos autorais.</div>
        <button class="btn-gold" data-loading-text="Salvando…">Salvar</button>
    </form>

    <div class="panel p-6">
        <h2 class="text-xs font-medium uppercase tracking-[0.22em] text-white">Prévia atual</h2>
        <div class="relative mt-4 overflow-hidden rounded-lg border border-white/10 bg-black">
            <img src="<?= e(url('assets/img/hero-rays.svg')) ?>" alt="" class="absolute inset-0 h-full w-full object-cover opacity-80">
            <img src="<?= e($heroImage ? product_image_src($heroImage) : url('assets/img/hero-bottle.svg')) ?>" alt="" class="relative mx-auto h-80 object-contain p-4">
        </div>
        <p class="mt-3 text-xs text-ink-500"><?= $heroImage ? 'Imagem personalizada.' : 'Frasco ilustrado padrão.' ?></p>
        <?php if ($heroImage): ?>
            <form method="post" class="mt-4" onsubmit="return confirm('Voltar ao frasco ilustrado padrão?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reset">
                <button class="btn-outline btn-sm">Voltar ao padrão</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Botão do WhatsApp -->
<form method="post" action="<?= e(url('admin/appearance.php#whatsapp')) ?>" id="whatsapp" class="panel mt-6 max-w-3xl space-y-5 p-6" data-loading novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="whatsapp">
    <div class="flex items-center gap-3">
        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-[#25d366] text-white"><?= icon('whatsapp', 'h-5 w-5') ?></span>
        <h2 class="text-xs font-medium uppercase tracking-[0.22em] text-white">Botão do WhatsApp</h2>
    </div>
    <p class="text-sm text-ink-400">Ícone flutuante no canto inferior direito de todas as páginas da loja. Ao tocar, abre uma conversa com o seu WhatsApp (no celular abre o app; no computador, o WhatsApp Web).</p>
    <label class="flex items-center gap-3 text-sm text-ink-200">
        <input type="checkbox" name="whatsapp_enabled" value="1" class="accent-[#d4af37]" <?= $waOnShow ? 'checked' : '' ?>> Mostrar o botão na loja
    </label>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="field-label" for="whatsapp_number">Número do WhatsApp (com DDD)</label>
            <input id="whatsapp_number" name="whatsapp_number" inputmode="tel" class="field<?= isset($errors['whatsapp_number']) ? ' is-invalid' : '' ?>" placeholder="(11) 91234-5678" value="<?= e($waNumberShow) ?>">
            <?php if (isset($errors['whatsapp_number'])): ?><p class="field-error"><?= e($errors['whatsapp_number']) ?></p><?php endif; ?>
            <p class="mt-1 text-xs text-ink-500">Número do Brasil: só DDD + número. De outro país: comece pelo código do país.</p>
        </div>
        <div>
            <label class="field-label" for="whatsapp_message">Mensagem inicial (opcional)</label>
            <input id="whatsapp_message" name="whatsapp_message" maxlength="300" class="field" placeholder="Olá! Vim pelo site da King Decants." value="<?= e((string) ($_POST['whatsapp_message'] ?? setting('whatsapp_message', 'Olá! Vim pelo site da King Decants.'))) ?>">
            <p class="mt-1 text-xs text-ink-500">Na página de um perfume, a mensagem já cita o produto.</p>
        </div>
    </div>
    <div class="flex flex-wrap items-center gap-3">
        <button class="btn-gold btn-sm" data-loading-text="Salvando…">Salvar</button>
        <?php if ($waTest = whatsapp_link()): ?>
            <a href="<?= e($waTest) ?>" target="_blank" rel="noopener" class="btn-outline btn-sm">Testar o link</a>
        <?php endif; ?>
    </div>
</form>

<?php require dirname(__DIR__) . '/inc/admin_footer.php'; ?>
