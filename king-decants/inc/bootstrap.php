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
require_once __DIR__ . '/orders.php';
require_once __DIR__ . '/mercadopago.php';
require_once __DIR__ . '/mailer.php';

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
