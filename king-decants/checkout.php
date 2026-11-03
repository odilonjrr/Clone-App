<?php
/**
 * King Decants — checkout.php
 * ---------------------------------------------------------------------------
 * Fluxo:
 *   1. Sem login e sem ter escolhido "convidado" -> tela de escolha
 *      (Criar conta & pagar | Entrar | Continuar como convidado).
 *   2. Formulário com dados obrigatórios: nome, telefone, CPF, e-mail e
 *      endereço completo (CEP, logradouro, número, complemento, bairro, cidade, UF).
 *   3. POST (com CSRF):
 *        a) sanitiza e valida no servidor (CPF com dígitos verificadores, CEP, e-mail, telefone, UF)
 *        b) recalcula carrinho e frete NO SERVIDOR (preço nunca vem do navegador)
 *        c) cria o pedido "pending" (external_reference = id do pedido)
 *        d) Cartão -> cria preferência Checkout Pro e devolve init_point
 *           PIX    -> cria pagamento PIX (/v1/payments) e leva à página com QR code
 *   4. Resposta: JSON (quando enviado via fetch pelo app.js) ou redirect normal.
 *
 * Endpoint auxiliar: GET checkout.php?action=shipping&cep=00000000 -> JSON do frete.
 * ---------------------------------------------------------------------------
 */
require __DIR__ . '/inc/bootstrap.php';

$cart = cart_items_detailed();
$user = current_user();

// ------------------------------------------------------------------ Frete (AJAX)
/** Resposta JSON com os totais (usada pelo frete e pelo cupom). */
function checkout_totals_json(array $t, array $extra = []): array
{
    return $extra + [
        'ok'             => true,
        'subtotal_label' => money($t['subtotal']),
        'discount'       => $t['discount'],
        'discount_label' => '− ' . money($t['discount']),
        'coupon_code'    => $t['coupon']['code'] ?? null,
        'coupon_desc'    => $t['coupon'] ? coupon_label($t['coupon']) : null,
        'shipping_label' => $t['shipping']['price'] > 0 ? money($t['shipping']['price']) : $t['shipping']['label'],
        'total_label'    => money($t['total']),
        'days'           => $t['shipping']['days'],
    ];
}

if (($_GET['action'] ?? '') === 'shipping') {
    json_response(checkout_totals_json(order_totals($cart, (string) ($_GET['cep'] ?? ''))));
}

// ------------------------------------------------------------------ Cupom (aplicar / remover)
// POST checkout.php?action=coupon  com coupon_code (vazio ou remove_coupon=1 remove).
if (($_GET['action'] ?? '') === 'coupon' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $code = coupon_normalize((string) ($_POST['coupon_code'] ?? ''));
    $message = null;
    $error = null;
    if (isset($_POST['remove_coupon']) || $code === '') {
        unset($_SESSION['coupon_code']);
        $message = 'Cupom removido.';
    } else {
        $error = coupon_error(coupon_find($code), (float) $cart['subtotal']);
        if ($error) {
            unset($_SESSION['coupon_code']);
        } else {
            $_SESSION['coupon_code'] = $code;
            $message = 'Cupom aplicado!';
        }
    }
    $t = order_totals($cart, only_digits($_POST['cep'] ?? ''));
    if (wants_json()) {
        json_response(checkout_totals_json($t, ['ok' => $error === null, 'message' => $message, 'error' => $error]), $error ? 422 : 200);
    }
    flash($error ? 'error' : 'success', $error ?? $message);
    redirect(url('checkout.php'));
}

if (!$cart['items']) {
    if (wants_json()) {
        json_response(['ok' => false, 'error' => 'Sua sacola está vazia.'], 422);
    }
    flash('info', 'Sua sacola está vazia.');
    redirect(url('cart.php'));
}

// ------------------------------------------------------------------ Convidado ou logado?
if (!$user && isset($_GET['guest'])) {
    $_SESSION['guest_checkout'] = true;
}
$isGuest = !$user;
$showChoice = !$user && empty($_SESSION['guest_checkout']);

$errors = [];
$old = [];

// ------------------------------------------------------------------ POST: criar pedido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$showChoice) {
    csrf_require();

    // 1) Sanitização
    $old = [
        'name'           => clean_string($_POST['name'] ?? '', 150),
        'email'          => mb_strtolower(clean_string($_POST['email'] ?? '', 190)),
        'phone'          => only_digits($_POST['phone'] ?? ''),
        'cpf'            => only_digits($_POST['cpf'] ?? ''),
        'cep'            => only_digits($_POST['cep'] ?? ''),
        'street'         => clean_string($_POST['street'] ?? '', 180),
        'number'         => clean_string($_POST['number'] ?? '', 20),
        'complement'     => clean_string($_POST['complement'] ?? '', 120),
        'district'       => clean_string($_POST['district'] ?? '', 120),
        'city'           => clean_string($_POST['city'] ?? '', 120),
        'state'          => strtoupper(clean_string($_POST['state'] ?? '', 2)),
        // InfinitePay: o cliente escolhe PIX ou cartão na própria página da InfinitePay.
        'payment_method' => payment_provider() === 'infinitepay' ? 'infinitepay' : (($_POST['payment_method'] ?? 'card') === 'pix' ? 'pix' : 'card'),
    ];

    // Usuário logado: o e-mail do pedido é o da conta (evita "sequestro" de pedidos).
    if ($user) {
        $old['email'] = $user['email'];
    }

    /*
    // ---- VIACEP SERVER-SIDE (opcional) -------------------------------------
    // Descomente para completar/normalizar o endereço pelo CEP no servidor,
    // útil quando o navegador está sem JavaScript.
    if (validate_cep($old['cep']) && ($addr = viacep_lookup($old['cep']))) {
        foreach (['street', 'district', 'city', 'state'] as $k) {
            if ($old[$k] === '' && !empty($addr[$k])) {
                $old[$k] = $addr[$k];
            }
        }
    }
    // ------------------------------------------------------------------------
    */

    // 2) Validação
    if (mb_strlen($old['name']) < 5 || !str_contains($old['name'], ' ')) {
        $errors['name'] = 'Informe nome e sobrenome.';
    }
    if (!validate_email($old['email'])) {
        $errors['email'] = 'E-mail inválido.';
    }
    if (!validate_phone($old['phone'])) {
        $errors['phone'] = 'Telefone inválido. Use DDD + número.';
    }
    if (!validate_cpf($old['cpf'])) {
        $errors['cpf'] = 'CPF inválido.';
    }
    if (!validate_cep($old['cep'])) {
        $errors['cep'] = 'CEP inválido.';
    }
    foreach (['street' => 'Logradouro', 'number' => 'Número', 'district' => 'Bairro', 'city' => 'Cidade'] as $field => $label) {
        if ($old[$field] === '') {
            $errors[$field] = "{$label} é obrigatório.";
        }
    }
    if (!validate_uf($old['state'])) {
        $errors['state'] = 'Selecione o estado.';
    }
    if (payment_provider() === 'infinitepay' && !ip_is_configured()) {
        $errors['_global'] = 'Pagamentos ainda não configurados: defina INFINITEPAY_HANDLE (sua InfiniteTag) no arquivo de configuração (veja o README).';
    } elseif (payment_provider() === 'mercadopago' && !mp_is_configured()) {
        $errors['_global'] = 'Pagamentos ainda não configurados: defina MP_ACCESS_TOKEN em inc/config.php (veja o README).';
    }

    // Totais finais (cupom revalidado; frete grátis conforme o admin).
    $totals = order_totals($cart, $old['cep']);
    if ($totals['coupon_error']) {
        $errors['_global'] = 'Cupom removido: ' . $totals['coupon_error'] . ' Confira o novo total e tente de novo.';
    }

    if (!$errors) {
        $shipping = $totals['shipping'];
        $orderRef = null;
        orders_expire_stale(); // libera reservas de pedidos abandonados antes de reservar
        try {
            // 3) Pedido "pending"
            $orderRef = order_create($old, $cart, $shipping, $old['payment_method'], $isGuest, $totals['coupon']['code'] ?? null);
            $order = order_find($orderRef['id']);
            $orderUrl = url('callback.php?order=' . $order['id'] . '&token=' . $order['access_token']);

            // Completa o cadastro do usuário logado (telefone/CPF) se estiver vazio.
            if ($user) {
                db()->prepare('UPDATE users SET phone = COALESCE(NULLIF(phone, \'\'), ?), cpf = COALESCE(cpf, ?) WHERE id = ?')
                    ->execute([$old['phone'], $old['cpf'], $user['id']]);
            }

            // 4) Provedor de pagamento
            if (payment_provider() === 'infinitepay') {
                // Link do Checkout Integrado (PIX e cartão na página da InfinitePay)
                $link = ip_create_checkout_link($order, order_items($order['id']));
                db()->prepare("UPDATE orders SET payment_provider = 'infinitepay', mp_preference_id = ?, mp_init_point = ? WHERE id = ?")
                    ->execute([$link['order_nsu'], $link['url'], $order['id']]);
                $initPoint = $link['url'];
                $redirectTo = $initPoint;
            } elseif ($old['payment_method'] === 'pix') {
                $payment = mp_create_pix_payment($order);
                payment_upsert($order['id'], $payment);
                $redirectTo = $orderUrl;
                $initPoint = null;
            } else {
                $payload = mp_build_preference_payload($order, order_items($order['id']));
                $preference = mp_create_preference($payload);
                $initPoint = mp_checkout_url($preference);
                db()->prepare('UPDATE orders SET mp_preference_id = ?, mp_init_point = ? WHERE id = ?')
                    ->execute([$preference['id'], $initPoint, $order['id']]);
                $redirectTo = $initPoint;
            }

            // 5) Sucesso: limpa a sacola, guarda o último pedido na sessão e avisa por e-mail
            // Cliente logado pode salvar o endereço em Minha conta (se ainda não existir igual).
            if ($user && isset($_POST['save_address'])) {
                $same = db()->prepare('SELECT COUNT(*) FROM user_addresses WHERE user_id = ? AND cep = ? AND number = ? AND street = ?');
                $same->execute([$user['id'], $old['cep'], $old['number'], $old['street']]);
                if (!(int) $same->fetchColumn()) {
                    address_save((int) $user['id'], ['label' => 'Entrega', 'recipient' => $old['name']] + $old);
                }
            }
            cart_clear();
            unset($_SESSION['guest_checkout'], $_SESSION['coupon_code']);
            $_SESSION['last_order'] = ['id' => $order['id'], 'token' => $order['access_token']];
            mail_order_created($order);

            if (wants_json()) {
                json_response(['ok' => true, 'redirect' => $redirectTo, 'init_point' => $initPoint, 'order_url' => $orderUrl]);
            }
            redirect($redirectTo);
        } catch (Throwable $e) {
            app_log('checkout', 'Falha no checkout', ['error' => $e->getMessage(), 'order' => $orderRef['id'] ?? null]);
            // Se o pedido foi criado mas o MP falhou, cancela para não deixar pedido órfão.
            if ($orderRef) {
                order_change_status($orderRef['id'], 'cancelled', 'system', null, 'Falha ao iniciar pagamento: ' . mb_substr($e->getMessage(), 0, 150));
            }
            $errors['_global'] = $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível concluir o pedido. Tente novamente.';
        }
    }

    if (wants_json()) {
        json_response(['ok' => false, 'error' => $errors['_global'] ?? 'Revise os campos destacados.', 'errors' => $errors], 422);
    }
}

// ------------------------------------------------------------------ Valores iniciais do formulário
if (!$old) {
    $old = ['payment_method' => payment_provider() === 'infinitepay' ? 'infinitepay' : 'pix'];
    if ($user) {
        $old += ['name' => $user['name'], 'email' => $user['email'], 'phone' => $user['phone'], 'cpf' => $user['cpf']];
        // Endereço padrão salvo em Minha conta; se não houver, o do último pedido feito com a conta.
        if ($def = user_default_address((int) $user['id'])) {
            $old += array_intersect_key($def, array_flip(['cep', 'street', 'number', 'complement', 'district', 'city', 'state']));
        } else {
            $last = db()->prepare('SELECT cep, street, number, complement, district, city, state FROM orders WHERE user_id = ? AND is_guest_order = 0 ORDER BY id DESC LIMIT 1');
            $last->execute([$user['id']]);
            $old += $last->fetch() ?: [];
        }
    }
}
$v = fn(string $k) => e($old[$k] ?? '');
$err = fn(string $k) => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
$inv = fn(string $k) => isset($errors[$k]) ? ' is-invalid' : '';

$preview = order_totals($cart, (string) ($old['cep'] ?? ''));
$shippingPreview = $preview['shipping'];
if ($preview['coupon_error'] && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('error', 'Cupom removido: ' . $preview['coupon_error']);
}

$pageTitle = 'Finalizar compra — King Decants';
require __DIR__ . '/inc/header.php';
?>

<section class="mx-auto max-w-7xl px-5 py-12 lg:px-8 lg:py-16">
    <p class="eyebrow">Checkout seguro</p>
    <h1 class="mt-3 font-serif text-5xl text-white">Finalizar <em class="text-gold-gradient">compra</em></h1>
    <div class="gold-divider my-10"></div>

<?php if ($showChoice): ?>
    <!-- ======================= ESCOLHA: CONTA OU CONVIDADO ======================= -->
    <div class="grid gap-6 md:grid-cols-3">
        <div class="panel flex flex-col p-8 animate-fade-up">
            <span class="text-gold-400"><?= icon('crown', 'h-8 w-8') ?></span>
            <h2 class="mt-5 font-serif text-2xl text-white">Criar conta &amp; pagar</h2>
            <p class="mt-2 flex-1 text-sm text-ink-400">Acompanhe pedidos e compre mais rápido da próxima vez.</p>
            <a href="<?= e(url('auth/register.php?redirect=checkout.php')) ?>" class="btn-gold mt-6">Criar conta</a>
        </div>
        <div class="panel flex flex-col p-8 animate-fade-up" style="animation-delay:.08s">
            <span class="text-gold-400"><?= icon('user', 'h-8 w-8') ?></span>
            <h2 class="mt-5 font-serif text-2xl text-white">Já sou cliente</h2>
            <p class="mt-2 flex-1 text-sm text-ink-400">Entre para usar seus dados salvos. Sua sacola é mantida.</p>
            <a href="<?= e(url('auth/login.php?redirect=checkout.php')) ?>" class="btn-outline mt-6">Entrar</a>
        </div>
        <div class="panel flex flex-col p-8 animate-fade-up" style="animation-delay:.16s">
            <span class="text-gold-400"><?= icon('bag', 'h-8 w-8') ?></span>
            <h2 class="mt-5 font-serif text-2xl text-white">Continuar como convidado</h2>
            <p class="mt-2 flex-1 text-sm text-ink-400">Compre sem cadastro. Você recebe o comprovante por e-mail.</p>
            <a href="<?= e(url('checkout.php?guest=1')) ?>" class="btn-outline mt-6">Continuar</a>
        </div>
    </div>

<?php else: ?>
    <?php if (!empty($errors['_global'])): ?>
        <div class="alert alert-error mb-8"><?= e($errors['_global']) ?></div>
    <?php endif; ?>

    <form action="<?= e(url('checkout.php')) ?>" method="post" class="grid gap-10 lg:grid-cols-3" novalidate
          data-checkout data-shipping-url="<?= e(url('checkout.php')) ?>?action=shipping" data-coupon-url="<?= e(url('checkout.php?action=coupon')) ?>">
        <?= csrf_field() ?>

        <div class="space-y-10 lg:col-span-2">
            <!-- Dados pessoais -->
            <fieldset class="panel p-6 sm:p-8">
                <legend class="sr-only">Seus dados</legend>
                <h2 class="text-xs font-medium uppercase tracking-[0.24em] text-white">1 · Seus dados</h2>
                <?php if ($isGuest): ?>
                    <p class="mt-2 text-xs text-ink-500">Comprando como convidado ·
                        <a class="text-gold-400 hover:text-gold-300" href="<?= e(url('auth/login.php?redirect=checkout.php')) ?>">entrar na conta</a></p>
                <?php endif; ?>
                <div class="mt-6 grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="field-label" for="name">Nome completo *</label>
                        <input id="name" name="name" class="field<?= $inv('name') ?>" required autocomplete="name" value="<?= $v('name') ?>">
                        <?= $err('name') ?>
                    </div>
                    <div>
                        <label class="field-label" for="email">E-mail (comprovante) *</label>
                        <input id="email" name="email" type="email" class="field<?= $inv('email') ?>" required autocomplete="email" value="<?= $v('email') ?>" <?= $user ? 'readonly' : '' ?>>
                        <?= $err('email') ?>
                    </div>
                    <div>
                        <label class="field-label" for="phone">Telefone (DDD + número) *</label>
                        <input id="phone" name="phone" type="tel" class="field<?= $inv('phone') ?>" required autocomplete="tel" placeholder="(11) 91234-5678" data-mask="phone" value="<?= $v('phone') ?>">
                        <?= $err('phone') ?>
                    </div>
                    <div>
                        <label class="field-label" for="cpf">CPF *</label>
                        <input id="cpf" name="cpf" class="field<?= $inv('cpf') ?>" required inputmode="numeric" placeholder="000.000.000-00" data-mask="cpf" value="<?= $v('cpf') ?>">
                        <?= $err('cpf') ?>
                    </div>
                </div>
            </fieldset>

            <!-- Endereço -->
            <fieldset class="panel p-6 sm:p-8">
                <legend class="sr-only">Endereço de entrega</legend>
                <h2 class="text-xs font-medium uppercase tracking-[0.24em] text-white">2 · Endereço de entrega</h2>
                <?php $savedAddresses = $user ? user_addresses((int) $user['id']) : []; ?>
                <?php if ($savedAddresses): ?>
                    <div class="mt-4">
                        <label class="field-label" for="saved-address">Usar endereço salvo</label>
                        <select id="saved-address" class="field" data-address-picker>
                            <option value="">— Escolha —</option>
                            <?php foreach ($savedAddresses as $sa): ?>
                                <option value="<?= (int) $sa['id'] ?>" data-address='<?= e(json_encode(array_intersect_key($sa, array_flip(['cep', 'street', 'number', 'complement', 'district', 'city', 'state'])), JSON_UNESCAPED_UNICODE)) ?>'>
                                    <?= e($sa['label'] . ' — ' . $sa['street'] . ', ' . $sa['number'] . ' · ' . $sa['city'] . '/' . $sa['state']) ?><?= (int) $sa['is_default'] ? ' (padrão)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="mt-6 grid gap-5 sm:grid-cols-6">
                    <div class="sm:col-span-2">
                        <label class="field-label" for="cep">CEP *</label>
                        <!-- data-viacep: ativa o autocompletar via ViaCEP (assets/js/app.js) -->
                        <input id="cep" name="cep" class="field<?= $inv('cep') ?>" required inputmode="numeric" placeholder="00000-000" data-mask="cep" data-viacep autocomplete="postal-code" value="<?= $v('cep') ?>">
                        <?= $err('cep') ?>
                        <p class="mt-1 text-xs text-gold-300" data-cep-status></p>
                    </div>
                    <div class="sm:col-span-4">
                        <label class="field-label" for="street">Logradouro *</label>
                        <input id="street" name="street" class="field<?= $inv('street') ?>" required autocomplete="address-line1" value="<?= $v('street') ?>">
                        <?= $err('street') ?>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="field-label" for="number">Número *</label>
                        <input id="number" name="number" class="field<?= $inv('number') ?>" required value="<?= $v('number') ?>">
                        <?= $err('number') ?>
                    </div>
                    <div class="sm:col-span-4">
                        <label class="field-label" for="complement">Complemento</label>
                        <input id="complement" name="complement" class="field" autocomplete="address-line2" placeholder="Apto, bloco… (opcional)" value="<?= $v('complement') ?>">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="field-label" for="district">Bairro *</label>
                        <input id="district" name="district" class="field<?= $inv('district') ?>" required value="<?= $v('district') ?>">
                        <?= $err('district') ?>
                    </div>
                    <div class="sm:col-span-3">
                        <label class="field-label" for="city">Cidade *</label>
                        <input id="city" name="city" class="field<?= $inv('city') ?>" required autocomplete="address-level2" value="<?= $v('city') ?>">
                        <?= $err('city') ?>
                    </div>
                    <div class="sm:col-span-1">
                        <label class="field-label" for="state">UF *</label>
                        <select id="state" name="state" class="field<?= $inv('state') ?>" required>
                            <option value="">—</option>
                            <?php foreach (brazil_states() as $uf): ?>
                                <option value="<?= $uf ?>" <?= ($old['state'] ?? '') === $uf ? 'selected' : '' ?>><?= $uf ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= $err('state') ?>
                    </div>
                    <?php if ($user): ?>
                        <label class="flex items-center gap-3 text-sm text-ink-300 sm:col-span-6">
                            <input type="checkbox" name="save_address" value="1" class="accent-[#d4af37]" <?= $savedAddresses ? '' : 'checked' ?>> Salvar este endereço em Minha conta
                        </label>
                    <?php endif; ?>
                </div>
            </fieldset>

            <!-- Pagamento -->
            <fieldset class="panel p-6 sm:p-8">
                <legend class="sr-only">Pagamento</legend>
                <h2 class="text-xs font-medium uppercase tracking-[0.24em] text-white">3 · Pagamento</h2>
                <?php if (payment_provider() === 'infinitepay'): ?>
                    <input type="hidden" name="payment_method" value="infinitepay">
                    <div class="choice mt-6 flex items-start gap-4 !cursor-default !border-gold-400/60 !bg-gold-400/5">
                        <span class="flex gap-1 text-gold-400"><?= icon('pix') ?><?= icon('card') ?></span>
                        <span>
                            <span class="block text-white">PIX ou cartão de crédito em até 12x</span>
                            <span class="mt-1 block text-xs text-ink-400">Você escolhe a forma de pagamento no ambiente seguro da InfinitePay e volta para a loja ao concluir.</span>
                        </span>
                    </div>
                <?php else: ?>
                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <label class="choice flex items-start gap-4">
                        <input type="radio" name="payment_method" value="pix" class="mt-1 accent-[#d4af37]" <?= ($old['payment_method'] ?? '') === 'pix' ? 'checked' : '' ?>>
                        <span>
                            <span class="flex items-center gap-2 text-white"><span class="text-gold-400"><?= icon('pix') ?></span> PIX</span>
                            <span class="mt-1 block text-xs text-ink-400">QR code na hora, aprovação em segundos.</span>
                        </span>
                    </label>
                    <label class="choice flex items-start gap-4">
                        <input type="radio" name="payment_method" value="card" class="mt-1 accent-[#d4af37]" <?= ($old['payment_method'] ?? '') === 'card' ? 'checked' : '' ?>>
                        <span>
                            <span class="flex items-center gap-2 text-white"><span class="text-gold-400"><?= icon('card') ?></span> Cartão de crédito</span>
                            <span class="mt-1 block text-xs text-ink-400">Até 12x pelo Checkout Pro do Mercado Pago.</span>
                        </span>
                    </label>
                </div>
                <?php endif; ?>
                <label class="mt-5 flex items-center gap-3 text-sm text-ink-400" data-new-tab-option>
                    <input type="checkbox" name="open_new_tab" value="1" class="accent-[#d4af37]">
                    Abrir o pagamento da <?= e(payment_provider_name()) ?> em uma nova aba
                </label>
            </fieldset>
        </div>

        <!-- Resumo do pedido -->
        <aside class="panel h-fit p-6 lg:sticky lg:top-28">
            <h2 class="text-xs font-medium uppercase tracking-[0.24em] text-white">Seu pedido</h2>
            <ul class="mt-6 space-y-4">
                <?php foreach ($cart['items'] as $item): ?>
                    <li class="flex items-center gap-3">
                        <div class="h-14 w-12 shrink-0 overflow-hidden rounded-md bg-ink-900"><?= product_img($item, 'h-full w-full object-cover') ?></div>
                        <div class="flex-1 text-sm">
                            <p class="text-white"><?= e($item['name']) ?><?= $item['variant_label'] ? ' <span class="text-gold-300">· ' . e($item['variant_label']) . '</span>' : '' ?></p>
                            <p class="text-xs text-ink-500"><?= (int) $item['quantity'] ?> × <?= money($item['price']) ?></p>
                        </div>
                        <span class="text-sm text-ink-200"><?= money($item['line_total']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="gold-divider my-6"></div>
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between"><dt class="text-ink-400">Subtotal</dt><dd class="text-white" data-subtotal><?= money($cart['subtotal']) ?></dd></div>
                <div class="flex justify-between <?= $preview['discount'] > 0 ? '' : 'hidden' ?>" data-discount-row>
                    <dt class="text-ink-400">Desconto <span class="text-[11px] text-gold-300" data-coupon-desc><?= $preview['coupon'] ? e($preview['coupon']['code'] . ' · ' . coupon_label($preview['coupon'])) : '' ?></span></dt>
                    <dd class="text-emerald-300" data-discount>− <?= money($preview['discount']) ?></dd>
                </div>
                <div class="flex justify-between"><dt class="text-ink-400">Frete <span class="block text-[11px] text-ink-500" data-shipping-days><?= e($shippingPreview['days']) ?></span></dt>
                    <dd class="text-white" data-shipping><?= $shippingPreview['price'] > 0 ? money($shippingPreview['price']) : e($shippingPreview['label']) ?></dd></div>
            </dl>
            <div class="gold-divider my-6"></div>
            <div class="flex items-baseline justify-between">
                <span class="text-xs uppercase tracking-[0.2em] text-ink-400">Total</span>
                <span class="font-serif text-3xl text-white" data-total><?= money($preview['total']) ?></span>
            </div>

            <!-- Cupom de desconto: aplicado ANTES de ir para o pagamento -->
            <div class="mt-6 rounded-lg border border-white/10 p-4" data-coupon-box>
                <label class="field-label" for="coupon_code">Cupom de desconto</label>
                <div class="flex gap-2">
                    <input id="coupon_code" name="coupon_code" class="field !py-2.5 uppercase" maxlength="30" autocomplete="off"
                           placeholder="Digite o código" value="<?= e($preview['coupon']['code'] ?? '') ?>" <?= $preview['coupon'] ? 'readonly' : '' ?>>
                    <button type="submit" formaction="<?= e(url('checkout.php?action=coupon')) ?>" formnovalidate
                            class="btn-outline btn-sm shrink-0 <?= $preview['coupon'] ? 'hidden' : '' ?>" data-apply-coupon>Aplicar</button>
                    <button type="submit" name="remove_coupon" value="1" formaction="<?= e(url('checkout.php?action=coupon')) ?>" formnovalidate
                            class="btn-outline btn-sm shrink-0 !border-red-400/40 !text-red-300 <?= $preview['coupon'] ? '' : 'hidden' ?>" data-remove-coupon>Remover</button>
                </div>
                <p class="mt-2 text-xs" data-coupon-msg><?= $preview['coupon'] ? '<span class="text-emerald-300">Cupom aplicado: ' . e(coupon_label($preview['coupon'])) . '</span>' : '' ?></p>
            </div>
            <button type="submit" class="btn-gold mt-6 w-full" data-loading-text="Gerando pagamento…">Pagar com <?= e(payment_provider_name()) ?></button>
            <p class="mt-4 flex items-center justify-center gap-2 text-xs text-ink-500"><span class="text-gold-400"><?= icon('shield', 'h-4 w-4') ?></span> Dados protegidos · ambiente seguro</p>
        </aside>
    </form>
<?php endif; ?>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
