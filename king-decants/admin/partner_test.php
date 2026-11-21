<?php
/**
 * King Decants — admin/partner_test.php
 * Testa um link da loja parceira (sem salvar): baixa a página e mostra o que
 * foi encontrado (disponibilidade, variações, preço). Só para admin logado.
 */
define('KD_ADMIN_AREA', true);
require dirname(__DIR__) . '/inc/bootstrap.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Método inválido.'], 405);
}
csrf_require();

$url = trim((string) ($_POST['url'] ?? ''));
$variant = clean_string($_POST['variant'] ?? '', 100);
if (!filter_var($url, FILTER_VALIDATE_URL) || !partner_url_valid($url)) {
    json_response(['error' => 'Link inválido. Use o endereço completo (https://...).'], 422);
}

$t = microtime(true);
[$html, $err] = partner_fetch($url);
if ($html === null) {
    json_response(['status' => 'unknown', 'status_label' => 'Parceiro não respondeu', 'error' => $err]);
}
$p = partner_parse($html, $variant);
$labels = ['available' => 'Disponível — a venda seguirá normalmente', 'out' => 'Esgotado — o site mostrará "Sem estoque no momento"', 'unknown' => 'Não foi possível ler o estoque nesta página'];
json_response([
    'status'       => $p['status'],
    'status_label' => $labels[$p['status']] . ' (' . number_format(microtime(true) - $t, 1, ',', '') . ' s)',
    'name'         => $p['name'],
    'price'        => $p['price'] !== null ? money($p['price']) : null,
    'matched'      => $p['matched'],
    'source'       => $p['source'],
    'variants'     => $p['variants'],
    'error'        => $p['status'] === 'unknown'
        ? ($variant !== '' && $p['variants'] ? 'A variação "' . $variant . '" não foi encontrada. Use um dos nomes da lista abaixo.' : 'A página não trouxe a informação de estoque no formato esperado. Me envie o HTML da página para ajustar.')
        : null,
]);
