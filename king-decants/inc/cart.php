<?php
/**
 * King Decants — inc/cart.php
 * ---------------------------------------------------------------------------
 * Carrinho híbrido:
 *   - Convidado: $_SESSION['cart'] = [product_id => quantidade]
 *   - Logado:    tabela cart_items (persiste entre dispositivos/sessões)
 *
 * Ao fazer login/registro, login_user() chama cart_merge_session_into_user(),
 * que soma os itens da sessão ao carrinho do usuário e limpa a sessão.
 *
 * Preços NUNCA vêm do navegador: cart_items_detailed() sempre lê o preço atual
 * na tabela products.
 * ---------------------------------------------------------------------------
 */

const CART_MAX_QTY_PER_ITEM = 10;

/** Retorna [product_id => qty] do carrinho atual. */
function cart_raw(): array
{
    $uid = $_SESSION['user_id'] ?? null;
    if ($uid) {
        $stmt = db()->prepare('SELECT product_id, quantity FROM cart_items WHERE user_id = ?');
        $stmt->execute([(int) $uid]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(int) $row['product_id']] = (int) $row['quantity'];
        }
        return $out;
    }
    $cart = $_SESSION['cart'] ?? [];
    return is_array($cart) ? array_map('intval', $cart) : [];
}

/** Grava a quantidade (0 remove). Respeita estoque e limite por item. */
function cart_set(int $productId, int $qty): array
{
    $product = cart_find_product($productId);
    if (!$product) {
        return ['ok' => false, 'error' => 'Produto indisponível.'];
    }
    $limit = min(CART_MAX_QTY_PER_ITEM, (int) $product['stock']);
    if ($qty > 0 && $limit <= 0) {
        return ['ok' => false, 'error' => 'Produto esgotado.'];
    }
    $notice = null;
    if ($qty > $limit) {
        $qty = $limit;
        $notice = "Quantidade ajustada ao limite disponível ({$limit}).";
    }

    $uid = $_SESSION['user_id'] ?? null;
    if ($uid) {
        if ($qty <= 0) {
            db()->prepare('DELETE FROM cart_items WHERE user_id = ? AND product_id = ?')->execute([(int) $uid, $productId]);
        } else {
            db()->prepare('INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)
                           ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)')
                ->execute([(int) $uid, $productId, $qty]);
        }
    } else {
        if ($qty <= 0) {
            unset($_SESSION['cart'][$productId]);
        } else {
            $_SESSION['cart'][$productId] = $qty;
        }
    }
    return ['ok' => true, 'quantity' => max(0, $qty), 'notice' => $notice];
}

/** Soma $qty ao item existente. */
function cart_add(int $productId, int $qty = 1): array
{
    $current = cart_raw()[$productId] ?? 0;
    return cart_set($productId, $current + max(1, $qty));
}

function cart_remove(int $productId): void
{
    cart_set($productId, 0);
}

function cart_clear(): void
{
    $uid = $_SESSION['user_id'] ?? null;
    if ($uid) {
        db()->prepare('DELETE FROM cart_items WHERE user_id = ?')->execute([(int) $uid]);
    }
    $_SESSION['cart'] = [];
}

/** Quantidade total de unidades (para o contador da sacola). */
function cart_count(): int
{
    return array_sum(cart_raw());
}

function cart_find_product(int $productId): ?array
{
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ? AND is_active = 1');
    $stmt->execute([$productId]);
    return $stmt->fetch() ?: null;
}

/**
 * Itens com dados do produto e totais calculados no servidor.
 * Retorna ['items' => [...], 'subtotal' => float, 'count' => int]
 */
function cart_items_detailed(): array
{
    $raw = cart_raw();
    if (!$raw) {
        return ['items' => [], 'subtotal' => 0.0, 'count' => 0];
    }
    $ids = array_keys($raw);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare("SELECT id, sku, name, slug, tagline, price, stock, image_url FROM products WHERE is_active = 1 AND id IN ($placeholders)");
    $stmt->execute($ids);

    $items = [];
    $subtotal = 0.0;
    $count = 0;
    foreach ($stmt->fetchAll() as $p) {
        $qty = $raw[(int) $p['id']];
        $line = round((float) $p['price'] * $qty, 2);
        $items[] = $p + ['quantity' => $qty, 'line_total' => $line];
        $subtotal += $line;
        $count += $qty;
    }
    return ['items' => $items, 'subtotal' => round($subtotal, 2), 'count' => $count];
}

/**
 * Migração do carrinho de convidado -> usuário (chamada no login/registro).
 * Soma as quantidades (respeitando limites) e esvazia o carrinho da sessão.
 */
function cart_merge_session_into_user(int $userId): void
{
    $sessionCart = $_SESSION['cart'] ?? [];
    if (!is_array($sessionCart) || !$sessionCart) {
        return;
    }
    foreach ($sessionCart as $productId => $qty) {
        $product = cart_find_product((int) $productId);
        if (!$product) {
            continue;
        }
        $stmt = db()->prepare('SELECT quantity FROM cart_items WHERE user_id = ? AND product_id = ?');
        $stmt->execute([$userId, (int) $productId]);
        $existing = (int) ($stmt->fetchColumn() ?: 0);
        $newQty = min($existing + (int) $qty, CART_MAX_QTY_PER_ITEM, (int) $product['stock']);
        if ($newQty > 0) {
            db()->prepare('INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)
                           ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)')
                ->execute([$userId, (int) $productId, $newQty]);
        }
    }
    $_SESSION['cart'] = [];
}
