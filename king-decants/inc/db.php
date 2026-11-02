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
        http_response_code(500);
        if (APP_ENV !== 'production') {
            exit('Erro ao conectar no banco de dados: ' . htmlspecialchars($e->getMessage()) .
                 '<br>Verifique inc/config.php e se o schema.sql foi importado.');
        }
        exit('Serviço temporariamente indisponível. Tente novamente em instantes.');
    }

    return $pdo;
}
