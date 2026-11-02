<?php
/**
 * King Decants — inc/footer.php
 * ---------------------------------------------------------------------------
 * Rodapé da loja + MODAL DE LOGIN RÁPIDO (disponível em todas as páginas
 * para visitantes) + scripts.
 *
 * O modal envia o formulário via fetch() para auth/login.php com
 * "Accept: application/json". Em caso de sucesso, recarrega a página atual,
 * mantendo o usuário onde estava (o carrinho de convidado é migrado no login).
 * ---------------------------------------------------------------------------
 */
// Página atual (com query string) para voltar a ela após o login rápido.
$currentPath = ltrim(substr((string) ($_SERVER['REQUEST_URI'] ?? ''), strlen(base_path())), '/');
if (strlen($currentPath) > 300) {
    $currentPath = 'index.php';
}
?>
</main>

<!-- ======================= RODAPÉ ======================= -->
<footer class="mt-24 border-t border-gold-400/15">
    <div class="mx-auto grid max-w-7xl gap-12 px-5 py-16 sm:grid-cols-2 lg:grid-cols-4 lg:px-8">
        <div class="space-y-4">
            <a href="<?= e(url('index.php')) ?>" class="flex items-center gap-2 text-gold-400">
                <?= icon('crown', 'h-6 w-6') ?>
                <span class="font-serif text-xl tracking-[0.28em] text-gold-gradient">KING DECANTS</span>
            </a>
            <p class="max-w-xs text-sm leading-relaxed text-ink-400">Fragrâncias icônicas em decants envasados com precisão. Luxo em cada gota.</p>
        </div>
        <div>
            <h4 class="mb-4 text-xs uppercase tracking-[0.22em] text-gold-400">Loja</h4>
            <ul class="space-y-2.5 text-sm text-ink-400">
                <li><a class="hover:text-white" href="<?= e(url('index.php#mais-vendidos')) ?>">Mais vendidos</a></li>
                <li><a class="hover:text-white" href="<?= e(url('index.php#colecoes')) ?>">Coleções</a></li>
                <li><a class="hover:text-white" href="<?= e(url('cart.php')) ?>">Sacola</a></li>
            </ul>
        </div>
        <div>
            <h4 class="mb-4 text-xs uppercase tracking-[0.22em] text-gold-400">Atendimento</h4>
            <ul class="space-y-2.5 text-sm text-ink-400">
                <li><a class="hover:text-white" href="<?= e(url('account.php')) ?>">Minha conta</a></li>
                <li>contato@seudominio.com.br</li>
                <li>Seg. a sex., 9h às 18h</li>
            </ul>
        </div>
        <div>
            <h4 class="mb-4 text-xs uppercase tracking-[0.22em] text-gold-400">Pagamento seguro</h4>
            <div class="flex items-center gap-3 text-gold-400">
                <?= icon('pix', 'h-6 w-6') ?><?= icon('card', 'h-6 w-6') ?><?= icon('shield', 'h-6 w-6') ?>
            </div>
            <p class="mt-3 text-sm text-ink-400">PIX e cartão via Mercado Pago.</p>
        </div>
    </div>
    <div class="gold-divider"></div>
    <p class="py-6 text-center text-xs tracking-wider text-ink-500">© <?= date('Y') ?> King Decants. Todos os direitos reservados.</p>
</footer>

<?php if (!current_user()): ?>
<!-- ======================= MODAL DE LOGIN RÁPIDO ======================= -->
<div id="login-modal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="login-modal-title">
    <div class="modal-backdrop" data-close-modal></div>
    <div class="modal-panel">
        <button type="button" class="absolute right-4 top-4 text-ink-400 hover:text-white" data-close-modal aria-label="Fechar"><?= icon('close') ?></button>
        <p class="eyebrow">Bem-vindo de volta</p>
        <h2 id="login-modal-title" class="mt-2 font-serif text-3xl text-white">Entrar na <em class="text-gold-gradient">sua conta</em></h2>

        <form action="<?= e(url('auth/login.php')) ?>" method="post" class="mt-6 space-y-4" data-ajax-login>
            <?= csrf_field() ?>
            <input type="hidden" name="redirect" value="<?= e($currentPath ?: 'index.php') ?>">
            <div>
                <label class="field-label" for="modal-email">E-mail</label>
                <input id="modal-email" type="email" name="email" required autocomplete="email" class="field">
            </div>
            <div>
                <label class="field-label" for="modal-password">Senha</label>
                <input id="modal-password" type="password" name="password" required autocomplete="current-password" class="field">
            </div>
            <p class="hidden rounded-md border border-red-400/30 bg-red-400/10 px-3 py-2 text-sm text-red-300" data-error></p>
            <button type="submit" class="btn-gold w-full" data-loading-text="Entrando…">Entrar</button>
        </form>
        <p class="mt-6 text-center text-sm text-ink-400">
            Ainda não tem conta?
            <a href="<?= e(url('auth/register.php?redirect=' . urlencode($currentPath ?: 'index.php'))) ?>" class="text-gold-400 hover:text-gold-300">Criar conta</a>
        </p>
    </div>
</div>
<?php endif; ?>

<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
