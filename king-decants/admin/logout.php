<?php
/**
 * King Decants — admin/logout.php
 * Registra o logout em admin_logs e destrói apenas a sessão do admin
 * (a sessão de cliente, se existir, usa outro cookie e não é afetada).
 */
define('KD_ADMIN_AREA', true);
require dirname(__DIR__) . '/inc/bootstrap.php';

if (is_admin()) {
    admin_log('logout', 'admin', (int) current_admin()['id']);
}
$_SESSION = [];
$p = session_get_cookie_params();
setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
session_destroy();
redirect(url('admin/login.php'));
