<?php
/**
 * King Decants — auth/logout.php
 * ---------------------------------------------------------------------------
 * Encerra a sessão do cliente: limpa $_SESSION, expira o cookie e destrói a
 * sessão no servidor. O carrinho do usuário continua salvo em cart_items e
 * volta no próximo login.
 *
 * Nota: logout via GET é aceitável neste MVP (o pior caso de um CSRF aqui é
 * deslogar o usuário). Para endurecer, troque o link por um <form method="post">
 * com csrf_field() e chame csrf_require() abaixo.
 * ---------------------------------------------------------------------------
 */
require dirname(__DIR__) . '/inc/bootstrap.php';

logout_user();

// Nova sessão limpa só para exibir a mensagem
kd_start_session();
flash('info', 'Você saiu da sua conta.');
redirect(url('index.php'));
