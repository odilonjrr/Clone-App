<?php
/**
 * King Decants — wishlist.php (Favoritos)
 * ---------------------------------------------------------------------------
 * POST product_id (+ CSRF): adiciona/remove o produto dos favoritos do cliente
 * logado. Responde JSON para o botão da página de produto (assets/js/app.js);
 * sem JavaScript, redireciona de volta.
 * A lista de favoritos aparece em account.php.
 * ---------------------------------------------------------------------------
 */
require __DIR__ . '/inc/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('account.php?secao=favoritos'));
}
csrf_require();

$user = current_user();
if (!$user) {
    if (wants_json()) {
        json_response(['ok' => false, 'error' => 'Entre na sua conta para salvar favoritos.'], 401);
    }
    redirect(url('auth/login.php'));
}

$productId = int_param($_POST, 'product_id');
$product = cart_find_product($productId);
if (!$product) {
    wants_json() ? json_response(['ok' => false, 'error' => 'Produto indisponível.'], 404) : redirect(url('index.php'));
}

if (wishlist_has((int) $user['id'], $productId)) {
    db()->prepare('DELETE FROM wishlists WHERE user_id = ? AND product_id = ?')->execute([$user['id'], $productId]);
    $in = false;
    $message = 'Removido dos favoritos.';
} else {
    db()->prepare('INSERT IGNORE INTO wishlists (user_id, product_id) VALUES (?, ?)')->execute([$user['id'], $productId]);
    $in = true;
    $message = 'Adicionado aos favoritos.';
}

if (wants_json()) {
    json_response(['ok' => true, 'in_wishlist' => $in, 'message' => $message]);
}
flash('success', $message);
redirect(($_POST['back'] ?? '') === 'account' ? url('account.php?secao=favoritos') : url('product.php?slug=' . urlencode($product['slug'])));
