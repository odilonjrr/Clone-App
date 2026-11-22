<?php
/**
 * King Decants — webhook-infinitepay.php (aviso de pagamento da InfinitePay)
 * ---------------------------------------------------------------------------
 * A URL deste arquivo é enviada automaticamente como "webhook_url" em cada
 * link de pagamento (somente quando APP_URL usa https).
 *
 * A InfinitePay envia um JSON com dados como order_nsu, transaction_nsu,
 * invoice_slug, amount, paid_amount, capture_method e receipt_url.
 *
 * Segurança: o aviso NÃO é tratado como prova de pagamento. Sempre
 * confirmamos com ip_payment_check() antes de marcar o pedido como pago.
 *
 * Respostas: 200 = recebido/processado; 400 = erro (a documentação da
 * InfinitePay indica que, nesse caso, o aviso é reenviado). Responder rápido.
 * ---------------------------------------------------------------------------
 */
define('KD_NO_SESSION', true);
require __DIR__ . '/inc/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('{"success":false}');
}

$raw = file_get_contents('php://input') ?: '';
$body = json_decode($raw, true);
if (!is_array($body)) {
    $body = $_POST;
}

$orderNsu = (string) ($body['order_nsu'] ?? '');
$tx = (string) ($body['transaction_nsu'] ?? '');
$slug = (string) ($body['invoice_slug'] ?? ($body['slug'] ?? ''));

db()->prepare('INSERT INTO webhook_logs (topic, resource_id, signature_ok, payload) VALUES (?, ?, NULL, ?)')
    ->execute(['infinitepay', mb_substr($orderNsu . ($tx ? ' / ' . $tx : ''), 0, 60) ?: null, mb_substr($raw ?: json_encode($body), 0, 60000)]);
$logId = (int) db()->lastInsertId();
app_log('webhook', 'InfinitePay: aviso recebido', ['order_nsu' => $orderNsu, 'transaction_nsu' => $tx]);

$finish = function (int $code, string $message, bool $processed = false) use ($logId) {
    db()->prepare('UPDATE webhook_logs SET processed = ?, message = ? WHERE id = ?')->execute([(int) $processed, mb_substr($message, 0, 255), $logId]);
    http_response_code($code);
    echo json_encode(['success' => $code < 400, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
};

$orderId = ip_order_id_from_nsu($orderNsu);
$order = $orderId ? order_find($orderId) : null;
if (!$order) {
    // Pedido inexistente: não adianta reenviar.
    $finish(200, 'Pedido não encontrado para order_nsu');
}
if (!ip_is_configured()) {
    $finish(400, 'INFINITEPAY_HANDLE não configurado');
}

try {
    $check = ip_payment_check($orderNsu, $tx, $slug);
    if ($check === null) {
        $finish(400, 'Pagamento ainda não localizado na InfinitePay');
    }
    $res = ip_process_payment($order, $check, [
        'transaction_nsu' => $tx,
        'slug'            => $slug,
        'receipt_url'     => (string) ($body['receipt_url'] ?? ''),
    ], 'webhook');
    $finish(200, $res['message'], true);
} catch (Throwable $e) {
    app_log('webhook', 'InfinitePay: erro ao processar', ['error' => $e->getMessage()]);
    $finish(400, 'Erro temporário');
}
