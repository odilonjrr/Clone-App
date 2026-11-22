<?php
/**
 * King Decants — partner-sync.php
 * ---------------------------------------------------------------------------
 * Verificação automática do estoque das lojas parceiras, para usar no
 * "Cron Jobs" da Hostinger (ex.: a cada hora):
 *   curl -s "https://SEU-DOMINIO/partner-sync.php?key=CHAVE"
 * A CHAVE aparece em Admin -> Produtos -> Loja parceira. Sem a chave certa,
 * responde 403 (ninguém de fora consegue disparar consultas em massa).
 * ---------------------------------------------------------------------------
 */
require __DIR__ . '/inc/bootstrap.php';
header('Content-Type: application/json');

$key = (string) setting('partner_sync_key', '');
if ($key === '' || !hash_equals($key, (string) ($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit('{"ok":false}');
}
@set_time_limit(120);
$c = partner_sync_all();
app_log('partner', 'Verificação agendada', $c);
echo json_encode(['ok' => true] + $c);
