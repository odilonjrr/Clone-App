# Mapa de recon: Brendi (web, cardápio do cliente + painel do restaurante)

Escopo: o loop central. O cliente final abre o cardápio digital da loja (link
ou WhatsApp), monta o pedido, escolhe entrega ou retirada, paga (Pix e cartão
online, ou na entrega) e o restaurante recebe, aceita, prepara, despacha e
finaliza no gestor de pedidos. Em volta: editor de cardápio, áreas e taxas de
entrega, horários, cupons, cashback, recuperador de clientes e o atendente de
IA no WhatsApp.
Para: produto SaaS próprio para vender a restaurantes (confirmado em 2026-11-18).
Data: 2026-11-18 (revisão 2, com acesso direto)

> **Como este mapa foi feito.**
> - **Revisão 1:** só trechos de busca na web.
> - **Revisão 2 (esta):** a rede foi liberada para `brendi.com.br`,
>   `pedido.brendi.com.br`, `menu.brendi.com.br`, `assets.brendi.com.br` e
>   `images.brendi.com.br`. Abri num navegador, uma página por vez:
>   - a home e a página de planos;
>   - duas lojas públicas: uma fechada (Pizzaria Saborella) e uma aberta
>     (Restaurante do Val).
>
>   Na loja aberta, percorri cardápio → item → sacola → entrega/retirada →
>   pagamento → resumo, e **parei antes de "Ir para Pagamento"**, para não
>   criar pedido real. Não fiz login, não li código JavaScript e não registrei
>   chamadas de API. Só anotei os domínios que falharam ao carregar, para
>   liberar estilos e imagens.
> - **Painel do restaurante:** não foi acessado. O que está abaixo vem da
>   réplica do painel exibida na própria home (`app.brendi.com.br`, imagem de
>   marketing) e da página de planos.
>
> Confiança:
> - **vista**: vi a tela funcionando;
> - **alta**: página oficial descreve;
> - **média**: marketing vago ou réplica de marketing;
> - **palpite**: sem fonte.

## Fontes

| # | fonte | URL | o que deu |
| --- | --- | --- | --- |
| 1 | home (lida no navegador) | https://brendi.com.br/ | menu de funcionalidades; **réplica do painel** (menu lateral, barra de ações, kanban de 5 colunas, cards de pedido); "11 agentes" com nomes; +9.500 restaurantes, +R$ 2 bi, +70 mi atendimentos |
| 2 | planos (lida no navegador) | https://brendi.com.br/planos | plano único com preço por faixa de faturamento (R$ 120/mês até R$ 3.000); lista do que está incluído; taxas de pagamento online; tráfego pago à parte; FAQ |
| 3 | loja fechada (navegada) | https://pedido.brendi.com.br/pizzaria-saborella | cabeçalho, estado "Abrimos hoje (quarta) às 18:00", cupom 5% no topo, "Mais Pedidos", pizza por tamanho → nº de sabores → sabores, "Estamos fechados :(" |
| 4 | loja aberta (navegada até o resumo) | https://pedido.brendi.com.br/restaurantedoval | "Aberto até 18:00", "2% de Cashback", grupos de opção, sacola, entrega/retirada, endereço Google, formas de pagamento, resumo com cashback a receber, login por celular |
| 5 | recuperador | https://brendi.com.br/recuperador-de-vendas | clientes sumidos + carrinho abandonado → WhatsApp com cupom (via busca) |
| 6 | blog: gestão de pedidos | https://brendi.com.br/blog/sistema-para-restaurantes-gestao-pedidos/ | kanban, ações, módulo de motoboys (via busca) |
| 7 | blog: área por bairro | https://brendi.com.br/blog/melhores-sistemas-delivery-por-bairro/ | taxa e prazo por área, geocodificação Google (via busca) |
| 8 | blog: integração iFood | https://brendi.com.br/blog/sistema-delivery-integracao-ifood/ | importa cardápio, horários, áreas e perfil (via busca) |
| 9 | blog: promoções | https://brendi.com.br/blog/cardapio-digital-promocoes-automaticas/ | galeria de cupons com limite semanal; IA escolhe o cupom (via busca) |
| 10 | blog: fidelidade | https://brendi.com.br/blog/como-implementar-sistema-para-restaurantes/ | pontos e cashback; validade de até 40 dias; inativo após ~21 dias (via busca) |
| 11 | blog: atendimento | https://brendi.com.br/blog/atendimento-automatizado-whatsapp-delivery/ | status por WhatsApp; feedback 1h–1h30 após a entrega (via busca) |
| 12 | notícia | https://www.startupz.com.br/artigo/brendi-capta-us-6-6-milhoes-com-a-propel-ventures-e-aposta-no-whatsapp-para-brigar-com-ifood-e-rappi | captação de US$ 6,6 mi; produto aíChef (via busca) |

**Ainda não lidos:**
- Central de ajuda e Termos de uso: há link no rodapé, mas não abri.
- O painel real: exige conta e leitura dos termos antes.
- A tela de Pix e a de acompanhamento do pedido: exigem fazer um pedido real.

**Capturas de referência** (nunca entram no clone): `brendi/replica/screens/`.

## Core loop

O cliente pede no canal próprio do restaurante, sem app e sem comissão por
pedido. O restaurante opera tudo num quadro de pedidos em tempo real, e o
WhatsApp (com IA) avisa o cliente a cada etapa.

## Telas

### Lado do cliente (`pedido.brendi.com.br/{slug}`, também `menu.brendi.com.br/{slug}`)

| ID | tela | rota | propósito | componentes-chave (vistos) | estados | conf. | print |
| --- | --- | --- | --- | --- | --- | --- | --- |
| S01 | Cardápio da loja | `/{slug}` | vitrine e entrada | ver lista abaixo | aberto ("Aberto até 18:00"), **fechado** ("Abrimos hoje (quarta) às 18:00"), sem mínimo / mínimo R$ 30 | **vista** | `loja-cardapio-mobile.png`, `val-cardapio.png` |
| S02 | Produto | `/{slug}/produto/{produto-slug}` (página cheia) | personalizar o item | ver lista abaixo | obrigatório pendente (botão em R$ 0,00), **loja fechada** ("Estamos fechados :(" no lugar do botão) | **vista** | `val-item.png`, `val-opcoes-1.png`, `lanche-1.png` |
| S03 | Pizza: nº de sabores | toque no tamanho (bottom sheet) | escolher 1, 2 ou 3 sabores | título "Escolha o número de sabores"; cards com ícone de pizza dividida; o máximo varia por tamanho | — | **vista** | `item-pizza-1.png` |
| S04 | Pizza: sabores | `/{slug}/pizza/{categoria}/{tamanho}/{n}` | escolher os sabores | ver lista abaixo | — | **vista** | `pizza2-2.png` |
| S05 | Login do cliente | "Fazer login" no topo (modal) | identificar o cliente | Nome, Celular com seletor de país, "Receber código" (código por SMS/WhatsApp: palpite), Cancelar | — | **vista** | `val-login-1.png` |
| S06 | Sacola | `/{slug}/checkout` (passo 1) | revisar | ver lista abaixo | vazia ("Sua sacola"), com itens | **vista** | `val-sacola.png` |
| S07 | Entrega e agendamento | checkout (passo 2) | entrega × retirada | ver lista abaixo | sem endereço, retirada | **vista** (o agendamento só aparece no título: palpite) | `val-endereco.png`, `val-novo-endereco.png`, `val-retirada.png` |
| S08 | Forma de pagamento | checkout (passo 3) | escolher como pagar | ver lista abaixo | — | **vista** | `val-pagamento.png` |
| S09 | Resumo do pedido | checkout (passo 4) | conferir | ver lista abaixo | — | **vista** | `val-revisar.png` |
| S10 | Pagamento online (Pix/cartão) | após "Ir para Pagamento" | pagar | QR / copia-e-cola, cartão, Apple Pay e Google Pay (citados nas taxas) | aguardando, pago, expirado | palpite | — |
| S11 | Pedido feito / acompanhamento | após pagar | status | linha do tempo com as 5 etapas do painel; aviso pelo WhatsApp | por status, recusado | média | — |

Componentes vistos em cada tela do cliente:

- **S01 Cardápio da loja**
  - Topo: foto de capa, botões de busca e perfil, logo, nome, bairro e cidade, link de informações da loja.
  - Faixas de informação: tempo de entrega ("20min - 1h"), pedido mínimo, selo "2% de Cashback", card de cupom ("Desconto 5% / Sem mínimo").
  - Convite: "Fazer login · Aproveite benefícios!".
  - Carrossel "Mais Pedidos" com selo "🔥 O favorito!".
  - Categorias em chips, incluindo "Em falta".
  - Cards de produto com descrição, "A partir de", "De R$ X por R$ Y", "Recomendado", "Serve N pessoas", "3un" e foto.
  - Fixos na tela: botão "Ver sacola R$ 25,00" e a barra "Tirar dúvidas no WhatsApp".
- **S02 Produto**
  - Foto, nome, descrição, preço ou "A partir de".
  - Grupos de opção com contador "0 / 1", selo "Obrigatório", "Escolha até 10 opções" e preços "+R$ 3,00".
  - Campo "Observações?", seletor de quantidade e botão "Adicionar R$ 25,00".
- **S04 Pizza: sabores**
  - Título "Tamanho Família - 2 sabores", "Escolha 2 opções", contador "0 / 2", "Obrigatório".
  - Regra **"Preço com base no maior valor"**.
  - Opções "Meia Pizza X", cada uma com o preço da pizza inteira.
- **S06 Sacola**
  - "Sua sacola", botão "Limpar", itens com quantidade, "Adicionar mais itens".
  - **"Peça também"** (cross-sell).
  - "Aplicar benefício": Cashback / Cupom.
  - Subtotal, taxa de entrega "(a ser calculado)", total, "Observações?", "Continuar para Endereço".
- **S07 Entrega e agendamento**
  - Pergunta "Como deseja receber seu pedido?" com as opções Entrega / Retirada.
  - Lista de endereços salvos e "Adicionar novo endereço".
  - Modal de endereço: "Usar minha localização" ou digitar em "Para onde?" (autocompletar do Google).
  - Retirada: endereço da loja, CEP, "Ver no mapa".
  - Botão "Continuar para Pagamento".
- **S08 Forma de pagamento**
  - **Pagar online:** PIX, Cartão de Crédito.
  - **Pagar na entrega:** Dinheiro, Cartão de Crédito, Cartão de Débito, Vale Refeição.
  - Botão "Revisar Pedido".
- **S09 Resumo do pedido**
  - Recebimento, Pagamento ("PIX (Online)").
  - **"Cashback a receber +R$ 0,50"**, com a nota "Calculados no fechamento".
  - Itens, subtotal, total, botão "Ir para Pagamento".

### Lado do restaurante (`app.brendi.com.br`). Não acessado

Fonte de todas as linhas: réplica do painel na home (fonte 1) e lista de
recursos dos planos (fonte 2). Os nomes de menu foram **vistos na réplica**.

| ID | tela (item do menu) | propósito | componentes / evidência | conf. |
| --- | --- | --- | --- | --- |
| S20 | Moldura do painel | navegar | seletor de loja ("Burger do Zé - Matriz") + botão "+" (**várias lojas**); "Loja aberta"; atalhos "Acessar cardápio" e "QR Code & Links"; menu lateral com os itens abaixo; "Tutoriais p/ crescer", "Indique e ganhe", "Suporte" | média (réplica) |
| S21 | Gestor de pedidos | operar ao vivo | ver detalhe abaixo | média-alta (réplica detalhada) |
| S22 | Detalhe do pedido ("Ver mais") | itens, cliente, pagamento | expansão do card | palpite |
| S23 | Novo pedido (manual) | lançar pedido do balcão ou do telefone | botão "+ Novo pedido" | média |
| S24 | Relatório diário / Relatórios | caixa e lucro ("o Finn fecha o caixa") | "Abrir relatório diário", item "Relatórios", relatórios de lucro, calculadora de CMV no site | média |
| S25 | Atendimento | conversas no WhatsApp com a IA Brenda | item "Atendimento"; Brenda entende áudio e figurinha, tom de voz configurável, pedido dentro da conversa | média |
| S26 | Brenda Gestora | assistente de IA para o dono (palpite: perguntas sobre a operação) | item no menu | palpite |
| S27 | Editor de cardápio | categorias, produtos, tamanhos, grupos de opção, sabores, "em falta" | item "Editor de cardápio"; a estrutura vista em S02/S04 define o editor | média |
| S28 | Cupons | galeria, valor, mínimo, limite | item "Cupons"; card "Desconto 5% Sem mínimo" visto no cardápio | média |
| S29 | Disparos e Fidelização | disparos, recompra automática, recuperador, fidelidade/cashback | item no menu; "2% de Cashback" visto no cardápio | média |
| S30 | Tráfego pago | anúncios gerenciados (serviço) | item "Tráfego Pago"; R$ 60/semana | alta (mas é serviço) |
| S31 | Administrar loja | perfil, horários, áreas e taxas, pedido mínimo, formas de pagamento, retirada, motoboys | item no menu | média |
| S32 | Financeiro | repasses dos pagamentos online, janela de repasse | item no menu; "janela de repasse que você configurar" | média |
| S33 | Integrações | iFood, 99Food, Foody, Pick n Go, Glow, Saipos, Colibri, GA, GTM, Meta Pixel | item + lista na página de planos | alta (lista) |
| S34 | QR Code & Links | link e QR do cardápio para divulgar | atalho no topo do menu | média |
| S35 | Cadastro / teste grátis | criar conta e migrar ("no ar em 15 min", "migração feita junto com você") | CTA do site | média |

**S21 Gestor de pedidos**, em detalhe:
- **Barra superior:** busca "(Nome, Telefone ou Código)", "Pausar loja", "Informar alta demanda", "Abrir relatório diário", "+ Novo pedido", alternar visualização, notificações.
- **5 colunas:** Em Análise, Em Preparo, Pronto, Em Entrega, Finalizados. Cada uma mostra quantos pedidos tem e a soma em R$.
- **Card do pedido:** código "B-5018"; "Há 4 seg" e cronômetro; cliente com telefone; etiqueta ("Cliente novo", "Segundo pedido" ou "WhatsApp"); bairro ou "Retirada no local"; "Ver mais"; total; pagamento ("Pix · Aprovado", "Crédito · Visa", "Dinheiro · Troco R$ 0,00"); selo "A Cobrar"; botões Recusar e Aceitar, Pronto ou Finalizar.
- **Aviso:** "Cliente avisado no WhatsApp: pedido saiu para entrega".

Total: 11 telas do cliente + 16 do painel = **27 telas** (9 vistas de verdade).

## Fluxos

```
F01 Cliente pede para entrega e paga com Pix (vista até S09)
    S01 -> S02 -> (Adicionar) S01 -> Ver sacola S06 -> S07 (Entrega, endereço)
        -> S08 (PIX) -> S09 -> S10 -> S11
    happy path toques, medidos até S09: produto, Adicionar, Ver sacola,
      Continuar, (endereço: localização ou digitar), Continuar, PIX, Revisar,
      Ir para Pagamento = 9 + endereço. O login (S05) deve aparecer em
      algum ponto antes de pagar: palpite.
    edge: loja fecha no meio (S02 vira "Estamos fechados :("), fora da área,
      abaixo do mínimo, item "Em falta", Pix expira

F02 Cliente pede pizza meio a meio para retirada e paga na entrega
    S01 -> S03 (tamanho -> nº de sabores) -> S04 (2 sabores, preço do maior)
        -> S06 -> S07 (Retirada) -> S08 (Dinheiro/VR) -> S09 -> S11
    edge: 3 sabores só no tamanho família; troco

F03 Cliente recorrente usa cashback ou cupom
    S05 (login por celular + código) -> S01 -> ... -> S06 (Aplicar benefício)
        -> S09 (cashback a receber de 2%)
    edge: cupom com limite semanal esgotado, cashback vencido

F04 Restaurante opera um pedido
    S21: Em Análise (Aceitar | Recusar) -> Em Preparo (Pronto) -> Pronto
        -> Em Entrega (Finalizar) -> Finalizados
    o cliente é avisado no WhatsApp a cada mudança; retirada pula Em Entrega (palpite)
    edge: pedido "A Cobrar" (pagamento na entrega), recusa, alta demanda
      (aumenta o prazo exibido: palpite), loja pausada

F05 Restaurante lança pedido manual
    S21 -> S23 (+ Novo pedido) -> Em Análise

F06 Restaurante monta a loja
    S35 -> S31 (perfil, horários, áreas) -> S27 (cardápio) -> S34 (link/QR)
    edge: importar do iFood (S33)

F07 Recuperar cliente inativo
    S29 (liga) -> job acha clientes com 21+ dias sem pedir e carrinhos abandonados
        -> WhatsApp com cupom -> F03

F08 Cliente pede conversando com a IA no WhatsApp
    WhatsApp -> Brenda responde (texto, áudio, figurinha) -> manda o link do
      cardápio (F01) ou fecha o pedido na conversa e manda o Pix -> S21 com
      a etiqueta "WhatsApp"
```

Número a bater: **~9 toques** de "abrir a loja" até "Ir para Pagamento",
num item sem opções obrigatórias, mais a digitação do endereço.

## Componentes

| componente | variantes | estados | usado em |
| --- | --- | --- | --- |
| Botão | primário (verde no cardápio), secundário com contorno, perigo (vermelho no painel), fixo ("Ver sacola R$ x") | padrão, desabilitado, carregando, **fechado** | todas |
| Cabeçalho da loja | capa + card sobreposto com logo | aberto, fechado | S01 |
| Faixa de informação | entrega, pedido mínimo, cashback | — | S01 |
| Card de cupom | percentual + mínimo | — | S01 |
| Carrossel "Mais Pedidos" | com selo "O favorito!" | — | S01 |
| Chips de categoria | rolagem horizontal, fixos ao rolar | ativo | S01 |
| Card de produto | com ou sem foto, "A partir de", "De/por", "Recomendado", "Serve N" | normal, em falta | S01, S06 ("Peça também") |
| Bottom sheet | título + fechar + alça | — | S03, S05, S07 |
| Card de escolha com ícone | 1/2/3 sabores | selecionado (borda verde) | S03 |
| Grupo de opção | escolha única, múltipla ("até N"), meia pizza | "0 / N", **Obrigatório**, completo | S02, S04 |
| Linha de opção | nome, descrição, "+R$" | marcada | S02, S04 |
| Seletor de quantidade | − 1 + | mín 1 | S02, S06 |
| Campo de observação | "Observações?" | — | S02, S06 |
| Segmentado | Entrega / Retirada | — | S07 |
| Autocompletar de endereço | Google Places + "Usar minha localização" | vazio, sugestões | S07 |
| Opção de pagamento (rádio) | agrupada: online / na entrega | selecionada | S08 |
| Linhas de resumo | subtotal, taxa "(a ser calculado)", total, cashback a receber | — | S06, S09 |
| Barra "Tirar dúvidas no WhatsApp" | fixa no rodapé | — | S01, S02 |
| Coluna kanban | 5 cores (vermelho, amarelo, azul, verde, cinza), contador + soma | — | S21 |
| Card de pedido | código, cronômetro, etiqueta do cliente, forma de pagamento, "A Cobrar" | novo, atrasado | S21 |
| Etiqueta | Cliente novo, Segundo pedido, WhatsApp, Pix · Aprovado | — | S21 |
| Menu lateral do painel | itens com ícone, grupos que abrem | ativo | S20 |
| Toast | "Cliente avisado no WhatsApp…" | — | S21 |

## Modelo de dados inferido

```
Store        id, slug, name, logo_url, cover_url, neighborhood, city, address, cep,
             lat, lng, whatsapp, min_order_cents (0 = "sem mínimo"),
             eta_min, eta_max, pickup_enabled, paused, high_demand,
             cashback_percent, brand_id (várias lojas: "Matriz")
             evidência: S01, S07 (retirada), S21 (Pausar loja, alta demanda), S20
             confiança: vista/alta
OpeningHours store_id, weekday, opens_at, closes_at    ("Abrimos hoje (quarta) às 18:00")
DeliveryZone store_id, name, fee_cents, eta (polígono/raio: palpite)   evidência: fonte 7
Category     id, store_id, name, position, is_special ("Mais Pedidos", "Em falta" = derivadas: palpite)
Product      id, category_id, slug, name, description, image_url, price_cents,
             compare_at_cents ("De/por"), serves, unit_label ("3un"), badge
             ("Recomendado"), is_available, kind (simple | pizza_size)
PizzaSize    product_id, slices, max_flavors, pricing_rule (max | avg: palpite;
             visto "maior valor")       evidência: S03/S04   confiança: vista
Flavor       id, store_id, name, description, price_by_size {size_id: cents}
OptionGroup  id, product_id, name, min, max, required        evidência: "0 / 1 Obrigatório", "até 10"
Option       id, group_id, name, description, price_cents, is_available

Customer     id, store_id, name, phone (login por código), cashback_balance_cents,
             order_count (etiquetas Cliente novo/Segundo pedido), last_order_at
Address      id, customer_id, formatted, number, complement, lat, lng, place_id

Order        id, store_id, code ("B-5018"), customer_id, channel (menu | whatsapp | manual | ifood),
             fulfillment (delivery | pickup), scheduled_for?, address_snapshot,
             subtotal, delivery_fee, discount, cashback_used, total,
             status (em_analise | em_preparo | pronto | em_entrega | finalizado | recusado),
             payment_method (pix | credit_online | cash | credit_on_delivery |
               debit_on_delivery | meal_voucher | apple_pay | google_pay),
             payment_status (aprovado | a_cobrar | pendente), change_for_cents,
             cashback_earned_cents, notes, accepted_at, ready_at, dispatched_at, finished_at
             evidência: S06–S09, S21     confiança: vista/média
OrderItem / OrderItemOption   (snapshot de nome e preço; fração para meia pizza)

Payment      order_id, method, provider_ref, fee_cents, settles_at (Pix D+1 após 12h)
Coupon       id, store_id, percent|amount, min_order, weekly_limit, valid_until
CashbackEntry customer_id, order_id, kind (earn | spend | expire), cents, expires_at (40 dias)
Campaign / CampaignSend      (recompra, recuperador, disparo)
Courier      id, store_id, name, phone
Conversation / Message       (WhatsApp; texto, áudio, figurinha)
Integration  store_id, provider, config
User         id, email, role; StoreMember(user_id, store_id)
```

Relações:
- Brand 1-n Store.
- Store 1-n Category 1-n Product.
- Product 1-n OptionGroup 1-n Option.
- Pizza: Product(pizza_size) n-n Flavor, com preço por tamanho.
- Store 1-n Order. Customer 1-n Order.
- Order 1-n OrderItem 1-n OrderItemOption.
- Customer 1-n CashbackEntry.

## Matriz de funcionalidades

Ver `features.csv`. Must: 30, should: 20, could: 14, skip: 7 (71 linhas).

## Fora de escopo (não pode ou não deve ser clonado)

- **Marca e conteúdo da Brendi:**
  - Nome, logo, nomes dos agentes (Brenda, Anderson, Kleber, Marta, Finn, Wilson, Brendinha), textos, ilustrações e cores exatas.
  - As fotos e os cardápios das lojas.
- **A rede:** os 9.500+ restaurantes e os clientes deles.
- **Tráfego pago gerenciado:** é um serviço humano, não software.
- **Importar do iFood e integrações com 99Food, Saipos, Colibri, Foody, Pick n Go e Glow:** dependem de API de parceiro ou de acordo comercial.
- **WhatsApp não oficial:** fica de fora. Usar só a WhatsApp Business Platform (Cloud API).
- **As taxas de pagamento da Brendi:** são acordo comercial. O clone usa um PSP brasileiro (Mercado Pago, Asaas, Pagar.me ou Stripe BR) com as taxas que ele cobrar.

## Tamanho

Telas 27, fluxos 8, entidades ~24.

Partes difíceis:
1. **Montagem do item:** pizza por tamanho → nº de sabores → meias com "preço do maior", grupos com mín/máx. O preço tem que bater no cliente e no servidor.
2. **Pagamento online com webhook:** Pix e cartão, pedido "A Cobrar" × "Aprovado", repasses (Financeiro).
3. **Tempo real no quadro de pedidos e avisos no WhatsApp a cada etapa:** os avisos exigem modelos de mensagem aprovados pela Meta.
4. **Atendente de IA no WhatsApp:** áudio, figurinha e pedido dentro da conversa.

Tamanho:
- **Tudo:** **L** (um trimestre).
- **Núcleo:** **M** (algumas semanas). Inclui S01–S09 e S21 com F01, F02 e F04, mais editor de cardápio, horários, áreas e login por código.
- **Fase 2:** IA no WhatsApp, recuperador e disparos.
