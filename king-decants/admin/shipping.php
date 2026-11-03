<?php
/**
 * King Decants — admin/shipping.php (Frete grátis)
 * ---------------------------------------------------------------------------
 * Três modos (gravados em settings, lidos por inc/promotions.php):
 *   - Sem frete grátis (tabela de frete por CEP)
 *   - Frete grátis em TODOS os pedidos
 *   - Frete grátis nas compras ACIMA DE R$ X (valor dos produtos, já com o
 *     desconto do cupom, se houver)
 * A mudança vale na hora para sacola, checkout, páginas de produto e home.
 * ---------------------------------------------------------------------------
 */
define('KD_ADMIN_AREA', true);
require dirname(__DIR__) . '/inc/bootstrap.php';
$admin = require_admin();

$error = null;
$rule = free_shipping_rule();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $mode = in_array($_POST['mode'] ?? '', ['off', 'all', 'above'], true) ? $_POST['mode'] : 'off';
    $fromRaw = str_replace(['R$', ' '], '', (string) ($_POST['from'] ?? ''));
    if (str_contains($fromRaw, ',')) {
        $fromRaw = str_replace(',', '.', str_replace('.', '', $fromRaw));
    }
    if ($mode === 'above' && (!is_numeric($fromRaw) || (float) $fromRaw <= 0 || (float) $fromRaw > 99999)) {
        $error = 'Informe o valor mínimo para o frete grátis (ex.: 299,00).';
        $rule = ['mode' => $mode, 'from' => (float) $fromRaw];
    } else {
        setting_set('free_shipping_mode', $mode);
        if ($mode === 'above') {
            setting_set('free_shipping_from', (string) round((float) $fromRaw, 2));
        }
        $desc = ['off' => 'sem frete grátis', 'all' => 'frete grátis em todos os pedidos', 'above' => 'frete grátis acima de ' . money($fromRaw)][$mode];
        admin_log('shipping_settings', 'settings', null, $desc);
        flash('success', 'Frete atualizado: ' . $desc . '.');
        redirect(url('admin/shipping.php'));
    }
}

$pageTitle = 'Frete';
$adminNav = 'shipping';
require dirname(__DIR__) . '/inc/admin_header.php';
?>
<p class="eyebrow">Loja</p>
<h1 class="mt-2 font-serif text-4xl text-white">Frete grátis</h1>
<div class="gold-divider my-8"></div>

<form method="post" class="panel max-w-2xl space-y-4 p-6 sm:p-8" data-loading novalidate>
    <?= csrf_field() ?>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <label class="choice flex items-start gap-4">
        <input type="radio" name="mode" value="off" class="mt-1 accent-[#d4af37]" <?= $rule['mode'] === 'off' ? 'checked' : '' ?>>
        <span><span class="block text-white">Sem frete grátis</span>
            <span class="mt-1 block text-xs text-ink-400">O frete é calculado pela tabela por CEP em todos os pedidos.</span></span>
    </label>

    <label class="choice flex items-start gap-4">
        <input type="radio" name="mode" value="all" class="mt-1 accent-[#d4af37]" <?= $rule['mode'] === 'all' ? 'checked' : '' ?>>
        <span><span class="block text-white">Frete grátis em todos os pedidos</span>
            <span class="mt-1 block text-xs text-ink-400">Qualquer valor: nenhum pedido paga frete.</span></span>
    </label>

    <label class="choice flex items-start gap-4">
        <input type="radio" name="mode" value="above" class="mt-1 accent-[#d4af37]" <?= $rule['mode'] === 'above' ? 'checked' : '' ?>>
        <span class="flex-1"><span class="block text-white">Frete grátis nas compras acima de</span>
            <span class="mt-2 flex items-center gap-2">
                <span class="text-ink-300">R$</span>
                <input name="from" inputmode="decimal" class="field !w-40 !py-2" placeholder="299,00"
                       value="<?= $rule['from'] > 0 ? e(number_format($rule['from'], 2, ',', '')) : '' ?>"
                       onfocus="this.closest('label').querySelector('input[type=radio]').checked = true">
            </span>
            <span class="mt-2 block text-xs text-ink-400">Considera o valor dos produtos já com o desconto do cupom (se houver).</span></span>
    </label>

    <p class="text-sm text-ink-300">Situação atual na loja: <strong class="text-gold-300"><?= e(free_shipping_text() ?? 'Sem frete grátis') ?></strong></p>
    <button class="btn-gold" data-loading-text="Salvando…">Salvar</button>
</form>

<?php require dirname(__DIR__) . '/inc/admin_footer.php'; ?>
