# Delisys

Cardápio digital e gestor de pedidos sem comissão para restaurantes. Versão de teste.

## O que já funciona

| parte | onde | o que faz |
| --- | --- | --- |
| Cardápio do cliente | `/<loja>` | categorias, busca, mais pedidos, produto com adicionais e pizza meio a meio (preço do sabor mais caro), sacola salva no aparelho |
| Checkout | `/<loja>/checkout` | entrega por bairro ou retirada, localização opcional, dinheiro com troco, cartão na entrega, Pix |
| Pagamento Pix | `/r/<código>` | QR + copia e cola; modo teste com "Simular pagamento"; Asaas (sandbox) com webhook |
| Acompanhamento | `/r/<código>` | etapas ao vivo; mapa com o motoboy quando o pedido sai para entrega |
| Painel: pedidos | `/painel` | quadro Novos → Em preparação → Prontos → Saiu para entrega; som de pedido novo; abrir/fechar loja; escolher motoboy |
| Painel: motoboys | `/painel/motoboys` | cadastro, link secreto por motoboy, envio do link pelo WhatsApp, trocar link |
| Painel: cardápio | `/painel/cardapio` | categorias, produtos, grupos de opções, em falta |
| Painel: loja | `/painel/loja` | dados, pedido mínimo, tempo de entrega, formas de pagamento, bairros e taxas |
| Painel: avisos | `/painel/mensagens` | histórico das mensagens de WhatsApp |
| App do motoboy | `/m/<código>` | pedidos atribuídos, rota no Google Maps, ligar e WhatsApp do cliente, valor a cobrar e troco, "Saí para entrega", compartilhar localização, "Entreguei" |
| WhatsApp | automático (Cloud API) ou manual | aviso em **Pedido realizado → Em preparação → Pronto → Saiu para entrega**, este último com o link de rastreio |

## Rodar no computador

```bash
cd delisys
npm install
npm run dev          # http://localhost:3100 (banco local em .data/, sem configurar nada)
```

Abra `/painel/criar-conta`, marque "cardápio de exemplo" e use o link do cardápio.

## Testes

```bash
npm test             # regras: preço, meio a meio, etapas, telefone, mensagens, link do Maps
npm run e2e          # navegador: fluxo completo (cliente → painel → motoboy → rastreio → WhatsApp) e Pix
npm run typecheck
```

## Colocar no ar

Veja [docs/colocar-no-ar.md](docs/colocar-no-ar.md): Vercel + Neon, em uns 15 minutos.
Os modelos de mensagem do WhatsApp estão em [docs/whatsapp-modelos.md](docs/whatsapp-modelos.md).

## Segurança e privacidade

- **Senhas e sessões:**
  - senhas com scrypt; sessão em cookie `httpOnly` + `SameSite=Lax` + `Secure`, guardada só como hash;
  - recuperação de senha por link de uso único (30 min), que encerra as sessões abertas.
- **Limite de tentativas no Postgres** (vale para todas as instâncias), guardando só o hash do IP ou do e-mail:
  - login: 10 por IP e 8 por conta a cada 15 min;
  - criação de loja, pedidos, Pix e recuperação de senha também têm limite.
- **Isolamento:** cada loja só acessa os próprios dados; o motoboy só vê os pedidos dele; o cliente só vê o próprio pedido (links com códigos aleatórios de 144 bits).
- **Cabeçalhos:**
  - CSP com nonce por requisição (scripts só do próprio app);
  - HSTS, `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy` (só localização);
  - `Cache-Control: no-store` em rotas com dados pessoais.
- **LGPD:** política em `/privacidade`; limpeza diária (`/api/cron/limpeza`) apaga localização do motoboy, tentativas, sessões e links vencidos nos prazos da política.
- **Testes:** `tests/e2e/seguranca.spec.ts` cobre cabeçalhos/CSP, recuperação de senha, não revelar e-mails cadastrados e o limite por conta.

## Ainda não tem

- Horários de funcionamento automáticos. Hoje a loja abre e fecha por um botão no painel.
- Login do cliente por código, cashback, cupons, recuperador de clientes, relatórios, impressão automática da comanda e upload de foto (hoje a foto é um link).
- Ferramenta no painel para exportar ou apagar os dados de um cliente a pedido dele (LGPD). Hoje é feito por solicitação ao suporte.
- Asaas testado de verdade contra o sandbox.
