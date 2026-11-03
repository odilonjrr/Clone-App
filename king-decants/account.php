<?php
/**
 * King Decants — account.php (Minha conta)
 * ---------------------------------------------------------------------------
 * Layout: barra lateral (avatar com iniciais, nome, nível e pontos, menu, Sair)
 * + conteúdo da seção escolhida em ?secao=
 *
 *   painel      Visão geral em cards (padrão)
 *   pedidos     Histórico de pedidos
 *   favoritos   Lista de favoritos
 *   enderecos   Endereços salvos (adicionar, editar, excluir, definir padrão)
 *   pagamentos  Formas de pagamento aceitas (cartões NÃO são armazenados pela loja)
 *   fidelidade  Pontos e nível (calculados dos pedidos pagos — inc/account.php)
 *
 * Pedidos feitos como convidado não aparecem aqui (ver auth/register.php).
 * ---------------------------------------------------------------------------
 */
require __DIR__ . '/inc/bootstrap.php';

$user = current_user();
if (!$user) {
    flash('info', 'Entre na sua conta para continuar.');
    redirect(url('auth/login.php?redirect=account.php'));
}
$uid = (int) $user['id'];

$sections = [
    'painel'     => ['Minha conta', 'user'],
    'pedidos'    => ['Histórico de pedidos', 'receipt'],
    'favoritos'  => ['Favoritos', 'heart'],
    'enderecos'  => ['Endereços', 'pin'],
    'pagamentos' => ['Formas de pagamento', 'card'],
    'fidelidade' => ['Fidelidade e recompensas', 'badge'],
];
$section = array_key_exists($_GET['secao'] ?? '', $sections) ? $_GET['secao'] : 'painel';
$sectionUrl = fn(string $s, array $extra = []) => url('account.php?' . http_build_query(['secao' => $s] + $extra));

// ------------------------------------------------------------------ Endereços (POST)
$addrErrors = [];
$addrOld = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = $_POST['action'] ?? '';
    $addressId = int_param($_POST, 'address_id') ?: null;

    if ($action === 'address_save') {
        [$data, $addrErrors] = address_from_input($_POST);
        if (!$addrErrors) {
            address_save($uid, $data, $addressId, isset($_POST['is_default']));
            flash('success', $addressId ? 'Endereço atualizado.' : 'Endereço adicionado.');
            redirect($sectionUrl('enderecos'));
        }
        $addrOld = $data + ['id' => $addressId];
        $section = 'enderecos';
    } elseif ($action === 'address_delete' && $addressId) {
        address_delete($uid, $addressId);
        flash('success', 'Endereço excluído.');
        redirect($sectionUrl('enderecos'));
    } elseif ($action === 'address_default' && $addressId) {
        address_set_default($uid, $addressId);
        flash('success', 'Endereço padrão atualizado.');
        redirect($sectionUrl('enderecos'));
    }
}

// ------------------------------------------------------------------ Dados
$loyalty = loyalty_summary($uid);

$cnt = db()->prepare('SELECT
    (SELECT COUNT(*) FROM orders WHERE user_id = ? AND is_guest_order = 0) AS orders,
    (SELECT COUNT(*) FROM wishlists w JOIN products p ON p.id = w.product_id WHERE w.user_id = ? AND p.is_active = 1) AS favorites,
    (SELECT COUNT(*) FROM user_addresses WHERE user_id = ?) AS addresses');
$cnt->execute([$uid, $uid, $uid]);
$counts = $cnt->fetch();

$orders = $favorites = $addresses = [];
if ($section === 'pedidos') {
    $stmt = db()->prepare('SELECT id, access_token, status, total, created_at, tracking_code,
                                  (SELECT COALESCE(SUM(quantity), 0) FROM order_items WHERE order_id = orders.id) AS items
                             FROM orders WHERE user_id = ? AND is_guest_order = 0 ORDER BY id DESC LIMIT 100');
    $stmt->execute([$uid]);
    $orders = $stmt->fetchAll();
} elseif ($section === 'favoritos') {
    $fav = db()->prepare('SELECT p.* FROM wishlists w JOIN products p ON p.id = w.product_id WHERE w.user_id = ? AND p.is_active = 1 ORDER BY w.created_at DESC');
    $fav->execute([$uid]);
    $favorites = $fav->fetchAll();
} elseif ($section === 'enderecos') {
    $addresses = user_addresses($uid);
    if (!$addrOld && ($editId = int_param($_GET, 'editar'))) {
        foreach ($addresses as $a) {
            if ((int) $a['id'] === $editId) {
                $addrOld = $a;
            }
        }
    }
}
$showAddressForm = $section === 'enderecos' && ($addrOld !== null || isset($_GET['novo']) || !$addresses);
$addrOld = $addrOld ?? ['label' => 'Casa', 'recipient' => $user['name']];
$av = fn(string $k) => e($addrOld[$k] ?? '');
$aerr = fn(string $k) => isset($addrErrors[$k]) ? '<p class="field-error">' . e($addrErrors[$k]) . '</p>' : '';

$pageTitle = $sections[$section][0] . ' — King Decants';
require __DIR__ . '/inc/header.php';
$firstName = explode(' ', trim($user['name']))[0];
?>

<section class="mx-auto max-w-7xl px-5 py-10 lg:px-8 lg:py-14">
    <div class="grid gap-8 lg:grid-cols-[300px_minmax(0,1fr)] lg:gap-10 xl:grid-cols-[330px_minmax(0,1fr)]">

        <!-- ======================= BARRA LATERAL ======================= -->
        <aside class="account-aside h-fit p-6 animate-fade-up lg:sticky lg:top-28">
            <div class="flex flex-col items-center text-center">
                <span class="avatar-ring h-28 w-28 text-3xl" aria-hidden="true"><?= e(user_initials($user['name'])) ?></span>
                <p class="mt-4 font-serif text-3xl text-white"><?= e($firstName) ?></p>
                <p class="mt-2 flex items-center gap-2 text-gold-300"><?= icon('crown', 'h-5 w-5') ?> Membro <?= e($loyalty['tier']) ?></p>
                <p class="mt-1 text-ink-200"><?= number_format($loyalty['points'], 0, ',', '.') ?> pontos</p>
            </div>
            <div class="gold-divider my-6"></div>
            <nav class="space-y-1" aria-label="Minha conta">
                <?php foreach ($sections as $key => [$label, $ic]): ?>
                    <a href="<?= e($sectionUrl($key)) ?>" class="account-nav <?= $section === $key ? 'is-active' : '' ?>" <?= $section === $key ? 'aria-current="page"' : '' ?>>
                        <span class="text-gold-400"><?= icon($ic, 'h-6 w-6') ?></span>
                        <span class="flex-1"><?= e($label) ?></span>
                        <?= icon('chevron', 'h-4 w-4 opacity-70') ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="gold-divider my-6"></div>
            <a href="<?= e(url('auth/logout.php')) ?>" class="btn-outline w-full !text-ink-100"><?= icon('logout', 'h-5 w-5 text-gold-400') ?> Sair</a>
        </aside>

        <!-- ======================= CONTEÚDO ======================= -->
        <div class="min-w-0">
<?php if ($section === 'painel'): ?>
            <p class="eyebrow">Minha conta</p>
            <h1 class="mt-3 font-serif text-5xl text-white sm:text-6xl">Olá, <?= e($firstName) ?></h1>
            <p class="mt-4 text-lg text-ink-200">Gerencie seus pedidos, favoritos, endereços, pagamentos e recompensas — tudo em um só lugar.</p>

            <div class="mt-10 grid gap-5 md:grid-cols-2">
                <?php
                $cards = [
                    ['pedidos', 'receipt', 'Histórico de pedidos', 'Veja e acompanhe todos os seus pedidos.', (int) $counts['orders']],
                    ['favoritos', 'heart', 'Favoritos', 'Salve seus decants preferidos para depois.', (int) $counts['favorites']],
                    ['enderecos', 'pin', 'Endereços', 'Gerencie seus endereços de entrega.', (int) $counts['addresses']],
                    ['pagamentos', 'card', 'Formas de pagamento', 'PIX e cartão com a segurança da ' . payment_provider_name() . '.', null],
                ];
                foreach ($cards as $i => [$key, $ic, $title, $desc, $badge]): ?>
                    <a href="<?= e($sectionUrl($key)) ?>" class="account-card group animate-fade-up" style="animation-delay: <?= $i * 60 ?>ms">
                        <span class="icon-ring"><?= icon($ic, 'h-8 w-8') ?></span>
                        <span class="min-w-0 flex-1">
                            <span class="block font-serif text-xl text-white xl:text-2xl"><?= e($title) ?></span>
                            <span class="mt-1 block text-ink-300"><?= e($desc) ?></span>
                        </span>
                        <?php if ($badge): ?><span class="count-ring"><?= $badge ?></span><?php endif; ?>
                        <?= icon('chevron', 'h-6 w-6 shrink-0 text-gold-300 transition-transform group-hover:translate-x-1') ?>
                    </a>
                <?php endforeach; ?>
                <a href="<?= e($sectionUrl('fidelidade')) ?>" class="account-card group animate-fade-up md:col-span-2" style="animation-delay: 240ms">
                    <span class="icon-ring"><?= icon('badge', 'h-9 w-9') ?></span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-serif text-2xl text-white">Fidelidade e recompensas</span>
                        <span class="mt-1 block text-ink-300">Veja seus pontos, seu nível de membro e como subir de nível.</span>
                    </span>
                    <?= icon('chevron', 'h-6 w-6 shrink-0 text-gold-300 transition-transform group-hover:translate-x-1') ?>
                </a>
            </div>

<?php elseif ($section === 'pedidos'): ?>
            <p class="eyebrow">Minha conta</p>
            <h1 class="mt-3 font-serif text-5xl text-white">Histórico de pedidos</h1>
            <div class="gold-divider my-8"></div>
            <?php if (!$orders): ?>
                <div class="account-card"><span class="icon-ring"><?= icon('receipt', 'h-9 w-9') ?></span><span class="flex-1 text-ink-300">Você ainda não fez pedidos com esta conta.</span><a class="btn-gold btn-sm" href="<?= e(url('index.php#mais-vendidos')) ?>">Ver decants</a></div>
            <?php else: ?>
                <div class="panel overflow-x-auto">
                    <table class="table-lux">
                        <thead><tr><th>Pedido</th><th>Data</th><th>Itens</th><th>Status</th><th class="text-right">Total</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($orders as $o): ?>
                            <tr>
                                <td class="text-white">#<?= (int) $o['id'] ?></td>
                                <td class="whitespace-nowrap text-ink-400"><?= date_br($o['created_at']) ?></td>
                                <td class="text-ink-400"><?= (int) $o['items'] ?></td>
                                <td><?= status_badge($o['status']) ?><?= $o['tracking_code'] ? '<p class="mt-1 text-xs text-ink-500">Rastreio: ' . e($o['tracking_code']) . '</p>' : '' ?></td>
                                <td class="whitespace-nowrap text-right text-ink-100"><?= money($o['total']) ?></td>
                                <td class="text-right"><a class="text-xs uppercase tracking-[0.18em] text-gold-400 hover:text-gold-300" href="<?= e(url('callback.php?order=' . $o['id'] . '&token=' . $o['access_token'])) ?>">Detalhes</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

<?php elseif ($section === 'favoritos'): ?>
            <p class="eyebrow">Minha conta</p>
            <h1 class="mt-3 font-serif text-5xl text-white">Favoritos</h1>
            <div class="gold-divider my-8"></div>
            <?php if (!$favorites): ?>
                <div class="account-card"><span class="icon-ring"><?= icon('heart', 'h-9 w-9') ?></span><span class="flex-1 text-ink-300">Nenhum favorito ainda. Toque no coração na página de um decant para salvá-lo aqui.</span></div>
            <?php else: ?>
                <div class="grid gap-4 sm:grid-cols-2">
                    <?php foreach ($favorites as $p): ?>
                        <div class="account-card !items-stretch">
                            <a href="<?= e(url('product.php?slug=' . urlencode($p['slug']))) ?>" class="h-28 w-24 shrink-0 overflow-hidden rounded-lg bg-ink-900"><?= product_img($p, 'h-full w-full object-cover') ?></a>
                            <div class="flex min-w-0 flex-1 flex-col">
                                <a href="<?= e(url('product.php?slug=' . urlencode($p['slug']))) ?>" class="truncate font-serif text-xl text-white hover:text-gold-300"><?= e($p['name']) ?></a>
                                <span class="text-sm text-ink-400"><?= e($p['concentration'] ?: $p['tagline']) ?></span>
                                <span class="mt-1 text-gold-300"><?= money($p['price']) ?></span>
                                <div class="mt-auto flex items-center gap-4 pt-2">
                                    <form action="<?= e(url('cart.php')) ?>" method="post" data-add-to-cart>
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="add"><input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="quantity" value="1">
                                        <button class="btn-gold btn-sm" <?= (int) $p['stock'] > 0 ? '' : 'disabled' ?>>Adicionar à sacola</button>
                                    </form>
                                    <form action="<?= e(url('wishlist.php')) ?>" method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="back" value="account">
                                        <button class="text-[11px] uppercase tracking-[0.16em] text-ink-500 hover:text-red-300">Remover</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

<?php elseif ($section === 'enderecos'): ?>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="eyebrow">Minha conta</p>
                    <h1 class="mt-3 font-serif text-5xl text-white">Endereços</h1>
                </div>
                <?php if (!$showAddressForm): ?>
                    <a href="<?= e($sectionUrl('enderecos', ['novo' => 1])) ?>" class="btn-gold btn-sm"><?= icon('plus', 'h-4 w-4') ?> Novo endereço</a>
                <?php endif; ?>
            </div>
            <div class="gold-divider my-8"></div>

            <?php if ($showAddressForm): ?>
                <form method="post" action="<?= e($sectionUrl('enderecos')) ?>" class="panel mb-8 grid gap-5 p-6 sm:grid-cols-6" data-loading novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="address_save">
                    <input type="hidden" name="address_id" value="<?= (int) ($addrOld['id'] ?? 0) ?>">
                    <p class="text-sm text-white sm:col-span-6"><?= !empty($addrOld['id']) ? 'Editar endereço' : 'Novo endereço' ?></p>
                    <div class="sm:col-span-2"><label class="field-label" for="a-label">Apelido</label><input id="a-label" name="label" class="field" placeholder="Casa, Trabalho…" value="<?= $av('label') ?>"></div>
                    <div class="sm:col-span-4"><label class="field-label" for="a-recipient">Quem recebe *</label><input id="a-recipient" name="recipient" class="field<?= isset($addrErrors['recipient']) ? ' is-invalid' : '' ?>" value="<?= $av('recipient') ?>"><?= $aerr('recipient') ?></div>
                    <div class="sm:col-span-2"><label class="field-label" for="a-cep">CEP *</label><input id="a-cep" name="cep" class="field<?= isset($addrErrors['cep']) ? ' is-invalid' : '' ?>" data-mask="cep" data-viacep inputmode="numeric" value="<?= $av('cep') ?>"><?= $aerr('cep') ?><p class="mt-1 text-xs text-gold-300" data-cep-status></p></div>
                    <div class="sm:col-span-4"><label class="field-label" for="a-street">Logradouro *</label><input id="a-street" name="street" class="field<?= isset($addrErrors['street']) ? ' is-invalid' : '' ?>" value="<?= $av('street') ?>"><?= $aerr('street') ?></div>
                    <div class="sm:col-span-2"><label class="field-label" for="a-number">Número *</label><input id="a-number" name="number" class="field<?= isset($addrErrors['number']) ? ' is-invalid' : '' ?>" value="<?= $av('number') ?>"><?= $aerr('number') ?></div>
                    <div class="sm:col-span-4"><label class="field-label" for="a-complement">Complemento</label><input id="a-complement" name="complement" class="field" value="<?= $av('complement') ?>"></div>
                    <div class="sm:col-span-2"><label class="field-label" for="a-district">Bairro *</label><input id="a-district" name="district" class="field<?= isset($addrErrors['district']) ? ' is-invalid' : '' ?>" value="<?= $av('district') ?>"><?= $aerr('district') ?></div>
                    <div class="sm:col-span-3"><label class="field-label" for="a-city">Cidade *</label><input id="a-city" name="city" class="field<?= isset($addrErrors['city']) ? ' is-invalid' : '' ?>" value="<?= $av('city') ?>"><?= $aerr('city') ?></div>
                    <div class="sm:col-span-1"><label class="field-label" for="a-state">UF *</label>
                        <select id="a-state" name="state" class="field<?= isset($addrErrors['state']) ? ' is-invalid' : '' ?>"><option value="">—</option>
                            <?php foreach (brazil_states() as $uf): ?><option value="<?= $uf ?>" <?= ($addrOld['state'] ?? '') === $uf ? 'selected' : '' ?>><?= $uf ?></option><?php endforeach; ?>
                        </select><?= $aerr('state') ?></div>
                    <label class="flex items-center gap-3 text-sm text-ink-200 sm:col-span-6"><input type="checkbox" name="is_default" value="1" class="accent-[#d4af37]" <?= !empty($addrOld['is_default']) ? 'checked' : '' ?>> Usar como endereço padrão</label>
                    <div class="flex gap-3 sm:col-span-6">
                        <button class="btn-gold btn-sm" data-loading-text="Salvando…">Salvar endereço</button>
                        <?php if ($addresses): ?><a href="<?= e($sectionUrl('enderecos')) ?>" class="btn-outline btn-sm">Cancelar</a><?php endif; ?>
                    </div>
                </form>
            <?php endif; ?>

            <div class="grid gap-4 md:grid-cols-2">
                <?php foreach ($addresses as $a): ?>
                    <div class="account-card !items-start">
                        <span class="icon-ring !h-14 !w-14"><?= icon('pin', 'h-6 w-6') ?></span>
                        <div class="min-w-0 flex-1 text-sm text-ink-300">
                            <p class="flex items-center gap-2 font-serif text-xl text-white"><?= e($a['label']) ?>
                                <?php if ((int) $a['is_default']): ?><span class="rounded-full border border-gold-400/50 px-2 py-0.5 font-sans text-[10px] uppercase tracking-wider text-gold-300">Padrão</span><?php endif; ?></p>
                            <p class="mt-1 text-ink-100"><?= e($a['recipient']) ?></p>
                            <p><?= e($a['street']) ?>, <?= e($a['number']) ?><?= $a['complement'] ? ' — ' . e($a['complement']) : '' ?></p>
                            <p><?= e($a['district']) ?> · <?= e($a['city']) ?>/<?= e($a['state']) ?> · CEP <?= e(format_cep($a['cep'])) ?></p>
                            <div class="mt-3 flex flex-wrap items-center gap-4 text-[11px] uppercase tracking-[0.16em]">
                                <a class="text-gold-400 hover:text-gold-300" href="<?= e($sectionUrl('enderecos', ['editar' => $a['id']])) ?>">Editar</a>
                                <?php if (!(int) $a['is_default']): ?>
                                    <form method="post" action="<?= e($sectionUrl('enderecos')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="address_default"><input type="hidden" name="address_id" value="<?= (int) $a['id'] ?>"><button class="uppercase tracking-[0.16em] text-ink-300 hover:text-white">Tornar padrão</button></form>
                                <?php endif; ?>
                                <form method="post" action="<?= e($sectionUrl('enderecos')) ?>" onsubmit="return confirm('Excluir este endereço?')"><?= csrf_field() ?><input type="hidden" name="action" value="address_delete"><input type="hidden" name="address_id" value="<?= (int) $a['id'] ?>"><button class="uppercase tracking-[0.16em] text-ink-500 hover:text-red-300">Excluir</button></form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="mt-6 text-xs text-ink-500">O endereço padrão é preenchido automaticamente no checkout.</p>

<?php elseif ($section === 'pagamentos'): ?>
            <p class="eyebrow">Minha conta</p>
            <h1 class="mt-3 font-serif text-5xl text-white">Formas de pagamento</h1>
            <p class="mt-4 text-lg text-ink-200">Todos os pagamentos são processados pela <?= e(payment_provider_name()) ?>. Por segurança, a King Decants não armazena dados de cartão.</p>
            <div class="gold-divider my-8"></div>
            <div class="grid gap-5 md:grid-cols-2">
                <div class="account-card !items-start">
                    <span class="icon-ring"><?= icon('pix', 'h-9 w-9') ?></span>
                    <span class="flex-1"><span class="block font-serif text-2xl text-white">PIX</span>
                        <span class="mt-1 block text-ink-300">QR code ou “copia e cola” gerado na hora. A confirmação costuma levar segundos.</span></span>
                </div>
                <div class="account-card !items-start">
                    <span class="icon-ring"><?= icon('card', 'h-9 w-9') ?></span>
                    <span class="flex-1"><span class="block font-serif text-2xl text-white">Cartão de crédito</span>
                        <span class="mt-1 block text-ink-300">Em até 12x no ambiente de pagamento seguro da <?= e(payment_provider_name()) ?>.</span></span>
                </div>
                <div class="account-card !items-start md:col-span-2">
                    <span class="icon-ring"><?= icon('shield', 'h-9 w-9') ?></span>
                    <span class="flex-1"><span class="block font-serif text-2xl text-white">Segurança</span>
                        <span class="mt-1 block text-ink-300">Conexão criptografada (HTTPS) em todo o site. Os dados do cartão são digitados diretamente no ambiente da <?= e(payment_provider_name()) ?> e nunca passam pelos nossos servidores.</span></span>
                </div>
            </div>

<?php else: /* fidelidade */ ?>
            <p class="eyebrow">Minha conta</p>
            <h1 class="mt-3 font-serif text-5xl text-white">Fidelidade e recompensas</h1>
            <div class="gold-divider my-8"></div>
            <div class="account-card !items-center">
                <span class="icon-ring"><?= icon('crown', 'h-9 w-9') ?></span>
                <div class="flex-1">
                    <p class="font-serif text-3xl text-white">Membro <span class="text-gold-gradient"><?= e($loyalty['tier']) ?></span></p>
                    <p class="mt-1 text-ink-300"><?= number_format($loyalty['points'], 0, ',', '.') ?> pontos · <?= (int) $loyalty['paid_orders'] ?> <?= $loyalty['paid_orders'] === 1 ? 'pedido pago' : 'pedidos pagos' ?></p>
                    <?php if ($loyalty['next_tier']): ?>
                        <div class="mt-4 h-2 overflow-hidden rounded-full bg-white/10"><div class="h-full rounded-full bg-gradient-to-r from-gold-600 to-gold-300" style="width: <?= (int) $loyalty['progress'] ?>%"></div></div>
                        <p class="mt-2 text-sm text-ink-400">Faltam <strong class="text-gold-300"><?= number_format($loyalty['next_at'] - $loyalty['points'], 0, ',', '.') ?> pontos</strong> para o nível <?= e($loyalty['next_tier']) ?>.</p>
                    <?php else: ?>
                        <p class="mt-3 text-sm text-gold-300">Você está no nível máximo. Obrigado pela preferência!</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <?php foreach (loyalty_tiers() as $name => $min): ?>
                    <div class="panel p-5 <?= $name === $loyalty['tier'] ? '!border-gold-400/60' : '' ?>">
                        <p class="font-serif text-2xl <?= $name === $loyalty['tier'] ? 'text-gold-300' : 'text-white' ?>"><?= e($name) ?></p>
                        <p class="text-sm text-ink-400">a partir de <?= number_format($min, 0, ',', '.') ?> pontos</p>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="panel mt-6 p-6 text-sm leading-relaxed text-ink-300">
                <p class="text-white">Como funciona</p>
                <ul class="mt-3 list-disc space-y-1 pl-5">
                    <li>Você ganha <?= LOYALTY_POINTS_PER_REAL ?> ponto a cada R$ 1,00 em pedidos pagos feitos com sua conta.</li>
                    <li>Os pontos aparecem assim que o pagamento é confirmado. Pedidos cancelados ou estornados não contam.</li>
                    <li>Seu nível sobe automaticamente conforme você acumula pontos.</li>
                </ul>
            </div>
<?php endif; ?>
        </div>
    </div>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
