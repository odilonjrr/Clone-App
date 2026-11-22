<?php
/**
 * King Decants — webhook-superfrete.php
 * ---------------------------------------------------------------------------
 * Recebe os avisos da SuperFrete (order.released / posted / delivered /
 * cancelled), cadastrados em Admin -> Frete -> "Ativar avisos automáticos".
 *
 * Segurança: o conteúdo do aviso NUNCA é usado para mudar o pedido. Dele só
 * se tira o id da etiqueta; o site então consulta a SuperFrete
 * (GET api/v0/order/info/{id}) com o próprio token e só confia nessa resposta.
 * Assim, um aviso falso não tem efeito (a etiqueta precisa existir no pedido
 * e na conta SuperFrete da loja).
 *
 * Respostas: 200 = recebido; 500 = erro temporário (a SuperFrete pode reenviar).
 * ---------------------------------------------------------------------------
 */
require __DIR__ . '/inc/bootstrap.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('{"ok":false}');
}

$raw = (string) file_get_contents('php://input', false, null, 0, 65536);
$payload = json_decode($raw, true);

/** Procura o id da etiqueta nos formatos mais prováveis do aviso. */
function sf_webhook_order_id($p): string
{
    if (!is_array($p)) {
        return '';
    }
    foreach ([['data', 'id'], ['data', 'order', 'id'], ['order', 'id'], ['data', 'order_id'], ['order_id'], ['id']] as $path) {
        $v = $p;
        foreach ($path as $k) {
            $v = is_array($v) && array_key_exists($k, $v) ? $v[$k] : null;
        }
        if (is_string($v) && preg_match('/^[A-Za-z0-9_-]{6,40}$/', $v)) {
            return $v;
        }
    }
    return '';
}

$sfId = sf_webhook_order_id($payload);
app_log('webhook', 'SuperFrete: aviso recebido', ['event' => is_array($payload) ? ($payload['event'] ?? $payload['type'] ?? null) : null, 'id' => $sfId]);

if ($sfId === '') {
    echo '{"ok":true,"ignored":"sem id"}';
    exit;
}

$st = db()->prepare('SELECT * FROM orders WHERE sf_order_id = ? LIMIT 1');
$st->execute([$sfId]);
$order = $st->fetch();
if (!$order) {
    echo '{"ok":true,"ignored":"etiqueta desconhecida"}';
    exit;
}

try {
    $fresh = sf_sync($order, 'webhook');
    echo json_encode(['ok' => true, 'order' => (int) $fresh['id'], 'status' => $fresh['status']]);
} catch (Throwable $e) {
    app_log('webhook', 'SuperFrete: erro ao sincronizar', ['order' => $order['id'], 'error' => $e->getMessage()]);
    http_response_code(500);
    echo '{"ok":false}';
}
