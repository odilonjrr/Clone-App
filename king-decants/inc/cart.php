<?php
/**
 * King Decants — inc/cart.php
 * ---------------------------------------------------------------------------
 * Carrinho híbrido com suporte a TAMANHOS (variações):
 *   - Convidado: $_SESSION['cart'] = ["produto:variação" => quantidade]
 *                (variação 0 = produto sem tamanhos)
 *   - Logado:    tabela cart_items (user_id, product_id, variant_id)
 *
 * Ao fazer login/registro, login_user() chama cart_merge_session_into_user().
 * Preços e estoque NUNCA vêm do navegador: cart_items_detailed() sempre lê o
 * valor atual do produto ou do tamanho no banco.
 * ---------------------------------------------------------------------------
 */

const CART_MAX_QTY_PER_ITEM = 10;

/** Chave do item: "12:3" (produto 12, tamanho 3) ou "12:0" (sem tamanho). */
function cart_key(int $productId, int $variantId = 0): string
{
    return $productId . ':' . $variantId;
}

/** "12:3" -> [12, 3]. Aceita chaves antigas só com o id do produto. */
function cart_parse_key($key): array
{
    $parts = explode(':', (string) $key);
    return [(int) $parts[0], (int) ($parts[1] ?? 0)];
}

/** Retorna ["produto:tamanho" => qty] do carrinho atual. */
function cart_raw(): array
{
    $uid = $_SESSION['user_id'] ?? null;
    if ($uid) {
        $stmt = db()->prepare('SELECT product_id, variant_id, quantity FROM cart_items WHERE user_id = ?');
        $stmt->execute([(int) $uid]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[cart_key((int) $row['product_id'], (int) $row['variant_id'])] = (int) $row['quantity'];
        }
        return $out;
    }
    $out = [];
    foreach ((array) ($_SESSION['cart'] ?? []) as $key => $qty) {
        [$p, $v] = cart_parse_key($key);
        if ($p > 0) {
            $out[cart_key($p, $v)] = (int) $qty;
        }
    }
    return $out;
}

function cart_find_product(int $productId): ?array
{
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ? AND is_active = 1');
    $stmt->execute([$productId]);
    return $stmt->fetch() ?: null;
}

/** Tamanhos ativos do produto (ordem de exibição). */
function product_variants(int $productId, bool $onlyActive = true): array
{
    $stmt = db()->prepare('SELECT * FROM product_variants WHERE product_id = ?' . ($onlyActive ? ' AND is_active = 1' : '') . ' ORDER BY sort_order, volume_ml, id');
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

/** Tamanho ativo que pertence ao produto, ou null. */
function cart_find_variant(int $productId, int $variantId): ?array
{
    $stmt = db()->prepare('SELECT * FROM product_variants WHERE id = ? AND product_id = ? AND is_active = 1');
    $stmt->execute([$variantId, $productId]);
    return $stmt->fetch() ?: null;
}

/** Quantidade de tamanhos ativos (0 = produto simples). */
function product_variant_count(int $productId): int
{
    static $cache = [];
    if (!isset($cache[$productId])) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM product_variants WHERE product_id = ? AND is_active = 1');
        $stmt->execute([$productId]);
        $cache[$productId] = (int) $stmt->fetchColumn();
    }
    return $cache[$productId];
}

/**
 * Grava a quantidade (0 remove). Valida produto/tamanho e respeita estoque.
 * Produto COM tamanhos exige um tamanho escolhido.
 */
function cart_set(int $productId, int $qty, int $variantId = 0): array
{
    $product = cart_find_product($productId);
    if (!$product) {
        return ['ok' => false, 'error' => 'Produto indisponível.'];
    }
    $variant = null;
    if ($variantId > 0) {
        $variant = cart_find_variant($productId, $variantId);
        if (!$variant) {
            return ['ok' => false, 'error' => 'Tamanho indisponível.'];
        }
    } elseif ($qty > 0 && product_variant_count($productId) > 0) {
        return ['ok' => false, 'error' => 'Escolha o tamanho antes de adicionar.'];
    }

    $stock = $variant ? (int) $variant['stock'] : (int) $product['stock'];
    $limit = min(CART_MAX_QTY_PER_ITEM, $stock);
    if ($qty > 0 && $limit <= 0) {
        return ['ok' => false, 'error' => $variant ? 'Este tamanho está esgotado.' : 'Produto esgotado.'];
    }
    $notice = null;
    if ($qty > $limit) {
        $qty = $limit;
        $notice = "Quantidade ajustada ao limite disponível ({$limit}).";
    }

    $uid = $_SESSION['user_id'] ?? null;
    if ($uid) {
        if ($qty <= 0) {
            db()->prepare('DELETE FROM cart_items WHERE user_id = ? AND product_id = ? AND variant_id = ?')->execute([(int) $uid, $productId, $variantId]);
        } else {
            db()->prepare('INSERT INTO cart_items (user_id, product_id, variant_id, quantity) VALUES (?, ?, ?, ?)
                           ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)')
                ->execute([(int) $uid, $productId, $variantId, $qty]);
        }
    } else {
        $cart = cart_raw();
        $key = cart_key($productId, $variantId);
        if ($qty <= 0) {
            unset($cart[$key]);
        } else {
            $cart[$key] = $qty;
        }
        $_SESSION['cart'] = $cart;
    }
    return ['ok' => true, 'quantity' => max(0, $qty), 'notice' => $notice];
}

/** Soma $qty ao item existente. */
function cart_add(int $productId, int $qty = 1, int $variantId = 0): array
{
    $current = cart_raw()[cart_key($productId, $variantId)] ?? 0;
    return cart_set($productId, $current + max(1, $qty), $variantId);
}

function cart_remove(int $productId, int $variantId = 0): void
{
    $uid = $_SESSION['user_id'] ?? null;
    if ($uid) {
        db()->prepare('DELETE FROM cart_items WHERE user_id = ? AND product_id = ? AND variant_id = ?')->execute([(int) $uid, $productId, $variantId]);
        return;
    }
    $cart = cart_raw();
    unset($cart[cart_key($productId, $variantId)]);
    $_SESSION['cart'] = $cart;
}

function cart_clear(): void
{
    $uid = $_SESSION['user_id'] ?? null;
    if ($uid) {
        db()->prepare('DELETE FROM cart_items WHERE user_id = ?')->execute([(int) $uid]);
    }
    $_SESSION['cart'] = [];
}

/** Quantidade total de unidades (contador da sacola). */
function cart_count(): int
{
    return array_sum(cart_raw());
}

/**
 * Itens com dados do produto/tamanho e totais calculados no servidor.
 * Cada item: id (produto), variant_id, variant_label, key, sku, name, price,
 * stock, quantity, line_total, slug, tagline, image_url.
 * Itens inválidos (produto/tamanho removido, ou produto que passou a ter
 * tamanhos e o item não tem) são descartados.
 * Retorna ['items' => [...], 'subtotal' => float, 'count' => int]
 */
function cart_items_detailed(): array
{
    $raw = cart_raw();
    $items = [];
    $subtotal = 0.0;
    $count = 0;
    foreach ($raw as $key => $qty) {
        [$pid, $vid] = cart_parse_key($key);
        $p = cart_find_product($pid);
        if (!$p || $qty <= 0) {
            continue;
        }
        $variant = null;
        if ($vid > 0) {
            $variant = cart_find_variant($pid, $vid);
            if (!$variant) {
                continue;
            }
        } elseif (product_variant_count($pid) > 0) {
            continue;
        }
        $price = (float) ($variant['price'] ?? $p['price']);
        $line = round($price * $qty, 2);
        $items[] = [
            'id'            => (int) $p['id'],
            'variant_id'    => $vid,
            'variant_label' => $variant['label'] ?? null,
            'key'           => $key,
            'sku'           => $variant['sku'] ?? $p['sku'],
            'name'          => $p['name'],
            'slug'          => $p['slug'],
            'tagline'       => $p['tagline'],
            'image_url'     => $p['image_url'],
            'price'         => $price,
            'stock'         => (int) ($variant['stock'] ?? $p['stock']),
            'quantity'      => $qty,
            'line_total'    => $line,
        ];
        $subtotal += $line;
        $count += $qty;
    }
    return ['items' => $items, 'subtotal' => round($subtotal, 2), 'count' => $count];
}

/**
 * Migração do carrinho de convidado -> usuário (login/registro).
 * Soma as quantidades (respeitando limites) e esvazia o carrinho da sessão.
 */
function cart_merge_session_into_user(int $userId): void
{
    $sessionCart = (array) ($_SESSION['cart'] ?? []);
    if (!$sessionCart) {
        return;
    }
    foreach ($sessionCart as $key => $qty) {
        [$pid, $vid] = cart_parse_key($key);
        $product = cart_find_product($pid);
        if (!$product) {
            continue;
        }
        $variant = $vid > 0 ? cart_find_variant($pid, $vid) : null;
        if ($vid > 0 && !$variant) {
            continue;
        }
        $stock = (int) ($variant['stock'] ?? $product['stock']);
        $stmt = db()->prepare('SELECT quantity FROM cart_items WHERE user_id = ? AND product_id = ? AND variant_id = ?');
        $stmt->execute([$userId, $pid, $vid]);
        $existing = (int) ($stmt->fetchColumn() ?: 0);
        $newQty = min($existing + (int) $qty, CART_MAX_QTY_PER_ITEM, $stock);
        if ($newQty > 0) {
            db()->prepare('INSERT INTO cart_items (user_id, product_id, variant_id, quantity) VALUES (?, ?, ?, ?)
                           ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)')
                ->execute([$userId, $pid, $vid, $newQty]);
        }
    }
    $_SESSION['cart'] = [];
}

/**
 * Recalcula preço "a partir de" (menor preço) e estoque total do produto a
 * partir dos tamanhos ativos. Mantém vitrine, buscas e relatórios coerentes.
 */
function product_sync_from_variants(int $productId): void
{
    $stmt = db()->prepare('SELECT MIN(price) AS min_price, COALESCE(SUM(GREATEST(stock, 0)), 0) AS total_stock, COUNT(*) AS n
                             FROM product_variants WHERE product_id = ? AND is_active = 1');
    $stmt->execute([$productId]);
    $r = $stmt->fetch();
    if ((int) $r['n'] > 0) {
        db()->prepare('UPDATE products SET price = ?, stock = ? WHERE id = ?')->execute([$r['min_price'], (int) $r['total_stock'], $productId]);
    }
}

/**
 * Preço para vitrines: com mais de um preço entre os tamanhos mostra
 * "a partir de R$ X" (products.price guarda o menor preço dos tamanhos).
 */
function product_price_label(array $product): string
{
    $pid = (int) $product['id'];
    if (product_variant_count($pid) > 1) {
        $stmt = db()->prepare('SELECT COUNT(DISTINCT price) FROM product_variants WHERE product_id = ? AND is_active = 1');
        $stmt->execute([$pid]);
        if ((int) $stmt->fetchColumn() > 1) {
            return 'a partir de ' . money($product['price']);
        }
    }
    return money($product['price']);
}
