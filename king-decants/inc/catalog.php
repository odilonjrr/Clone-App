<?php
/**
 * King Decants — inc/catalog.php
 * ---------------------------------------------------------------------------
 * Dados da página de produto:
 *   - galeria (foto principal + product_images)
 *   - pirâmide olfativa (topo / coração / fundo) com imagem por nível
 *   - avaliações de COMPRADORES VERIFICADOS (média e total reais, nunca editáveis)
 *   - favoritos (wishlists)
 * ---------------------------------------------------------------------------
 */

/** URLs da galeria: a foto principal primeiro, depois as extras na ordem definida. */
function product_gallery(array $product): array
{
    $images = [product_image_src((string) $product['image_url'])];
    $stmt = db()->prepare('SELECT id, image_url FROM product_images WHERE product_id = ? ORDER BY sort_order, id');
    $stmt->execute([$product['id']]);
    foreach ($stmt->fetchAll() as $row) {
        $images[] = product_image_src($row['image_url']);
    }
    return $images;
}

/** Fotos extras (para o admin). */
function product_extra_images(int $productId): array
{
    $stmt = db()->prepare('SELECT id, image_url, sort_order FROM product_images WHERE product_id = ? ORDER BY sort_order, id');
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

/**
 * Pirâmide olfativa pronta para exibir. Níveis sem notas são omitidos.
 * Imagem: a enviada no admin ou a ilustração padrão de assets/img/notes/.
 */
function product_pyramid(array $product): array
{
    $levels = [
        'top'   => 'Topo',
        'heart' => 'Coração',
        'base'  => 'Fundo',
    ];
    $out = [];
    foreach ($levels as $key => $label) {
        $notes = trim((string) ($product['notes_' . $key] ?? ''));
        if ($notes === '') {
            continue;
        }
        $img = trim((string) ($product['notes_' . $key . '_image'] ?? ''));
        $out[] = [
            'key'   => $key,
            'label' => $label,
            'notes' => $notes,
            'image' => $img !== '' ? product_image_src($img) : url('assets/img/notes/' . $key . '.svg'),
        ];
    }
    return $out;
}

/** Média e quantidade de avaliações visíveis. */
function product_rating(int $productId): array
{
    $stmt = db()->prepare('SELECT COUNT(*) AS total, COALESCE(AVG(rating), 0) AS avg FROM product_reviews WHERE product_id = ? AND is_visible = 1');
    $stmt->execute([$productId]);
    $r = $stmt->fetch();
    return ['count' => (int) $r['total'], 'avg' => round((float) $r['avg'], 1)];
}

function product_reviews(int $productId, int $limit = 10): array
{
    $stmt = db()->prepare(
        "SELECT r.rating, r.comment, r.created_at, u.name
           FROM product_reviews r JOIN users u ON u.id = r.user_id
          WHERE r.product_id = ? AND r.is_visible = 1
          ORDER BY r.id DESC LIMIT " . max(1, (int) $limit)
    );
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

/**
 * Só avalia quem COMPROU com a conta: pedido pago/enviado/entregue, feito logado
 * (não como convidado), contendo o produto. Evita avaliações falsas.
 */
function user_can_review(int $userId, int $productId): bool
{
    $stmt = db()->prepare(
        "SELECT 1 FROM orders o JOIN order_items i ON i.order_id = o.id
          WHERE o.user_id = ? AND o.is_guest_order = 0 AND i.product_id = ?
            AND o.status IN ('paid','shipped','delivered') LIMIT 1"
    );
    $stmt->execute([$userId, $productId]);
    return (bool) $stmt->fetchColumn();
}

function user_review(int $userId, int $productId): ?array
{
    $stmt = db()->prepare('SELECT rating, comment FROM product_reviews WHERE user_id = ? AND product_id = ?');
    $stmt->execute([$userId, $productId]);
    return $stmt->fetch() ?: null;
}

function wishlist_has(int $userId, int $productId): bool
{
    $stmt = db()->prepare('SELECT 1 FROM wishlists WHERE user_id = ? AND product_id = ?');
    $stmt->execute([$userId, $productId]);
    return (bool) $stmt->fetchColumn();
}

/** Estrelas douradas (aceita frações, ex.: 4.6). */
function stars_html(float $rating, string $size = 'h-5 w-5'): string
{
    $star = '<svg class="shrink-0 ' . e($size) . '" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2.8l2.8 5.9 6.4.8-4.7 4.4 1.2 6.4L12 17.2l-5.7 3.1 1.2-6.4-4.7-4.4 6.4-.8z"/></svg>';
    $pct = max(0, min(100, $rating / 5 * 100));
    return '<span class="relative inline-flex" aria-label="' . e(number_format($rating, 1, ',', '')) . ' de 5 estrelas">'
        . '<span class="flex text-white/15">' . str_repeat($star, 5) . '</span>'
        . '<span class="absolute inset-y-0 left-0 flex overflow-hidden text-gold-400" style="width:' . $pct . '%">' . str_repeat($star, 5) . '</span>'
        . '</span>';
}
