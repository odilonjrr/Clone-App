<?php
/**
 * King Decants — inc/admin_filters.php
 * ---------------------------------------------------------------------------
 * Monta cláusulas WHERE reutilizadas pelas listagens do admin e pelo export
 * CSV, garantindo que "o que você vê é o que você exporta".
 * Sempre retornam [string $whereSql, array $params] para prepared statements.
 * ---------------------------------------------------------------------------
 */

/** Filtros de pedidos: status, busca (nº, nome, e-mail, CPF, telefone), período. */
function admin_orders_filter(array $q): array
{
    $where = ['1=1'];
    $params = [];

    $status = $q['status'] ?? '';
    if (isset(order_statuses()[$status])) {
        $where[] = 'o.status = ?';
        $params[] = $status;
    }

    $term = clean_string($q['q'] ?? '', 100);
    if ($term !== '') {
        $digits = only_digits($term);
        $sub = ['o.customer_name LIKE ?', 'o.customer_email LIKE ?'];
        $params[] = '%' . $term . '%';
        $params[] = '%' . $term . '%';
        if ($digits !== '') {
            $sub[] = 'o.customer_cpf LIKE ?';
            $sub[] = 'o.customer_phone LIKE ?';
            $params[] = $digits . '%';
            $params[] = '%' . $digits . '%';
            if (strlen($digits) <= 9) {
                $sub[] = 'o.id = ?';
                $params[] = (int) $digits;
            }
        }
        $where[] = '(' . implode(' OR ', $sub) . ')';
    }

    if (!empty($q['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $q['from'])) {
        $where[] = 'o.created_at >= ?';
        $params[] = $q['from'] . ' 00:00:00';
    }
    if (!empty($q['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $q['to'])) {
        $where[] = 'o.created_at <= ?';
        $params[] = $q['to'] . ' 23:59:59';
    }
    if (!empty($q['customer'])) {
        $where[] = 'o.user_id = ?';
        $params[] = (int) $q['customer'];
    }
    return [implode(' AND ', $where), $params];
}

/** Filtros de clientes: busca por nome, e-mail, CPF ou telefone; tipo (conta/convidado). */
function admin_customers_filter(array $q): array
{
    $where = ['1=1'];
    $params = [];
    $term = clean_string($q['q'] ?? '', 100);
    if ($term !== '') {
        $digits = only_digits($term);
        $sub = ['u.name LIKE ?', 'u.email LIKE ?'];
        $params[] = '%' . $term . '%';
        $params[] = '%' . $term . '%';
        if ($digits !== '') {
            $sub[] = 'u.cpf LIKE ?';
            $sub[] = 'u.phone LIKE ?';
            $params[] = $digits . '%';
            $params[] = '%' . $digits . '%';
        }
        $where[] = '(' . implode(' OR ', $sub) . ')';
    }
    if (($q['type'] ?? '') === 'account') {
        $where[] = 'u.is_guest = 0';
    } elseif (($q['type'] ?? '') === 'guest') {
        $where[] = 'u.is_guest = 1';
    }
    return [implode(' AND ', $where), $params];
}
