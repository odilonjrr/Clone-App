<?php
/**
 * King Decants — webhook.php (Notificações do Mercado Pago)
 * ---------------------------------------------------------------------------
 * Configure no painel do MP:
 *   Suas integrações -> sua aplicação -> Webhooks -> Configurar notificações
 *   URL (produção e teste): https://SEU-DOMINIO/webhook.php
 *   Eventos: "Pagamentos" (payment). Opcional: "Pedidos comerciais" (merchant_order).
 *   Copie a "Assinatura secreta" para MP_WEBHOOK_SECRET em inc/config.php.
 *
 * Formatos aceitos:
 *   - Webhooks (novo):  POST JSON {"type":"payment","data":{"id":"123"}}
 *                       + query  ?data.id=123&type=payment  (PHP converte para data_id)
 *   - IPN (legado):     ?topic=payment&id=123  ou  ?topic=merchant_order&id=456
 *
 * Passos:
 *   1. Lê topic/id e grava a notificação em webhook_logs (+ storage/logs/webhook.log).
 *   2. Valida a assinatura x-signature (se MP_WEBHOOK_SECRET estiver definido).
 *   3. NUNCA confia no corpo: consulta GET /v1/payments/{id} na API do MP.
 *   4. Atualiza payments e orders (pending -> paid / cancelled) de forma idempotente.
 *   5. Responde 200 rapidamente. Erros internos -> 500 (o MP reenvia depois).
 * ---------------------------------------------------------------------------
 */
define('KD_NO_SESSION', true);   // chamada servidor-a-servidor: não precisa de sessão
require __DIR__ . '/inc/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'GET'], true)) {
    http_response_code(405);
    exit('{"ok":false}');
}

$raw = file_get_contents('php://input') ?: '';
$body = json_decode($raw, true) ?: [];

$topic = (string) ($body['type'] ?? $body['topic'] ?? $_GET['type'] ?? $_GET['topic'] ?? '');
$resourceId = (string) ($body['data']['id'] ?? $_GET['data_id'] ?? $_GET['id'] ?? '');
// IPN legado de merchant_order às vezes manda a URL do recurso em "resource"
if ($resourceId === '' && !empty($body['resource']) && preg_match('#/(\d+)$#', (string) $body['resource'], $m)) {
    $resourceId = $m[1];
}
$resourceId = preg_replace('/[^A-Za-z0-9_\-]/', '', $resourceId);

// Assinatura (apenas para notificações do tipo Webhook que trazem data.id)
$signatureOk = isset($_GET['data_id']) || isset($body['data']['id'])
    ? mp_verify_webhook_signature((string) ($_GET['data_id'] ?? $body['data']['id'] ?? ''))
    : null;

// 1) Log da notificação
db()->prepare('INSERT INTO webhook_logs (topic, resource_id, signature_ok, payload) VALUES (?, ?, ?, ?)')
    ->execute([$topic ?: null, $resourceId ?: null, $signatureOk === null ? null : (int) $signatureOk,
        mb_substr(json_encode(['query' => $_GET, 'body' => $body], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 60000)]);
$logId = (int) db()->lastInsertId();
app_log('webhook', 'Notificação recebida', ['topic' => $topic, 'id' => $resourceId, 'signature' => $signatureOk]);

$finish = function (int $code, string $message, bool $processed = false) use ($logId) {
    db()->prepare('UPDATE webhook_logs SET processed = ?, message = ? WHERE id = ?')->execute([(int) $processed, mb_substr($message, 0, 255), $logId]);
    http_response_code($code);
    echo json_encode(['ok' => $code < 400, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
};

// 2) Assinatura inválida -> rejeita
if ($signatureOk === false) {
    $finish(401, 'Assinatura inválida');
}

if ($resourceId === '' || $topic === '') {
    $finish(200, 'Notificação ignorada (sem topic/id)');
}

if (!mp_is_configured()) {
    $finish(500, 'MP_ACCESS_TOKEN não configurado');
}

// 3 + 4) Consulta a API e processa
try {
    $paymentIds = [];
    if (in_array($topic, ['payment', 'payments'], true)) {
        $paymentIds = [$resourceId];
    } elseif ($topic === 'merchant_order') {
        $paymentIds = mp_get_merchant_order_payment_ids($resourceId);
    } else {
        $finish(200, "Tópico {$topic} ignorado");
    }

    $messages = [];
    foreach ($paymentIds as $pid) {
        $payment = mp_get_payment($pid);
        if (!$payment) {
            // Em sandbox, notificações de teste do painel usam ids fictícios (ex.: 123456).
            $messages[] = "Pagamento {$pid} não encontrado na API";
            continue;
        }
        $res = payment_process_mp($payment, 'webhook');
        $messages[] = "#{$pid}: " . $res['message'];
    }
    $finish(200, implode(' | ', $messages) ?: 'Nada a processar', true);
} catch (Throwable $e) {
    app_log('webhook', 'Erro ao processar', ['error' => $e->getMessage()]);
    $finish(500, 'Erro interno');
}
