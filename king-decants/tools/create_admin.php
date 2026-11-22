<?php
/**
 * King Decants — tools/create_admin.php
 * ---------------------------------------------------------------------------
 * Cria um administrador ou redefine a senha de um existente. SOMENTE via CLI:
 *
 *   php tools/create_admin.php "Seu Nome" voce@dominio.com.br "SenhaForte123!"
 *
 * Se o e-mail já existir, a senha (e o nome) são atualizados.
 * A pasta tools/ é bloqueada para acesso web pelo .htaccess.
 * ---------------------------------------------------------------------------
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Somente via linha de comando.');
}

define('KD_NO_SESSION', true);
require dirname(__DIR__) . '/inc/bootstrap.php';

[$script, $name, $email, $password] = array_pad($argv, 4, null);
if (!$name || !$email || !$password) {
    fwrite(STDERR, "Uso: php tools/create_admin.php \"Nome\" email@dominio.com \"Senha\"\n");
    exit(1);
}
$email = mb_strtolower(trim($email));
if (!validate_email($email)) {
    fwrite(STDERR, "E-mail inválido.\n");
    exit(1);
}
if (!validate_password($password)) {
    fwrite(STDERR, "Senha fraca: mínimo 8 caracteres com letras e números.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
db()->prepare('INSERT INTO admin_users (name, email, password_hash) VALUES (?, ?, ?)
               ON DUPLICATE KEY UPDATE name = VALUES(name), password_hash = VALUES(password_hash), is_active = 1')
    ->execute([clean_string($name, 120), $email, $hash]);

echo "Administrador {$email} criado/atualizado com sucesso.\n";
