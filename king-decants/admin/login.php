<?php
/**
 * King Decants — admin/login.php
 * ---------------------------------------------------------------------------
 * Login do painel, separado do login de clientes:
 *   - Tabela admin_users (senha com password_hash)
 *   - Sessão própria (cookie KDADMINSESS — definido por KD_ADMIN_AREA)
 *   - session_regenerate_id(true) após autenticar
 *   - CSRF + rate limit (escopo 'admin' em login_attempts)
 *   - Registro em admin_logs de cada login bem-sucedido
 * ---------------------------------------------------------------------------
 */
define('KD_ADMIN_AREA', true);
require dirname(__DIR__) . '/inc/bootstrap.php';

if (is_admin()) {
    redirect(url('admin/index.php'));
}

$error = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $email = mb_strtolower(clean_string($_POST['email'] ?? '', 190));
    $password = (string) ($_POST['password'] ?? '');

    if (login_is_throttled('admin', $email)) {
        $error = 'Muitas tentativas. Aguarde ' . LOGIN_WINDOW_MINUTES . ' minutos.';
    } else {
        $stmt = db()->prepare('SELECT id, password_hash FROM admin_users WHERE email = ? AND is_active = 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch();

        if ($row && password_verify($password, $row['password_hash'])) {
            if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
                db()->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $row['id']]);
            }
            login_clear_failures('admin', $email);
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $row['id'];
            db()->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = ?')->execute([$row['id']]);
            admin_log('login', 'admin', (int) $row['id'], null, (int) $row['id']);
            redirect(url('admin/index.php'));
        }
        login_register_failure('admin', $email);
        $error = 'Credenciais inválidas.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Login · Admin King Decants</title>
    <link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;1,400&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?= e(asset('js/tailwind.config.js')) ?>"></script>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="flex min-h-screen items-center justify-center bg-ink-950 px-5 font-sans text-ink-200 antialiased">
    <div class="pointer-events-none fixed left-1/2 top-1/3 h-[420px] w-[420px] -translate-x-1/2 rounded-full bg-gold-400/10 blur-[120px]"></div>
    <div class="relative w-full max-w-sm animate-fade-up">
        <div class="mb-8 flex flex-col items-center gap-3 text-gold-400">
            <?= icon('crown', 'h-9 w-9') ?>
            <span class="font-serif text-2xl tracking-[0.3em] text-gold-gradient">KING DECANTS</span>
            <span class="text-[10px] uppercase tracking-[0.3em] text-ink-500">Painel administrativo</span>
        </div>
        <div class="modal-panel !max-w-none">
            <?php if ($error): ?>
                <div class="alert alert-error mb-5"><?= e($error) ?></div>
            <?php endif; ?>
            <form method="post" class="space-y-5" data-loading>
                <?= csrf_field() ?>
                <div>
                    <label class="field-label" for="email">E-mail</label>
                    <input id="email" name="email" type="email" class="field" required autocomplete="username" value="<?= e($email) ?>">
                </div>
                <div>
                    <label class="field-label" for="password">Senha</label>
                    <input id="password" name="password" type="password" class="field" required autocomplete="current-password">
                </div>
                <button class="btn-gold w-full" type="submit" data-loading-text="Entrando…">Entrar</button>
            </form>
        </div>
    </div>
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
