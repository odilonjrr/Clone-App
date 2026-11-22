<?php
/**
 * King Decants — inc/partner.php
 * ---------------------------------------------------------------------------
 * Estoque da LOJA PARCEIRA (Nuvemshop) lido da página pública do produto.
 *
 * Cadastro (Admin -> Produtos -> editar): "Link na loja parceira" e, se o
 * produto lá tiver variações, "Variação no parceiro" (ex.: 100 ml).
 *
 * Como a disponibilidade é lida (em ordem):
 *   1. data-variants='[...]' — JSON das variações nos temas Nuvemshop
 *      (campos "available", "stock", "option0..2", "id", "price_number").
 *   2. JSON-LD schema.org Product -> offers.availability (InStock/OutOfStock).
 *   3. <meta property="product:availability"> / og:availability.
 * Se nada for encontrado o resultado é "unknown" (o admin vê no "Testar link").
 *
 * Quando é consultado:
 *   - ao clicar em "Adicionar à sacola" (o botão mostra "Buscando estoque…");
 *   - ao criar o pedido no checkout (usa o resultado guardado, se recente);
 *   - em Admin -> Produtos -> "Verificar estoque dos parceiros" ou pela URL
 *     partner-sync.php?key=... (tarefa agendada / cron da Hostinger).
 * O resultado fica guardado no produto (partner_status / partner_checked_at)
 * por alguns minutos para não sobrecarregar o site parceiro.
 *
 * Parceiro sem resposta (Admin -> Produtos -> configuração, partner_fail_mode):
 *   'allow' (padrão): vende se a última leitura conhecida não era "esgotado";
 *                     o pedido recebe a nota "estoque do parceiro não confirmado".
 *   'block':          não vende sem confirmação.
 * ---------------------------------------------------------------------------
 */

const PARTNER_TIMEOUT = 6;              // segundos por consulta
const PARTNER_MAX_BYTES = 2500000;      // corta páginas maiores que ~2,5 MB

function partner_cache_minutes(): int
{
    return max(1, min(1440, (int) setting('partner_cache_minutes', '10')));
}

function partner_fail_mode(): string
{
    return setting('partner_fail_mode', 'allow') === 'block' ? 'block' : 'allow';
}

/** URL aceitável: http(s), host público (bloqueia endereços internos/rede local). */
function partner_url_valid(string $url): bool
{
    $p = parse_url($url);
    if (!$p || !in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true) || empty($p['host'])) {
        return false;
    }
    $host = strtolower($p['host']);
    if (getenv('KD_PARTNER_ALLOW_LOCAL') === '1') {
        return true; // somente para testes locais
    }
    if ($host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
        return false;
    }
    $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
    if (!$ips) {
        return true; // DNS indisponível agora: a consulta simplesmente falhará
    }
    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
    }
    return true;
}

/** Baixa a página do produto. Retorna [html, erro]. */
function partner_fetch(string $url): array
{
    if (!function_exists('curl_init')) {
        return [null, 'Extensão cURL indisponível no servidor.'];
    }
    if (!partner_url_valid($url)) {
        return [null, 'Link inválido.'];
    }
    $buf = '';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_TIMEOUT        => PARTNER_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_ENCODING       => '',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => [
            'User-Agent: Mozilla/5.0 (compatible; KingDecantsStockCheck/1.0; +' . absolute_url('') . ')',
            'Accept: text/html,application/xhtml+xml',
            'Accept-Language: pt-BR,pt;q=0.9',
        ],
        CURLOPT_WRITEFUNCTION  => function ($ch, $chunk) use (&$buf) {
            $buf .= $chunk;
            return strlen($buf) > PARTNER_MAX_BYTES ? 0 : strlen($chunk);
        },
    ]);
    curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($status === 404 || $status === 410) {
        return [null, 'Produto não encontrado no parceiro (HTTP ' . $status . ').'];
    }
    if ($buf === '' || $status >= 400 || $status === 0) {
        return [null, 'Parceiro não respondeu' . ($status ? " (HTTP {$status})" : '') . ($err && !str_contains($err, 'write') ? ': ' . $err : '') . '.'];
    }
    return [$buf, null];
}

/** Normaliza texto para comparar variações ("100 ML" ~ "100ml"). */
function partner_norm(string $s): string
{
    $s = mb_strtolower(trim($s));
    return preg_replace('/[^a-z0-9]+/u', '', strtr($s, ['á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c']));
}

/**
 * Lê a disponibilidade no HTML.
 * Retorna ['status' => available|out|unknown, 'source', 'name', 'price', 'variants' => [[label, available, stock, id]], 'matched' => label|null]
 */
function partner_parse(string $html, string $variantHint = ''): array
{
    $res = ['status' => 'unknown', 'source' => null, 'name' => null, 'price' => null, 'variants' => [], 'matched' => null];

    if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)/i', $html, $m)) {
        $res['name'] = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    } elseif (preg_match('/<title>([^<]+)/i', $html, $m)) {
        $res['name'] = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    // 1) data-variants (Nuvemshop)
    if (preg_match('/data-variants\s*=\s*(["\'])(.*?)\1/s', $html, $m)) {
        $json = json_decode(html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
        if (is_array($json) && $json) {
            foreach ($json as $v) {
                if (!is_array($v)) {
                    continue;
                }
                $label = trim(implode(' / ', array_filter([(string) ($v['option0'] ?? ''), (string) ($v['option1'] ?? ''), (string) ($v['option2'] ?? '')], 'strlen')));
                $stock = $v['stock'] ?? null;
                $avail = array_key_exists('available', $v)
                    ? filter_var($v['available'], FILTER_VALIDATE_BOOLEAN)
                    : ($stock === null || (int) $stock > 0);
                if ($stock !== null && is_numeric($stock) && (int) $stock <= 0) {
                    $avail = false;
                }
                $res['variants'][] = [
                    'label'     => $label !== '' ? $label : 'Única',
                    'available' => $avail,
                    'stock'     => is_numeric($stock) ? (int) $stock : null,
                    'id'        => (string) ($v['id'] ?? ''),
                    'price'     => isset($v['price_number']) ? (float) $v['price_number'] : null,
                ];
            }
            if ($res['variants']) {
                $res['source'] = 'data-variants';
                $pick = null;
                $hint = partner_norm($variantHint);
                if ($hint !== '') {
                    foreach ($res['variants'] as $v) {
                        if ($v['id'] === trim($variantHint) || partner_norm($v['label']) === $hint || ($hint !== '' && str_contains(partner_norm($v['label']), $hint))) {
                            $pick = $v;
                            break;
                        }
                    }
                }
                if ($pick) {
                    $res['matched'] = $pick['label'];
                    $res['status'] = $pick['available'] ? 'available' : 'out';
                    $res['price'] = $pick['price'];
                } elseif ($hint !== '') {
                    $res['status'] = 'unknown'; // variação informada não existe na página
                } else {
                    $any = array_values(array_filter($res['variants'], fn($v) => $v['available']));
                    $res['status'] = $any ? 'available' : 'out';
                    $res['price'] = ($any[0] ?? $res['variants'][0])['price'];
                }
                return $res;
            }
        }
    }

    // 2) JSON-LD schema.org
    if (preg_match_all('/<script[^>]+application\/ld\+json[^>]*>(.*?)<\/script>/si', $html, $all)) {
        foreach ($all[1] as $block) {
            $data = json_decode(trim($block), true);
            if (!is_array($data)) {
                continue;
            }
            $nodes = isset($data['@graph']) ? $data['@graph'] : (array_is_list($data) ? $data : [$data]);
            foreach ($nodes as $node) {
                if (!is_array($node) || !str_contains(strtolower(json_encode($node['@type'] ?? '')), 'product')) {
                    continue;
                }
                $offers = $node['offers'] ?? [];
                $offers = isset($offers['@type']) || isset($offers['availability']) ? [$offers] : (array) $offers;
                $states = [];
                foreach ($offers as $o) {
                    if (is_array($o) && isset($o['availability'])) {
                        $states[] = stripos((string) $o['availability'], 'instock') !== false || stripos((string) $o['availability'], 'limitedavailability') !== false;
                        $res['price'] = $res['price'] ?? (isset($o['price']) ? (float) $o['price'] : null);
                    }
                }
                if ($states) {
                    $res['source'] = 'json-ld';
                    $res['name'] = $res['name'] ?? ($node['name'] ?? null);
                    $res['status'] = in_array(true, $states, true) ? 'available' : 'out';
                    return $res;
                }
            }
        }
    }

    // 3) meta tags
    if (preg_match('/<meta[^>]+(?:property|name)=["\'](?:product:availability|og:availability)["\'][^>]+content=["\']([^"\']+)/i', $html, $m)) {
        $v = strtolower($m[1]);
        $res['source'] = 'meta';
        $res['status'] = (str_contains($v, 'out') || str_contains($v, 'oos') || str_contains($v, 'sem')) ? 'out' : (str_contains($v, 'in') ? 'available' : 'unknown');
    }
    return $res;
}

/**
 * Consulta (ou usa o resultado guardado) e atualiza o produto.
 * Retorna ['status' => available|out|unknown, 'checked_at', 'from_cache' => bool, 'error' => ?string, 'parsed' => ?array]
 */
function partner_check(array $product, bool $force = false): array
{
    $url = trim((string) ($product['partner_url'] ?? ''));
    if ($url === '') {
        return ['status' => 'none', 'from_cache' => true, 'error' => null, 'parsed' => null, 'checked_at' => null];
    }
    $fresh = !empty($product['partner_checked_at'])
        && strtotime((string) $product['partner_checked_at']) > time() - partner_cache_minutes() * 60
        && in_array($product['partner_status'], ['available', 'out'], true);
    if ($fresh && !$force) {
        return ['status' => $product['partner_status'], 'from_cache' => true, 'error' => null, 'parsed' => null, 'checked_at' => $product['partner_checked_at']];
    }

    [$html, $err] = partner_fetch($url);
    $parsed = $html !== null ? partner_parse($html, (string) ($product['partner_variant'] ?? '')) : null;
    $status = $parsed['status'] ?? 'unknown';
    if ($parsed && $status === 'unknown') {
        $err = 'Não foi possível ler o estoque nessa página' . (($product['partner_variant'] ?? '') !== '' ? ' (confira a variação informada)' : '') . '.';
    }
    if (in_array($status, ['available', 'out'], true)) {
        // Horário do PHP (mesmo fuso usado na comparação do cache; o relógio do MySQL pode estar em UTC)
        db()->prepare('UPDATE products SET partner_status = ?, partner_checked_at = ?, partner_price = ? WHERE id = ?')
            ->execute([$status, date('Y-m-d H:i:s'), $parsed['price'] ?? null, $product['id']]);
    } else {
        // Falha: mantém a última leitura boa, só registra o erro
        app_log('partner', 'Falha ao consultar estoque do parceiro', ['product' => $product['id'], 'url' => $url, 'error' => $err]);
    }
    return ['status' => $status, 'from_cache' => false, 'error' => $err, 'parsed' => $parsed, 'checked_at' => date('Y-m-d H:i:s'),
            'last_known' => $product['partner_status'] ?? null];
}

/**
 * Decide se pode vender. Retorna ['ok' => bool, 'error' => ?string, 'unconfirmed' => bool].
 */
function partner_can_sell(array $product, bool $force = false): array
{
    if (trim((string) ($product['partner_url'] ?? '')) === '') {
        return ['ok' => true, 'error' => null, 'unconfirmed' => false];
    }
    $c = partner_check($product, $force);
    if ($c['status'] === 'available') {
        return ['ok' => true, 'error' => null, 'unconfirmed' => false];
    }
    if ($c['status'] === 'out') {
        return ['ok' => false, 'error' => 'Sem estoque no momento.', 'unconfirmed' => false];
    }
    // Não foi possível confirmar agora
    $last = $c['last_known'] ?? ($product['partner_status'] ?? null);
    if ($last === 'out') {
        return ['ok' => false, 'error' => 'Sem estoque no momento.', 'unconfirmed' => true];
    }
    if (partner_fail_mode() === 'block') {
        return ['ok' => false, 'error' => 'Não foi possível confirmar o estoque agora. Tente novamente em instantes.', 'unconfirmed' => true];
    }
    return ['ok' => true, 'error' => null, 'unconfirmed' => true];
}

/** Produto já marcado como esgotado no parceiro (última leitura)? Usado na vitrine. */
function partner_known_out(array $product): bool
{
    return trim((string) ($product['partner_url'] ?? '')) !== '' && ($product['partner_status'] ?? null) === 'out';
}

/** Verifica todos os produtos com parceiro (admin / tarefa agendada). Retorna contagem por status. */
function partner_sync_all(int $limit = 60): array
{
    $count = ['available' => 0, 'out' => 0, 'unknown' => 0];
    $rows = db()->query("SELECT * FROM products WHERE partner_url IS NOT NULL AND partner_url <> '' AND is_active = 1
                          ORDER BY partner_checked_at IS NOT NULL, partner_checked_at LIMIT " . (int) $limit)->fetchAll();
    foreach ($rows as $p) {
        $r = partner_check($p, true);
        $count[$r['status']] = ($count[$r['status']] ?? 0) + 1;
        usleep(300000); // 0,3 s entre consultas para não sobrecarregar o parceiro
    }
    return $count;
}

function partner_status_label(?string $s): string
{
    return ['available' => 'Disponível no parceiro', 'out' => 'Esgotado no parceiro', 'unknown' => 'Não confirmado'][(string) $s] ?? 'Ainda não verificado';
}
