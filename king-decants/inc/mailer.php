<?php
/**
 * King Decants — inc/mailer.php
 * ---------------------------------------------------------------------------
 * E-mails automáticos para o cliente:
 *   - Pedido recebido (aguardando pagamento) ......... checkout.php
 *   - Pagamento confirmado ........................... status "paid"
 *   - Pagamento não aprovado / pedido expirado ....... status "cancelled" (webhook ou 24h sem pagar)
 *   - Pedido cancelado pela loja ..................... status "cancelled" (admin)
 *   - Pedido enviado (com código de rastreio) ........ status "shipped"
 *   - Pedido entregue ................................ status "delivered"
 *
 * MAIL_DRIVER (no kingdecants-config.php):
 *   'smtp' -> RECOMENDADO. Envia pela caixa de e-mail do seu domínio (ex.: Hostinger:
 *             SMTP_HOST smtp.hostinger.com, SMTP_PORT 465, SMTP_SECURE 'ssl').
 *             Cliente SMTP próprio, sem Composer. (Se o PHPMailer estiver instalado,
 *             ele é usado no lugar.)
 *   'mail' -> função mail() do PHP (funciona em muitas hospedagens, mas cai mais em spam).
 *   'log'  -> não envia; só grava em storage/logs/mail.log (desenvolvimento).
 *
 * Teste em Admin > E-mails (envio de teste e pré-visualização dos modelos).
 * Falha de e-mail NUNCA quebra a compra: só gera registro em storage/logs/mail.log.
 * ---------------------------------------------------------------------------
 */

/** Envia um e-mail HTML. Nunca lança exceção: retorna true/false e registra log. */
function send_mail(string $to, string $subject, string $html, ?string &$error = null): bool
{
    $error = null;
    if (!validate_email($to)) {
        $error = 'Destinatário inválido.';
        return false;
    }
    $text = mail_html_to_text($html);
    try {
        switch (MAIL_DRIVER) {
            case 'smtp':
                if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host       = SMTP_HOST;
                    $mail->Port       = (int) SMTP_PORT;
                    $mail->SMTPAuth   = true;
                    $mail->Username   = SMTP_USER;
                    $mail->Password   = SMTP_PASS;
                    $mail->SMTPSecure = SMTP_SECURE;
                    $mail->CharSet    = 'UTF-8';
                    $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
                    $mail->addAddress($to);
                    $mail->isHTML(true);
                    $mail->Subject = $subject;
                    $mail->Body    = $html;
                    $mail->AltBody = $text;
                    $mail->send();
                } else {
                    smtp_send_native($to, $subject, $html, $text);
                }
                app_log('mail', "Enviado (smtp) para {$to} | {$subject}");
                return true;

            case 'mail':
                $boundary = 'kd' . bin2hex(random_bytes(8));
                $headers = [
                    'MIME-Version: 1.0',
                    'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
                    'From: ' . mb_encode_mimeheader(MAIL_FROM_NAME, 'UTF-8') . ' <' . MAIL_FROM . '>',
                    'Reply-To: ' . MAIL_FROM,
                ];
                $ok = mail($to, mb_encode_mimeheader($subject, 'UTF-8'), mail_mime_body($boundary, $html, $text), implode("\r\n", $headers), '-f' . MAIL_FROM);
                if (!$ok) {
                    $error = 'A função mail() do servidor recusou o envio.';
                }
                app_log('mail', ($ok ? 'Enviado' : 'FALHA') . " (mail) para {$to} | {$subject}");
                return $ok;

            default: // 'log'
                app_log('mail', "[modo log, não enviado] Para: {$to} | Assunto: {$subject}", ['texto' => mb_substr($text, 0, 400)]);
                return true;
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
        app_log('mail', 'FALHA no envio', ['to' => $to, 'subject' => $subject, 'error' => $error]);
        return false;
    }
}

/** Corpo multipart (texto + HTML) codificado em base64. */
function mail_mime_body(string $boundary, string $html, string $text): string
{
    return "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($text))
        . "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html))
        . "--{$boundary}--\r\n";
}

function mail_html_to_text(string $html): string
{
    $html = preg_replace(['#<head>.*?</head>#is', '#<span style="display:none[^>]*>.*?</span>#is'], '', $html);
    $t = preg_replace(['/<(br|\/p|\/tr|\/h1|\/div)[^>]*>/i', '/<\/td>/i', '/<\/a>\s*<div/i'], ["\n", ' ', "</a>\n<div"], $html);
    $t = html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace(["/[ \t]+/", "/\n\s*\n+/"], [' ', "\n\n"], $t));
}

/**
 * Cliente SMTP mínimo (AUTH LOGIN, SSL na porta 465 ou STARTTLS na 587).
 * Lança RuntimeException com a resposta do servidor em caso de erro.
 */
function smtp_send_native(string $to, string $subject, string $html, string $text): void
{
    $secure = strtolower((string) SMTP_SECURE);
    $host = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . SMTP_HOST . ':' . (int) SMTP_PORT;
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => SMTP_HOST]]);
    $fp = @stream_socket_client($host, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        throw new RuntimeException("Não conectou em " . SMTP_HOST . ':' . SMTP_PORT . " ({$errstr}). Confira host, porta e SMTP_SECURE.");
    }
    stream_set_timeout($fp, 20);

    $read = function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $cmd = function (?string $line, array $expect, string $what) use ($fp, $read): string {
        if ($line !== null) {
            fwrite($fp, $line . "\r\n");
        }
        $resp = $read();
        if (!in_array((int) substr($resp, 0, 3), $expect, true)) {
            throw new RuntimeException("SMTP recusou {$what}: " . trim($resp ?: 'sem resposta'));
        }
        return $resp;
    };

    try {
        $helo = preg_replace('/[^a-z0-9.\-]/i', '', parse_url((string) APP_URL, PHP_URL_HOST) ?: 'localhost');
        $cmd(null, [220], 'a conexão');
        $cmd('EHLO ' . $helo, [250], 'o EHLO');
        if ($secure === 'tls') {
            $cmd('STARTTLS', [220], 'o STARTTLS');
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new RuntimeException('Falha ao iniciar TLS.');
            }
            $cmd('EHLO ' . $helo, [250], 'o EHLO');
        }
        $cmd('AUTH LOGIN', [334], 'o login');
        $cmd(base64_encode((string) SMTP_USER), [334], 'o usuário');
        $cmd(base64_encode((string) SMTP_PASS), [235], 'a senha (usuário/senha do e-mail incorretos?)');
        $cmd('MAIL FROM:<' . MAIL_FROM . '>', [250], 'o remetente (MAIL_FROM deve ser a mesma caixa do SMTP_USER)');
        $cmd('RCPT TO:<' . $to . '>', [250, 251], 'o destinatário');
        $cmd('DATA', [354], 'o DATA');

        $boundary = 'kd' . bin2hex(random_bytes(8));
        $domain = substr(strrchr(MAIL_FROM, '@'), 1) ?: 'localhost';
        $headers = [
            'Date: ' . date('r'),
            'From: ' . mb_encode_mimeheader(MAIL_FROM_NAME, 'UTF-8') . ' <' . MAIL_FROM . '>',
            'To: <' . $to . '>',
            'Reply-To: ' . MAIL_FROM,
            'Subject: ' . mb_encode_mimeheader($subject, 'UTF-8'),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];
        $message = implode("\r\n", $headers) . "\r\n\r\n" . mail_mime_body($boundary, $html, $text);
        // "dot-stuffing": linhas que começam com ponto ganham outro ponto
        $message = preg_replace('/^\./m', '..', $message);
        $cmd($message . "\r\n.", [250], 'a mensagem');
        fwrite($fp, "QUIT\r\n");
    } finally {
        fclose($fp);
    }
}

// ===========================================================================
// MODELOS
// ===========================================================================

/** Layout no estilo da marca (preto + dourado). Tabelas e estilos inline (compatível com Gmail/Outlook). */
function mail_layout(string $title, string $body, string $preheader = ''): string
{
    $site = absolute_url('index.php');
    return '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($title) . '</title></head>'
        . '<body style="margin:0;padding:0;background:#0b0b0b">'
        . '<span style="display:none;max-height:0;overflow:hidden;opacity:0">' . e($preheader) . '</span>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0b0b0b"><tr><td align="center" style="padding:32px 12px">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;border:1px solid #4a3d16;border-radius:12px;background:#111111;font-family:Arial,Helvetica,sans-serif;color:#d6d3cd">'
        . '<tr><td align="center" style="padding:28px 32px 8px"><a href="' . e($site) . '" style="text-decoration:none;color:#d4af37;font-family:Georgia,serif;font-size:24px;letter-spacing:6px">KING</a>'
        . '<div style="color:#b8952a;font-size:10px;letter-spacing:6px;margin-top:2px">DECANTS</div></td></tr>'
        . '<tr><td style="padding:16px 32px 32px">'
        . '<h1 style="color:#ffffff;font-family:Georgia,serif;font-weight:400;font-size:26px;margin:0 0 16px">' . e($title) . '</h1>'
        . $body
        . '</td></tr>'
        . '<tr><td style="border-top:1px solid #2c2b29;padding:18px 32px;font-size:12px;color:#8a867e;line-height:1.6">'
        . 'King Decants · Luxo em cada gota.<br>Dúvidas? Responda este e-mail.</td></tr>'
        . '</table></td></tr></table></body></html>';
}

function mail_button(string $href, string $label): string
{
    return '<p style="margin:24px 0"><a href="' . e($href) . '" style="display:inline-block;background:#d4af37;color:#000000;padding:13px 22px;border-radius:6px;text-decoration:none;font-weight:bold;font-size:13px;letter-spacing:1px">' . e($label) . '</a></p>';
}

function mail_p(string $html): string
{
    return '<p style="line-height:1.65;margin:0 0 14px;font-size:15px">' . $html . '</p>';
}

/** Resumo do pedido: itens, desconto, frete, total e endereço. */
function mail_order_summary(array $order): string
{
    $rows = '';
    foreach (order_items((int) $order['id']) as $it) {
        $rows .= '<tr><td style="padding:8px 0;border-bottom:1px solid #2c2b29;font-size:14px;color:#ffffff">'
            . (int) $it['quantity'] . '× ' . e($it['name']) . '</td>'
            . '<td align="right" style="padding:8px 0;border-bottom:1px solid #2c2b29;font-size:14px;white-space:nowrap">' . money($it['unit_price'] * $it['quantity']) . '</td></tr>';
    }
    $line = fn(string $label, string $value, string $color = '#d6d3cd') => '<tr><td style="padding:4px 0;font-size:13px;color:#8f8b83">' . $label . '</td><td align="right" style="padding:4px 0;font-size:13px;color:' . $color . '">' . $value . '</td></tr>';
    $rows .= $line('Subtotal', money($order['subtotal']));
    if ((float) ($order['discount'] ?? 0) > 0) {
        $rows .= $line('Desconto' . (!empty($order['coupon_code']) ? ' (' . e($order['coupon_code']) . ')' : ''), '− ' . money($order['discount']), '#6ee7b7');
    }
    $rows .= $line('Frete', (float) $order['shipping'] > 0 ? money($order['shipping']) : 'Grátis');
    $rows .= '<tr><td style="padding:10px 0 0;font-size:15px;color:#ffffff"><strong>Total</strong></td><td align="right" style="padding:10px 0 0;font-size:18px;color:#e6c76f"><strong>' . money($order['total']) . '</strong></td></tr>';

    $addr = e($order['street'] . ', ' . $order['number'] . ($order['complement'] ? ' — ' . $order['complement'] : ''))
        . '<br>' . e($order['district'] . ' · ' . $order['city'] . '/' . $order['state']) . '<br>CEP ' . e(format_cep($order['cep']));

    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:8px 0 20px;background:#0c0c0c;border:1px solid #2c2b29;border-radius:8px"><tr><td style="padding:16px 18px">'
        . '<div style="font-size:11px;letter-spacing:3px;color:#d4af37;margin-bottom:8px">PEDIDO #' . (int) $order['id'] . '</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $rows . '</table>'
        . '<div style="font-size:11px;letter-spacing:3px;color:#d4af37;margin:18px 0 6px">ENTREGA</div>'
        . '<div style="font-size:13px;line-height:1.6">' . e($order['customer_name']) . '<br>' . $addr . '</div>'
        . '</td></tr></table>';
}

function mail_order_link(array $order): string
{
    return absolute_url('callback.php?order=' . $order['id'] . '&token=' . $order['access_token']);
}

function mail_first_name(array $order): string
{
    return e(explode(' ', trim((string) $order['customer_name']))[0] ?: 'cliente');
}

/**
 * Monta [assunto, html] do e-mail de um pedido.
 * $kind: created | paid | not_approved | expired | cancelled | shipped | delivered
 */
function mail_order_message(string $kind, array $order): ?array
{
    $n = (int) $order['id'];
    $hi = 'Olá, ' . mail_first_name($order) . '! ';
    $link = mail_order_link($order);
    $summary = mail_order_summary($order);
    switch ($kind) {
        case 'created':
            return ["Recebemos seu pedido #{$n}", mail_layout('Pedido recebido',
                mail_p($hi . 'Recebemos seu pedido e estamos aguardando a confirmação do pagamento. Assim que for aprovado, você recebe outro e-mail.')
                . mail_p('<span style="color:#8f8b83;font-size:13px">Se ainda não concluiu o pagamento, use o botão abaixo. O pedido fica reservado por 24 horas.</span>')
                . $summary . mail_button($link, 'Acompanhar pedido'), 'Aguardando pagamento do pedido #' . $n)];
        case 'paid':
            return ["Pagamento aprovado — Pedido #{$n}", mail_layout('Pagamento aprovado',
                mail_p($hi . 'Seu pagamento foi <strong style="color:#6ee7b7">aprovado</strong>. Seu pedido já está sendo preparado com todo cuidado e você será avisado quando for enviado.')
                . $summary . mail_button($link, 'Ver pedido'), 'Pagamento confirmado — estamos preparando seu pedido')];
        case 'not_approved':
            return ["Pagamento não aprovado — Pedido #{$n}", mail_layout('Pagamento não aprovado',
                mail_p($hi . 'Infelizmente o pagamento do seu pedido <strong>não foi aprovado</strong> pela operadora, e o pedido foi cancelado. Nenhum valor foi cobrado.')
                . mail_p('Você pode tentar novamente com outro cartão ou pelo PIX.')
                . $summary . mail_button(absolute_url('index.php'), 'Comprar novamente'), 'O pagamento do pedido #' . $n . ' não foi aprovado')];
        case 'expired':
            return ["Pedido #{$n} não foi pago", mail_layout('Pagamento não identificado',
                mail_p($hi . 'Não identificamos o pagamento do seu pedido em até 24 horas, então ele foi cancelado e os produtos voltaram ao estoque.')
                . mail_p('Se ainda quiser as fragrâncias, é só fazer um novo pedido.')
                . $summary . mail_button(absolute_url('index.php'), 'Voltar à loja'), 'Seu pedido #' . $n . ' expirou')];
        case 'cancelled':
            return ["Pedido #{$n} cancelado", mail_layout('Pedido cancelado',
                mail_p($hi . 'Seu pedido foi cancelado. Se o pagamento já tinha sido feito, o estorno é processado pela operadora de pagamento.')
                . mail_p('Ficou com alguma dúvida? Responda este e-mail que a gente ajuda.')
                . $summary, 'Pedido #' . $n . ' cancelado')];
        case 'shipped':
            $tracking = trim((string) ($order['tracking_code'] ?? ''));
            $trackHtml = $tracking !== ''
                ? '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:6px 0 18px"><tr><td style="border:1px dashed #d4af37;border-radius:8px;padding:12px 18px"><div style="font-size:11px;letter-spacing:3px;color:#8f8b83">CÓDIGO DE RASTREIO</div><div style="font-size:20px;color:#ffffff;letter-spacing:2px;margin-top:4px">' . e($tracking) . '</div></td></tr></table>'
                    . mail_button('https://rastreamento.correios.com.br/app/index.php', 'Rastrear nos Correios')
                : '';
            return ["Seu pedido #{$n} foi enviado", mail_layout('Seu pedido foi enviado',
                mail_p($hi . 'Boa notícia: seu pedido saiu para entrega!') . $trackHtml . $summary
                . ($tracking === '' ? mail_button($link, 'Ver pedido') : ''), 'Pedido #' . $n . ' a caminho')];
        case 'delivered':
            return ["Pedido #{$n} entregue", mail_layout('Pedido entregue',
                mail_p($hi . 'Seu pedido foi entregue. Esperamos que cada gota seja memorável.')
                . mail_p('Que tal contar o que achou? Sua avaliação ajuda outros clientes.')
                . mail_button(absolute_url('index.php'), 'Visitar a loja'), 'Seu pedido #' . $n . ' chegou')];
    }
    return null;
}

/** Envia o e-mail de um tipo para o cliente do pedido. */
function mail_order_send(string $kind, array $order): bool
{
    $msg = mail_order_message($kind, $order);
    if (!$msg || empty($order['customer_email'])) {
        return false;
    }
    return send_mail($order['customer_email'], $msg[0], $msg[1]);
}

/** Confirmação de pedido recebido (status pending). */
function mail_order_created(array $order): void
{
    try {
        mail_order_send('created', $order);
    } catch (Throwable $e) {
        app_log('mail', 'Falha ao montar e-mail de pedido recebido', ['order' => $order['id'] ?? null, 'error' => $e->getMessage()]);
    }
}

/**
 * E-mail a cada mudança de status. $source/$note dizem o motivo do cancelamento:
 *   - webhook/callback (operadora recusou) ........ "pagamento não aprovado"
 *   - system + "Expirado..." ...................... "pagamento não identificado"
 *   - system + "Falha ao iniciar pagamento" ....... nenhum e-mail (o cliente vê o erro na tela)
 *   - admin ....................................... "pedido cancelado"
 */
function mail_order_status(array $order, string $source = '', ?string $note = null): void
{
    $kind = $order['status'];
    if ($kind === 'cancelled') {
        if (str_starts_with((string) $note, 'Falha ao iniciar pagamento')) {
            return;
        }
        if (str_starts_with((string) $note, 'Expirado')) {
            $kind = 'expired';
        } elseif (preg_match('/refunded|charged_back/', (string) $note)) {
            $kind = 'cancelled'; // estorno/chargeback: não é "pagamento recusado"
        } elseif (in_array($source, ['webhook', 'callback'], true)) {
            $kind = 'not_approved';
        }
    }
    if (!in_array($kind, ['paid', 'shipped', 'delivered', 'cancelled', 'expired', 'not_approved'], true)) {
        return;
    }
    mail_order_send($kind, $order);
}
