<?php
/**
 * King Decants — inc/db.php
 * ---------------------------------------------------------------------------
 * Conexão PDO única (singleton) com MySQL/MariaDB.
 *
 * Pontos de atenção:
 *  - ERRMODE_EXCEPTION: qualquer erro SQL vira exceção (nada falha em silêncio).
 *  - EMULATE_PREPARES = false: prepared statements reais no servidor MySQL.
 *  - charset utf8mb4: suporta acentos e emojis corretamente.
 *  - TODAS as queries do projeto usam prepare()/execute() com parâmetros.
 * ---------------------------------------------------------------------------
 */

require_once __DIR__ . '/config.php';

/**
 * Retorna a instância compartilhada do PDO.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST,
        DB_PORT,
        DB_NAME
    );

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        // Mantém o fuso do MySQL alinhado ao do PHP (America/Sao_Paulo = -03:00).
        $pdo->exec("SET time_zone = '" . (new DateTime('now', new DateTimeZone(APP_TIMEZONE)))->format('P') . "'");
    } catch (PDOException $e) {
        // Nunca exponha a mensagem real (pode conter usuário/host) em produção.
        error_log('[KingDecants] Falha na conexão com o banco: ' . $e->getMessage());
        // Diagnóstico legível no Gerenciador de Arquivos (pasta protegida pelo .htaccess).
        // Nunca grava a senha: só host, banco, usuário, a origem da configuração e o erro do MySQL.
        $hints = [];
        if (str_starts_with((string) DB_NAME, 'u000000000') || str_starts_with((string) DB_USER, 'u000000000')) {
            $hints[] = 'DB_NAME/DB_USER ainda com valores de EXEMPLO (u000000000_...)';
        }
        if (str_contains($e->getMessage(), 'to database')) {
            $hints[] = 'nome do banco incorreto OU o usuário não está vinculado a esse banco (confira DB_NAME no hPanel -> Bancos de dados)';
        } elseif (str_contains($e->getMessage(), 'Access denied')) {
            $hints[] = 'usuário ou SENHA do banco incorretos (confira DB_USER e DB_PASS; se trocou a senha no hPanel, atualize o arquivo)';
        } elseif (str_contains($e->getMessage(), 'Unknown database')) {
            $hints[] = 'nome do banco incorreto (confira DB_NAME na lista de bancos do hPanel)';
        } elseif (str_contains($e->getMessage(), 'getaddrinfo') || str_contains($e->getMessage(), 'Connection refused')) {
            $hints[] = 'servidor do banco inacessível (na Hostinger use DB_HOST = localhost)';
        }
        @file_put_contents(dirname(__DIR__) . '/storage/logs/db-errors.log', sprintf(
            "[%s] Falha ao conectar no banco | host=%s banco=%s usuario=%s | config=%s | erro=%s%s\n",
            date('Y-m-d H:i:s'), DB_HOST, DB_NAME, DB_USER, defined('KD_CONFIG_SOURCE') ? KD_CONFIG_SOURCE : '?',
            $e->getMessage(), $hints ? ' | PROVÁVEL CAUSA: ' . implode('; ', $hints) : ''
        ), FILE_APPEND | LOCK_EX);
        http_response_code(500);
        if (APP_ENV !== 'production') {
            exit('Erro ao conectar no banco de dados: ' . htmlspecialchars($e->getMessage()) .
                 '<br>Verifique inc/config.php e se o schema.sql foi importado.');
        }
        exit('Serviço temporariamente indisponível. Tente novamente em instantes.'
            . '<!-- Administrador: veja storage/logs/db-errors.log -->');
    }

    return $pdo;
}
