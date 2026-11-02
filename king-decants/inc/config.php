<?php
/**
 * King Decants — inc/config.php
 * ---------------------------------------------------------------------------
 * Configuração central do projeto (banco de dados, Mercado Pago, e-mail, app).
 *
 * ONDE COLOCAR AS CHAVES
 *   1) Preferível: variáveis de ambiente (painel da hospedagem / VPS / Apache
 *      SetEnv / php-fpm env[]). Cada define() abaixo lê primeiro getenv().
 *   2) Alternativa: copie este arquivo para FORA do webroot, por exemplo
 *      /home/usuario/kingdecants-config.php, preencha os valores reais lá e
 *      ele será carregado automaticamente (veja o bloco "override" abaixo).
 *   3) Último recurso: edite os placeholders aqui mesmo — e NUNCA faça commit
 *      de chaves reais no Git.
 *
 * Nenhum valor abaixo é um segredo real: todos são PLACEHOLDERS.
 * ---------------------------------------------------------------------------
 */

// ---------------------------------------------------------------------------
// Override opcional: arquivo de configuração fora do webroot.
// Se existir, ele deve conter os mesmos define() com valores reais.
// Como usamos "if (!defined(...))" abaixo, o que for definido lá prevalece.
// ---------------------------------------------------------------------------
$kdExternalConfig = getenv('KD_CONFIG_FILE') ?: dirname(__DIR__, 2) . '/kingdecants-config.php';
if (is_file($kdExternalConfig)) {
    require_once $kdExternalConfig;
}

/** Helper local: define a constante só se ainda não existir (env > arquivo externo > placeholder). */
function kd_define(string $name, $default): void
{
    if (defined($name)) {
        return;
    }
    $env = getenv($name);
    if ($env !== false && $env !== '') {
        // Converte "true"/"false" de variáveis de ambiente em booleanos
        if (is_bool($default)) {
            $env = filter_var($env, FILTER_VALIDATE_BOOLEAN);
        }
        define($name, $env);
        return;
    }
    define($name, $default);
}

// ---------------------------------------------------------------------------
// DB
// ---------------------------------------------------------------------------
kd_define('DB_HOST', 'localhost');
kd_define('DB_NAME', 'deckstore');
kd_define('DB_USER', 'root');
kd_define('DB_PASS', '');
kd_define('DB_PORT', '3306');

// ---------------------------------------------------------------------------
// Mercado Pago
//   Painel: https://www.mercadopago.com.br/developers/panel/app
//   -> Sua aplicação -> "Credenciais de teste" (sandbox) ou "Credenciais de produção".
// ---------------------------------------------------------------------------
kd_define('MP_PUBLIC_KEY', 'COLOQUE_SUA_PUBLIC_KEY_AQUI');
kd_define('MP_ACCESS_TOKEN', 'COLOQUE_SEU_ACCESS_TOKEN_AQUI');
// Assinatura secreta do Webhook (Suas integrações -> Webhooks -> "Assinatura secreta").
// Se vazia, a validação HMAC do x-signature é ignorada (o status ainda é confirmado na API).
kd_define('MP_WEBHOOK_SECRET', '');
// true = usa sandbox_init_point (credenciais de teste). Em produção: false.
kd_define('MP_SANDBOX', true);
// Nome que aparece na fatura do cartão (máx. ~13 caracteres).
kd_define('MP_STATEMENT_DESCRIPTOR', 'KINGDECANTS');

// ---------------------------------------------------------------------------
// Aplicação
// ---------------------------------------------------------------------------
// URL pública SEM barra final. Usada em links absolutos, back_urls e notification_url.
// Ex.: https://www.kingdecants.com.br  ou  https://meusite.com.br/loja
kd_define('APP_URL', 'http://localhost:8000');
kd_define('APP_NAME', 'King Decants');
kd_define('APP_ENV', 'development');          // 'production' esconde erros detalhados
kd_define('APP_TIMEZONE', 'America/Sao_Paulo');

// Sessão: tempo de vida (segundos). 7200 = 2h de inatividade.
kd_define('SESSION_LIFETIME', 7200);

// Rate limit de login: máx. tentativas falhas por janela (minutos).
kd_define('LOGIN_MAX_ATTEMPTS', 5);
kd_define('LOGIN_WINDOW_MINUTES', 15);

// Frete: pedidos com subtotal >= este valor têm frete grátis (0 desativa).
kd_define('FREE_SHIPPING_FROM', 299.00);

// ---------------------------------------------------------------------------
// E-mail (veja inc/mailer.php). MAIL_DRIVER: 'mail' (função mail()), 'smtp'
// (PHPMailer via Composer) ou 'log' (apenas grava em storage/logs/mail.log).
// ---------------------------------------------------------------------------
kd_define('MAIL_DRIVER', 'log');
kd_define('MAIL_FROM', 'nao-responda@seudominio.com.br');
kd_define('MAIL_FROM_NAME', 'King Decants');
kd_define('SMTP_HOST', 'smtp.seudominio.com.br');
kd_define('SMTP_PORT', 587);
kd_define('SMTP_USER', 'COLOQUE_SEU_USUARIO_SMTP');
kd_define('SMTP_PASS', 'COLOQUE_SUA_SENHA_SMTP');
kd_define('SMTP_SECURE', 'tls');              // 'tls' (587) ou 'ssl' (465)
