# Mapa de recon: Brendi (web, cardápio do cliente + painel do restaurante)

Escopo: o loop central — o cliente final abre o cardápio digital da loja (link
ou WhatsApp), monta o pedido, paga (Pix, cartão ou na entrega) e o restaurante
recebe, aceita, despacha e conclui no gestor de pedidos. Em volta: editor de
cardápio, áreas e taxas de entrega, horários, cupons, fidelidade, recuperador
de clientes e o atendente de IA no WhatsApp.
Para: **a confirmar** — assumido como produto SaaS próprio para vender a
restaurantes (como o projeto anterior deste repositório).
Data: 2026-11-18

> **Como este mapa foi feito.** A política de rede deste ambiente bloqueou o
> acesso direto a `brendi.com.br`, `pedido.brendi.com.br` e a sites de
> terceiros (proxy respondeu 403 / DNS não resolveu). Tudo abaixo vem de
> **trechos indexados por busca na web** de páginas públicas: o site e o blog
> da própria Brendi, títulos de páginas de lojas em `pedido.brendi.com.br` e
> notícias. Nenhum login, nenhum código, nenhuma chamada de API privada.
> Não há capturas de tela em `replica/screens/`. O blog é material de marketing
> do fornecedor: descreve o que o produto faz, raramente como a tela é.
> Confiança: **alta** (página da Brendi descreve diretamente), **média**
> (marketing vago ou fonte única indireta), **palpite** (inferido do padrão de
> mercado, sem fonte).

## Fontes

| # | fonte | URL | o que deu |
| --- | --- | --- | --- |
| 1 | site: home | https://brendi.com.br/ | "Sistema de delivery para vender mais, sem comissão"; +8.500/9.500 restaurantes (varia por página) |
| 2 | site: planos | https://brendi.com.br/planos , https://brendi.com.br/nossos-planos | mínimo R$ 120/mês, todos os recursos; taxa de operação R$ 60/semana; Pix online R$ 0,40 + 0,5%/pedido; dinheiro/maquininha sem taxa |
| 3 | site: recuperador de vendas | https://brendi.com.br/recuperador-de-vendas | clientes sumidos + carrinho abandonado → WhatsApp com cupom |
| 4 | blog: preço | https://brendi.com.br/blog/quanto-custa-sistema-restaurantes-2026/ | mensalidade varia com faturamento, 15 dias grátis sem cartão, sem multa |
| 5 | blog: gestão de pedidos | https://brendi.com.br/blog/sistema-para-restaurantes-gestao-pedidos/ | gestor kanban, status ao vivo, aceitar/despachar/finalizar, módulo de motoboys |
| 6 | blog: cardápio integrado | https://brendi.com.br/blog/cardapio-digital-com-delivery-integrado/ | cardápio por link/WhatsApp sem app, checkout Pix+cartão, cross-sell 90 dias |
| 7 | blog: IA no WhatsApp | https://brendi.com.br/blog/ia-atendimento-whatsapp-restaurantes/ | Brenda: texto, áudio, imagem; sugere promoções; guia ao pedido |
| 8 | blog: atendimento automatizado | https://brendi.com.br/blog/atendimento-automatizado-whatsapp-delivery/ | comanda na cozinha, status ao cliente, feedback 1h–1h30 após entrega |
| 9 | blog: área por bairro | https://brendi.com.br/blog/melhores-sistemas-delivery-por-bairro/ | taxa e prazo por área, validação de endereço via Google Geocoding |
| 10 | blog: pizzaria | https://brendi.com.br/blog/como-criar-cardapio-digital-pizzaria/ , https://brendi.com.br/blog/ia-para-pizzaria-delivery-2026/ | meio a meio com preço automático, bordas por tamanho, regras de combo |
| 11 | blog: integração iFood | https://brendi.com.br/blog/sistema-delivery-integracao-ifood/ | importa cardápio, horários, áreas, perfil em até 24 h; integra iFood, 99Food, Saipos, Colibri, Meta Pixel, GA, GTM |
| 12 | blog: promoções automáticas | https://brendi.com.br/blog/cardapio-digital-promocoes-automaticas/ | galeria de cupons com limite semanal; IA escolhe o cupom |
| 13 | blog: fidelidade/recuperação | https://brendi.com.br/blog/como-implementar-sistema-para-restaurantes/ | pontos e cashback; cashback válido até 40 dias; inativo após ~21 dias, risco em ~7 |
| 14 | blog: pagamento online | https://brendi.com.br/blog/sistema-delivery-com-pagamento-online/ | Pix/cartão no cardápio; dinheiro e maquininha na entrega continuam |
| 15 | lojas reais (títulos/trechos) | https://pedido.brendi.com.br/pizzaria-saborella , https://pedido.brendi.com.br/pizzaria-familia , https://pedido.brendi.com.br/pizzaria-firenze-vila-passarelli/ | rota `/{slug}`, "Entrega 1 - 55min", "Pedido mínimo R$ 30,00" / "Sem pedido mínimo", categorias (combos, pizzas doces...) |
| 16 | checkout real (título/trecho) | https://pedido.brendi.com.br/ricotarella-esfiharia-diadema/checkout , https://menu.brendi.com.br/bulldogs-dogueria-tatuquara/checkout | rota `/{slug}/checkout`, título "Finalizar Pedido", sacola vazia "Sua sacola"; existe também o host `menu.brendi.com.br` |
| 17 | notícia: captação | https://www.startupz.com.br/artigo/brendi-capta-us-6-6-milhoes-com-a-propel-ventures-e-aposta-no-whatsapp-para-brigar-com-ifood-e-rappi | US$ 6,6 mi (Propel Ventures); produto aíChef (pedido por conversa com IA) |
| 18 | notícia: canais próprios | https://gironews.com/food-service/restaurantes-ampliam-canais-proprios-para-reduzir-dependencia-de-marketplaces/ | ~90% dos pedidos fecham no cardápio, ~10% inteiramente na conversa (autodeclarado) |
| 19 | parceiro | https://saipos.com/integracoes/brendi | integração com PDV Saipos (conteúdo não lido, só título/trecho) |

Fontes que faltam (e o que destravariam): a central de ajuda (se existir), uma
loja real navegada ao vivo (estados de item, checkout preenchido), o painel do
restaurante (só com conta própria e sob os termos da Brendi), avaliações
públicas (nada achado no Reclame Aqui / Play).

## Core loop

O cliente pede no canal próprio do restaurante (cardápio por link/WhatsApp, sem
app e sem comissão por pedido) e o restaurante opera tudo num gestor de pedidos
em tempo real.

## Telas

### Lado do cliente (`pedido.brendi.com.br/{slug}`)

| ID | tela | rota / como chegar | propósito | componentes-chave | estados vistos / esperados | conf. |
| --- | --- | --- | --- | --- | --- | --- |
| S01 | Cardápio da loja | `/{slug}` (link, QR, WhatsApp) | vitrine + entrada do pedido | cabeçalho da loja (logo, nome, cidade), selo aberto/fechado, tempo de entrega, pedido mínimo, abas de categoria fixas, busca, cards de produto, botão flutuante da sacola | aberto, **fechado**, sem pedido mínimo, categoria vazia, mobile | alta (título/trechos) |
| S02 | Detalhe do produto | toque num produto em S01 (modal/bottom sheet) | personalizar o item | foto, descrição, preço, seletor de tamanho, seletor de sabores (meio a meio), grupos de complementos com mín/máx, observação, quantidade, "Adicionar" com total | obrigatório não preenchido, máx atingido, item esgotado | média (meio a meio, bordas por tamanho no blog); tela = palpite |
| S03 | Sacola | botão da sacola | revisar o pedido | lista de itens com opções, +/- , remover, subtotal, cross-sell ("peça também"), aviso de pedido mínimo | **vazia ("Sua sacola")**, abaixo do mínimo, cheia | alta (vazio visto), resto palpite |
| S04 | Finalizar pedido | `/{slug}/checkout` | dados + entrega + pagamento | nome e telefone (WhatsApp), entrega × retirada, endereço com validação, taxa/prazo da área, cupom, cashback disponível, forma de pagamento (Pix, cartão online, dinheiro com troco, maquininha), resumo, "Fazer pedido" | fora da área, loja fechou, cupom inválido, mobile | alta (rota/título), campos = média |
| S05 | Pagamento Pix | após S04 com Pix | pagar online | QR code, copia-e-cola, contagem regressiva, "já paguei" | aguardando, pago, expirado | palpite (Pix online confirmado, tela não) |
| S06 | Pedido confirmado / acompanhamento | após S04/S05 | mostrar status | número do pedido, linha do tempo (recebido → em preparo → saiu → entregue), resumo, botão WhatsApp da loja | cada status, cancelado | média (atualizações de status confirmadas, via WhatsApp) |

### Lado do restaurante (painel)

| ID | tela | rota / como chegar | propósito | componentes-chave | estados | conf. |
| --- | --- | --- | --- | --- | --- | --- |
| S10 | Cadastro / onboarding | site → teste grátis 15 dias | criar a loja | formulário, importar do iFood, conectar WhatsApp, conectar impressora | importando, falhou | média |
| S11 | Gestor de pedidos | home do painel | operar pedidos ao vivo | colunas kanban por status, card do pedido (nº, cliente, total, tempo), ações aceitar/despachar/finalizar, alerta sonoro, toggle loja aberta | sem pedidos, pedido novo, atrasado | alta (kanban + ações no blog) |
| S12 | Detalhe do pedido | clique num card | ver e agir | itens e opções, cliente, endereço, pagamento, imprimir, atribuir motoboy, cancelar | pago/não pago, cancelado | média |
| S13 | Cardápio: lista | menu "Cardápio" | organizar o cardápio | categorias reordenáveis, produtos, pausar/esgotar, busca | vazio, importado do iFood | média |
| S14 | Cardápio: editor de produto | S13 → produto | criar/editar item | nome, descrição, foto, preço, tamanhos, grupos de complemento (mín/máx), regras de sabores e bordas | validação | média (regras no blog) / tela palpite |
| S15 | Áreas de entrega | Configurações | taxa e prazo por região | lista de bairros/áreas, taxa, prazo, (mapa/raio palpite) | sem áreas | alta |
| S16 | Horários | Configurações | abrir/fechar | grade por dia da semana, pausa manual | fechado agora | alta (importa do iFood) |
| S17 | Configurações da loja | Configurações | perfil e regras | logo, nome, slug, pedido mínimo, formas de pagamento, retirada on/off | — | média |
| S18 | Motoboys | menu "Entregadores" | equipe de entrega | lista de entregadores, pedidos atribuídos, (acerto palpite) | vazio | média |
| S19 | Clientes (CRM) | menu "Clientes" | base própria | lista, último pedido, total gasto, status ativo/em risco/inativo | vazio | média |
| S20 | Cupons | menu "Marketing" | descontos | galeria de cupons, valor, limite semanal, validade | — | alta (limite semanal) |
| S21 | Fidelidade | menu "Marketing" | reter | modo pontos × cashback, % e validade (até 40 dias) | desligado | alta |
| S22 | Recuperador / disparos | menu "Marketing" | trazer cliente de volta | ativar recuperador, regras (7/21 dias, carrinho abandonado), campanhas de disparo, resultados | desligado | alta |
| S23 | Relatórios | menu "Relatórios" | acompanhar | pedidos, faturamento, ticket médio, lucro, origem | sem dados | média |
| S24 | Atendente IA (WhatsApp) | menu "Atendimento" | configurar e ver conversas | conexão do número, liga/desliga IA, conversas, assumir manualmente, feedback pós-entrega | IA off, desconectado | média |
| S25 | Integrações | Configurações | conectar terceiros | iFood, 99Food, Saipos, Colibri, Meta Pixel, GA, GTM | conectado/não | alta (lista) |
| S26 | Plano e cobrança | Configurações | assinatura | plano, faturas, taxas de Pix | teste, ativo, atrasado | média |

Total: 6 telas do cliente + 17 do painel = **23 telas**.

## Fluxos

```
F01 Cliente faz pedido para entrega com Pix
    S01 -> S02 -> S03 -> S04 -> S05 -> S06
    happy path cliques: ~8 (produto, tamanho, adicionar, sacola, continuar,
      entrega+endereço, Pix, fazer pedido) + digitação de nome/telefone/endereço
    edge: loja fecha no meio, endereço fora da área, abaixo do mínimo, sabor
      esgotado, Pix expira, cliente volta e a sacola persiste

F02 Cliente pede meio a meio para retirada, paga na loja
    S01 -> S02 (2 sabores + borda) -> S03 -> S04 (retirada, dinheiro) -> S06
    edge: preço do meio a meio (maior sabor vs média — regra a definir), troco

F03 Cliente recorrente usa cashback / cupom
    S01 -> S02 -> S03 -> S04 (aplica saldo/cupom) -> S06
    edge: cupom esgotado na semana, cashback vencido (40 dias), cupom + cashback juntos?

F04 Restaurante recebe e conclui um pedido
    S11 (alerta) -> S12 -> aceitar -> imprimir -> atribuir motoboy -> despachar -> finalizar
    happy path cliques: 3–4
    edge: pedido Pix não pago, cancelar com motivo, cliente liga pedindo mudança

F05 Restaurante monta a loja
    S10 -> (importar iFood ou manual) S13 -> S14 -> S15 -> S16 -> S17 -> link publicado
    edge: importação falha parcialmente, slug já usado

F06 Recuperar cliente inativo
    S22 (liga) -> job diário acha clientes 21+ dias / carrinho abandonado
      -> WhatsApp com cupom -> cliente cai em F03
    edge: opt-out, limite de cupons da semana, número inválido

F07 Cliente pede conversando com a IA no WhatsApp
    WhatsApp -> IA responde e manda link do cardápio (~90%) -> F01
             -> ou monta o pedido na conversa (~10%) -> S11
    edge: áudio/imagem, pergunta fora do cardápio, humano assume (S24)
```

Número a bater: F01 em ~8 toques no celular, sem cadastro nem senha.

## Componentes

| componente | variantes | estados | usado em |
| --- | --- | --- | --- |
| Botão | primário, secundário, fantasma, perigo, flutuante (sacola) | padrão, hover, foco, desabilitado, carregando | todas |
| Cabeçalho da loja | completo, compacto (ao rolar) | aberto, fechado | S01 |
| Abas de categoria | rolagem horizontal fixa | ativa, inativa | S01 |
| Card de produto | com foto, sem foto, promo | normal, esgotado | S01, S03 (cross-sell) |
| Bottom sheet / modal | item, confirmação | aberto, fechando | S02, S12 |
| Grupo de opções | único (rádio), múltiplo (checkbox), quantidade (+/-) | obrigatório, completo, máx atingido | S02, S14 |
| Stepper de quantidade | pequeno, grande | mín, máx | S02, S03 |
| Campo de texto | texto, telefone, CEP, moeda | vazio, foco, erro | S04, S14, S17 |
| Seletor segmentado | entrega/retirada, pontos/cashback | — | S04, S21 |
| Cartão de forma de pagamento | Pix, cartão, dinheiro, maquininha | selecionado | S04 |
| Bloco Pix | QR + copiar | aguardando, pago, expirado | S05 |
| Linha do tempo de status | vertical | concluído, atual, futuro | S06 |
| Coluna kanban + card de pedido | por status | novo (pulsando), atrasado | S11 |
| Badge de status | aberto, fechado, pago, pendente, cancelado | — | S01, S11, S12 |
| Tabela/lista | clientes, cupons, áreas | vazia, carregando | S15, S19, S20 |
| Toggle | — | on, off | S16, S21, S22, S24 |
| Toast | sucesso, erro, info | — | todas |
| Navegação do painel | sidebar desktop, barra inferior mobile | ativa | S10–S26 |

## Modelo de dados inferido

```
Store        id, slug, name, logo_url, city, phone_whatsapp, is_open_override,
             min_order_cents, pickup_enabled, payment_methods[], plan_status
             evidência: S01 (rota /{slug}, cidade no título, pedido mínimo), planos
             confiança: alta

OpeningHours store_id, weekday, opens_at, closes_at
             evidência: importação de horários do iFood        confiança: alta

DeliveryZone store_id, name (bairro), fee_cents, eta_min, eta_max, (polygon|radius: palpite)
             evidência: blog área por bairro, "Entrega 1 - 55min"   confiança: alta

Category     id, store_id, name, position, is_active          confiança: alta
Product      id, category_id, name, description, image_url, base_price_cents,
             is_available, position                          confiança: alta
ProductSize  id, product_id, name (broto, média, grande, família), price_cents, max_flavors
             evidência: lojas reais (tamanhos), blog meio a meio   confiança: média
OptionGroup  id, product_id, name, min, max, kind (flavor|crust|addon), size_id?
             evidência: "bordas por tamanho", "regras de combo"   confiança: média
Option       id, group_id, name, price_cents, is_available    confiança: média

Customer     id, store_id, name, phone, created_at, last_order_at, total_spent_cents,
             cashback_balance_cents, points, opt_out
             evidência: CRM, recuperador, cashback          confiança: média
Address      id, customer_id, street, number, district, city, lat, lng, complement
             evidência: geocoding do Google                    confiança: média

Order        id, store_id, customer_id, number, channel (menu|whatsapp_ai|ifood),
             fulfillment (delivery|pickup), zone_id, address_snapshot (json),
             subtotal, delivery_fee, discount, cashback_used, total (cents),
             status (received|accepted|preparing|dispatched|delivered|cancelled),
             payment_method (pix|card|cash|card_on_delivery), change_for_cents,
             payment_status (pending|paid|refunded), courier_id, created_at
             evidência: gestor kanban (aceitar/despachar/finalizar), checkout
             confiança: alta para o núcleo, palpite para os nomes de status
OrderItem    id, order_id, product_id, size_id, name_snapshot, unit_price, qty, notes
OrderItemOption order_item_id, option_id, name_snapshot, price, fraction (meio a meio)

Payment      id, order_id, provider, provider_ref, pix_qr, amount, fee, status
             evidência: Pix R$0,40 + 0,5%                      confiança: média
Coupon       id, store_id, code, type (pct|fixed|free_delivery), value,
             weekly_limit, used_this_week, min_order, valid_until
             evidência: galeria de cupons com limite semanal   confiança: média
LoyaltyEntry id, customer_id, order_id, kind (earn|spend|expire), points|cents, expires_at
             evidência: pontos e cashback, 40 dias              confiança: média
Campaign     id, store_id, type (inactive|abandoned_cart|broadcast), rules (json),
             coupon_id, enabled                                  confiança: média
CampaignSend id, campaign_id, customer_id, sent_at, converted_order_id   confiança: palpite
Cart         id, store_id, customer_phone?, items (json), updated_at, recovered
             evidência: recuperador de carrinho abandonado      confiança: palpite
Courier      id, store_id, name, phone, active                confiança: média
Conversation id, store_id, customer_id, channel (whatsapp), ai_enabled, assigned_user_id
Message      id, conversation_id, role (customer|ai|staff), type (text|audio|image), body
             evidência: Brenda texto/áudio/imagem               confiança: média
Integration  id, store_id, provider (ifood|99food|saipos|colibri|meta_pixel|ga|gtm), config
             confiança: alta (lista)
User         id, email, store_id, role (owner|staff)          confiança: palpite
```

Relações: Store 1-n Category 1-n Product 1-n ProductSize; Product 1-n OptionGroup
1-n Option; Store 1-n DeliveryZone, OpeningHours, Customer, Order, Coupon,
Campaign, Courier; Customer 1-n Address, Order, LoyaltyEntry; Order 1-n
OrderItem 1-n OrderItemOption; Order 1-1 Payment; Order n-1 Courier.

## Matriz de funcionalidades

Ver `features.csv`. Must: 24, should: 17, could: 10, skip: 6.

## Fora de escopo (não pode ou não deve ser clonado)

- **Nome e marca "Brendi"/"Brenda"/"aíChef"**, logo, textos do site e fotos das lojas.
- **A base de 8.500+ restaurantes e seus clientes** — a rede é deles.
- **Importação do iFood**: só pela API oficial de parceiros do iFood (exige
  credenciamento); nada de raspar a loja do restaurante no iFood.
- **WhatsApp**: só pela WhatsApp Business Platform (Cloud API) oficial, com o
  número do próprio restaurante; nada de WhatsApp Web não oficial.
- **Integrações Saipos/Colibri/99Food**: dependem de acordo de parceria.
- **Adquirência própria / taxa de Pix**: o clone usa um PSP (ex.: Mercado Pago,
  Stripe BR, Asaas) — a margem sobre o Pix é negócio, não código.
- **"Taxa de operação" e "investimento em mídia"**: serviço humano de
  marketing, não software.

## Tamanho

Telas 23, fluxos 7, entidades ~22. Partes difíceis:

1. **Personalização de item** (tamanhos × sabores meio a meio × bordas por
   tamanho × mín/máx) com preço correto no cliente e no servidor.
2. **Pagamento Pix online com webhook** + estados pago/expirado e pedido em
   tempo real no gestor (realtime/push + alerta sonoro + impressão).
3. **Atendente de IA no WhatsApp** (Cloud API, áudio/imagem, handoff humano,
   pedido completo na conversa).

Também: geocodificação de endereço e áreas, jobs agendados do recuperador,
ledger de cashback com expiração.

Tamanho: **L** (um trimestre) para tudo. O núcleo F01 + F04 + F05 (cardápio,
checkout com Pix, gestor kanban, editor de cardápio, áreas e horários) é **M**
(algumas semanas). Recomendação: fatiar assim e deixar IA no WhatsApp para a
fase 2.
