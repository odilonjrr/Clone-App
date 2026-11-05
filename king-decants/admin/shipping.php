<?php
/**
 * King Decants — admin/shipping.php (Frete)
 * ---------------------------------------------------------------------------
 * 1) Frete grátis: sem / todos os pedidos / acima de R$ X (valor dos produtos
 *    já com desconto do cupom). Com SuperFrete, a opção mais barata fica grátis
 *    e as demais cobram só a diferença.
 * 2) Cálculo do frete: tabela por CEP (padrão) ou SuperFrete (cotação real),
 *    com CEP/endereço de postagem, medidas por decant, transportadoras,
 *    margem e dias de preparo. Inclui teste de cotação, saldo e webhook.
 * O token fica no kingdecants-config.php (SUPERFRETE_TOKEN), nunca no banco.
 * ---------------------------------------------------------------------------
 */
define('KD_ADMIN_AREA', true);
require dirname(__DIR__) . '/inc/bootstrap.php';
$admin = require_admin();

$error = null;
$calcErrors = [];
$testResult = null;
$rule = free_shipping_rule();

/** "1.234,56" / "10,5" / "10.5" -> "1234.56" */
function ship_num($v): string
{
    $v = str_replace(['R$', ' ', 'cm', 'g'], '', (string) $v);
    if (str_contains($v, ',')) {
        $v = str_replace(',', '.', str_replace('.', '', $v));
    }
    return $v;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $section = $_POST['section'] ?? 'free';

    if ($section === 'free') {
        $mode = in_array($_POST['mode'] ?? '', ['off', 'all', 'above'], true) ? $_POST['mode'] : 'off';
        $fromRaw = ship_num($_POST['from'] ?? '');
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

    if ($section === 'calc') {
        $in = [
            'shipping_calc'      => ($_POST['shipping_calc'] ?? '') === 'superfrete' ? 'superfrete' : 'table',
            'sf_origin_cep'      => only_digits($_POST['sf_origin_cep'] ?? ''),
            'sf_services'        => implode(',', array_values(array_intersect(array_map('intval', (array) ($_POST['sf_services'] ?? [])), array_keys(SF_SERVICES)))),
            'sf_item_height'     => ship_num($_POST['sf_item_height'] ?? ''),
            'sf_item_width'      => ship_num($_POST['sf_item_width'] ?? ''),
            'sf_item_length'     => ship_num($_POST['sf_item_length'] ?? ''),
            'sf_item_weight_g'   => ship_num($_POST['sf_item_weight_g'] ?? ''),
            'sf_extra_fee'       => ship_num($_POST['sf_extra_fee'] ?? '') ?: '0',
            'sf_extra_days'      => (string) max(0, min(30, (int) ($_POST['sf_extra_days'] ?? 0))),
            'sf_insurance'       => isset($_POST['sf_insurance']) ? '1' : '0',
            'sf_from_name'       => clean_string($_POST['sf_from_name'] ?? '', 50),
            'sf_from_address'    => clean_string($_POST['sf_from_address'] ?? '', 50),
            'sf_from_number'     => clean_string($_POST['sf_from_number'] ?? '', 10),
            'sf_from_complement' => clean_string($_POST['sf_from_complement'] ?? '', 20),
            'sf_from_district'   => clean_string($_POST['sf_from_district'] ?? '', 60),
            'sf_from_city'       => clean_string($_POST['sf_from_city'] ?? '', 50),
            'sf_from_state'      => strtoupper(clean_string($_POST['sf_from_state'] ?? '', 2)),
            'sf_from_document'   => only_digits($_POST['sf_from_document'] ?? ''),
        ];
        if ($in['sf_origin_cep'] !== '' && !validate_cep($in['sf_origin_cep'])) {
            $calcErrors['sf_origin_cep'] = 'CEP inválido.';
        }
        foreach (['sf_item_height' => 'Altura', 'sf_item_width' => 'Largura', 'sf_item_length' => 'Comprimento', 'sf_item_weight_g' => 'Peso'] as $k => $label) {
            if ($in[$k] !== '' && (!is_numeric($in[$k]) || (float) $in[$k] <= 0 || (float) $in[$k] > 30000)) {
                $calcErrors[$k] = "{$label} inválido.";
            }
        }
        if (!is_numeric($in['sf_extra_fee']) || (float) $in['sf_extra_fee'] < 0 || (float) $in['sf_extra_fee'] > 500) {
            $calcErrors['sf_extra_fee'] = 'Margem inválida.';
        }
        if ($in['sf_from_state'] !== '' && !preg_match('/^[A-Z]{2}$/', $in['sf_from_state'])) {
            $calcErrors['sf_from_state'] = 'UF com 2 letras.';
        }
        if ($in['sf_from_document'] !== '' && !in_array(strlen($in['sf_from_document']), [11, 14], true)) {
            $calcErrors['sf_from_document'] = 'CPF (11) ou CNPJ (14 dígitos).';
        }
        if ($in['sf_from_name'] !== '' && !str_contains(trim($in['sf_from_name']), ' ')) {
            $calcErrors['sf_from_name'] = 'Use nome e sobrenome (ex.: "Loja King Decants").';
        }
        if (!$calcErrors && $in['shipping_calc'] === 'superfrete') {
            $tmp = sf_missing([
                'origin_cep' => $in['sf_origin_cep'], 'height' => (float) $in['sf_item_height'], 'width' => (float) $in['sf_item_width'],
                'length' => (float) $in['sf_item_length'], 'weight_g' => (float) $in['sf_item_weight_g'], 'services' => array_filter(explode(',', $in['sf_services'])),
            ]);
            if ($tmp) {
                $calcErrors['_global'] = 'Para ativar a SuperFrete, falta: ' . implode(', ', $tmp) . '.';
            }
        }
        if (!$calcErrors) {
            foreach ($in as $k => $v) {
                setting_set($k, (string) $v);
            }
            unset($_SESSION['sf_quotes']);
            admin_log('shipping_calc', 'settings', null, $in['shipping_calc']);
            flash('success', $in['shipping_calc'] === 'superfrete' ? 'Frete pela SuperFrete ativado.' : 'Configurações salvas (frete pela tabela por CEP).');
            redirect(url('admin/shipping.php#calculo'));
        }
    }

    if ($section === 'test') {
        $cep = only_digits($_POST['test_cep'] ?? '');
        $qty = max(1, min(20, (int) ($_POST['test_qty'] ?? 1)));
        if (!validate_cep($cep)) {
            $testResult = ['error' => 'Informe um CEP de destino válido.'];
        } elseif ($miss = sf_missing()) {
            $testResult = ['error' => 'Falta configurar: ' . implode(', ', $miss) . '.'];
        } else {
            unset($_SESSION['sf_quotes']);
            try {
                $s = sf_settings();
                $raw = sf_request('POST', 'api/v0/calculator', [
                    'from' => ['postal_code' => $s['origin_cep']], 'to' => ['postal_code' => $cep],
                    'services' => implode(',', $s['services']),
                    'products' => [['quantity' => $qty, 'height' => $s['height'], 'width' => $s['width'], 'length' => $s['length'], 'weight' => round($s['weight_g'] / 1000, 4)]],
                ], 12);
                $testResult = ['cep' => $cep, 'qty' => $qty, 'rows' => is_array($raw) ? $raw : []];
            } catch (Throwable $e) {
                $testResult = ['error' => $e->getMessage()];
            }
        }
    }

    if ($section === 'webhook') {
        try {
            $wh = sf_register_webhook();
            admin_log('sf_webhook', 'settings', null, (string) $wh['id']);
            flash('success', 'Avisos automáticos ativados: postado e entregue atualizam o pedido sozinhos.');
        } catch (Throwable $e) {
            flash('error', 'Webhook: ' . $e->getMessage());
        }
        redirect(url('admin/shipping.php#calculo'));
    }
}

$sf = sf_settings();
$v = fn(string $k, string $d = '') => e((string) ($_POST[$k] ?? setting($k, $d)));
$numv = function (string $k) {
    $raw = $_POST[$k] ?? setting($k, '');
    return is_numeric($raw) && (float) $raw > 0 ? e(rtrim(rtrim(number_format((float) $raw, 2, ',', ''), '0'), ',')) : e((string) $raw);
};
$cerr = fn(string $k) => isset($calcErrors[$k]) ? '<p class="field-error">' . e($calcErrors[$k]) . '</p>' : '';
$selServices = isset($_POST['sf_services']) ? array_map('intval', (array) $_POST['sf_services']) : $sf['services'];
$balance = sf_token_ok() ? sf_balance() : null;

$pageTitle = 'Frete';
$adminNav = 'shipping';
require dirname(__DIR__) . '/inc/admin_header.php';
?>
<p class="eyebrow">Loja</p>
<h1 class="mt-2 font-serif text-4xl text-white">Frete</h1>
<div class="gold-divider my-8"></div>

<div class="grid gap-6 2xl:grid-cols-2">
<!-- ============ FRETE GRÁTIS ============ -->
<form method="post" class="panel h-fit space-y-4 p-6 sm:p-8" data-loading novalidate>
    <?= csrf_field() ?><input type="hidden" name="section" value="free">
    <h2 class="text-xs font-medium uppercase tracking-[0.22em] text-white">Frete grátis</h2>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <label class="choice flex items-start gap-4">
        <input type="radio" name="mode" value="off" class="mt-1 accent-[#d4af37]" <?= $rule['mode'] === 'off' ? 'checked' : '' ?>>
        <span><span class="block text-white">Sem frete grátis</span>
            <span class="mt-1 block text-xs text-ink-400">O cliente paga o frete calculado.</span></span>
    </label>
    <label class="choice flex items-start gap-4">
        <input type="radio" name="mode" value="all" class="mt-1 accent-[#d4af37]" <?= $rule['mode'] === 'all' ? 'checked' : '' ?>>
        <span><span class="block text-white">Frete grátis em todos os pedidos</span>
            <span class="mt-1 block text-xs text-ink-400">Qualquer valor.</span></span>
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
    <p class="text-xs text-ink-500">Com a SuperFrete, o frete grátis vale para a opção de entrega mais barata; as outras cobram só a diferença.</p>
    <p class="text-sm text-ink-300">Situação atual: <strong class="text-gold-300"><?= e(free_shipping_text() ?? 'Sem frete grátis') ?></strong></p>
    <button class="btn-gold btn-sm" data-loading-text="Salvando…">Salvar frete grátis</button>
</form>

<!-- ============ TESTE / STATUS ============ -->
<div class="space-y-6">
    <section class="panel p-6 sm:p-8">
        <h2 class="text-xs font-medium uppercase tracking-[0.22em] text-white">SuperFrete · situação</h2>
        <dl class="mt-4 grid grid-cols-[150px_1fr] gap-y-2 text-sm">
            <dt class="text-ink-400">Cálculo no checkout</dt>
            <dd class="<?= sf_enabled() ? 'text-emerald-300' : 'text-ink-200' ?>"><?= sf_enabled() ? 'SuperFrete (cotação real)' : 'Tabela por CEP' ?></dd>
            <dt class="text-ink-400">Ambiente</dt><dd class="text-white"><?= SUPERFRETE_SANDBOX ? 'Sandbox (testes)' : 'Produção' ?></dd>
            <dt class="text-ink-400">Token</dt><dd class="<?= sf_token_ok() ? 'text-emerald-300' : 'text-red-300' ?>"><?= sf_token_ok() ? 'configurado' : 'não configurado (SUPERFRETE_TOKEN)' ?></dd>
            <dt class="text-ink-400">Saldo</dt><dd class="text-white"><?= $balance !== null ? money($balance) : '—' ?></dd>
            <dt class="text-ink-400">Avisos automáticos</dt><dd class="<?= $sf['webhook_id'] ? 'text-emerald-300' : 'text-ink-200' ?>"><?= $sf['webhook_id'] ? 'ativos' : 'desativados' ?></dd>
        </dl>
        <?php if ($m = sf_missing()): ?><p class="mt-4 text-xs text-amber-300">Para usar a SuperFrete, falta: <?= e(implode(', ', $m)) ?>.</p><?php endif; ?>
        <div class="mt-5 flex flex-wrap gap-2">
            <a class="btn-outline btn-sm" href="<?= e(sf_panel_url()) ?>account/credits" target="_blank" rel="noopener">Adicionar saldo</a>
            <?php if (sf_token_ok()): ?>
                <form method="post" onsubmit="return confirm('Cadastrar os avisos automáticos (webhook) na SuperFrete?')">
                    <?= csrf_field() ?><input type="hidden" name="section" value="webhook">
                    <button class="btn-outline btn-sm"><?= $sf['webhook_id'] ? 'Recadastrar avisos automáticos' : 'Ativar avisos automáticos' ?></button>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <form method="post" action="#teste" id="teste" class="panel space-y-4 p-6 sm:p-8" data-loading>
        <?= csrf_field() ?><input type="hidden" name="section" value="test">
        <h2 class="text-xs font-medium uppercase tracking-[0.22em] text-white">Testar cotação</h2>
        <div class="flex flex-wrap items-end gap-3">
            <div><label class="field-label" for="test_cep">CEP de destino</label>
                <input id="test_cep" name="test_cep" class="field !w-40 !py-2" data-mask="cep" placeholder="00000-000" value="<?= e($_POST['test_cep'] ?? '') ?>"></div>
            <div><label class="field-label" for="test_qty">Decants</label>
                <input id="test_qty" name="test_qty" type="number" min="1" max="20" class="field !w-24 !py-2" value="<?= (int) ($_POST['test_qty'] ?? 1) ?>"></div>
            <button class="btn-gold btn-sm" data-loading-text="Cotando…">Cotar</button>
        </div>
        <?php if ($testResult): ?>
            <?php if (isset($testResult['error'])): ?>
                <div class="alert alert-error"><?= e($testResult['error']) ?></div>
            <?php else: ?>
                <table class="table-lux text-sm">
                    <thead><tr><th>Serviço</th><th class="text-right">Preço SuperFrete</th><th class="text-right">Cliente paga*</th><th>Prazo</th><th>Caixa</th></tr></thead>
                    <tbody>
                    <?php foreach ($testResult['rows'] as $r): $p = $r['packages'][0] ?? []; ?>
                        <tr class="<?= !empty($r['has_error']) ? 'opacity-50' : '' ?>">
                            <td class="text-white"><?= e((string) ($r['name'] ?? '?')) ?></td>
                            <?php if (!empty($r['has_error'])): ?>
                                <td colspan="4" class="text-xs text-ink-500">Indisponível para este CEP/pacote<?= !empty($r['error']) ? ': ' . e(is_string($r['error']) ? $r['error'] : json_encode($r['error'])) : '' ?></td>
                            <?php else: ?>
                                <td class="text-right"><?= money((float) $r['price']) ?></td>
                                <td class="text-right text-gold-300"><?= money((float) $r['price'] + $sf['extra_fee']) ?></td>
                                <td class="whitespace-nowrap"><?= e(sf_days_label((int) ($r['delivery_range']['min'] ?? $r['delivery_time'] ?? 0) + $sf['extra_days'], (int) ($r['delivery_range']['max'] ?? $r['delivery_time'] ?? 0) + $sf['extra_days'])) ?></td>
                                <td class="whitespace-nowrap text-xs text-ink-400"><?= e(($p['dimensions']['height'] ?? '?') . '×' . ($p['dimensions']['width'] ?? '?') . '×' . ($p['dimensions']['length'] ?? '?') . ' cm · ' . ($p['weight'] ?? '?') . ' kg') ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$testResult['rows']): ?><tr><td colspan="5" class="text-ink-500">Nenhuma opção retornada.</td></tr><?php endif; ?>
                    </tbody>
                </table>
                <p class="text-xs text-ink-500">* com a margem configurada, antes de frete grátis. Prazo inclui os dias de preparo.</p>
            <?php endif; ?>
        <?php endif; ?>
    </form>
</div>
</div>

<!-- ============ CÁLCULO (SUPERFRETE) ============ -->
<form method="post" action="#calculo" id="calculo" class="panel mt-6 space-y-6 p-6 sm:p-8" data-loading novalidate>
    <?= csrf_field() ?><input type="hidden" name="section" value="calc">
    <h2 class="text-xs font-medium uppercase tracking-[0.22em] text-white">Cálculo do frete</h2>
    <?php if (isset($calcErrors['_global'])): ?><div class="alert alert-error"><?= e($calcErrors['_global']) ?></div><?php endif; ?>

    <div class="grid gap-3 md:grid-cols-2">
        <label class="choice flex items-start gap-4">
            <input type="radio" name="shipping_calc" value="table" class="mt-1 accent-[#d4af37]" <?= $sf['mode'] !== 'superfrete' ? 'checked' : '' ?>>
            <span><span class="block text-white">Tabela por CEP</span><span class="mt-1 block text-xs text-ink-400">Valor fixo por região (sem integração).</span></span>
        </label>
        <label class="choice flex items-start gap-4">
            <input type="radio" name="shipping_calc" value="superfrete" class="mt-1 accent-[#d4af37]" <?= $sf['mode'] === 'superfrete' ? 'checked' : '' ?>>
            <span><span class="block text-white">SuperFrete</span><span class="mt-1 block text-xs text-ink-400">Cotação real; o cliente escolhe PAC, SEDEX, Mini Envios…</span></span>
        </label>
    </div>

    <div>
        <h3 class="field-label">1 · Medidas de 1 decant embalado para envio</h3>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div><label class="field-label" for="h">Altura (cm)</label><input id="h" name="sf_item_height" inputmode="decimal" class="field" placeholder="4" value="<?= $numv('sf_item_height') ?>"><?= $cerr('sf_item_height') ?></div>
            <div><label class="field-label" for="w">Largura (cm)</label><input id="w" name="sf_item_width" inputmode="decimal" class="field" placeholder="11" value="<?= $numv('sf_item_width') ?>"><?= $cerr('sf_item_width') ?></div>
            <div><label class="field-label" for="l">Comprimento (cm)</label><input id="l" name="sf_item_length" inputmode="decimal" class="field" placeholder="16" value="<?= $numv('sf_item_length') ?>"><?= $cerr('sf_item_length') ?></div>
            <div><label class="field-label" for="g">Peso (gramas)</label><input id="g" name="sf_item_weight_g" inputmode="decimal" class="field" placeholder="80" value="<?= $numv('sf_item_weight_g') ?>"><?= $cerr('sf_item_weight_g') ?></div>
        </div>
        <p class="mt-2 text-xs text-ink-500">Com vários decants, a SuperFrete calcula a caixa ideal sozinha. Mini Envios aceita até 300 g, 4 cm de altura, 16 cm de largura e 24 cm de comprimento.</p>
    </div>

    <div>
        <h3 class="field-label">2 · Transportadoras oferecidas ao cliente</h3>
        <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-ink-200">
            <?php foreach (SF_SERVICES as $id => $name): ?>
                <label class="flex items-center gap-2"><input type="checkbox" name="sf_services[]" value="<?= $id ?>" class="accent-[#d4af37]" <?= in_array($id, $selServices, true) ? 'checked' : '' ?>> <?= e($name) ?></label>
            <?php endforeach; ?>
        </div>
        <p class="mt-2 text-xs text-ink-500">Jadlog, J&amp;T e Loggi só aparecem se houver ponto de postagem perto do seu CEP. A Loggi também pode ser ligada/desligada nas configurações do token, no painel da SuperFrete.</p>
    </div>

    <div>
        <h3 class="field-label">3 · Valor e prazo</h3>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div><label class="field-label" for="fee">Margem (R$ por pedido)</label><input id="fee" name="sf_extra_fee" inputmode="decimal" class="field" placeholder="0,00" value="<?= $numv('sf_extra_fee') ?>"><?= $cerr('sf_extra_fee') ?></div>
            <div><label class="field-label" for="days">Dias de preparo</label><input id="days" name="sf_extra_days" type="number" min="0" max="30" class="field" value="<?= $v('sf_extra_days', '0') ?>"></div>
        </div>
        <label class="mt-3 flex items-center gap-3 text-sm text-ink-200"><input type="checkbox" name="sf_insurance" value="1" class="accent-[#d4af37]" <?= ($_POST ? isset($_POST['sf_insurance']) : $sf['insurance']) ? 'checked' : '' ?>> Declarar o valor dos produtos (seguro). Deixa o frete mais caro; Mini Envios aceita até R$ 100.</label>
    </div>

    <div>
        <h3 class="field-label">4 · Endereço de postagem (remetente da etiqueta)</h3>
        <div class="grid gap-3 sm:grid-cols-6">
            <div class="sm:col-span-2"><label class="field-label" for="ocep">CEP de origem *</label><input id="ocep" name="sf_origin_cep" class="field" data-mask="cep" placeholder="00000-000" value="<?= $v('sf_origin_cep') ?>"><?= $cerr('sf_origin_cep') ?></div>
            <div class="sm:col-span-4"><label class="field-label" for="fname">Nome do remetente</label><input id="fname" name="sf_from_name" maxlength="50" class="field" value="<?= $v('sf_from_name', 'Loja King Decants') ?>"><?= $cerr('sf_from_name') ?></div>
            <div class="sm:col-span-3"><label class="field-label" for="fstreet">Rua</label><input id="fstreet" name="sf_from_address" maxlength="50" class="field" value="<?= $v('sf_from_address') ?>"></div>
            <div><label class="field-label" for="fnum">Número</label><input id="fnum" name="sf_from_number" maxlength="10" class="field" value="<?= $v('sf_from_number') ?>"></div>
            <div class="sm:col-span-2"><label class="field-label" for="fcomp">Complemento</label><input id="fcomp" name="sf_from_complement" maxlength="20" class="field" value="<?= $v('sf_from_complement') ?>"></div>
            <div class="sm:col-span-2"><label class="field-label" for="fdist">Bairro</label><input id="fdist" name="sf_from_district" maxlength="60" class="field" value="<?= $v('sf_from_district') ?>"></div>
            <div class="sm:col-span-2"><label class="field-label" for="fcity">Cidade</label><input id="fcity" name="sf_from_city" maxlength="50" class="field" value="<?= $v('sf_from_city') ?>"></div>
            <div><label class="field-label" for="fuf">UF</label><input id="fuf" name="sf_from_state" maxlength="2" class="field uppercase" value="<?= $v('sf_from_state') ?>"><?= $cerr('sf_from_state') ?></div>
            <div><label class="field-label" for="fdoc">CPF/CNPJ</label><input id="fdoc" name="sf_from_document" inputmode="numeric" class="field" placeholder="opcional" value="<?= $v('sf_from_document') ?>"><?= $cerr('sf_from_document') ?></div>
        </div>
        <p class="mt-2 text-xs text-ink-500">Só o CEP é necessário para cotar; o endereço completo é usado na etiqueta. CPF/CNPJ vazio = o da sua conta SuperFrete.</p>
    </div>

    <button class="btn-gold" data-loading-text="Salvando…">Salvar cálculo do frete</button>
</form>

<?php require dirname(__DIR__) . '/inc/admin_footer.php'; ?>
