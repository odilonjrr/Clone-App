<?php
/**
 * King Decants — auth/login.php (Login de cliente)
 * ---------------------------------------------------------------------------
 * Atende dois formatos:
 *   - Página completa (GET/POST tradicional)
 *   - Modal de login rápido (POST via fetch com Accept: application/json)
 *
 * Segurança:
 *   - CSRF obrigatório
 *   - Rate limit: LOGIN_MAX_ATTEMPTS falhas por IP/e-mail em LOGIN_WINDOW_MINUTES
 *     (tabela login_attempts — ver helpers.php)
 *   - Mensagem de erro genérica (não revela se o e-mail existe)
 *   - password_verify() + rehash automático quando o algoritmo padrão mudar
 *   - login_user(): session_regenerate_id(true) + migração do carrinho
 * ---------------------------------------------------------------------------
 */
require dirname(__DIR__) . '/inc/bootstrap.php';

$redirectTo = safe_redirect_target($_POST['redirect'] ?? ($_GET['redirect'] ?? ''), 'account.php');
if (is_logged_in()) {
    redirect($redirectTo);
}

$error = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $email = mb_strtolower(clean_string($_POST['email'] ?? '', 190));
    $password = (string) ($_POST['password'] ?? '');

    if (login_is_throttled('store', $email)) {
        $error = 'Muitas tentativas. Aguarde ' . LOGIN_WINDOW_MINUTES . ' minutos e tente novamente.';
    } else {
        $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE email = ? AND is_guest = 0 AND password_hash IS NOT NULL');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            }
            login_clear_failures('store', $email);
            login_user((int) $user['id']);

            if (wants_json()) {
                json_response(['ok' => true, 'redirect' => $redirectTo]);
            }
            flash('success', 'Bem-vindo(a) de volta!');
            redirect($redirectTo);
        }
        login_register_failure('store', $email);
        $error = 'E-mail ou senha incorretos.';
    }

    if (wants_json()) {
        json_response(['ok' => false, 'error' => $error], 422);
    }
}

$pageTitle = 'Entrar — King Decants';
require dirname(__DIR__) . '/inc/header.php';
?>
<section class="mx-auto max-w-md px-5 py-20">
    <div class="panel p-8 animate-fade-up sm:p-10">
        <p class="eyebrow">Minha conta</p>
        <h1 class="mt-3 font-serif text-4xl text-white">Entrar</h1>
        <?php if ($error): ?>
            <div class="alert alert-error mt-6"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" class="mt-8 space-y-5" data-loading>
            <?= csrf_field() ?>
            <input type="hidden" name="redirect" value="<?= e($_GET['redirect'] ?? ($_POST['redirect'] ?? '')) ?>">
            <div>
                <label class="field-label" for="email">E-mail</label>
                <input id="email" name="email" type="email" class="field" required autocomplete="email" value="<?= e($email) ?>">
            </div>
            <div>
                <label class="field-label" for="password">Senha</label>
                <input id="password" name="password" type="password" class="field" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn-gold w-full" data-loading-text="Entrando…">Entrar</button>
        </form>
        <p class="mt-6 text-center text-sm text-ink-400">Novo por aqui?
            <a class="text-gold-400 hover:text-gold-300" href="<?= e(url('auth/register.php?redirect=' . urlencode($_GET['redirect'] ?? ''))) ?>">Criar conta</a></p>
    </div>
</section>
<?php require dirname(__DIR__) . '/inc/footer.php'; ?>
