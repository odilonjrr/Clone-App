# Delisys: especificação de componentes

**Fontes:**
- Tokens: `tokens.json`.
- Padrões visuais medidos em saipos.com (2026-11-18).
- Lista de componentes: `brendi/replica/recon.md`. S-IDs = telas do mapa.

**Ícones:** Lucide (licença ISC). Nunca ícones, ilustrações ou fotos da Saipos ou da Brendi.

**Regras gerais:**
- **Foco visível sempre:** anel `--ring-width` (4px) na cor `secondary-soft` + contorno de 2px `secondary`.
- **Área de toque:** mínimo 44×44px no cardápio do cliente (celular).
- **Movimento:** transições de `--motion-fast`/`--motion-base`; tudo desligado em `prefers-reduced-motion`.
- **Formato dos valores:** sempre `R$ 1.234,56` (`Intl.NumberFormat('pt-BR', {style:'currency', currency:'BRL'})`).

---

```
Button (botão)
  variantes  primary   bg secondary, texto on-secondary            (ação principal do painel)
             cta       bg accent, texto on-accent                   (o botão mais importante da tela: "Fazer pedido", "Ver sacola")
             dark      bg primary, texto on-dark                    (sobre fundos claros quando secondary já está em uso)
             secondary fundo surface, borda 2px secondary, texto secondary
             ghost     sem fundo, texto secondary, sublinhado no hover
             danger    bg danger, texto branco                      ("Recusar" confirmado, "Excluir")
             danger-outline borda 1px danger, texto danger          ("Recusar" no card do pedido)
  formato    pílula (radius pill)
  tamanhos   sm 36px (padding 8/16, type sm/700)   md 48px (12/24, base/700)
             lg 62px (16/32, lg/600) + halo 4px secondary-soft      (padrão da Saipos nos CTAs grandes)
  estados    default, hover (bg -strong), active (translateY 1px), focus-visible (anel),
             disabled (opacidade .45, cursor not-allowed, sem hover),
             loading (spinner à esquerda, rótulo mantido, aria-busy="true", sem clique duplo)
             fechado (cardápio): vira "Estamos fechados", estilo secondary desabilitado, com o horário de abertura
  a11y       <button> de verdade; <a> só quando navega; ícone sozinho exige aria-label
  usado em   todas; cta em S01 (Ver sacola), S02 (Adicionar R$ x), S06–S09 (Continuar, Fazer pedido)
```

```
Badge / Selo
  variantes  outline  borda 1.5px primary, texto primary, fundo surface   (sobrelinha de seção, "Mais pedidos")
             soft     fundo *-soft, texto da cor de status                 (status de pedido, pagamento)
             solid    fundo accent, texto on-accent                         ("🔥 Favorito", "-10%")
             count    círculo 20px, fundo da cor da coluna, texto branco     (contador da coluna do quadro)
  formato    pílula, padding 4/12, type 2xs (11/700) em caixa alta ou xs/600
  status     analise · preparo · pronto · entrega · finalizado · recusado(danger) · pago(success) · a cobrar(warning)
  a11y       cor nunca é a única pista: sempre texto ("Pix · Aprovado", não só um ponto verde)
  usado em   S01, S02, S21, S22
```

```
Input / Campo de texto
  variantes  text, tel (celular com DDD), cep, moeda, busca (ícone à esquerda), textarea ("Observações?")
  tamanho    48px, padding 12/16, radius md (12), type base
  tokens     fundo surface, borda 1px border-input, texto text, placeholder text-muted
  estados    default, hover (borda text-muted), focus (borda secondary + anel), preenchido,
             erro (borda danger + mensagem xs danger abaixo, aria-invalid + aria-describedby),
             desabilitado (fundo surface-sunken)
  rótulo     sempre visível acima (sm/600), nunca só placeholder
  usado em   S04 (endereço), S05 (login), S07, S27, S31
```

```
Select / Seletor
  igual ao Input + chevron à direita; nativo <select> no celular; Radix Select no painel
  usado em   S05 (DDD), S31, filtros do painel
```

```
Segmented (Entrega | Retirada)
  2–3 opções, trilho surface-sunken radius pill, opção ativa surface + shadow sm + texto text/600
  teclado    setas trocam a opção (role="radiogroup")
  usado em   S07, S21 (alternar visualização)
```

```
Radio card / Opção de pagamento
  card surface, borda 1px border, radius lg, padding 16; selecionado: borda 2px secondary + check
  agrupado com título ("Pagar online", "Pagar na entrega")
  usado em   S08, S03 (nº de sabores, com ícone de pizza dividida feito por nós)
```

```
Option group (grupo de complementos)
  cabeçalho  título sm/700 + contador "0 / 2" + selo "Obrigatório" (soft warning) ou "Opcional"
             regra exibida quando existe: "Preço com base no maior valor"
  linhas     nome base/500, descrição sm muted, preço "+R$ 3,00" à direita,
             controle: radio (máx 1), checkbox (máx N), stepper (quantidade por opção)
  estados    incompleto (contador em text-muted), completo (contador success + check),
             máximo atingido (demais opções desabilitadas), opção esgotada (riscada + "Em falta")
  a11y       <fieldset><legend>; anúncio aria-live do contador
  usado em   S02, S04
```

```
Stepper de quantidade
  [−] n [+], botões 36px (44px de área de toque), radius pill, borda border
  mín 1 na sacola (no 1, o "−" vira lixeira e remove com confirmação)
  usado em   S02, S06
```

```
Product card (card de produto)
  lista      texto à esquerda (nome base/600, descrição 2 linhas sm muted, preço), foto 96×96 radius md à direita
  destaque   carrossel "Mais pedidos": foto em cima 160px, radius lg, selo solid sobre a foto
  preço      "A partir de R$ 25,00" | "R$ 25,00" | "De R$ 69,90 por R$ 59,90" (de: riscado muted, por: secondary/700)
  extras     "Serve 2 pessoas", "3 un" (xs muted), selo "Recomendado"
  estados    normal, hover (shadow card), em falta (opacidade .5 + selo), sem foto (sem espaço reservado)
  usado em   S01, S06 ("Peça também")
```

```
Store header (cabeçalho da loja)
  capa 180px (celular) com degradê para baixo; card sobreposto surface radius xl (24) shadow card:
  logo 64px radius md, status ("Aberto até 18:00" success | "Abre hoje às 18:00" warning), nome xl display, bairro e cidade
  linha de informações: Entrega "20–60 min" | Pedido mínimo | Cashback
  usado em   S01
```

```
Feature card (marketing e vitrine) — padrão Saipos
  radius xl (24), foto cobrindo, degradê de baixo para cima na cor de fundo (secondary, primary ou accent),
  título lg/700 branco + descrição sm on-dark; grade em mosaico (bento)
  usado em   landing page (/replica-launch)
```

```
Section heading
  sobrelinha (Badge outline) + título 2xl/3xl display com 1–3 palavras em secondary + parágrafo base muted
  usado em   landing page, páginas vazias do painel
```

```
Bottom sheet / Modal
  celular   sheet que sobe de baixo, radius xl só em cima, alça 40×4, título lg + botão fechar (X)
  desktop   modal centralizado, máx 560px, radius xl, shadow pop, fundo escurecido rgba(15,47,44,.5)
  a11y      Radix Dialog: foco preso, Esc fecha, foco volta ao gatilho, aria-labelledby
  usado em  S03, S05, S07 (endereço), S22
```

```
Kanban column + Order card (quadro de pedidos)
  coluna     largura 300, cabeçalho com fundo status-*-soft, borda 1px da cor de status, radius lg:
             nome lg/700 na cor de status + Badge count + soma "R$ 111,40" xs
  card       surface, radius md, shadow sm, padding 12:
             código "#1042" lg/800 display · tempo "Há 4 min" xs (vira danger após o prazo) · cronômetro mono
             cliente sm/600 + telefone · selo do cliente (Novo | 2º pedido | WhatsApp)
             bairro ou "Retirada no local" · "Ver mais" (expande itens)
             total base/700 · pagamento ("Pix · Aprovado" success, "A cobrar" warning)
             ações: danger-outline "Recusar" + primary "Aceitar | Pronto | Saiu | Finalizar" (sm)
  estados    novo (anel accent pulsando 3x + som), atrasado (barra lateral danger), arrastando (shadow pop)
  a11y       mover por botões (não só arrastar); região aria-live anuncia "Novo pedido #1042"
  usado em   S21
```

```
Sidebar (painel)
  largura 248, fundo surface, borda direita border; seletor de loja no topo; status "Loja aberta" (toggle success)
  itens 40px, ícone Lucide 18px + sm/500; ativo: fundo secondary-soft, texto secondary-strong, barra 3px secondary
  grupos com submenu (accordion); no celular vira gaveta
  usado em   S20
```

```
Top bar do painel
  72px, busca (Nome, telefone ou código) + ações em Button secondary sm: Pausar loja, Alta demanda, Relatório do dia;
  CTA "+ Novo pedido"; sino com contador
  usado em   S21
```

```
Accordion (perguntas e ajuda) — padrão Saipos
  linhas surface radius md, título base/600 + chevron; aberto: conteúdo sm muted
  a11y       <button aria-expanded> + região
  usado em   landing, ajuda do painel
```

```
Summary rows (resumo de valores)
  linhas sm: rótulo muted à esquerda, valor à direita; total lg/700; "a calcular" em itálico muted;
  cashback a receber em success
  usado em   S06, S09
```

```
Toast
  canto inferior (celular: em cima da barra fixa), surface-dark + on-dark, radius md, shadow pop, 4s;
  variantes info, success, error (barra lateral da cor)
  a11y       role="status" (erro: role="alert")
  usado em   todas ("Cliente avisado no WhatsApp")
```

```
Empty state
  ilustração própria (ou ícone Lucide 40px em círculo secondary-soft) + título lg + texto sm + 1 botão
  textos novos, escritos para o Delisys
  usado em   S21 sem pedidos, S27 cardápio vazio, S06 sacola vazia
```

```
Floating bar (cardápio)
  fixa no rodapé do celular: Button cta lg em largura total "Ver sacola · R$ 25,00" + atalho de WhatsApp
  respeita a área segura do iPhone (env(safe-area-inset-bottom))
  usado em   S01, S02
```

## Fora do sistema (nunca copiar)

- O logo da Saipos (raposa), as ilustrações de traço azul (bicicleta, mão), as fotos e os mockups de produto.
- A fonte Iquost. O Delisys usa **Outfit** nos títulos (licença OFL).
- Os textos da Saipos e da Brendi. Todo rótulo e toda mensagem são escritos do zero.
- A combinação roxo-escuro + azul royal + laranja sobre creme. O Delisys usa verde-petróleo + teal + mostarda sobre creme claro.
