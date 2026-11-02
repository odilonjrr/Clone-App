<?php
/**
 * King Decants — admin/export.php (Exportação CSV)
 * ---------------------------------------------------------------------------
 *   ?type=orders     [status, q, from, to, customer]  -> pedidos
 *   ?type=customers  [q, ctype=account|guest]         -> clientes
 *
 * - Separador ";" e BOM UTF-8: abre corretamente no Excel em português.
 * - Streaming linha a linha (fputcsv em php://output) com limite de 50.000
 *   linhas para não estourar memória/tempo em hospedagem compartilhada.
 * - Proteção contra "CSV injection": células iniciadas com = + - @ recebem '.
 * - Cada exportação é registrada em admin_logs.
 * ---------------------------------------------------------------------------
 */
define('KD_ADMIN_AREA', true);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/admin_filters.php';
$admin = require_admin();

const EXPORT_LIMIT = 50000;

$type = ($_GET['type'] ?? '') === 'customers' ? 'customers' : 'orders';

/** Neutraliza fórmulas em planilhas. */
function csv_safe($value): string
{
    $value = (string) $value;
    return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
}

if ($type === 'orders') {
    [$whereSql, $params] = admin_orders_filter($_GET);
    $sql = "SELECT o.id, o.created_at, o.status, o.payment_method, o.customer_name, o.customer_email, o.customer_phone, o.customer_cpf,
                   o.cep, o.street, o.number, o.complement, o.district, o.city, o.state,
                   o.subtotal, o.shipping, o.total, o.tracking_code, o.paid_at, o.shipped_at, o.delivered_at,
                   (SELECT GROUP_CONCAT(CONCAT(quantity, 'x ', sku) SEPARATOR ', ') FROM order_items WHERE order_id = o.id) AS items
              FROM orders o WHERE $whereSql ORDER BY o.id DESC LIMIT " . EXPORT_LIMIT;
    $header = ['Pedido', 'Data', 'Status', 'Pagamento', 'Cliente', 'E-mail', 'Telefone', 'CPF', 'CEP', 'Logradouro', 'Número', 'Complemento', 'Bairro', 'Cidade', 'UF', 'Subtotal', 'Frete', 'Total', 'Rastreio', 'Pago em', 'Enviado em', 'Entregue em', 'Itens'];
} else {
    $filter = $_GET;
    $filter['type'] = $_GET['ctype'] ?? '';
    [$whereSql, $params] = admin_customers_filter($filter);
    $sql = "SELECT u.id, u.name, u.email, u.phone, u.cpf, IF(u.is_guest, 'convidado', 'conta') AS tipo, u.created_at,
                   (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS pedidos,
                   (SELECT COALESCE(SUM(total), 0) FROM orders o WHERE o.user_id = u.id AND o.status IN ('paid','shipped','delivered')) AS total_pago
              FROM users u WHERE $whereSql ORDER BY u.id DESC LIMIT " . EXPORT_LIMIT;
    $header = ['ID', 'Nome', 'E-mail', 'Telefone', 'CPF', 'Tipo', 'Cadastro', 'Pedidos', 'Total pago'];
}

$stmt = db()->prepare($sql);
$stmt->execute($params);

admin_log('export_csv', $type, null, http_build_query(array_diff_key($_GET, ['type' => 1])));

$filename = 'kingdecants-' . $type . '-' . date('Ymd-His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM para o Excel reconhecer UTF-8
fputcsv($out, $header, ';', '"', '');
while ($row = $stmt->fetch()) {
    foreach (['subtotal', 'shipping', 'total', 'total_pago'] as $moneyCol) {
        if (isset($row[$moneyCol])) {
            $row[$moneyCol] = number_format((float) $row[$moneyCol], 2, ',', '');
        }
    }
    fputcsv($out, array_map('csv_safe', array_values($row)), ';', '"', '');
}
fclose($out);
exit;
