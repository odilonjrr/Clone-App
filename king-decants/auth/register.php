<?php
/**
 * King Decants — auth/register.php (Cadastro de cliente)
 * ---------------------------------------------------------------------------
 * Campos: nome completo, e-mail, senha (+ confirmação), telefone (opcional)
 * e CPF (opcional, validado pelos dígitos verificadores se preenchido).
 *
 * Segurança:
 *  - CSRF obrigatório
 *  - Senha com password_hash() (bcrypt/argon conforme PHP; nunca texto puro)
 *  - E-mail já usado por CONVIDADO: a conta é "promovida" (define a senha),
 *    mantendo o vínculo no mini-CRM. Como não há verificação de e-mail neste
 *    MVP, a nova conta NÃO herda telefone/CPF do convidado e não vê pedidos
 *    nem endereços de compras feitas como convidado.
 *  - Após criar a conta: login_user() -> session_regenerate_id() + migra o carrinho.
 * ---------------------------------------------------------------------------
 */
require dirname(__DIR__) . '/inc/bootstrap.php';

$redirectTo = safe_redirect_target($_GET['redirect'] ?? ($_POST['redirect'] ?? ''), 'account.php');
if (is_logged_in()) {
    redirect($redirectTo);
}

$errors = [];
$old = ['name' => '', 'email' => '', 'phone' => '', 'cpf' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $old = [
        'name'  => clean_string($_POST['name'] ?? '', 150),
        'email' => mb_strtolower(clean_string($_POST['email'] ?? '', 190)),
        'phone' => only_digits($_POST['phone'] ?? ''),
        'cpf'   => only_digits($_POST['cpf'] ?? ''),
    ];
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');

    if (mb_strlen($old['name']) < 5 || !str_contains($old['name'], ' ')) {
        $errors['name'] = 'Informe nome e sobrenome.';
    }
    if (!validate_email($old['email'])) {
        $errors['email'] = 'E-mail inválido.';
    }
    if (!validate_password($password)) {
        $errors['password'] = 'A senha deve ter ao menos 8 caracteres, com letras e números.';
    } elseif (!hash_equals($password, $confirm)) {
        $errors['password_confirm'] = 'As senhas não conferem.';
    }
    if ($old['phone'] !== '' && !validate_phone($old['phone'])) {
        $errors['phone'] = 'Telefone inválido. Use DDD + número.';
    }
    if ($old['cpf'] !== '' && !validate_cpf($old['cpf'])) {
        $errors['cpf'] = 'CPF inválido.';
    }

    if (!$errors) {
        $stmt = db()->prepare('SELECT id, is_guest FROM users WHERE email = ?');
        $stmt->execute([$old['email']]);
        $existing = $stmt->fetch();

        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($existing && !(int) $existing['is_guest']) {
            $errors['email'] = 'Este e-mail já possui conta. Faça login.';
        } elseif ($existing) {
            // Promove o registro de convidado a conta. Sem verificação de e-mail não dá
            // para provar que quem se cadastra é o dono das compras como convidado, então
            // telefone/CPF do convidado NÃO são herdados (ficam só nos pedidos, visíveis ao admin).
            db()->prepare('UPDATE users SET name = ?, password_hash = ?, phone = ?, cpf = ?, is_guest = 0 WHERE id = ?')
                ->execute([$old['name'], $hash, $old['phone'] ?: null, $old['cpf'] ?: null, $existing['id']]);
            $userId = (int) $existing['id'];
        } else {
            db()->prepare('INSERT INTO users (name, email, password_hash, phone, cpf, is_guest) VALUES (?, ?, ?, ?, ?, 0)')
                ->execute([$old['name'], $old['email'], $hash, $old['phone'] ?: null, $old['cpf'] ?: null]);
            $userId = (int) db()->lastInsertId();
        }

        if (!$errors) {
            login_user($userId); // regenera sessão + migra carrinho
            flash('success', 'Conta criada! Bem-vindo(a) à King Decants.');
            redirect($redirectTo);
        }
    }
}

$err = fn(string $k) => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
$inv = fn(string $k) => isset($errors[$k]) ? ' is-invalid' : '';

$pageTitle = 'Criar conta — King Decants';
require dirname(__DIR__) . '/inc/header.php';
?>
<section class="mx-auto max-w-lg px-5 py-16">
    <div class="panel p-8 animate-fade-up sm:p-10">
        <p class="eyebrow">Nova conta</p>
        <h1 class="mt-3 font-serif text-4xl text-white">Faça parte da <em class="text-gold-gradient">realeza.</em></h1>
        <p class="mt-3 text-sm text-ink-400">Sua sacola atual será mantida.</p>

        <form method="post" class="mt-8 space-y-5" data-loading novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="redirect" value="<?= e($_GET['redirect'] ?? ($_POST['redirect'] ?? '')) ?>">
            <div>
                <label class="field-label" for="name">Nome completo *</label>
                <input id="name" name="name" class="field<?= $inv('name') ?>" required autocomplete="name" value="<?= e($old['name']) ?>">
                <?= $err('name') ?>
            </div>
            <div>
                <label class="field-label" for="email">E-mail *</label>
                <input id="email" name="email" type="email" class="field<?= $inv('email') ?>" required autocomplete="email" value="<?= e($old['email']) ?>">
                <?= $err('email') ?>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="field-label" for="phone">Telefone</label>
                    <input id="phone" name="phone" type="tel" class="field<?= $inv('phone') ?>" data-mask="phone" placeholder="(11) 91234-5678" value="<?= e($old['phone']) ?>">
                    <?= $err('phone') ?>
                </div>
                <div>
                    <label class="field-label" for="cpf">CPF</label>
                    <input id="cpf" name="cpf" class="field<?= $inv('cpf') ?>" data-mask="cpf" inputmode="numeric" placeholder="Opcional" value="<?= e($old['cpf']) ?>">
                    <?= $err('cpf') ?>
                </div>
            </div>
            <div>
                <label class="field-label" for="password">Senha *</label>
                <input id="password" name="password" type="password" class="field<?= $inv('password') ?>" required autocomplete="new-password" minlength="8">
                <?= $err('password') ?>
            </div>
            <div>
                <label class="field-label" for="password_confirm">Confirmar senha *</label>
                <input id="password_confirm" name="password_confirm" type="password" class="field<?= $inv('password_confirm') ?>" required autocomplete="new-password">
                <?= $err('password_confirm') ?>
            </div>
            <button type="submit" class="btn-gold w-full" data-loading-text="Criando conta…">Criar conta</button>
        </form>
        <p class="mt-6 text-center text-sm text-ink-400">Já tem conta?
            <a class="text-gold-400 hover:text-gold-300" href="<?= e(url('auth/login.php?redirect=' . urlencode($_GET['redirect'] ?? ''))) ?>">Entrar</a></p>
    </div>
</section>
<?php require dirname(__DIR__) . '/inc/footer.php'; ?>
