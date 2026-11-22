<?php
/**
 * King Decants — admin/account.php (Minha conta do administrador)
 * ---------------------------------------------------------------------------
 * Permite ao admin logado alterar nome, e-mail e senha sem usar o phpMyAdmin.
 *
 * Segurança:
 *  - Exige a SENHA ATUAL para qualquer alteração (se a sessão for roubada,
 *    o invasor não consegue trocar a senha nem o e-mail de acesso).
 *  - CSRF, password_hash, session_regenerate_id após trocar a senha.
 *  - Registro em admin_logs (sem gravar senhas).
 * ---------------------------------------------------------------------------
 */
define('KD_ADMIN_AREA', true);
require dirname(__DIR__) . '/inc/bootstrap.php';
$admin = require_admin();

$errors = [];
$old = ['name' => $admin['name'], 'email' => $admin['email']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $old['name'] = clean_string($_POST['name'] ?? '', 120);
    $old['email'] = mb_strtolower(clean_string($_POST['email'] ?? '', 190));
    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['new_password_confirm'] ?? '');

    $stmt = db()->prepare('SELECT password_hash FROM admin_users WHERE id = ?');
    $stmt->execute([$admin['id']]);
    $hash = (string) $stmt->fetchColumn();

    if (mb_strlen($old['name']) < 2) {
        $errors['name'] = 'Informe seu nome.';
    }
    if (!validate_email($old['email'])) {
        $errors['email'] = 'E-mail inválido.';
    } else {
        $dup = db()->prepare('SELECT COUNT(*) FROM admin_users WHERE email = ? AND id <> ?');
        $dup->execute([$old['email'], $admin['id']]);
        if ((int) $dup->fetchColumn() > 0) {
            $errors['email'] = 'Este e-mail já é usado por outro administrador.';
        }
    }
    if ($new !== '' || $confirm !== '') {
        if (!validate_password($new)) {
            $errors['new_password'] = 'A nova senha deve ter ao menos 8 caracteres, com letras e números.';
        } elseif (!hash_equals($new, $confirm)) {
            $errors['new_password_confirm'] = 'As senhas não conferem.';
        } elseif (password_verify($new, $hash)) {
            $errors['new_password'] = 'A nova senha deve ser diferente da atual.';
        }
    }
    if (!password_verify($current, $hash)) {
        $errors['current_password'] = 'Senha atual incorreta.';
    }

    if (!$errors) {
        $changes = [];
        if ($old['name'] !== $admin['name']) {
            $changes[] = 'nome';
        }
        if ($old['email'] !== $admin['email']) {
            $changes[] = "e-mail ({$admin['email']} → {$old['email']})";
        }
        db()->prepare('UPDATE admin_users SET name = ?, email = ? WHERE id = ?')->execute([$old['name'], $old['email'], $admin['id']]);
        if ($new !== '') {
            db()->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $admin['id']]);
            session_regenerate_id(true);
            $changes[] = 'senha';
        }
        if ($changes) {
            admin_log('admin_account_update', 'admin', (int) $admin['id'], 'Alterou: ' . implode(', ', $changes));
            flash('success', 'Dados atualizados: ' . implode(', ', $changes) . '.');
        } else {
            flash('info', 'Nenhuma alteração.');
        }
        redirect(url('admin/account.php'));
    }
}

$err = fn(string $k) => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
$inv = fn(string $k) => isset($errors[$k]) ? ' is-invalid' : '';
$isDefault = $admin['email'] === 'admin@kingdecants.local';

$pageTitle = 'Minha conta';
$adminNav = 'account';
require dirname(__DIR__) . '/inc/admin_header.php';
?>
<p class="eyebrow">Administrador</p>
<h1 class="mt-2 font-serif text-4xl text-white">Minha conta</h1>
<div class="gold-divider my-8"></div>

<?php if ($isDefault): ?>
    <div class="alert alert-error mb-6 max-w-2xl">Você ainda usa o acesso padrão de instalação. Troque o e-mail e a senha agora.</div>
<?php endif; ?>

<form method="post" class="panel max-w-2xl space-y-6 p-6 sm:p-8" data-loading novalidate>
    <?= csrf_field() ?>
    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <label class="field-label" for="name">Nome</label>
            <input id="name" name="name" class="field<?= $inv('name') ?>" required value="<?= e($old['name']) ?>">
            <?= $err('name') ?>
        </div>
        <div>
            <label class="field-label" for="email">E-mail de acesso</label>
            <input id="email" name="email" type="email" class="field<?= $inv('email') ?>" required autocomplete="username" value="<?= e($old['email']) ?>">
            <?= $err('email') ?>
        </div>
    </div>

    <div class="gold-divider"></div>
    <p class="text-sm text-ink-400">Nova senha (deixe em branco para manter a atual)</p>
    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <label class="field-label" for="new_password">Nova senha</label>
            <input id="new_password" name="new_password" type="password" class="field<?= $inv('new_password') ?>" autocomplete="new-password">
            <?= $err('new_password') ?>
        </div>
        <div>
            <label class="field-label" for="new_password_confirm">Confirmar nova senha</label>
            <input id="new_password_confirm" name="new_password_confirm" type="password" class="field<?= $inv('new_password_confirm') ?>" autocomplete="new-password">
            <?= $err('new_password_confirm') ?>
        </div>
    </div>

    <div class="gold-divider"></div>
    <div class="sm:w-1/2">
        <label class="field-label" for="current_password">Senha atual (obrigatória para salvar)</label>
        <input id="current_password" name="current_password" type="password" class="field<?= $inv('current_password') ?>" required autocomplete="current-password">
        <?= $err('current_password') ?>
    </div>
    <button type="submit" class="btn-gold" data-loading-text="Salvando…">Salvar alterações</button>
</form>

<?php require dirname(__DIR__) . '/inc/admin_footer.php'; ?>
