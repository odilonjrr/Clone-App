# Componentes

Sistema próprio. Não houve capturas do DM Champ (rede bloqueada), então nada
aqui foi medido do original: os papéis e padrões vêm da lista de componentes do
`recon.md`, e os valores são nossos. Ícones: Lucide (ISC). Fonte: Inter
(SIL OFL). Nenhum logo, ícone ou texto do original.

Tokens: `replica/design/tokens.json` → `node replica/design/build-tokens.mjs`.
Classes do Tailwind (nunca hex nos componentes):

| papel | classe | | papel | classe |
| --- | --- | --- | --- | --- |
| bg | `bg-bg` | | accent | `bg-accent` / `text-accent` |
| surface | `bg-surface` | | on-accent | `text-on-accent` |
| surface-raised | `bg-surface-raised` | | accent-soft | `bg-accent-soft` |
| surface-hover | `bg-surface-hover` | | danger / success / warning | `text-danger`, `bg-danger-soft`… |
| border | `border-border` | | note-bg / note-text | `bg-note-bg text-note-text` |
| border-input | `border-border-input` | | channel-* / on-channel | `bg-channel-telegram text-on-channel` |
| text | `text-fg` | | raio | `rounded-sm/md/lg/pill` |
| text-muted | `text-muted` | | sombra | `shadow-card`, `shadow-pop` |

Regras gerais para todos os componentes:
- **Foco:** `focus-visible` com anel de 2px `accent` e 2px de afastamento.
  Nunca remover o contorno sem pôr o anel.
- **Desabilitado:** opacidade 50%, `cursor-not-allowed`, atributo `disabled` real
  (ou `aria-disabled` quando o elemento precisa continuar focável para explicar
  por quê, como o toggle da IA com o agente desligado).
- **Movimento:** `--motion-fast` (120ms) em hover/foco, `--motion-base` (200ms)
  em modais e drawers; tudo desligado com `prefers-reduced-motion`.
- **Alvo de toque:** mínimo 40px de altura no celular (botões `sm` ganham área
  extra de toque).
- **Texto:** escrito do zero em português; nada copiado do original.

---

## Primitivos

```
Button
  variants  primary, secondary, ghost, danger
  sizes     sm 32px, md 40px, lg 48px; icon (quadrado) em cada tamanho
  states    default, hover, active, focus-visible, disabled, loading
  tokens    primary: bg accent / hover accent-hover / text on-accent
            secondary: bg bg, borda border-input, text fg, hover surface-hover
            ghost: sem fundo, text fg, hover surface-hover
            danger: bg danger, text on-accent
            radius md, type sm/600
  a11y      <button> real; loading = spinner + aria-busy, o rótulo continua
            no DOM para leitor de tela; botão só com ícone exige aria-label
  used on   todas
```

```
Input / Textarea
  variants  text, email, url, search (com ícone), textarea (auto-cresce)
  sizes     md 40px
  states    default, hover, focus-visible, disabled, invalid, read-only
  tokens    bg bg, borda border-input, foco anel accent, invalid borda danger
            + mensagem text-danger, placeholder text-muted, radius md
  a11y      <label> sempre ligado (htmlFor); erro em aria-describedby e
            aria-invalid; dica abaixo em text-muted xs
  used on   S01, S02, S08, S10, S14, S15, S16, S19
```

```
Select
  base      Radix Select
  states    default, aberto, foco, disabled, invalid
  tokens    gatilho igual ao Input; lista bg surface-raised, shadow-pop,
            item ativo bg accent-soft
  a11y      navegação por setas, digitar para buscar, Esc fecha
  used on   S08d (idioma), S08a (tom), S15 (fonte), S16
```

```
Switch (toggle)
  variants  padrão; com rótulo de estado ("IA ativa" / "IA pausada")
  sizes     md 20x36
  states    ligado (accent), desligado (border-input), foco, disabled,
            bloqueado (aria-disabled + tooltip explicando o motivo)
  a11y      Radix Switch (role="switch", aria-checked); o rótulo de estado
            muda junto com o valor
  used on   S05, S06, S07, S08b, S08f, S08i, S15
```

```
Checkbox
  base      Radix Checkbox; states marcado, desmarcado, indeterminado, foco, disabled
  used on   S15 (campos coletados), S19 (seleção em massa)
```

```
Badge
  variants  neutral, accent, success, warning, danger
  tokens    fundo *-soft, texto do papel; radius pill; type xs/500
  used on   S04 (não lidas), S07 (rascunho/publicado), S11 (status)
```

```
ChannelBadge
  variants  whatsapp, instagram, telegram, web; sizes dot 8px, sm (ícone), md (ícone + nome)
  tokens    bg channel-*, text on-channel
  a11y      a cor nunca é a única pista: sempre ícone + nome ou aria-label
  note      ícones genéricos do Lucide (sem logos das plataformas)
  used on   S04, S05, S11, S19
```

```
Avatar
  variants  foto, iniciais (fundo surface-hover), com ChannelBadge no canto
  sizes     24, 32, 40
  used on   S04, S05, S06, S19
```

```
Card
  tokens    bg bg (claro) / surface (escuro), borda border, radius lg, shadow-card
  used on   S03, S07, S11
```

```
Tabs
  variants  linha (abas do editor), segmentado (filtros curtos)
  states    ativa (texto fg + barra accent), inativa (muted), com mudança
            não publicada (ponto accent), foco
  a11y      Radix Tabs; setas trocam aba; no celular vira lista rolável
  used on   S08, S04 (filtros)
```

```
Dialog (modal)
  tokens    overlay black/40, painel surface-raised, radius lg, shadow-pop
  states    aberto, fechando, com formulário enviando
  a11y      Radix Dialog: foco preso, Esc fecha, volta o foco ao gatilho,
            título obrigatório
  used on   S11 (conectar canal), S16, confirmações destrutivas
```

```
Drawer (painel lateral)
  uso       painel do contato no celular e telas médias; mesmo base do Dialog
  used on   S06
```

```
Toast
  variants  success, danger, info
  a11y      região aria-live="polite" (danger = "assertive"); some em 5s,
            pausa com hover/foco; ação opcional ("Desfazer")
  used on   todas
```

```
Tooltip
  base      Radix Tooltip; atraso 400ms; também abre por foco de teclado
  used on   botões só com ícone, toggle bloqueado
```

```
Table
  states    vazio (EmptyState), carregando (Skeleton nas linhas), paginada
  a11y      <table> real com <th scope>; ordenação com aria-sort
  used on   S10, S19
```

```
EmptyState
  partes    ícone Lucide 32px em muted, título sm/600, texto muted, ação primária
  used on   S04, S07, S10, S19, S20 (cada um com texto próprio)
```

```
Skeleton
  tokens    bg surface-hover, pulso desligado com prefers-reduced-motion
  used on   listas e tabelas carregando
```

```
CodeBlock (copiável)
  tokens    bg surface, fonte mono, borda border, radius md
  states    padrão, copiado (botão vira "Copiado" por 2s, anunciado via aria-live)
  used on   S15
```

## Compostos

```
SideNav
  variants  desktop (232px, recolhe para 64px), celular (gaveta)
  states    item ativo (bg accent-soft, texto accent), hover, foco, com contador
  a11y      <nav aria-label="Principal">; item ativo com aria-current="page"
  used on   todas as telas logadas
```

```
ConversationItem
  partes    Avatar + ChannelBadge, nome (sm/600), prévia (sm, muted, 1 linha),
            hora (xs, muted), contador de não lidas (Badge accent),
            ícones de fixada/estrela, indicador "IA pausada"
  states    lida, não lida (nome e prévia em fg), selecionada (bg accent-soft),
            hover, foco
  a11y      item de lista com link; a prévia é truncada só visualmente
  used on   S04
```

```
MessageBubble
  variants  recebida (bg surface, alinhada à esquerda)
            IA (bg accent-soft, rótulo "IA" em xs)
            humano (bg accent, text on-accent, nome do atendente)
            nota interna (bg note-bg, text note-text, ícone de nota, largura total)
            sistema (centralizada, xs, muted)
  states    enviando (relógio), enviada, entregue, lida (dois tiques),
            falhou (ícone danger + "Tentar de novo"), agendada (hora prevista)
  anexos    Attachment dentro do balão
  a11y      cada balão é um <article> com autor e hora para leitor de tela;
            status em texto, não só ícone
  used on   S05, S09, S18
```

```
Attachment
  variants  áudio (player + transcrição recolhível), imagem (miniatura,
            abre em Dialog), vídeo, arquivo (ícone + nome + tamanho)
  states    carregando, erro (arquivo grande demais / não baixou)
  used on   S05, S18
```

```
ChatStatusBar
  variants  fora do horário ("Fora do horário · volta às 9h"), IA pausada,
            canal desconectado (danger), janela de 24h fechada (warning)
  a11y      role="status"
  used on   S05
```

```
Composer
  variants  resposta, nota interna (fundo note-bg, aviso "Só a equipe vê")
  states    vazio (enviar desabilitado), digitando, enviando, bloqueado
            (janela fechada: explica e oferece template)
  a11y      Enter envia, Shift+Enter quebra linha; troca resposta/nota por
            abas com teclado
  used on   S05, S09
```

```
PublishBar
  states    sem mudanças ("Publicado"), com mudanças ("Rascunho com mudanças"
            + Publicar + Descartar), publicando, erro
  posição   fixa no topo do editor; no celular, no rodapé
  used on   S08
```

```
HoursEditor
  partes    Switch "Ativo 24/7"; quando desligado, uma linha por dia:
            Switch do dia + início + fim (+ adicionar intervalo)
  states    24/7 (linhas escondidas), por dia, inválido (fim antes do início,
            mensagem danger na linha)
  a11y      cada linha é um grupo com o nome do dia como legenda
  used on   S08b
```

```
EditableList
  uso       pares tag + regra (S08e), pergunta + resposta (S10), etapas do
            playbook (S08c)
  states    vazio, preenchido, editando item, arrastando (reordenar)
  a11y      reordenar também por botões subir/descer (não só arrastar)
```

```
ChannelCard
  partes    ChannelBadge md, nome do canal, descrição curta, status (Badge),
            ação (Conectar / Configurar / Reconectar)
  states    não conectado, conectado, erro (texto do erro + Reconectar),
            em breve (fase 2: desabilitado com Badge "Em breve")
  used on   S11
```

```
ColorField / DomainChips
  ColorField  amostra + input hex validado + seletor nativo
  DomainChips input que vira chip ao apertar Enter ou vírgula; valida domínio
              e curinga (*.exemplo.com); chip inválido em danger; Backspace
              apaga o último
  used on   S15
```

```
SlotPicker
  partes    dias em abas curtas, horários em grade de botões, fuso exibido
  states    carregando, sem horários, selecionado, ocupado (some + aviso)
  used on   S18, S05
```

```
ChatWidget (lado do visitante)
  variants  bolha flutuante (canto configurável), inline
  states    fechado (bolha 56px), aberto (380x600 no desktop, tela cheia no
            celular), digitando (três pontos), offline, erro de conexão
  tokens    cor de destaque vem da configuração do cliente (WidgetConfig),
            não do nosso accent; o widget calcula on-color para manter 4.5:1
  a11y      bolha é <button aria-expanded>; janela é role="dialog";
            mensagens novas em aria-live="polite"
  used on   S18, prévia em S15
```

## Layout

| área | medida |
| --- | --- |
| barra lateral | 232px, recolhida 64px |
| cabeçalho | 56px |
| caixa de entrada | lista 320px · conversa flexível · painel do contato 320px |
| conteúdo de configuração | máximo 1120px, centralizado |
| breakpoints | sm 640 · md 768 · lg 1024 · xl 1280 |
| caixa de entrada abaixo de lg | uma coluna por vez (lista → conversa → contato em drawer) |

Primitivos construídos em `web/src/components/ui/` e mostrados em `/design`.
