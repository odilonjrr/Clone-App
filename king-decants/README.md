# King Decants — E-commerce MVP (PHP puro + MySQL + Tailwind CDN)

Loja de decants de perfumes com estética de perfumaria de luxo (preto, dourado, serifa elegante), carrinho para convidado, contas de cliente, checkout com **Mercado Pago (PIX + Cartão / Checkout Pro)**, webhook de confirmação e **painel administrativo** com dashboard, pedidos, mini-CRM e exportação CSV.

- **Sem frameworks, sem build, sem TypeScript.** PHP 8.0+ puro, PDO, Tailwind via CDN, JavaScript vanilla.
- **4 produtos seed** aparecem na homepage logo após importar o `schema.sql`.
- Nenhum segredo real no código: tudo é **placeholder**.

---

## Sumário

1. [Requisitos](#1-requisitos)
2. [Estrutura de arquivos](#2-estrutura-de-arquivos)
3. [Instalação local (5 minutos)](#3-instalação-local)
4. [Configuração (`inc/config.php`) e proteção das chaves](#4-configuração-e-proteção-das-chaves)
5. [Mercado Pago: sandbox, webhook e produção](#5-mercado-pago)
6. [Painel admin](#6-painel-admin)
7. [E-mail (mail() ou SMTP com PHPMailer)](#7-e-mail)
8. [Deploy (cPanel/Plesk, VPS, Netlify)](#8-deploy)
9. [Segurança: o que já está implementado e o que fazer em produção](#9-segurança)
10. [Sessões PHP](#10-sessões-php)
11. [Fluxos de negócio](#11-fluxos-de-negócio)
12. [Personalização rápida](#12-personalização)
13. [Limitações conhecidas do MVP](#13-limitações-conhecidas)

---

## 1. Requisitos

| Item | Versão |
|---|---|
| PHP | 8.0 ou superior (testado em 8.3) |
| Extensões PHP | `pdo_mysql`, `curl`, `mbstring`, `json`, `openssl` |
| Banco | MySQL 5.7+/8.0 ou MariaDB 10.3+ (testado em MariaDB 10.11) |
| Servidor web | Apache 2.4 (com `.htaccess`) ou Nginx + PHP-FPM |
| Composer | **Opcional** (SDK do Mercado Pago e PHPMailer) |

---

## 2. Estrutura de arquivos

```
king-decants/
├── index.php               Homepage: banner, coleções, vitrine "Mais vendidos", Descobrir, A marca
├── product.php             Detalhe do produto (?slug=...)
├── cart.php                Sacola (GET) + ações add/update/remove/clear (POST, JSON p/ AJAX)
├── checkout.php            Escolha conta/convidado, formulário, validações e criação do pagamento MP
├── callback.php            Retorno do Checkout Pro + página do pedido + QR code PIX + polling
├── webhook.php             Notificações do Mercado Pago (Webhooks e IPN legado)
├── account.php             Minha conta (dados e pedidos)
├── auth/
│   ├── register.php        Cadastro (password_hash, CPF opcional validado)
│   ├── login.php           Login (página + modal rápido via AJAX, rate limit)
│   └── logout.php
├── admin/
│   ├── login.php           Login separado (tabela admin_users, sessão própria)
│   ├── index.php           Dashboard (métricas + gráfico 14 dias + atividade)
│   ├── orders.php          Pedidos: filtros por status, busca, período, paginação, ações rápidas
│   ├── order_view.php      Detalhe: itens, cliente, endereço, pagamentos, histórico, notas, ações
│   ├── customers.php       Mini-CRM: busca nome/e-mail/CPF/telefone, paginação
│   ├── customer_view.php   Ficha do cliente: pedidos, métricas, últimos contatos
│   ├── export.php          Exportação CSV (pedidos ou clientes, respeita filtros)
│   └── logout.php
├── inc/
│   ├── config.php          ⚙️ Configuração (DB, MP, e-mail) — placeholders
│   ├── bootstrap.php       Carrega tudo + sessão segura (incluído por todas as páginas)
│   ├── db.php              Conexão PDO (singleton, prepared statements reais)
│   ├── helpers.php         CSRF, sanitização, CPF/CEP/telefone, ViaCEP, frete, auth, rate limit, ícones
│   ├── cart.php            Carrinho híbrido (sessão p/ convidado, tabela p/ logado) + migração
│   ├── orders.php          Pedidos, máquina de status, estoque, processamento de pagamento
│   ├── mercadopago.php     Opção A (SDK) e Opção B (cURL: createPreferenceCurl), PIX, assinatura
│   ├── mailer.php          E-mails transacionais (log / mail() / SMTP)
│   ├── header.php, footer.php           Layout da loja + modal de login rápido
│   ├── product_card.php                 Card de produto
│   ├── admin_header.php, admin_footer.php, admin_pagination.php, admin_filters.php
│   └── .htaccess           Bloqueia acesso web
├── assets/
│   ├── css/app.css         Componentes da identidade (botão dourado, campos, toasts, modal…)
│   ├── js/app.js           UX: AJAX da sacola, modal, máscaras, ViaCEP, checkout, PIX
│   ├── js/tailwind.config.js  Paleta ink/gold e fontes para o Tailwind CDN
│   └── img/                hero-bottle.svg, product-placeholder.svg, favicon.svg
├── storage/logs/           Logs (webhook, mercadopago, mail, checkout) — bloqueado na web
├── tools/create_admin.php  Cria/atualiza admin via linha de comando
├── schema.sql              Tabelas + 4 produtos seed + admin inicial
├── composer.json           Dependências opcionais
└── .htaccess               HTTPS (opcional), bloqueios e cabeçalhos de segurança
```

---

## 3. Instalação local

```bash
# 1) Banco de dados (cria o banco "deckstore", tabelas e seeds)
mysql -u root -p < schema.sql

# 2) Configure o acesso ao banco em inc/config.php (ou variáveis de ambiente)
#    DB_HOST, DB_NAME, DB_USER, DB_PASS

# 3) Suba o servidor embutido do PHP na pasta do projeto
php -S localhost:8000

# 4) Acesse
#    Loja:  http://localhost:8000
#    Admin: http://localhost:8000/admin   (admin@kingdecants.local / TroqueEstaSenha!2026)
```

> **Troque a senha do admin imediatamente:**
> `php tools/create_admin.php "Seu Nome" admin@kingdecants.local "NovaSenhaForte123"`

Sem as chaves do Mercado Pago a loja funciona normalmente (vitrine, sacola, contas, admin); o checkout mostra um aviso pedindo a configuração.

**Hospedagem sem `CREATE DATABASE` (cPanel):** crie o banco pelo painel, apague as duas primeiras linhas do `schema.sql` (`CREATE DATABASE` e `USE`) e importe pelo phpMyAdmin dentro do banco criado.

---

## 4. Configuração e proteção das chaves

Todas as constantes estão em **`inc/config.php`**. Cada uma é resolvida nesta ordem de prioridade:

1. **Variável de ambiente** com o mesmo nome (`MP_ACCESS_TOKEN`, `DB_PASS`…) — **recomendado**.
2. **Arquivo fora do webroot**: `../../kingdecants-config.php` (dois níveis acima de `inc/`), ou o caminho em `KD_CONFIG_FILE`.
3. O placeholder do próprio `inc/config.php`.

| Constante | Para que serve |
|---|---|
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_PORT` | Conexão MySQL |
| `MP_PUBLIC_KEY` | Chave pública (só seria usada no front com Bricks; hoje é apenas referência) |
| `MP_ACCESS_TOKEN` | **Segredo.** Usado só no servidor para criar preferências/pagamentos |
| `MP_WEBHOOK_SECRET` | "Assinatura secreta" do webhook (valida o header `x-signature`) |
| `MP_SANDBOX` | `true` usa `sandbox_init_point`; `false` em produção |
| `APP_URL` | URL pública **sem barra final** (usada em back_urls, notification_url e e-mails) |
| `APP_ENV` | `production` oculta erros detalhados |
| `SESSION_LIFETIME` | Expiração por inatividade (segundos) |
| `LOGIN_MAX_ATTEMPTS`, `LOGIN_WINDOW_MINUTES` | Rate limit de login |
| `FREE_SHIPPING_FROM` | Frete grátis a partir deste subtotal (0 desativa) |
| `MAIL_*`, `SMTP_*` | E-mail (ver seção 7) |

**Exemplos de como definir as variáveis**

```apache
# Apache (VirtualHost ou .htaccess, se permitido)
SetEnv MP_ACCESS_TOKEN "APP_USR-xxxxxxxx"
SetEnv DB_PASS "senha-do-banco"
```

```ini
; PHP-FPM (pool www.conf)
env[MP_ACCESS_TOKEN] = APP_USR-xxxxxxxx
env[DB_PASS] = senha-do-banco
```

```php
<?php // /home/usuario/kingdecants-config.php  (FORA do public_html)
define('DB_PASS', 'senha-do-banco');
define('MP_ACCESS_TOKEN', 'APP_USR-xxxxxxxx');
define('MP_WEBHOOK_SECRET', 'xxxxxxxx');
define('MP_SANDBOX', false);
define('APP_URL', 'https://www.seudominio.com.br');
define('APP_ENV', 'production');
```

Regras de ouro: nunca faça commit de chaves reais (o `.gitignore` já ignora `kingdecants-config.php` e `.env`), nunca envie o `ACCESS_TOKEN` ao navegador e use credenciais diferentes para teste e produção.

---

## 5. Mercado Pago

### 5.1 Duas opções de integração (escolha automática)

| Opção | Como ativar | Onde está |
|---|---|---|
| **A — SDK oficial (recomendada)** | `composer require mercadopago/dx-php` na raiz do projeto | `mp_create_preference()` em `inc/mercadopago.php` detecta `vendor/autoload.php` e usa `PreferenceClient` |
| **B — cURL (fallback, zero dependências)** | Nada a fazer | `createPreferenceCurl()` → `POST https://api.mercadopago.com/checkout/preferences` com `Authorization: Bearer MP_ACCESS_TOKEN` |

O **PIX com QR code na própria loja** usa `POST /v1/payments` via cURL (`mp_create_pix_payment()`), com header `X-Idempotency-Key` para evitar cobrança duplicada. A resposta traz `point_of_interaction.transaction_data.qr_code` (copia e cola), `qr_code_base64` (imagem) e `ticket_url`, que são exibidos em `callback.php`.

### 5.2 O que é enviado ao Mercado Pago

A preferência (cartão) inclui: itens (SKU, nome, quantidade, preço), frete como item separado (para o total bater com `orders.total`), comprador (nome, sobrenome, e-mail, telefone com DDD, CPF, CEP, logradouro e número), `back_urls` (success/failure/pending → `callback.php`), `auto_return=approved`, `external_reference` (= id do pedido), `notification_url` (só quando `APP_URL` é https), parcelamento em até 12x, exclusão de boleto e expiração em 24h.

### 5.3 Testando no sandbox

1. Em <https://www.mercadopago.com.br/developers/panel/app>, crie uma aplicação (produto: Checkout Pro / Pagamentos online).
2. Copie as **credenciais de teste** para `MP_PUBLIC_KEY` e `MP_ACCESS_TOKEN` e mantenha `MP_SANDBOX=true`.
3. Em **Contas de teste**, crie um **comprador de teste** e use-o para pagar (não use sua conta real no checkout de teste).
4. **Cartões de teste**: use os cartões listados na documentação do Mercado Pago ("Cartões de teste"). O **nome do titular** define o resultado — por exemplo, `APRO` = aprovado e `OTHE` = recusado. Consulte a lista atual de cartões e CPFs de teste na documentação, pois ela muda com o tempo.
5. **PIX em sandbox**: o QR code é gerado, mas normalmente não é possível pagá-lo de verdade. Para testar a aprovação, use cartão de teste no Checkout Pro ou o simulador de notificações do painel.
6. **Webhook em localhost**: o Mercado Pago não alcança `localhost`. Use um túnel (ex.: `cloudflared tunnel --url http://localhost:8000` ou `ngrok http 8000`), coloque a URL **https** do túnel em `APP_URL` e cadastre `https://SEU-TUNEL/webhook.php` no painel.
7. Mesmo sem webhook, ao voltar do Checkout Pro o `callback.php` consulta o pagamento na API e atualiza o pedido.

### 5.4 Configurando o webhook

1. Painel do MP → sua aplicação → **Webhooks** → **Configurar notificações**.
2. URL de teste e de produção: `https://SEU-DOMINIO/webhook.php`.
3. Eventos: **Pagamentos** (`payment`). Opcional: **Pedidos comerciais** (`merchant_order`) — também é tratado.
4. Copie a **assinatura secreta** para `MP_WEBHOOK_SECRET`. Com ela preenchida, notificações sem `x-signature` válido recebem **401**.
5. Use **"Simular notificação"** no painel e confira `storage/logs/webhook.log` e a tabela `webhook_logs`.

O que o `webhook.php` faz:
- aceita o formato novo (`{"type":"payment","data":{"id":"..."}}` + `?data.id=`) e o IPN legado (`?topic=payment&id=`);
- grava toda notificação em `webhook_logs` e em `storage/logs/webhook.log`;
- valida o HMAC-SHA256 do manifesto `id:{data.id};request-id:{x-request-id};ts:{ts};`;
- **não confia no corpo**: consulta `GET /v1/payments/{id}` e só então atualiza `payments`/`orders`;
- é **idempotente** (a mesma notificação repetida não baixa o estoque duas vezes) e confere se o valor pago bate com o total do pedido;
- responde 200 quando processa e 500 em erro interno (o MP reenvia).

Mapeamento de status: `approved` → **paid**; `cancelled` / `refunded` / `charged_back` → **cancelled**; `pending` / `in_process` / `authorized` → continua **pending**; `rejected` fica registrado em `payments`, mas o pedido segue **pending** para o cliente tentar de novo.

### 5.5 Indo para produção

1. Ative as credenciais de produção no painel (o MP pode pedir dados do negócio e homologação).
2. Troque `MP_ACCESS_TOKEN`/`MP_PUBLIC_KEY` pelas de **produção**, defina `MP_SANDBOX=false`, `APP_ENV=production` e `APP_URL` com **https**.
3. Para receber PIX, cadastre uma **chave PIX** na conta Mercado Pago.
4. Cadastre o webhook de produção e a assinatura secreta.
5. Faça uma compra real de valor baixo e estorne pelo painel do MP.

### 5.6 JavaScript: abrir o `init_point`

`assets/js/app.js` envia o checkout via `fetch` e recebe `{ok, redirect, init_point, order_url}`:
- **mesma aba** (padrão): `window.location.href = data.redirect`;
- **nova aba** (checkbox no checkout): a aba é aberta de forma síncrona no clique (evita bloqueio de pop-up), recebe o `init_point` e a aba original vai para a página do pedido;
- **PIX**: redireciona para `callback.php`, que mostra o QR code, o botão "copiar" e consulta o status a cada 5 s. Se o PIX expirar, há um botão "Gerar novo PIX". Para pedidos de cartão pendentes, a página oferece "Pagar com PIX" como alternativa.

Sem JavaScript, o formulário faz POST normal e o servidor redireciona.

---

## 6. Painel admin

Acesse `/admin`. O login é **separado** do de clientes (tabela `admin_users` e cookie de sessão `KDADMINSESS`).

| Tela | Recursos |
|---|---|
| **Dashboard** | Total de pedidos, pedidos pagos, total faturado (soma de pagos/enviados/entregues), pendentes de envio, gráfico de 14 dias em CSS, estoque baixo e atividade recente |
| **Pedidos** | Abas por status com contadores, busca (nº, nome, e-mail, CPF, telefone), período, 20 por página, ações rápidas (enviado/entregue/cancelar) e exportação CSV com os filtros atuais |
| **Detalhe do pedido** | Itens, cliente (link para WhatsApp), endereço, pagamentos MP, linha do tempo de status, notas internas, código de rastreio, "Sincronizar pagamento" |
| **Clientes (mini-CRM)** | Busca por nome/e-mail/CPF/telefone, filtro conta/convidado, nº de pedidos, total pago, último pedido e CSV |
| **Ficha do cliente** | Métricas, histórico de pedidos, últimos contatos (nota/telefone/WhatsApp/e-mail), último endereço e CSV dos pedidos |

Transições permitidas: `pending → paid|cancelled`, `paid → shipped|cancelled`, `shipped → delivered`, `cancelled → paid` (pagamento aprovado depois de um cancelamento). Cancelar um pedido pago **devolve o estoque**, mas o **estorno do dinheiro** deve ser feito no painel do Mercado Pago.

Toda ação crítica exige token **CSRF** e é registrada em `admin_logs` (quem, o quê, quando e IP).

Os CSVs usam separador `;` e BOM UTF-8 (abrem direto no Excel em português), têm limite de 50.000 linhas e proteção contra *CSV injection*.

---

## 7. E-mail

`MAIL_DRIVER` em `inc/config.php`:

- `log` (padrão): grava os e-mails em `storage/logs/mail.log` — ideal para desenvolvimento.
- `mail`: usa a função `mail()` do PHP. Configure **SPF/DKIM** no domínio para não cair no spam.
- `smtp`: `composer require phpmailer/phpmailer` e preencha `SMTP_HOST`, `SMTP_PORT` (587 com `tls` ou 465 com `ssl`), `SMTP_USER`, `SMTP_PASS` e `MAIL_FROM`. Serve para Gmail Workspace, Zoho, Amazon SES, SendGrid, Brevo etc.

E-mails enviados: pedido recebido, pagamento confirmado, pedido enviado (com rastreio), entregue e cancelado.

---

## 8. Deploy

### cPanel / Plesk (hospedagem compartilhada)
1. Envie os arquivos para `public_html/` (ou para uma subpasta — ajuste `APP_URL`, por exemplo `https://site.com.br/loja`).
2. Crie o banco e o usuário MySQL no painel e importe o `schema.sql` pelo phpMyAdmin (veja a nota da seção 3).
3. Crie `kingdecants-config.php` **fora** do `public_html` com os valores reais.
4. Selecione PHP 8.x e habilite `pdo_mysql`, `curl` e `mbstring` (em "Selecionar versão do PHP").
5. Ative o SSL gratuito (AutoSSL/Let's Encrypt) e descomente o bloco "Força HTTPS" no `.htaccess`.
6. Dê permissão de escrita em `storage/logs` (755 ou 775, conforme o host).
7. Composer opcional: via terminal do cPanel, ou rode localmente e envie a pasta `vendor/`.

### VPS (Ubuntu + Nginx + PHP-FPM)
```bash
sudo apt install nginx php8.3-fpm php8.3-mysql php8.3-curl php8.3-mbstring mariadb-server certbot python3-certbot-nginx
sudo mysql < schema.sql
sudo certbot --nginx -d seudominio.com.br
```
```nginx
server {
    server_name seudominio.com.br;
    root /var/www/king-decants;
    index index.php;

    location ~ ^/(inc|storage|tools|vendor)/ { deny all; }
    location ~ /\.  { deny all; }
    location ~ \.(sql|md|log|lock)$ { deny all; }

    location / { try_files $uri $uri/ =404; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param MP_ACCESS_TOKEN "APP_USR-xxxxxxxx";   # ou env[] no pool do FPM
    }
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options SAMEORIGIN;
    add_header Strict-Transport-Security "max-age=31536000" always;
}
```

### Netlify / Vercel
**Não executam PHP nem MySQL** (são hospedagens estáticas/serverless em JS). Este projeto precisa de um host com PHP: hospedagem compartilhada, VPS ou PaaS com PHP (por exemplo Railway, Render com Docker, Laravel Forge + VPS, Hostinger, Locaweb, KingHost). Use a Netlify só para o domínio/DNS ou para uma landing page estática, se quiser.

---

## 9. Segurança

**Já implementado**
- **PDO com prepared statements** em todas as queries (`ATTR_EMULATE_PREPARES=false`). Os poucos valores interpolados (LIMIT/OFFSET, nome de coluna de data) são inteiros calculados ou listas fixas no servidor.
- **CSRF** (token por sessão + `hash_equals`) em registro, login, checkout, sacola e todas as ações do admin; nas requisições AJAX vai no header `X-CSRF-Token`.
- **Senhas** com `password_hash()` / `password_verify()` e rehash automático.
- **`session_regenerate_id(true)`** no login de cliente e de admin; sessões separadas para loja e admin.
- **Rate limit** de login (tabela `login_attempts`): 5 falhas por IP **ou** e-mail em 15 minutos bloqueiam novas tentativas; o sucesso limpa o contador. Para mais robustez: fail2ban, Cloudflare Rate Limiting ou Redis.
- **Sanitização** server-side (`clean_string`, `only_digits`) + **validações** (CPF com dígitos verificadores, CEP, e-mail, telefone com DDD, UF, senha forte).
- **Escape de saída** com `e()` (htmlspecialchars) em todo dado exibido.
- Preços e frete **sempre recalculados no servidor**; estoque revalidado com `SELECT … FOR UPDATE`.
- Webhook com **assinatura HMAC**, consulta à API e conferência do valor pago.
- Página do pedido de convidado protegida por **token aleatório de 160 bits**.
- `.htaccess` bloqueia `inc/`, `storage/`, `tools/`, `vendor/`, `.sql`, `.md`, `.log` e dotfiles; cabeçalhos `nosniff`, `X-Frame-Options` e `Referrer-Policy`.
- Redirecionamentos pós-login restritos a caminhos internos (anti open redirect).

**Checklist de produção**
- [ ] HTTPS obrigatório (descomente o bloco no `.htaccess`) e HSTS.
- [ ] `APP_ENV=production`, `MP_SANDBOX=false`.
- [ ] Chaves em variáveis de ambiente ou arquivo fora do webroot.
- [ ] Senha do admin trocada; usuário MySQL **sem** privilégios de root (apenas no banco `deckstore`).
- [ ] `MP_WEBHOOK_SECRET` preenchido.
- [ ] Backups automáticos do banco.
- [ ] Logs rotacionados (`storage/logs/*.log`).
- [ ] Considere Content-Security-Policy e gerar o CSS do Tailwind estaticamente (o Play CDN não é recomendado pela própria Tailwind para alto tráfego).

---

## 10. Sessões PHP

`inc/bootstrap.php` (função `kd_start_session()`) é incluído **antes de qualquer saída** em todas as páginas e:
- configura `session.use_strict_mode=1`, `use_only_cookies=1` e `gc_maxlifetime=SESSION_LIFETIME`;
- define o cookie `HttpOnly`, `SameSite=Lax` e `Secure` (automático quando detecta HTTPS);
- usa nomes diferentes: `KDSESS` (loja) e `KDADMINSESS` (admin);
- expira por **inatividade** (`SESSION_LIFETIME`, padrão 2h). Na expiração, a autenticação é descartada, mas o carrinho do convidado é preservado;
- não inicia sessão no `webhook.php` (chamada servidor-a-servidor).

Dica: em hospedagem compartilhada, se o `session.save_path` for compartilhado, o garbage collector de outros sites pode apagar sessões antes do tempo. Nesse caso defina um `session.save_path` próprio no `php.ini`/`.user.ini`.

---

## 11. Fluxos de negócio

**Carrinho:** o convidado usa `$_SESSION['cart']`. Ao **logar ou criar conta**, `login_user()` chama `cart_merge_session_into_user()`, que soma os itens na tabela `cart_items` (respeitando estoque e o limite de 10 por item). O carrinho logado persiste entre dispositivos.

**Finalizar compra sem login:** modal (ou tela no checkout) com **Criar conta & pagar**, **Entrar** ou **Continuar como convidado**.

**Pedido:** é criado como `pending` **antes** de chamar o MP (o id vira `external_reference`). Se o MP falhar, o pedido é cancelado automaticamente com a nota do erro. O estoque é baixado apenas quando o pagamento é aprovado (flag `stock_applied` evita baixa dupla).

**Convidados no CRM:** compras como convidado criam/atualizam um registro em `users` com `is_guest=1`. Se a pessoa se cadastrar depois com o mesmo e-mail, o registro vira conta. Por segurança (não há verificação de e-mail no MVP), pedidos feitos como convidado **não** aparecem em "Minha conta"; o cliente acessa pelo link do e-mail.

**Frete:** `shipping_quote()` em `inc/helpers.php` usa uma tabela fictícia pelo 1º dígito do CEP, com frete grátis acima de `FREE_SHIPPING_FROM`. Troque pela API dos Correios, Melhor Envio ou Frenet quando precisar.

**ViaCEP:** o checkout preenche o endereço automaticamente ao sair do campo CEP (atributo `data-viacep`; remova-o para desativar). Há também a versão servidor `viacep_lookup()`, com um bloco comentado em `checkout.php` para ativá-la.

---

## 12. Personalização

- **Cores e fontes:** `assets/js/tailwind.config.js` (paletas `ink` e `gold`) e as variáveis no topo de `assets/css/app.css`.
- **Imagem do banner:** substitua `assets/img/hero-bottle.svg` por uma fotografia publicitária com fundo preto (PNG/WebP); a máscara `.blend-edges` integra a imagem ao fundo.
- **Imagens dos produtos:** atualize `products.image_url`. Os seeds usam `https://via.placeholder.com/…`; se esse serviço estiver fora do ar, cada `<img>` cai automaticamente em `assets/img/product-placeholder.svg`.
- **Coleções:** função `collections()` em `inc/helpers.php` + coluna `products.collection`.
- **Textos institucionais e contato:** `index.php` (Descobrir / A marca) e `inc/footer.php`.

---

## 13. Limitações conhecidas

- Não há CRUD de produtos no admin (edite pelo phpMyAdmin/SQL). É a próxima evolução natural.
- Não há verificação de e-mail nem recuperação de senha.
- O frete é ilustrativo.
- O estorno de valores é feito no painel do Mercado Pago (o cancelamento no admin só ajusta o status e o estoque).
- Os valores de teste do Mercado Pago (cartões, CPFs, comportamento do PIX em sandbox) podem mudar: confirme sempre na documentação oficial antes de testar.
