<?php
/**
 * King Decants — inc/helpers.php
 * ---------------------------------------------------------------------------
 * Funções utilitárias compartilhadas pela loja e pelo admin:
 *   - Escape/saída (e), URLs (url, asset), redirecionamento e flash messages
 *   - CSRF (token por sessão, campo oculto, verificação de form e AJAX)
 *   - Sanitização e validações brasileiras (CPF, CEP, telefone, UF)
 *   - ViaCEP (consulta de endereço por CEP no servidor)
 *   - Frete simplificado por faixa de CEP
 *   - Autenticação de clientes e admins + rate limit de login
 *   - Ícones SVG de traço fino (identidade visual dourada)
 * ---------------------------------------------------------------------------
 */

// ===========================================================================
// SAÍDA / URL
// ===========================================================================

/** Escapa texto para HTML. Use SEMPRE ao imprimir dados vindos do usuário/banco. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Caminho-base da aplicação (ex.: "" ou "/loja"), derivado de APP_URL. */
function base_path(): string
{
    static $base = null;
    if ($base === null) {
        $path = parse_url(APP_URL, PHP_URL_PATH) ?: '';
        $base = rtrim($path, '/');
    }
    return $base;
}

/** URL relativa à raiz da aplicação. url('cart.php') -> /loja/cart.php */
function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

/** URL absoluta (para Mercado Pago, e-mails etc.). */
function absolute_url(string $path = ''): string
{
    return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
}

/** URL de arquivo em assets/ com "cache busting" pela data de modificação. */
function asset(string $path): string
{
    $file = dirname(__DIR__) . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? filemtime($file) : '1';
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}

/** Detecta HTTPS (inclusive atrás de proxy/CDN que envia X-Forwarded-Proto). */
function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) == 443)
        || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

/** IP do cliente. Atenção: não confie em X-Forwarded-For sem um proxy confiável. */
function client_ip(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
}

/** Redireciona e encerra. Aceita apenas caminhos internos para evitar open redirect. */
function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

/** Sanitiza um parâmetro "redirect" para que aponte somente para páginas internas. */
function safe_redirect_target(?string $target, string $fallback = 'index.php'): string
{
    $target = (string) $target;
    // Aceita apenas nomes de arquivo/caminhos relativos simples, sem esquema nem "//".
    if ($target === '' || preg_match('#^(?:[a-z]+:)?//#i', $target) || str_contains($target, '\\')
        || !preg_match('#^[a-zA-Z0-9_\-/\.\?=&%]+$#', $target)) {
        return url($fallback);
    }
    return url(ltrim($target, '/'));
}

/** Gera token aleatório criptograficamente seguro (hex). */
function random_token(int $bytes = 20): string
{
    return bin2hex(random_bytes($bytes));
}

// ===========================================================================
// FLASH MESSAGES (cards de sucesso/erro)
// ===========================================================================

/** Agenda uma mensagem para a próxima página. $type: success | error | info */
function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

/** Retorna e limpa as mensagens pendentes. */
function flash_pull(): array
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $messages;
}

// ===========================================================================
// CSRF
// ===========================================================================

/** Token CSRF da sessão (criado sob demanda). */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = random_token(32);
    }
    return $_SESSION['_csrf'];
}

/** Campo oculto para colocar dentro de cada <form method="post">. */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/**
 * Verifica o token enviado no POST (_csrf) ou no header X-CSRF-Token (AJAX).
 * hash_equals() evita ataque de timing.
 */
function csrf_verify(): bool
{
    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($sent) && $sent !== '' && hash_equals(csrf_token(), $sent);
}

/** Interrompe a requisição se o CSRF for inválido. */
function csrf_require(): void
{
    if (!csrf_verify()) {
        if (wants_json()) {
            json_response(['ok' => false, 'error' => 'Sessão expirada. Recarregue a página e tente novamente.'], 419);
        }
        http_response_code(419);
        flash('error', 'Sua sessão expirou. Por favor, tente novamente.');
        redirect($_SERVER['HTTP_REFERER'] ?? url('index.php'));
    }
}

// ===========================================================================
// JSON / AJAX
// ===========================================================================

/** True quando a requisição é AJAX (fetch com Accept: application/json). */
function wants_json(): bool
{
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
}

/** Envia resposta JSON e encerra. */
function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ===========================================================================
// SANITIZAÇÃO
// ===========================================================================

/** Texto simples: remove tags, caracteres de controle e espaços extras; limita tamanho. */
function clean_string($value, int $max = 255): string
{
    $value = is_string($value) ? $value : '';
    $value = strip_tags($value);
    $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    return mb_substr($value, 0, $max);
}

/** Texto multilinha (notas): preserva quebras de linha. */
function clean_text($value, int $max = 2000): string
{
    $value = is_string($value) ? $value : '';
    $value = strip_tags($value);
    $value = preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    return mb_substr(trim($value), 0, $max);
}

/** Mantém apenas dígitos. */
function only_digits($value): string
{
    return preg_replace('/\D+/', '', (string) $value) ?? '';
}

/** Inteiro positivo de $_GET/$_POST com valor padrão. */
function int_param(array $source, string $key, int $default = 0): int
{
    $v = filter_var($source[$key] ?? null, FILTER_VALIDATE_INT);
    return $v === false || $v === null ? $default : (int) $v;
}

// ===========================================================================
// VALIDAÇÕES BRASIL
// ===========================================================================

/**
 * Valida CPF pelos dígitos verificadores (algoritmo módulo 11).
 * Aceita com ou sem máscara. Rejeita sequências repetidas (111.111.111-11).
 */
function validate_cpf(string $cpf): bool
{
    $cpf = only_digits($cpf);
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }
    // Calcula o 1º (t=9) e o 2º (t=10) dígito verificador
    for ($t = 9; $t < 11; $t++) {
        $sum = 0;
        for ($i = 0; $i < $t; $i++) {
            $sum += (int) $cpf[$i] * (($t + 1) - $i);
        }
        $digit = ((10 * $sum) % 11) % 10;
        if ((int) $cpf[$t] !== $digit) {
            return false;
        }
    }
    return true;
}

/** CEP: 8 dígitos. */
function validate_cep(string $cep): bool
{
    return (bool) preg_match('/^\d{8}$/', only_digits($cep));
}

/** E-mail (filtro nativo do PHP + limite de tamanho). */
function validate_email(string $email): bool
{
    return strlen($email) <= 190 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/** Telefone BR: DDD (11-99) + 8 ou 9 dígitos. */
function validate_phone(string $phone): bool
{
    $d = only_digits($phone);
    return (bool) preg_match('/^[1-9]{2}9?\d{8}$/', $d);
}

/** Lista de UFs válidas. */
function brazil_states(): array
{
    return ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
}

function validate_uf(string $uf): bool
{
    return in_array(strtoupper($uf), brazil_states(), true);
}

/** Senha: mínimo 8 caracteres, com letra e número. */
function validate_password(string $password): bool
{
    return strlen($password) >= 8 && strlen($password) <= 200
        && preg_match('/[A-Za-z]/', $password) && preg_match('/\d/', $password);
}

function format_cpf(?string $cpf): string
{
    $d = only_digits($cpf);
    return strlen($d) === 11 ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $d) : (string) $cpf;
}

function format_cep(?string $cep): string
{
    $d = only_digits($cep);
    return strlen($d) === 8 ? substr($d, 0, 5) . '-' . substr($d, 5) : (string) $cep;
}

function format_phone(?string $phone): string
{
    $d = only_digits($phone);
    if (strlen($d) === 11) {
        return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 5), substr($d, 7));
    }
    if (strlen($d) === 10) {
        return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 4), substr($d, 6));
    }
    return (string) $phone;
}

/** Valor em reais: 1234.5 -> "R$ 1.234,50" */
function money($value): string
{
    return 'R$ ' . number_format((float) $value, 2, ',', '.');
}

/** Data BR. */
function date_br(?string $datetime, bool $withTime = true): string
{
    if (!$datetime) {
        return '—';
    }
    $ts = strtotime($datetime);
    return $ts ? date($withTime ? 'd/m/Y H:i' : 'd/m/Y', $ts) : '—';
}

// ===========================================================================
// VIACEP (autocompletar endereço)
// ---------------------------------------------------------------------------
// Uso no front-end: assets/js/app.js chama https://viacep.com.br/ws/{cep}/json/
// diretamente do navegador (CORS liberado pelo ViaCEP) ao sair do campo CEP.
// Esta função é a versão SERVIDOR, útil para validar/normalizar o endereço ou
// se você preferir não depender de JavaScript. Para ativá-la no checkout,
// descomente o bloco "VIACEP SERVER-SIDE" em checkout.php.
// ===========================================================================
function viacep_lookup(string $cep): ?array
{
    $cep = only_digits($cep);
    if (!validate_cep($cep) || !function_exists('curl_init')) {
        return null;
    }
    $ch = curl_init("https://viacep.com.br/ws/{$cep}/json/");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200 || !$body) {
        return null;
    }
    $data = json_decode($body, true);
    if (!is_array($data) || !empty($data['erro'])) {
        return null;
    }
    return [
        'street'   => $data['logradouro'] ?? '',
        'district' => $data['bairro'] ?? '',
        'city'     => $data['localidade'] ?? '',
        'state'    => $data['uf'] ?? '',
    ];
}

// ===========================================================================
// FRETE SIMPLIFICADO (tabela fictícia por faixa de CEP)
// ---------------------------------------------------------------------------
// O 1º dígito do CEP indica a região postal. Substitua por integração real
// (Correios, Melhor Envio, Frenet...) quando necessário.
// ===========================================================================
function shipping_quote(string $cep, float $subtotal): array
{
    $cep = only_digits($cep);
    // Frete grátis conforme Admin -> Frete (inc/promotions.php). $subtotal = valor dos produtos já com desconto.
    if (free_shipping_applies($subtotal)) {
        return ['price' => 0.0, 'label' => 'Frete grátis', 'days' => '3 a 8 dias úteis'];
    }
    if (!validate_cep($cep)) {
        return ['price' => 0.0, 'label' => 'Informe o CEP', 'days' => ''];
    }
    $region = (int) $cep[0];
    $table = [
        0 => [19.90, '2 a 4 dias úteis'],  // Grande São Paulo
        1 => [22.90, '3 a 5 dias úteis'],  // Interior de SP
        2 => [24.90, '3 a 6 dias úteis'],  // RJ / ES
        3 => [24.90, '3 a 6 dias úteis'],  // MG
        4 => [29.90, '5 a 8 dias úteis'],  // BA / SE
        5 => [32.90, '6 a 9 dias úteis'],  // PE / AL / PB / RN
        6 => [36.90, '7 a 12 dias úteis'], // CE / PI / MA / Norte
        7 => [29.90, '4 a 7 dias úteis'],  // DF / GO / TO / MT / MS / RO / AC
        8 => [24.90, '3 a 6 dias úteis'],  // PR / SC
        9 => [26.90, '4 a 7 dias úteis'],  // RS
    ];
    [$price, $days] = $table[$region];
    return ['price' => $price, 'label' => 'Envio padrão', 'days' => $days];
}

// ===========================================================================
// AUTENTICAÇÃO — CLIENTES
// ===========================================================================

/** Usuário logado (cache por requisição) ou null. */
function current_user(): ?array
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    $cache = null;
    if (!empty($_SESSION['user_id'])) {
        $stmt = db()->prepare('SELECT id, name, email, phone, cpf FROM users WHERE id = ? AND is_guest = 0');
        $stmt->execute([(int) $_SESSION['user_id']]);
        $cache = $stmt->fetch() ?: null;
        if (!$cache) {
            unset($_SESSION['user_id']); // conta removida
        }
    }
    return $cache;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

/**
 * Autentica o usuário na sessão.
 *  - session_regenerate_id(true): novo ID de sessão após login (anti session fixation).
 *  - Migra o carrinho de convidado ($_SESSION['cart']) para cart_items do usuário.
 */
function login_user(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    unset($_SESSION['guest_checkout']);
    cart_merge_session_into_user($userId);
    db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$userId]);
}

/** Encerra a sessão do cliente por completo. */
function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ===========================================================================
// RATE LIMIT DE LOGIN
// ---------------------------------------------------------------------------
// Implementação: cada falha grava (scope, ip, email, data) em login_attempts.
// Antes de verificar a senha, contamos falhas na janela LOGIN_WINDOW_MINUTES
// por IP OU por e-mail. Acima de LOGIN_MAX_ATTEMPTS, o login é bloqueado
// temporariamente. Login bem-sucedido limpa as falhas daquele e-mail/IP.
// Alternativas mais robustas: fail2ban, Cloudflare Rate Limiting, Redis.
// ===========================================================================
function login_is_throttled(string $scope, string $email): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM login_attempts
          WHERE scope = ? AND (ip = ? OR email = ?)
            AND attempted_at > (NOW() - INTERVAL ? MINUTE)'
    );
    $stmt->execute([$scope, client_ip(), mb_strtolower($email), (int) LOGIN_WINDOW_MINUTES]);
    return (int) $stmt->fetchColumn() >= LOGIN_MAX_ATTEMPTS;
}

function login_register_failure(string $scope, string $email): void
{
    db()->prepare('INSERT INTO login_attempts (scope, ip, email) VALUES (?, ?, ?)')
        ->execute([$scope, client_ip(), mb_strtolower($email)]);
    // Limpeza oportunista de registros antigos (1% das vezes)
    if (random_int(1, 100) === 1) {
        db()->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
    }
}

function login_clear_failures(string $scope, string $email): void
{
    db()->prepare('DELETE FROM login_attempts WHERE scope = ? AND (ip = ? OR email = ?)')
        ->execute([$scope, client_ip(), mb_strtolower($email)]);
}

// ===========================================================================
// AUTENTICAÇÃO — ADMIN (sessão separada: cookie KDADMINSESS)
// ===========================================================================

function current_admin(): ?array
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    $cache = null;
    if (!empty($_SESSION['admin_id'])) {
        $stmt = db()->prepare('SELECT id, name, email FROM admin_users WHERE id = ? AND is_active = 1');
        $stmt->execute([(int) $_SESSION['admin_id']]);
        $cache = $stmt->fetch() ?: null;
    }
    return $cache;
}

function is_admin(): bool
{
    return current_admin() !== null;
}

/** Proteção de rota: coloque no topo de toda página do admin (exceto login). */
function require_admin(): array
{
    $admin = current_admin();
    if (!$admin) {
        redirect(url('admin/login.php'));
    }
    return $admin;
}

/** Auditoria: registra ação do admin (quem, o quê, quando, IP). */
function admin_log(string $action, ?string $entityType = null, ?int $entityId = null, ?string $details = null, ?int $adminId = null): void
{
    // $adminId explícito é usado no login (o cache de current_admin() ainda está vazio nesse momento).
    $adminId = $adminId ?? (current_admin()['id'] ?? null);
    db()->prepare('INSERT INTO admin_logs (admin_id, action, entity_type, entity_id, details, ip) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$adminId, $action, $entityType, $entityId, $details ? mb_substr($details, 0, 500) : null, client_ip()]);
}

// ===========================================================================
// LOG EM ARQUIVO
// ===========================================================================

/** Grava uma linha em storage/logs/{canal}.log (pasta protegida por .htaccess). */
function app_log(string $channel, string $message, array $context = []): void
{
    $dir = dirname(__DIR__) . '/storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    $line = sprintf("[%s] %s %s\n", date('Y-m-d H:i:s'), $message, $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '');
    @file_put_contents($dir . '/' . preg_replace('/[^a-z0-9_\-]/i', '', $channel) . '.log', $line, FILE_APPEND | LOCK_EX);
}

// ===========================================================================
// CATÁLOGO / STATUS
// ===========================================================================

/** Coleções exibidas na faixa de navegação da home. */
function collections(): array
{
    return [
        'floral'     => ['label' => 'Floral',           'icon' => 'flower'],
        'amadeirado' => ['label' => 'Amadeirado',       'icon' => 'tree'],
        'fresco'     => ['label' => 'Fresco',           'icon' => 'drop'],
        'noturno'    => ['label' => 'Noturno',          'icon' => 'moon'],
        'assinatura' => ['label' => 'Assinatura King',  'icon' => 'crown'],
    ];
}

function order_statuses(): array
{
    return [
        'pending'   => 'Aguardando pagamento',
        'paid'      => 'Pago',
        'shipped'   => 'Enviado',
        'delivered' => 'Entregue',
        'cancelled' => 'Cancelado',
    ];
}

function status_label(string $status): string
{
    return order_statuses()[$status] ?? ucfirst($status);
}

/** Classes Tailwind do "badge" de status. */
function status_badge(string $status): string
{
    $map = [
        'pending'   => 'border-amber-400/40 text-amber-300 bg-amber-400/10',
        'paid'      => 'border-gold-400/50 text-gold-300 bg-gold-400/10',
        'shipped'   => 'border-sky-400/40 text-sky-300 bg-sky-400/10',
        'delivered' => 'border-emerald-400/40 text-emerald-300 bg-emerald-400/10',
        'cancelled' => 'border-red-400/40 text-red-300 bg-red-400/10',
    ];
    $cls = $map[$status] ?? 'border-white/20 text-white/70';
    return '<span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-medium uppercase tracking-wider ' . $cls . '">' . e(status_label($status)) . '</span>';
}

/**
 * URL pública da imagem do produto. Aceita URL absoluta (https://...) ou caminho
 * relativo salvo pelo upload do admin (assets/uploads/products/arquivo.webp).
 */
function product_image_src(string $imageUrl): string
{
    if ($imageUrl === '') {
        return url('assets/img/product-placeholder.svg');
    }
    return preg_match('#^https?://#i', $imageUrl) ? $imageUrl : url($imageUrl);
}

/** <img> de produto com fallback local caso o placeholder externo falhe. */
function product_img(array $product, string $class = ''): string
{
    $fallback = url('assets/img/product-placeholder.svg');
    return sprintf(
        '<img src="%s" alt="%s" loading="lazy" class="%s" onerror="this.onerror=null;this.src=\'%s\'">',
        e(product_image_src((string) $product['image_url'])),
        e($product['name']),
        e($class),
        e($fallback)
    );
}

// ===========================================================================
// PAGINAÇÃO
// ===========================================================================

/** Calcula paginação. Retorna [page, perPage, offset, totalPages]. */
function paginate(int $total, int $perPage = 20): array
{
    $page = max(1, int_param($_GET, 'page', 1));
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $pages);
    return [$page, $perPage, ($page - 1) * $perPage, $pages];
}

/** Monta query string preservando filtros atuais. */
function query_with(array $changes): string
{
    $q = array_merge($_GET, $changes);
    $q = array_filter($q, fn($v) => $v !== null && $v !== '');
    return '?' . http_build_query($q);
}

// ===========================================================================
// ÍCONES (SVG de traço fino — stroke herdado de currentColor)
// ===========================================================================
function icon(string $name, string $class = 'h-5 w-5'): string
{
    $paths = [
        'search'  => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'bag'     => '<path d="M5 8h14l-1 12H6L5 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
        'user'    => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/>',
        'menu'    => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'close'   => '<path d="M6 6l12 12M18 6 6 18"/>',
        'arrow'   => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'plus'    => '<path d="M12 5v14M5 12h14"/>',
        'minus'   => '<path d="M5 12h14"/>',
        'trash'   => '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/>',
        'check'   => '<path d="m5 12 5 5 9-10"/>',
        'alert'   => '<path d="M12 3 2 20h20L12 3Z"/><path d="M12 10v4M12 17v.5"/>',
        'info'    => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8v.5"/>',
        'truck'   => '<path d="M3 6h11v10H3zM14 10h4l3 3v3h-7"/><circle cx="7" cy="18" r="1.6"/><circle cx="17" cy="18" r="1.6"/>',
        'shield'  => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6l-8-3Z"/><path d="m9 12 2 2 4-4"/>',
        'gift'    => '<path d="M4 10h16v10H4zM3 7h18v3H3zM12 7v13"/><path d="M12 7c-2-3-5-3-5-1s3 1 5 1c2 0 5 1 5-1s-3-2-5 1"/>',
        'logout'  => '<path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10"/>',
        'chart'   => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'box'     => '<path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z"/><path d="m4 7.5 8 4.5 8-4.5M12 12v9"/>',
        'users'   => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c1-3.5 3.5-5 6.5-5s5.5 1.5 6.5 5"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 15c2 .6 3 2.2 3.5 5"/>',
        'download'=> '<path d="M12 4v11M7 10l5 5 5-5M5 20h14"/>',
        'eye'     => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
        'copy'    => '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3"/>',
        'pix'     => '<path d="m12 3 4 4-4 4-4-4 4-4ZM12 13l4 4-4 4-4-4 4-4ZM3 12l4-4 4 4-4 4-4-4ZM13 12l4-4 4 4-4 4-4-4Z"/>',
        'card'    => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h4"/>',
        'heart'   => '<path d="M12 20s-7-4.4-9.2-8.6C1.3 8.4 3.2 5 6.6 5c2 0 3.4 1.1 4.4 2.5C12 6.1 13.4 5 15.4 5c3.4 0 5.3 3.4 3.8 6.4C19 15.6 12 20 12 20z"/>',
        'receipt' => '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h3"/><path d="M15 3v3h3"/>',
        'pin'     => '<path d="M12 21s-6.5-6-6.5-11a6.5 6.5 0 0 1 13 0c0 5-6.5 11-6.5 11Z"/><circle cx="12" cy="10" r="2.5"/>',
        'badge'   => '<path d="M12 2.5l2.4 1.8 3-.2.9 2.9 2.4 1.8-1 2.8 1 2.8-2.4 1.8-.9 2.9-3-.2L12 21.5l-2.4-1.8-3 .2-.9-2.9-2.4-1.8 1-2.8-1-2.8 2.4-1.8.9-2.9 3 .2Z"/><path d="m12 8 1.2 2.5 2.7.3-2 1.9.5 2.7L12 14.1l-2.4 1.3.5-2.7-2-1.9 2.7-.3Z"/>',
        'chevron' => '<path d="m9 6 6 6-6 6"/>',
        'tag'     => '<path d="M3 12V4h8l10 10-8 8L3 12Z"/><circle cx="7.5" cy="8.5" r="1.5"/>',
        'home'    => '<path d="M3.5 11 12 4l8.5 7"/><path d="M5.5 9.5V20h13V9.5"/><path d="M10 20v-5.5h4V20"/>',
        'cart'    => '<path d="M3 4h2.2l2.3 11h10.3l2-8H6.6"/><circle cx="9.5" cy="19" r="1.4"/><circle cx="16.5" cy="19" r="1.4"/>',
        'edit'    => '<path d="M4 20h4L19 9l-4-4L4 16v4Z"/><path d="m13 7 4 4"/>',
        // Coleções
        'flower'  => '<circle cx="12" cy="12" r="2.5"/><path d="M12 9.5C10 6 10.5 3 12 3s2 3 0 6.5ZM12 14.5c2 3.5 1.5 6.5 0 6.5s-2-3 0-6.5ZM9.5 12C6 14 3 13.5 3 12s3-2 6.5 0ZM14.5 12c3.5-2 6.5-1.5 6.5 0s-3 2-6.5 0Z"/>',
        'tree'    => '<path d="M12 21v-6"/><path d="M12 3c4 3 6 6.5 6 9a6 6 0 0 1-12 0c0-2.5 2-6 6-9Z"/><path d="M12 15l-3-3M12 12l3-3"/>',
        'drop'    => '<path d="M12 3s6 6.5 6 11a6 6 0 0 1-12 0c0-4.5 6-11 6-11Z"/><path d="M9 15a3 3 0 0 0 3 3"/>',
        'moon'    => '<path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a7 7 0 0 0 10.5 10.5Z"/><path d="M17 4v3M15.5 5.5h3"/>',
        'crown'   => '<path d="M3 8l4.5 4L12 5l4.5 7L21 8l-2 10H5L3 8Z"/><path d="M5 21h14"/>',
    ];
    $svg = $paths[$name] ?? $paths['info'];
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $svg . '</svg>';
}

// ===========================================================================
// CONFIGURAÇÕES DA LOJA (tabela settings) — ex.: imagem do banner da home
// ===========================================================================

/** Lê uma configuração (com cache por requisição). */
function setting(string $key, ?string $default = null): ?string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (db()->query('SELECT setting_key, setting_value FROM settings')->fetchAll() as $row) {
                $cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Throwable $e) {
            // Tabela ainda não criada: usa padrões.
        }
    }
    return array_key_exists($key, $cache) && $cache[$key] !== null && $cache[$key] !== '' ? $cache[$key] : $default;
}

function setting_set(string $key, ?string $value): void
{
    db()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)')
        ->execute([$key, $value]);
}

/**
 * A imagem tem fundo opaco (cantos não transparentes)? Usado no banner para
 * "fundir" fotos com fundo escuro às luzes. Resultado guardado em settings.
 */
function image_is_opaque(string $relativePath): bool
{
    $full = dirname(__DIR__) . '/' . ltrim($relativePath, '/');
    if (!is_file($full) || !function_exists('imagecreatefromstring')) {
        return false;
    }
    $im = @imagecreatefromstring((string) file_get_contents($full));
    if (!$im) {
        return false;
    }
    $w = imagesx($im);
    $h = imagesy($im);
    $opaque = 0;
    foreach ([[2, 2], [$w - 3, 2], [2, $h - 3], [$w - 3, $h - 3]] as [$x, $y]) {
        $alpha = (imagecolorat($im, $x, $y) & 0x7F000000) >> 24; // 0 = opaco, 127 = transparente
        if ($alpha < 100) {
            $opaque++;
        }
    }
    imagedestroy($im);
    return $opaque >= 3;
}

/** Status "opaco" da imagem do banner (calcula uma vez e guarda em settings). */
function hero_image_opaque(?string $heroImage): bool
{
    if (!$heroImage) {
        return false;
    }
    $cached = setting('hero_image_opaque');
    $parts = $cached ? explode('|', $cached, 2) : [];
    if (count($parts) === 2 && $parts[1] === $heroImage) {
        return $parts[0] === '1';
    }
    $opaque = image_is_opaque($heroImage);
    try {
        setting_set('hero_image_opaque', ($opaque ? '1' : '0') . '|' . $heroImage);
    } catch (Throwable $e) {
    }
    return $opaque;
}
