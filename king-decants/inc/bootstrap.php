<?php
/**
 * King Decants — inc/bootstrap.php
 * ---------------------------------------------------------------------------
 * Ponto de entrada comum: TODA página faz require deste arquivo primeiro.
 *
 * Responsabilidades:
 *  1. Carregar configuração, conexão PDO e helpers.
 *  2. Configurar e iniciar a sessão PHP de forma segura.
 *  3. Definir fuso horário e exibição de erros por ambiente.
 *
 * Sessão separada para o admin:
 *  - Páginas em /admin definem `define('KD_ADMIN_AREA', true);` ANTES do require.
 *  - Assim usamos outro nome de cookie (KDADMINSESS), isolando completamente a
 *    sessão do cliente da sessão do administrador.
 * ---------------------------------------------------------------------------
 */

require_once __DIR__ . '/config.php';

/**
 * Erros FATAIS (ex.: arquivo do sistema faltando após uma atualização incompleta)
 * não passam pelo set_exception_handler. Esta função mostra uma página clara em
 * vez da tela branca e indica qual arquivo está faltando.
 */
register_shutdown_function(function (): void {
    $err = error_get_last();
    if (!$err || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    $root = dirname(__DIR__);
    $hint = '';
    if (preg_match("/Failed opening required '([^']+)'/", $err['message'], $m)
        || preg_match('/Failed opening required "([^"]+)"/', $err['message'], $m)) {
        $missing = ltrim(str_replace($root, '', $m[1]), '/\\');
        $hint = '<p style="color:#e6c76f">Arquivo do sistema não encontrado: <strong>public_html/' . htmlspecialchars($missing) . '</strong>.<br>Envie novamente o .zip mais recente e extraia em public_html, substituindo os arquivos.</p>';
    }
    @file_put_contents($root . '/storage/logs/php-errors.log', '[' . date('Y-m-d H:i:s') . '] FATAL ' . $err['message'] . ' em ' . $err['file'] . ':' . $err['line'] . "\n", FILE_APPEND);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!DOCTYPE html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Erro — King Decants</title>'
        . '<body style="margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#070707;color:#ece9e3;font-family:Arial,sans-serif;padding:24px">'
        . '<div style="max-width:640px;text-align:center"><p style="color:#d4af37;letter-spacing:4px;font-size:12px">KING DECANTS</p>'
        . '<h1 style="font-family:Georgia,serif;font-weight:400">Site em manutenção</h1>' . $hint
        . '<p style="color:#8f8b83;font-size:13px">Detalhes em storage/logs/php-errors.log.</p></div></body></html>';
});

date_default_timezone_set(APP_TIMEZONE);

// Em produção nunca exiba erros na tela (vão para o log do servidor).
if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

// Composer (opcional): SDK do Mercado Pago e PHPMailer, se instalados.
if (is_file(dirname(__DIR__) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/cart.php';
require_once __DIR__ . '/catalog.php';
require_once __DIR__ . '/account.php';
require_once __DIR__ . '/promotions.php';
require_once __DIR__ . '/superfrete.php';
require_once __DIR__ . '/orders.php';
require_once __DIR__ . '/mercadopago.php';
require_once __DIR__ . '/infinitepay.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/migrate.php';

/**
 * Erros não tratados: registra em storage/logs/php-errors.log e mostra uma
 * página amigável (em vez de tela em branco). Em desenvolvimento mostra a mensagem.
 */
set_exception_handler(function (Throwable $e): void {
    app_log('php-errors', get_class($e) . ': ' . $e->getMessage(), ['file' => $e->getFile() . ':' . $e->getLine()]);
    error_log('[KingDecants] ' . $e);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    $detail = APP_ENV !== 'production'
        ? '<pre style="white-space:pre-wrap;text-align:left;color:#fca5a5;font-size:12px;margin-top:24px">' . htmlspecialchars((string) $e) . '</pre>'
        : '<p style="color:#8f8b83;font-size:13px">O erro foi registrado em storage/logs/php-errors.log.</p>';
    echo '<!DOCTYPE html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Erro — King Decants</title>'
        . '<body style="margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#070707;color:#ece9e3;font-family:Arial,sans-serif;padding:24px">'
        . '<div style="max-width:640px;text-align:center"><p style="color:#d4af37;letter-spacing:4px;font-size:12px">KING DECANTS</p>'
        . '<h1 style="font-family:Georgia,serif;font-weight:400">Algo não saiu como esperado</h1>'
        . '<p style="color:#b8b4ab">Tente novamente em instantes.</p>' . $detail . '</div></body></html>';
});

/**
 * Inicia a sessão com parâmetros seguros.
 *
 * - cookie_httponly: JavaScript não lê o cookie (mitiga roubo via XSS).
 * - cookie_secure: só trafega em HTTPS (ativado automaticamente se detectado).
 * - cookie_samesite=Lax: bloqueia envio do cookie em POSTs cross-site (ajuda contra CSRF).
 *   (Lax e não Strict para que o retorno do Mercado Pago -> callback.php mantenha a sessão.)
 * - use_strict_mode: rejeita IDs de sessão não gerados pelo servidor (session fixation).
 * - gc_maxlifetime: tempo de vida no servidor; complementado pelo controle de inatividade abaixo.
 */
function kd_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isAdmin = defined('KD_ADMIN_AREA') && KD_ADMIN_AREA;

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', (string) SESSION_LIFETIME);

    session_name($isAdmin ? 'KDADMINSESS' : 'KDSESS');
    session_set_cookie_params([
        'lifetime' => 0,                 // cookie de sessão (expira ao fechar o navegador)
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Expiração por inatividade (independe do garbage collector do PHP).
    $now = time();
    if (isset($_SESSION['_last_activity']) && ($now - $_SESSION['_last_activity']) > SESSION_LIFETIME) {
        // Preserva o carrinho do convidado; descarta autenticação.
        $cart = $_SESSION['cart'] ?? [];
        session_unset();
        session_regenerate_id(true);
        if (!$isAdmin) {
            $_SESSION['cart'] = $cart;
        }
    }
    $_SESSION['_last_activity'] = $now;
}

// Atualiza o banco automaticamente após o envio de uma versão nova (inc/migrate.php).
kd_ensure_schema();

// Webhook (chamado servidor-a-servidor) não precisa de sessão.
if (!defined('KD_NO_SESSION')) {
    kd_start_session();
}

// Cabeçalhos de segurança básicos (complementam o .htaccess).
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}
