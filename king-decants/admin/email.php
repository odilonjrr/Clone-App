<?php
/**
 * King Decants — admin/email.php (E-mails automáticos)
 * ---------------------------------------------------------------------------
 * - Mostra como o envio está configurado (sem exibir a senha).
 * - Envia um e-mail de teste e mostra o erro exato do servidor, se houver.
 * - Pré-visualiza cada modelo com os dados do último pedido.
 * A configuração (MAIL_DRIVER, SMTP_*) fica no kingdecants-config.php, fora
 * do public_html, para a senha do e-mail não ficar no banco de dados.
 * ---------------------------------------------------------------------------
 */
define('KD_ADMIN_AREA', true);
require dirname(__DIR__) . '/inc/bootstrap.php';
$admin = require_admin();

$kinds = [
    'created'      => 'Pedido recebido (aguardando pagamento)',
    'paid'         => 'Pagamento aprovado',
    'not_approved' => 'Pagamento não aprovado',
    'expired'      => 'Pedido não pago em 24h',
    'shipped'      => 'Pedido enviado (com rastreio)',
    'delivered'    => 'Pedido entregue',
    'cancelled'    => 'Pedido cancelado pela loja',
];
$lastOrder = db()->query('SELECT * FROM orders ORDER BY id DESC LIMIT 1')->fetch() ?: null;

// ---------------- Pré-visualização (abre o HTML do e-mail)
if (isset($_GET['preview'], $kinds[$_GET['preview']])) {
    if (!$lastOrder) {
        exit('Faça ao menos um pedido de teste para pré-visualizar os modelos.');
    }
    $sample = $lastOrder;
    if ($_GET['preview'] === 'shipped' && empty($sample['tracking_code'])) {
        $sample['tracking_code'] = 'AA123456789BR';
    }
    [$subject, $html] = mail_order_message($_GET['preview'], $sample);
    header('Content-Type: text/html; charset=utf-8');
    echo str_replace('<body', '<body data-subject="' . e($subject) . '"', $html);
    exit;
}

$testResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $to = mb_strtolower(trim((string) ($_POST['to'] ?? '')));
    $kind = $_POST['kind'] ?? 'test';
    if (!validate_email($to)) {
        $testResult = ['ok' => false, 'msg' => 'Informe um e-mail válido.'];
    } else {
        if ($kind !== 'test' && isset($kinds[$kind]) && $lastOrder) {
            $sample = $lastOrder;
            if ($kind === 'shipped' && empty($sample['tracking_code'])) {
                $sample['tracking_code'] = 'AA123456789BR';
            }
            [$subject, $html] = mail_order_message($kind, $sample);
            $subject = '[TESTE] ' . $subject;
        } else {
            $subject = 'Teste de e-mail — King Decants';
            $html = mail_layout('Tudo certo!', mail_p('Se você recebeu esta mensagem, os e-mails automáticos da loja estão funcionando.')
                . mail_p('<span style="color:#8f8b83;font-size:13px">Enviado em ' . date('d/m/Y H:i') . ' · modo ' . e(MAIL_DRIVER) . '</span>'));
        }
        $ok = send_mail($to, $subject, $html, $err);
        admin_log('mail_test', 'mail', null, $to . ' · ' . ($ok ? 'ok' : 'falha'));
        $testResult = MAIL_DRIVER === 'log'
            ? ['ok' => false, 'msg' => 'O envio está no modo "log": nada foi enviado de verdade, só registrado em storage/logs/mail.log. Configure o SMTP (passo a passo abaixo).']
            : ($ok ? ['ok' => true, 'msg' => "Enviado para {$to}. Confira a caixa de entrada (e o spam)."]
                   : ['ok' => false, 'msg' => 'Falhou: ' . ($err ?: 'erro desconhecido') ]);
    }
}

$driverLabel = ['smtp' => 'SMTP (caixa de e-mail do domínio)', 'mail' => 'mail() do PHP', 'log' => 'Desligado (modo log — nada é enviado)'][MAIL_DRIVER] ?? MAIL_DRIVER;
$smtpPlaceholder = str_contains((string) SMTP_USER, 'COLOQUE') || str_contains((string) SMTP_PASS, 'COLOQUE') || SMTP_PASS === '';
$lastLines = [];
$logFile = dirname(__DIR__) . '/storage/logs/mail.log';
if (is_file($logFile)) {
    $lastLines = array_slice(file($logFile, FILE_IGNORE_NEW_LINES) ?: [], -8);
}

$pageTitle = 'E-mails';
$adminNav = 'email';
require dirname(__DIR__) . '/inc/admin_header.php';
?>
<p class="eyebrow">Loja</p>
<h1 class="mt-2 font-serif text-4xl text-white">E-mails automáticos</h1>
<div class="gold-divider my-8"></div>

<div class="grid gap-6 2xl:grid-cols-2">
    <div class="space-y-6">
        <section class="panel p-6">
            <h2 class="text-xs font-medium uppercase tracking-[0.22em] text-white">Configuração atual</h2>
            <dl class="mt-4 grid grid-cols-[140px_1fr] gap-y-2 text-sm">
                <dt class="text-ink-400">Envio</dt>
                <dd class="<?= MAIL_DRIVER === 'log' ? 'text-red-300' : 'text-emerald-300' ?>"><?= e($driverLabel) ?></dd>
                <dt class="text-ink-400">Remetente</dt><dd class="text-white"><?= e(MAIL_FROM_NAME . ' <' . MAIL_FROM . '>') ?></dd>
                <?php if (MAIL_DRIVER === 'smtp'): ?>
                    <dt class="text-ink-400">Servidor</dt><dd class="text-white"><?= e(SMTP_HOST . ':' . SMTP_PORT . ' (' . SMTP_SECURE . ')') ?></dd>
                    <dt class="text-ink-400">Usuário</dt><dd class="text-white"><?= e(SMTP_USER) ?></dd>
                    <dt class="text-ink-400">Senha</dt><dd class="<?= $smtpPlaceholder ? 'text-red-300' : 'text-ink-200' ?>"><?= $smtpPlaceholder ? 'não preenchida' : '•••••••• (preenchida)' ?></dd>
                <?php endif; ?>
            </dl>
        </section>

        <form method="post" class="panel space-y-4 p-6" data-loading>
            <?= csrf_field() ?>
            <h2 class="text-xs font-medium uppercase tracking-[0.22em] text-white">Enviar teste</h2>
            <?php if ($testResult): ?>
                <div class="alert <?= $testResult['ok'] ? 'alert-success' : 'alert-error' ?>"><?= e($testResult['msg']) ?></div>
            <?php endif; ?>
            <div>
                <label class="field-label" for="to">Enviar para</label>
                <input id="to" name="to" type="email" class="field" value="<?= e($_POST['to'] ?? $admin['email']) ?>" required>
            </div>
            <div>
                <label class="field-label" for="kind">Modelo</label>
                <select id="kind" name="kind" class="field !pr-9">
                    <option value="test">Mensagem simples de teste</option>
                    <?php if ($lastOrder): foreach ($kinds as $k => $label): ?>
                        <option value="<?= e($k) ?>" <?= ($_POST['kind'] ?? '') === $k ? 'selected' : '' ?>><?= e($label) ?> (pedido #<?= (int) $lastOrder['id'] ?>)</option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
            <button class="btn-gold btn-sm" data-loading-text="Enviando…">Enviar e-mail de teste</button>
        </form>

        <section class="panel p-6">
            <h2 class="text-xs font-medium uppercase tracking-[0.22em] text-white">Quando o cliente recebe</h2>
            <ul class="mt-4 space-y-3 text-sm">
                <?php foreach ($kinds as $k => $label): ?>
                    <li class="flex items-center justify-between gap-4 border-b border-white/5 pb-3">
                        <span class="text-ink-200"><?= e($label) ?></span>
                        <?php if ($lastOrder): ?>
                            <a class="text-xs uppercase tracking-wider text-gold-400 hover:text-gold-300" target="_blank" href="<?= e(url('admin/email.php?preview=' . $k)) ?>">Ver modelo</a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="mt-4 text-xs text-ink-500">Os e-mails saem sozinhos quando o pagamento é confirmado/recusado (InfinitePay) e quando você muda o status em Pedidos (Enviado, Entregue, Cancelado). Para o e-mail de envio levar o rastreio, preencha o código de rastreio antes de marcar como Enviado.</p>
        </section>
    </div>

    <div class="space-y-6">
        <section class="panel p-6 text-sm leading-relaxed text-ink-300">
            <h2 class="text-xs font-medium uppercase tracking-[0.22em] text-white">Como ativar (Hostinger)</h2>
            <ol class="mt-4 list-decimal space-y-2 pl-5">
                <li>No hPanel, vá em <strong class="text-white">E-mails</strong> e crie uma caixa no seu domínio, ex.: <span class="font-mono text-gold-300">contato@seudominio.com.br</span>, com uma senha.</li>
                <li>No Gerenciador de Arquivos, abra o <span class="font-mono text-gold-300">kingdecants-config.php</span> (fora do public_html) e ajuste:</li>
            </ol>
<pre class="mt-3 overflow-x-auto rounded-md border border-gold-400/20 bg-ink-950 p-4 font-mono text-xs text-ink-200">define('MAIL_DRIVER', 'smtp');
define('MAIL_FROM', 'contato@seudominio.com.br');
define('MAIL_FROM_NAME', 'King Decants');
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', 465);
define('SMTP_SECURE', 'ssl');
define('SMTP_USER', 'contato@seudominio.com.br');
define('SMTP_PASS', 'a senha da caixa de e-mail');</pre>
            <ol start="3" class="mt-3 list-decimal space-y-2 pl-5">
                <li>Volte aqui e clique em <strong class="text-white">Enviar e-mail de teste</strong>.</li>
                <li>Se cair no spam: no hPanel, em E-mails, confira se os registros <strong class="text-white">SPF, DKIM e DMARC</strong> do domínio estão ativos.</li>
            </ol>
            <p class="mt-3 text-xs text-ink-500">MAIL_FROM deve ser a mesma caixa do SMTP_USER. Se a porta 465 não conectar, tente 587 com SMTP_SECURE 'tls'.</p>
        </section>

        <section class="panel p-6">
            <h2 class="text-xs font-medium uppercase tracking-[0.22em] text-white">Últimos registros de envio</h2>
            <?php if ($lastLines): ?>
                <pre class="mt-4 max-h-72 overflow-auto whitespace-pre-wrap break-all font-mono text-[11px] leading-relaxed text-ink-400"><?= e(implode("\n", array_reverse($lastLines))) ?></pre>
            <?php else: ?>
                <p class="mt-4 text-sm text-ink-500">Nenhum e-mail registrado ainda.</p>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php require dirname(__DIR__) . '/inc/admin_footer.php'; ?>
