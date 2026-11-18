# Arquitetura: nome a definir (recriação das funções do Brendi: cardápio digital + gestor de pedidos)

Base: `brendi/replica/recon.md` (rev. 2) e `brendi/replica/features.csv`, de 2026-11-18.

**Decisões já tomadas:**
- SaaS próprio para vender a restaurantes.
- WhatsApp só pela Cloud API oficial.
- iFood, Saipos e outros marketplaces ou PDVs ficam fora do v1.
- Ordem de construção: núcleo do pedido primeiro (cardápio → checkout → quadro de pedidos). IA no WhatsApp e recompra ficam para a fase 2.

**Stack:** a mesma do projeto anterior deste repositório (`web/`), que você já tem montada.

## Stack

| camada | escolha | por quê |
| --- | --- | --- |
| web (cardápio + painel) | Next.js (App Router) + TypeScript, um app só | cardápio público com renderização no servidor (rápido no celular, bom para SEO); painel; rotas de API e webhooks no mesmo projeto |
| estilo | Tailwind + tokens do `/replica-design` | padrão do pacote |
| banco | Postgres no Supabase | um banco só; RLS, Realtime, Storage e Vault no mesmo lugar |
| ORM | Drizzle | SQL explícito, migrações em arquivo; `schema.sql` vira a migração 0001 |
| auth do restaurante | Supabase Auth (e-mail + Google) | integra com a RLS via `auth.uid()` |
| auth do cliente final | própria, por loja: celular + código de 6 dígitos | o cliente é da loja, não da plataforma; o mesmo celular em duas lojas são dois clientes. O código vai pelo WhatsApp (modelo de autenticação da Cloud API) ou por SMS (Twilio Verify) como reserva |
| tempo real | Supabase Realtime em `orders` | o quadro atualiza sozinho; o alerta sonoro toca no navegador |
| arquivos | Supabase Storage + transformação de imagem | fotos do cardápio, logo e capa em tamanhos para celular |
| endereços | Google Places Autocomplete + Geocoding, com a chave da plataforma | o mesmo que o original usa ("Powered by Google"); dá lat/lng para calcular a área de entrega |
| pagamentos dos pedidos | PSP brasileiro com **subcontas e split**. Recomendação: **Asaas**; alternativa: Pagar.me | Pix com webhook, cartão, o dinheiro cai na conta do restaurante e a plataforma fica com a taxa dela no split. Confirmar na documentação atual durante o `/replica-backend` |
| assinatura do SaaS | o mesmo PSP (assinatura recorrente) ou Stripe Billing | um fornecedor a menos se for o mesmo. Preço por faixa de faturamento, como no original |
| filas e agendamento | Inngest | expirar Pix, expirar cashback, recuperador diário, avisos de WhatsApp com retry |
| WhatsApp | WhatsApp Business Platform (Cloud API), número do restaurante via Embedded Signup | avisos de status, código de login e, na fase 2, a IA. Exige cadastro como Tech Provider na Meta |
| impressão | v1: comanda HTML em 80 mm + `window.print()` com impressão automática; v2: agente local de impressão | sem instalar nada no v1 |
| e-mail | Resend | convites da equipe, cobrança do SaaS |
| hospedagem | Vercel | deploy do Next.js; domínio próprio para o cardápio (`pedido.seudominio.com.br/{slug}`) |
| monitoramento | Sentry | erros de webhook e de checkout aparecem antes da reclamação |

Sem microsserviços: um app Next.js, um Postgres e um Inngest.

## Schema

Arquivo: `brendi/replica/schema.sql`.

- **Tamanho:** 35 tabelas.
- **Testado:** carregado num Postgres local (PGlite, com stub de `auth`) sem erro.
  - `next_order_number` gera números em sequência.
  - O banco recusa total que não fecha, entrega sem endereço e pedido duplicado.

**Acesso.** A RLS do Supabase vale para o painel: membro da organização → lojas dela.
- O **cardápio público e o checkout** rodam no servidor, com a service role. O código checa `is_published` e o horário da loja. O cliente final nunca recebe uma chave do banco.
- Tabelas só do servidor, sem política: `customer_sessions`, `store_counters` e `webhook_events`.

**Grupos de tabelas:**

| grupo | tabelas |
| --- | --- |
| tenant | organizations, org_members, subscriptions |
| loja | stores, store_counters, store_hours, store_hour_exceptions, delivery_zones, couriers, integrations, whatsapp_accounts |
| cardápio | categories, products (simples ou tamanho de pizza), option_groups, options, flavors, flavor_prices |
| cliente | customers, customer_addresses, customer_otps, customer_sessions, carts |
| pedido | orders, order_items, order_item_options, order_events, payments, webhook_events |
| marketing | coupons, coupon_redemptions, cashback_entries, campaigns, campaign_sends |
| conversa | conversations, messages |

**Regras que estão no banco, não na tela:**
- `total = subtotal + entrega − desconto − cashback` (check).
- Número do pedido único por loja, gerado por `UPDATE … RETURNING` (sem corrida).
- `unique (store_id, idempotency_key)`: clique duplo em "Fazer pedido" não cria dois pedidos.
- `unique (provider, event_id)` em `webhook_events`: webhook repetido não paga duas vezes.
- `unique (store_id, phone)` em customers; `unique (store_id, slug)` em produtos.
- Cashback é um livro-razão: o saldo é a soma, ganho sempre com validade, uso e expiração negativos.
- Pizza: `flavor_prices` guarda o preço da pizza **inteira** por sabor e tamanho. A meia é calculada pela regra do tamanho (`max` = maior valor, visto no original; `average` = média, opcional).

## API

Convenções:
- Cardápio público em `/(loja)/[slug]/...`, renderizado no servidor.
- Painel em `/painel/...` com Server Actions.
- Rotas HTTP só onde algo de fora chama: webhooks, Inngest e o navegador do cliente no checkout.

**"Quem":**
- **público:** qualquer um;
- **cliente:** sessão do cliente final da loja (cookie httpOnly);
- **membro:** usuário do painel com acesso à loja;
- **dono:** papel owner;
- **sistema:** webhook assinado ou Inngest.

### F01–F03: cliente pede (cardápio, sacola, checkout)

| método e caminho | faz | quem | entrada | saída | telas |
| --- | --- | --- | --- | --- | --- |
| GET `/[slug]` | cardápio com status aberto/fechado, cupom do topo, cashback e "Mais pedidos" | público | — | HTML | S01 |
| GET `/[slug]/produto/[produto]` | página do produto | público | — | HTML | S02 |
| GET `/[slug]/pizza/[categoria]/[tamanho]/[n]` | escolha de sabores | público | — | HTML | S03, S04 |
| POST `/api/lojas/[slug]/carrinho` | grava ou atualiza a sacola no servidor e **recalcula preços** | público | itens com opções e sabores | sacola com preços oficiais e erros por item | S06 |
| POST `/api/lojas/[slug]/frete` | acha a área pelo lat/lng (raio, bairro ou polígono) | público | lat, lng, bairro | taxa, prazo e mínimo, ou "fora da área" | S07 |
| POST `/api/lojas/[slug]/beneficios` | valida cupom e mostra o saldo de cashback | cliente | código, subtotal | desconto e saldo | S06 |
| POST `/api/lojas/[slug]/login/codigo` | envia o código (WhatsApp ou SMS), com limite de taxa | público | nome, celular | ok | S05 |
| POST `/api/lojas/[slug]/login/verificar` | confere o código e cria a sessão | público | celular, código | cookie | S05 |
| GET `/api/lojas/[slug]/enderecos` | endereços salvos do cliente | cliente | — | lista | S07 |
| POST `/api/lojas/[slug]/pedidos` | **cria o pedido**: revalida loja aberta, preços, área, cupom, cashback e mínimo; gera o número; se for pagamento online, cria a cobrança no PSP | cliente | sacola, entrega/retirada, endereço, pagamento, troco, cupom, usa cashback, `Idempotency-Key` | pedido e dados do Pix ou do cartão | S09 → S10 |
| GET `/[slug]/pedido/[id]` | acompanhamento (Realtime ou polling a cada 10 s) | cliente dono do pedido | — | HTML com status | S11 |
| GET `/api/lojas/[slug]/pedidos/[id]/pagamento` | estado do Pix (reserva, se o Realtime cair) | cliente | — | pending, paid ou expired | S10 |

### F04, F05: restaurante opera

| método e caminho | faz | quem | entrada | saída | telas |
| --- | --- | --- | --- | --- | --- |
| GET `/painel/pedidos` | quadro de 5 colunas com contador e soma; assina o Realtime | membro | filtros, busca (nome, telefone, código) | HTML | S21 |
| action `mudarStatus` | Aceitar, Pronto, Saiu, Finalizar; grava em `order_events`; dispara aviso no WhatsApp | membro | pedido, próximo status | pedido | S21 |
| action `recusarPedido` | recusa com motivo; estorna se pago; devolve o cashback usado | membro | pedido, motivo | pedido | S21 |
| action `atribuirEntregador` | define o motoboy | membro | pedido, entregador | pedido | S22 |
| action `criarPedidoManual` | pedido do balcão ou do telefone (canal manual) | membro | o mesmo do checkout | pedido | S23 |
| action `pausarLoja` / `altaDemanda` | `paused_until` / minutos a mais no prazo | membro | duração | loja | S21 |
| GET `/painel/pedidos/[id]/comanda` | HTML de 80 mm para imprimir | membro | — | HTML | S22 |
| GET `/painel/relatorios/diario` | fechamento: vendas, ticket médio, por forma de pagamento | membro | data | HTML | S24 |

### F06: restaurante monta a loja

| método e caminho | faz | quem | entrada | saída | telas |
| --- | --- | --- | --- | --- | --- |
| POST `/cadastro` | cria usuário, organização, primeira loja e teste de 15 dias | público | nome, e-mail, loja, slug | sessão | S35 |
| actions `loja.*` | perfil, horários, exceções, áreas, mínimo, pagamentos, retirada, agendamento, publicar | dono | formulários | loja | S31 |
| actions `cardapio.*` | CRUD de categorias, produtos, grupos, opções, sabores e preços por tamanho; reordenar; marcar "em falta" | membro | formulários | itens | S27 |
| POST `/api/upload` | URL assinada do Storage para foto | membro | tipo e tamanho | URL | S27, S31 |
| actions `cupons.*`, `cashback.config` | CRUD de cupons; percentual e validade do cashback | dono | formulários | — | S28, S29 |
| GET `/painel/divulgar` | link e QR code (gerado no servidor) | membro | — | PNG/SVG | S34 |
| actions `equipe.*` | convidar e remover membro | dono | e-mail, papel | — | S20 |
| actions `assinatura.*` | escolher faixa e ver faturas | dono | faixa | link do PSP | S35 |

### Webhooks

**Entrando** (todos gravam primeiro em `webhook_events`, depois processam):
- `POST /api/webhooks/psp`: Pix pago, Pix expirado, cartão aprovado ou recusado, estorno.
  - Muda `payments.status`.
  - Pedido `aguardando_pagamento` → `em_analise`.
  - Toca no quadro.
- `POST /api/webhooks/whatsapp`: status de entrega das mensagens; na fase 2, mensagens recebidas. Valida a assinatura `X-Hub-Signature-256`.
- `POST /api/webhooks/assinatura`: pagamento ou atraso da mensalidade do SaaS → `organizations.plan_status`.

**Saindo:** nenhum no v1. Um webhook "pedido criado" para PDVs pode vir depois.

### Jobs (Inngest)

| job | quando | faz |
| --- | --- | --- |
| `pedido.criado` | evento | aviso ao restaurante (push no navegador); WhatsApp "recebemos seu pedido" |
| `pedido.status` | evento | WhatsApp com o modelo de cada etapa; retry com backoff |
| `pix.expirar` | `sleepUntil(expires_at)` | se não pago: pedido → cancelado, devolve cashback e cupom |
| `pedido.finalizado` | evento | lança o cashback (`earn` com validade), atualiza `customers.order_count` e o total gasto |
| `cashback.expirar` | diário, 03:00 no fuso da loja | lança `expire` para os ganhos vencidos ainda não usados |
| `recuperador.diario` (fase 2) | diário, 10:00 | inativos com 21+ dias e carrinhos abandonados há 1 h+ → WhatsApp com cupom |
| `feedback.pos_entrega` (fase 2) | 1 h após finalizado | pergunta de satisfação |
| `limpeza` | diário | apaga OTPs e sessões vencidas, sacolas com 30+ dias |

Só APIs oficiais e públicas, com as chaves da sua empresa: PSP, Meta Cloud API, Google Maps Platform, Supabase, Inngest. **Nunca** os endpoints internos da Brendi vistos no navegador.

## As partes que mordem

- **Fuso horário:** o Brasil tem 4 fusos. "Abrimos hoje (quarta) às 18:00" e "Aberto até 18:00" saem de `stores.timezone`.
  - Horário que vira a meia-noite (18:00 → 02:00) conta no dia em que abriu.
  - Exceções de feriado têm prioridade.
  - Sem horário de verão hoje, mas use sempre a base de fusos (IANA), nunca um deslocamento fixo.
- **Preço:** o navegador nunca manda o preço. O servidor recalcula tudo no `/carrinho` e de novo no `/pedidos`:
  - opções, sabores com a regra do tamanho, cupom, cashback, frete e mínimo.
  - Se mudou entre a sacola e o pedido, devolve um erro claro ("o preço de X mudou").
- **Arredondamento da meia pizza:** "maior valor" é exato; "média" precisa de regra de centavos (arredondar para cima). Testes unitários com 1, 2 e 3 sabores.
- **Idempotência:** `Idempotency-Key` no checkout. Webhooks do PSP e da Meta chegam repetidos e fora de ordem, então se ignora a transição para um estado anterior.
- **Corridas:**
  - número do pedido (resolvido no banco);
  - limite semanal de cupom: contar em `coupon_redemptions` dentro da mesma transação, com `select … for update` na linha do cupom;
  - duas abas usando o mesmo saldo de cashback: transação + checar o saldo depois do lançamento.
- **Loja fecha durante o checkout:** checar de novo no `POST /pedidos`. Se fechou, recusar com mensagem e oferecer agendamento (se ligado).
- **Tempo real:** o Realtime pode cair numa aba aberta por horas.
  - Reconectar e buscar de novo o quadro ao voltar o foco.
  - O alerta sonoro precisa de um clique inicial: o navegador bloqueia áudio sem interação, então o painel mostra "Ativar som".
- **Multi-tenant:** toda consulta do painel passa pela RLS. O cardápio público usa service role, então todo handler público filtra por `store_id` vindo do slug, com teste automatizado para isso.
- **WhatsApp:**
  - mensagens iniciadas pelo negócio só por modelos aprovados;
  - a janela de 24 h vale para texto livre;
  - cada número exige verificação na Meta;
  - o onboarding (Embedded Signup) leva dias na primeira vez, o que é risco de prazo.
  - Plano B do v1: avisos com o link do pedido por SMS, ou só na página de acompanhamento.
- **Limites de taxa:** envio de código no máximo 1 por minuto e 5 por hora por celular e por IP; tentativas de código no máximo 5.
- **Imagens:** limite de 5 MB no upload, redimensionar para 800 px e WebP. O cardápio tem centenas de fotos.
- **Busca:** `ilike` no nome dentro da loja basta (cardápios têm menos de 500 itens). No painel, busca por código, nome ou telefone com índice.
- **LGPD:**
  - o restaurante é o controlador dos dados dos clientes dele; a plataforma é operadora (isso vai no contrato);
  - exportar e apagar cliente sob pedido (anonimizar `orders.customer_name/phone` e manter os valores para a contabilidade);
  - opt-out de marketing respeitado nos disparos.
- **Dinheiro do cliente:** com split, o PSP repassa direto ao restaurante, e a plataforma nunca segura o dinheiro. Isso evita virar instituição de pagamento, mas **confirme com um advogado** e com o PSP.

## Ordem de construção

1. **Recorte vertical** (prova a stack de ponta a ponta, ainda sem acabamento):
   - **Telas:** S01, S02, S06, S07 (só retirada), S08 (só "pagar na retirada"), S09, S21, S35, S27 (só nome e preço).
   - **Tabelas:** organizations, org_members, stores, store_counters, store_hours, categories, products, customers, orders, order_items, order_events.
   - **Rotas:** `/cadastro`, `cardapio.*` básico, `/[slug]`, `/[slug]/produto/[p]`, `/carrinho`, `/pedidos`, `/painel/pedidos` + `mudarStatus`.
   - **Pronto quando:** o dono cadastra 3 produtos, abre o link no celular, faz um pedido para retirada, e o card aparece em "Em Análise" no painel sozinho (Realtime) e anda até "Finalizados".

2. **Essenciais (must, 30)** por área:
   1. **Cardápio completo:** option_groups/options, pizza (S03, S04, flavors, flavor_prices), "Mais pedidos", "em falta", fotos.
   2. **Loja:** horários com exceções, aberto/fechado, pausar, áreas de entrega (raio e bairro primeiro, polígono depois), Google Places, mínimo.
   3. **Login do cliente** por código (S05). Começa por SMS se o WhatsApp ainda não estiver aprovado.
   4. **Pagamento na entrega** (dinheiro com troco, crédito, débito) e **Pix online** com webhook e expiração (S10, `payments`, `webhook_events`, `pix.expirar`).
   5. **Quadro completo:** card com tudo, recusar, comanda impressa, alerta sonoro.
   6. **Acompanhamento do pedido** (S11).

3. **Importantes (should, 20):**
   - cartão online;
   - vale-refeição;
   - cupons e cashback (sacola, resumo, `pedido.finalizado`, `cashback.expirar`);
   - "Peça também";
   - avisos por WhatsApp a cada etapa;
   - pedido manual;
   - alta demanda;
   - QR e link;
   - relatório diário;
   - assinatura do SaaS com teste de 15 dias.

4. **Opcionais (could, 14):**
   - atendente de IA no WhatsApp (mesma abordagem do projeto DM Champ: Claude via API, ferramentas `ver_cardapio`, `montar_pedido`, `gerar_pix`);
   - recuperador e disparos;
   - pontos;
   - várias lojas na mesma conta;
   - agendamento;
   - motoboys;
   - financeiro;
   - relatórios de lucro;
   - pesquisa pós-entrega;
   - indique e ganhe;
   - Pixel, GA e GTM.

5. **Correções do `/replica-entrepreneur`:** ainda não rodou. As reclamações reais de usuários de cardápios digitais vão reordenar os itens 3 e 4.
