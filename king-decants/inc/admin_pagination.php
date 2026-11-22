<?php
/**
 * King Decants — inc/admin_pagination.php
 * Componente de paginação. Espera $page, $pages e $total já calculados por paginate().
 * Mantém os filtros atuais na query string (query_with).
 */
if (($pages ?? 1) <= 1) {
    echo '<p class="mt-4 text-xs text-ink-500">' . (int) ($total ?? 0) . ' registro(s)</p>';
    return;
}
$start = max(1, $page - 2);
$end = min($pages, $page + 2);
?>
<nav class="mt-6 flex flex-wrap items-center justify-between gap-3" aria-label="Paginação">
    <p class="text-xs text-ink-500"><?= (int) $total ?> registro(s) · página <?= (int) $page ?> de <?= (int) $pages ?></p>
    <div class="flex items-center gap-1">
        <?php if ($page > 1): ?>
            <a class="rounded-md border border-white/10 px-3 py-1.5 text-xs text-ink-300 hover:border-gold-400/50" href="<?= e(query_with(['page' => $page - 1])) ?>">Anterior</a>
        <?php endif; ?>
        <?php for ($i = $start; $i <= $end; $i++): ?>
            <a class="rounded-md border px-3 py-1.5 text-xs <?= $i === $page ? 'border-gold-400 bg-gold-400/10 text-gold-300' : 'border-white/10 text-ink-300 hover:border-gold-400/50' ?>" href="<?= e(query_with(['page' => $i])) ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($page < $pages): ?>
            <a class="rounded-md border border-white/10 px-3 py-1.5 text-xs text-ink-300 hover:border-gold-400/50" href="<?= e(query_with(['page' => $page + 1])) ?>">Próxima</a>
        <?php endif; ?>
    </div>
</nav>
