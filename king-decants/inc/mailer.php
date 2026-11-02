<?php
/**
 * King Decants — inc/mailer.php
 * ---------------------------------------------------------------------------
 * Envio de e-mails transacionais (pedido recebido / pago / enviado...).
 *
 * MAIL_DRIVER (inc/config.php):
 *   'log'  -> padrão de desenvolvimento: grava o e-mail em storage/logs/mail.log
 *   'mail' -> usa a função mail() do PHP (depende do sendmail/postfix do servidor;
 *             em hospedagens compartilhadas costuma funcionar, mas cai em spam
 *             sem SPF/DKIM configurados no domínio).
 *   'smtp' -> PHPMailer via Composer (recomendado em produção):
 *               composer require phpmailer/phpmailer
 *             e preencha SMTP_HOST/PORT/USER/PASS/SECURE.
 * ---------------------------------------------------------------------------
 */

/** Envia um e-mail HTML. Nunca lança exceção: retorna true/false e registra log. */
function send_mail(string $to, string $subject, string $html): bool
{
    try {
        switch (MAIL_DRIVER) {
            case 'smtp':
                if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                    app_log('mail', 'PHPMailer não instalado (composer require phpmailer/phpmailer). E-mail não enviado.', ['to' => $to]);
                    return false;
                }
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
                $mail->AltBody = trim(strip_tags(str_replace(['<br>', '</p>'], "\n", $html)));
                $mail->send();
                return true;

            case 'mail':
                $headers = [
                    'MIME-Version: 1.0',
                    'Content-Type: text/html; charset=UTF-8',
                    'From: ' . mb_encode_mimeheader(MAIL_FROM_NAME) . ' <' . MAIL_FROM . '>',
                ];
                return mail($to, mb_encode_mimeheader($subject), $html, implode("\r\n", $headers));

            default: // 'log'
                app_log('mail', "Para: {$to} | Assunto: {$subject}", ['html' => mb_substr(strip_tags($html), 0, 500)]);
                return true;
        }
    } catch (Throwable $e) {
        app_log('mail', 'Falha no envio', ['to' => $to, 'error' => $e->getMessage()]);
        return false;
    }
}

/** Template HTML simples, no estilo da marca (preto + dourado). */
function mail_layout(string $title, string $body): string
{
    return '<div style="background:#0b0b0b;padding:32px;font-family:Arial,sans-serif;color:#d6d3cd">'
        . '<div style="max-width:560px;margin:0 auto;border:1px solid rgba(212,175,55,.35);border-radius:12px;padding:32px">'
        . '<p style="color:#d4af37;letter-spacing:4px;font-size:12px;margin:0 0 24px">KING DECANTS</p>'
        . '<h1 style="color:#fff;font-family:Georgia,serif;font-weight:400;font-size:24px;margin:0 0 16px">' . e($title) . '</h1>'
        . $body
        . '<p style="font-size:12px;color:#8a867e;margin-top:32px">Luxo em cada gota.</p>'
        . '</div></div>';
}

/** E-mail disparado a cada mudança de status relevante do pedido. */
function mail_order_status(array $order): void
{
    $messages = [
        'paid'      => ['Pagamento confirmado', 'Recebemos o pagamento do seu pedido. Ele já está sendo preparado com todo cuidado.'],
        'shipped'   => ['Seu pedido foi enviado', 'Seu pedido saiu para entrega.' . ($order['tracking_code'] ? ' Código de rastreio: <strong>' . e($order['tracking_code']) . '</strong>.' : '')],
        'delivered' => ['Pedido entregue', 'Seu pedido foi entregue. Esperamos que cada gota seja memorável.'],
        'cancelled' => ['Pedido cancelado', 'Seu pedido foi cancelado. Se tiver dúvidas, responda este e-mail.'],
    ];
    if (!isset($messages[$order['status']])) {
        return;
    }
    [$title, $text] = $messages[$order['status']];
    $link = absolute_url('callback.php?order=' . $order['id'] . '&token=' . $order['access_token']);
    $body = '<p style="line-height:1.6">' . $text . '</p>'
        . '<p>Pedido <strong style="color:#fff">#' . (int) $order['id'] . '</strong> · Total <strong style="color:#fff">' . money($order['total']) . '</strong></p>'
        . '<p><a href="' . e($link) . '" style="display:inline-block;background:#d4af37;color:#000;padding:12px 20px;border-radius:6px;text-decoration:none;font-weight:bold">Ver pedido</a></p>';
    send_mail($order['customer_email'], $title . ' — Pedido #' . $order['id'], mail_layout($title, $body));
}

/** Confirmação de pedido recebido (status pending). */
function mail_order_created(array $order): void
{
    $link = absolute_url('callback.php?order=' . $order['id'] . '&token=' . $order['access_token']);
    $body = '<p style="line-height:1.6">Olá, ' . e(mp_split_name($order['customer_name'])[0]) . '! Recebemos seu pedido e estamos aguardando a confirmação do pagamento.</p>'
        . '<p>Pedido <strong style="color:#fff">#' . (int) $order['id'] . '</strong> · Total <strong style="color:#fff">' . money($order['total']) . '</strong></p>'
        . '<p><a href="' . e($link) . '" style="color:#d4af37">Acompanhar pedido</a></p>';
    send_mail($order['customer_email'], 'Recebemos seu pedido #' . $order['id'], mail_layout('Pedido recebido', $body));
}
