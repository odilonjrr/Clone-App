<?php
/**
 * King Decants — admin/coupons.php (Cupons de desconto)
 * ---------------------------------------------------------------------------
 * Criar / editar / ativar-desativar / excluir cupons.
 *   Código (ex.: KING10), tipo: % ou valor fixo (R$), valor.
 *   Opcionais: pedido mínimo, limite de usos, início e fim da validade.
 * O cliente digita o código no resumo do checkout, antes do pagamento.
 * Um cupom já usado em pedidos não é excluído (é desativado), para manter o
 * histórico. Todas as ações exigem CSRF e ficam em admin_logs.
 * ---------------------------------------------------------------------------
 */
define('KD_ADMIN_AREA', true);
require dirname(__DIR__) . '/inc/bootstrap.php';
$admin = require_admin();

$errors = [];
$editId = int_param($_GET, 'editar');
$old = ['code' => '', 'type' => 'percent', 'value' => '', 'min_subtotal' => '', 'max_uses' => '', 'starts_at' => '', 'expires_at' => '', 'is_active' => 1];

/** "1.234,56" / "10" / "10.5" -> "1234.56" (ou '' se vazio). */
function coupon_money_input($v): string
{
    $v = str_replace(['R$', '%', ' '], '', (string) $v);
    if (str_contains($v, ',')) {
        $v = str_replace(',', '.', str_replace('.', '', $v));
    }
    return $v;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = $_POST['action'] ?? 'save';
    $id = int_param($_POST, 'id');

    if ($action === 'toggle' && $id) {
        db()->prepare('UPDATE coupons SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
        admin_log('coupon_toggle', 'coupon', $id);
        flash('success', 'Situação do cupom atualizada.');
        redirect(url('admin/coupons.php'));
    }
    if ($action === 'delete' && $id) {
        $c = db()->prepare('SELECT code FROM coupons WHERE id = ?');
        $c->execute([$id]);
        $code = (string) $c->fetchColumn();
        $used = db()->prepare('SELECT COUNT(*) FROM orders WHERE coupon_code = ?');
        $used->execute([$code]);
        if ((int) $used->fetchColumn() > 0) {
            db()->prepare('UPDATE coupons SET is_active = 0 WHERE id = ?')->execute([$id]);
            flash('info', "O cupom {$code} já foi usado em pedidos: foi desativado (não excluído) para manter o histórico.");
        } else {
            db()->prepare('DELETE FROM coupons WHERE id = ?')->execute([$id]);
            flash('success', "Cupom {$code} excluído.");
        }
        admin_log('coupon_delete', 'coupon', $id, $code);
        redirect(url('admin/coupons.php'));
    }

    // ---------------- salvar (criar ou editar)
    $old = [
        'code'         => coupon_normalize((string) ($_POST['code'] ?? '')),
        'type'         => ($_POST['type'] ?? '') === 'fixed' ? 'fixed' : 'percent',
        'value'        => coupon_money_input($_POST['value'] ?? ''),
        'min_subtotal' => coupon_money_input($_POST['min_subtotal'] ?? ''),
        'max_uses'     => trim((string) ($_POST['max_uses'] ?? '')),
        'starts_at'    => trim((string) ($_POST['starts_at'] ?? '')),
        'expires_at'   => trim((string) ($_POST['expires_at'] ?? '')),
        'is_active'    => isset($_POST['is_active']) ? 1 : 0,
    ];
    $editId = $id;
    if (strlen($old['code']) < 3) {
        $errors['code'] = 'Código com 3 a 30 caracteres (letras, números, - e _).';
    } else {
        $dup = db()->prepare('SELECT COUNT(*) FROM coupons WHERE code = ? AND id <> ?');
        $dup->execute([$old['code'], $id]);
        if ((int) $dup->fetchColumn() > 0) {
            $errors['code'] = 'Já existe um cupom com este código.';
        }
    }
    if (!is_numeric($old['value']) || (float) $old['value'] <= 0) {
        $errors['value'] = 'Informe o valor do desconto.';
    } elseif ($old['type'] === 'percent' && (float) $old['value'] > 100) {
        $errors['value'] = 'A porcentagem vai de 0,01% a 100%.';
    } elseif ($old['type'] === 'fixed' && (float) $old['value'] > 99999) {
        $errors['value'] = 'Valor muito alto.';
    }
    if ($old['min_subtotal'] !== '' && (!is_numeric($old['min_subtotal']) || (float) $old['min_subtotal'] < 0)) {
        $errors['min_subtotal'] = 'Pedido mínimo inválido.';
    }
    if ($old['max_uses'] !== '' && filter_var($old['max_uses'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
        $errors['max_uses'] = 'Limite de usos deve ser um número inteiro (1 ou mais).';
    }
    foreach (['starts_at' => 'Início', 'expires_at' => 'Fim'] as $k => $label) {
        if ($old[$k] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $old[$k])) {
            $errors[$k] = "{$label}: data inválida.";
        }
    }
    if (!$errors && $old['starts_at'] !== '' && $old['expires_at'] !== '' && $old['expires_at'] < $old['starts_at']) {
        $errors['expires_at'] = 'O fim da validade deve ser depois do início.';
    }

    if (!$errors) {
        $data = [
            $old['code'], $old['type'], round((float) $old['value'], 2),
            $old['min_subtotal'] !== '' ? round((float) $old['min_subtotal'], 2) : null,
            $old['max_uses'] !== '' ? (int) $old['max_uses'] : null,
            $old['starts_at'] !== '' ? $old['starts_at'] . ' 00:00:00' : null,
            $old['expires_at'] !== '' ? $old['expires_at'] . ' 23:59:59' : null,
            $old['is_active'],
        ];
        if ($id) {
            db()->prepare('UPDATE coupons SET code = ?, type = ?, value = ?, min_subtotal = ?, max_uses = ?, starts_at = ?, expires_at = ?, is_active = ? WHERE id = ?')
                ->execute([...$data, $id]);
            admin_log('coupon_update', 'coupon', $id, $old['code']);
            flash('success', "Cupom {$old['code']} atualizado.");
        } else {
            db()->prepare('INSERT INTO coupons (code, type, value, min_subtotal, max_uses, starts_at, expires_at, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute($data);
            admin_log('coupon_create', 'coupon', (int) db()->lastInsertId(), $old['code']);
            flash('success', "Cupom {$old['code']} criado.");
        }
        redirect(url('admin/coupons.php'));
    }
}

// Edição: carrega o cupom
if ($editId && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $st = db()->prepare('SELECT * FROM coupons WHERE id = ?');
    $st->execute([$editId]);
    if ($c = $st->fetch()) {
        $old = [
            'code' => $c['code'], 'type' => $c['type'], 'value' => $c['value'], 'min_subtotal' => $c['min_subtotal'] ?? '',
            'max_uses' => $c['max_uses'] ?? '', 'starts_at' => $c['starts_at'] ? substr($c['starts_at'], 0, 10) : '',
            'expires_at' => $c['expires_at'] ? substr($c['expires_at'], 0, 10) : '', 'is_active' => (int) $c['is_active'],
        ];
    } else {
        $editId = 0;
    }
}

$coupons = db()->query('SELECT * FROM coupons ORDER BY is_active DESC, id DESC LIMIT 200')->fetchAll();
$fmt = fn($v) => is_numeric($v) ? number_format((float) $v, 2, ',', '') : (string) $v;
$err = fn(string $k) => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
$inv = fn(string $k) => isset($errors[$k]) ? ' is-invalid' : '';

$pageTitle = 'Cupons';
$adminNav = 'coupons';
require dirname(__DIR__) . '/inc/admin_header.php';
?>
<p class="eyebrow">Loja</p>
<h1 class="mt-2 font-serif text-4xl text-white">Cupons de desconto</h1>
<div class="gold-divider my-8"></div>

<div class="grid gap-6 2xl:grid-cols-[360px_1fr]">
    <!-- Formulário -->
    <form method="post" action="<?= e(url('admin/coupons.php')) ?>" class="panel h-fit w-full max-w-xl space-y-4 p-6" data-loading novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int) $editId ?>">
        <h2 class="text-xs font-medium uppercase tracking-[0.22em] text-white"><?= $editId ? 'Editar cupom' : 'Novo cupom' ?></h2>
        <div>
            <label class="field-label" for="code">Código *</label>
            <input id="code" name="code" class="field uppercase<?= $inv('code') ?>" maxlength="30" placeholder="Ex.: KING10" value="<?= e($old['code']) ?>" required>
            <?= $err('code') ?>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="field-label" for="type">Tipo *</label>
                <select id="type" name="type" class="field !pr-9">
                    <option value="percent" <?= $old['type'] === 'percent' ? 'selected' : '' ?>>Porcentagem</option>
                    <option value="fixed" <?= $old['type'] === 'fixed' ? 'selected' : '' ?>>Valor fixo (R$)</option>
                </select>
            </div>
            <div>
                <label class="field-label" for="value">Valor *</label>
                <input id="value" name="value" inputmode="decimal" class="field<?= $inv('value') ?>" placeholder="10" value="<?= e($old['value'] !== '' ? $fmt($old['value']) : '') ?>" required>
                <?= $err('value') ?>
            </div>
        </div>
        <p class="text-xs text-ink-500">Ex.: Porcentagem 10 = 10% de desconto · Valor fixo 20 = R$ 20,00 de desconto. O desconto vale sobre os produtos (não sobre o frete).</p>
        <div class="gold-divider"></div>
        <p class="text-xs uppercase tracking-[0.18em] text-ink-400">Opcional</p>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="field-label" for="min_subtotal">Pedido mínimo (R$)</label>
                <input id="min_subtotal" name="min_subtotal" inputmode="decimal" class="field<?= $inv('min_subtotal') ?>" placeholder="—" value="<?= e($old['min_subtotal'] !== '' && $old['min_subtotal'] !== null ? $fmt($old['min_subtotal']) : '') ?>">
                <?= $err('min_subtotal') ?>
            </div>
            <div>
                <label class="field-label" for="max_uses">Limite de usos</label>
                <input id="max_uses" name="max_uses" type="number" min="1" class="field<?= $inv('max_uses') ?>" placeholder="Ilimitado" value="<?= e((string) $old['max_uses']) ?>">
                <?= $err('max_uses') ?>
            </div>
            <div>
                <label class="field-label" for="starts_at">Válido a partir de</label>
                <input id="starts_at" name="starts_at" type="date" class="field<?= $inv('starts_at') ?>" value="<?= e($old['starts_at']) ?>">
                <?= $err('starts_at') ?>
            </div>
            <div>
                <label class="field-label" for="expires_at">Válido até</label>
                <input id="expires_at" name="expires_at" type="date" class="field<?= $inv('expires_at') ?>" value="<?= e($old['expires_at']) ?>">
                <?= $err('expires_at') ?>
            </div>
        </div>
        <label class="flex items-center gap-3 text-sm text-ink-200">
            <input type="checkbox" name="is_active" value="1" class="accent-[#d4af37]" <?= (int) $old['is_active'] ? 'checked' : '' ?>> Ativo
        </label>
        <div class="flex gap-3">
            <button class="btn-gold btn-sm" data-loading-text="Salvando…"><?= $editId ? 'Salvar alterações' : 'Criar cupom' ?></button>
            <?php if ($editId): ?><a href="<?= e(url('admin/coupons.php')) ?>" class="btn-outline btn-sm">Cancelar</a><?php endif; ?>
        </div>
    </form>

    <!-- Lista -->
    <div class="panel overflow-x-auto">
        <table class="table-lux">
            <thead><tr><th>Código</th><th>Desconto</th><th>Regras</th><th class="text-right">Usos</th><th>Situação</th><th class="text-right"><span class="sr-only">Ações</span></th></tr></thead>
            <tbody>
            <?php foreach ($coupons as $c):
                $expired = $c['expires_at'] && date('Y-m-d H:i:s') > $c['expires_at'];
                $exhausted = $c['max_uses'] !== null && (int) $c['used_count'] >= (int) $c['max_uses']; ?>
                <tr class="<?= (int) $c['is_active'] ? '' : 'opacity-50' ?>">
                    <td class="font-mono text-white"><?= e($c['code']) ?></td>
                    <td class="whitespace-nowrap text-gold-300"><?= e(coupon_label($c)) ?></td>
                    <td class="text-xs text-ink-400">
                        <?= $c['min_subtotal'] !== null ? 'Mín. ' . money($c['min_subtotal']) . '<br>' : '' ?>
                        <?= $c['starts_at'] ? 'De ' . date_br($c['starts_at'], false) . ' ' : '' ?><?= $c['expires_at'] ? 'até ' . date_br($c['expires_at'], false) : '' ?>
                        <?= $c['min_subtotal'] === null && !$c['starts_at'] && !$c['expires_at'] ? 'Sem restrições' : '' ?>
                    </td>
                    <td class="whitespace-nowrap text-right"><?= (int) $c['used_count'] ?><?= $c['max_uses'] !== null ? ' / ' . (int) $c['max_uses'] : '' ?></td>
                    <td>
                        <?php if (!(int) $c['is_active']): ?><span class="text-xs uppercase tracking-wider text-ink-500">Inativo</span>
                        <?php elseif ($expired): ?><span class="text-xs uppercase tracking-wider text-red-300">Expirado</span>
                        <?php elseif ($exhausted): ?><span class="text-xs uppercase tracking-wider text-amber-300">Esgotado</span>
                        <?php else: ?><span class="text-xs uppercase tracking-wider text-emerald-300">Ativo</span><?php endif; ?>
                    </td>
                    <td class="whitespace-nowrap text-right !pr-6">
                        <div class="inline-flex items-center gap-1">
                            <a class="icon-btn h-8 w-8 text-gold-400" href="<?= e(url('admin/coupons.php?editar=' . $c['id'])) ?>" aria-label="Editar" title="Editar"><?= icon('edit', 'h-4 w-4') ?></a>
                            <form method="post" action="<?= e(url('admin/coupons.php')) ?>">
                                <?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                <button class="rounded border border-gold-400/30 px-2 py-1 text-[11px] uppercase tracking-wider text-gold-300 hover:bg-gold-400/10"><?= (int) $c['is_active'] ? 'Desativar' : 'Ativar' ?></button>
                            </form>
                            <form method="post" action="<?= e(url('admin/coupons.php')) ?>" onsubmit="return confirm('Excluir o cupom <?= e($c['code']) ?>?')">
                                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                <button class="icon-btn h-8 w-8 text-red-300" aria-label="Excluir"><?= icon('trash', 'h-4 w-4') ?></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$coupons): ?><tr><td colspan="6" class="py-12 text-center text-ink-500">Nenhum cupom criado ainda.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require dirname(__DIR__) . '/inc/admin_footer.php'; ?>
