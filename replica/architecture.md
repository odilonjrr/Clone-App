# Arquitetura: nome a definir (recriação das funções de chatbot do DM Champ)

Base: `replica/recon.md` e `replica/features.csv` (2026-10-18).
Decisões aprovadas: SaaS próprio para vender; WhatsApp só pela Cloud API
oficial; Telegram pela Bot API (token do BotFather); ordem de construção =
agente + widget + caixa de entrada + Telegram primeiro, WhatsApp e Instagram
depois.

## Stack

| camada | escolha | por quê |
| --- | --- | --- |
| web | Next.js (App Router) + TypeScript | painel, rotas de API e webhooks num projeto só |
| estilo | Tailwind + tokens do `/replica-design` | padrão do pacote; tokens viram variáveis CSS |
| banco | Postgres no Supabase | um banco só; RLS, Realtime e Storage no mesmo lugar |
| ORM | Drizzle | SQL explícito, tipos gerados, migrações em arquivo |
| auth | Supabase Auth (e-mail + Google) | integra com RLS via `auth.uid()` |
| tempo real | Supabase Realtime | caixa de entrada ao vivo e mensagens no widget sem servidor próprio de WebSocket |
| arquivos | Supabase Storage | mídia recebida, mídia do agente, uploads de conhecimento |
| filas e agendamento | Inngest | `sleepUntil` para horário ativo, atrasos de digitação e follow-ups; cancelamento por evento quando o humano assume; retries |
| IA (texto, imagem, PDF) | API Claude, `claude-opus-5-5`, via `@anthropic-ai/sdk` | modelo padrão atual; lê imagens e PDFs; ferramentas com `strict: true`; cache de prompt |
| transcrição de áudio | provedor de STT à parte (recomendação: Deepgram) | o Claude não transcreve áudio |
| busca no conhecimento | Postgres full-text no v1; base inteira no prompt em cache quando couber | sem provedor de embeddings no v1 (o Claude não gera embeddings) |
| pagamentos | Stripe Checkout + Billing | planos mensais; portal do cliente pronto |
| e-mail | Resend | convites, avisos de canal desconectado |
| hospedagem | Vercel | deploy do Next.js; Inngest roda as funções longas por chamadas HTTP |
| monitoramento | Sentry | erros de webhook e de IA aparecem antes do cliente reclamar |

Sem microsserviços. Um app Next.js, um Postgres, um Inngest.

### Como a IA é chamada

- **Modelo:** `claude-opus-5-5`, configurável por variável de ambiente
  (`AI_MODEL`). Respostas de chat com `output_config.effort: "low"`, que é o
  ponto de partida recomendado para chat. Tagging e geração de FAQ também em
  `low`. O pensamento adaptativo fica no padrão (não dá para desligar neste
  modelo).
- **Recusas:** `fallbacks: "default"` com o beta `server-side-fallback-2026-07-01`.
  O código sempre confere `stop_reason` antes de ler o conteúdo.
- **Montagem do prompt (ordem fixa, para o cache funcionar):**
  1. ferramentas (`book_slot`, `list_slots`, `send_media`, `apply_tag`,
     `handoff_to_human`, webhooks do cliente, `web_search_20260209` se ligado),
     em ordem estável;
  2. sistema: instruções, tom, playbook, regras de tag, lista de mídia e
     **conhecimento inteiro**, se a versão publicada couber em ~150 mil tokens.
     Marcado com `cache_control`;
  3. histórico da conversa (só acrescenta, nunca edita, por causa do
     "preserved thinking");
  4. dados que mudam (hora atual no fuso do contato, canal, janela de 24 h)
     entram como mensagem de sistema no meio da conversa, depois do ponto de
     cache.
  Bases maiores que o limite usam busca full-text (`knowledge_chunks.search`)
  e mandam só os trechos mais relevantes.
- **Custo estimado por resposta:** cerca de US$ 0,01 a 0,03 com 20 mil tokens
  de prompt em cache. É uma conta de cabeça a partir da tabela de preços, não uma
  medição: ~US$ 0,20/MTok de leitura de cache, US$ 4/MTok de entrada nova,
  US$ 20/MTok de saída. Medir com `usage_events` no recorte vertical antes de
  definir preços. Se a margem não fechar, a troca para `claude-sonnet-5-5` ou
  `claude-haiku-5-5` é uma variável de ambiente, mas é decisão sua.

## Schema

Tabelas: 30. Acesso: **Row Level Security** por workspace (`is_member`,
`is_admin`). Os workers (webhooks, Inngest) usam a service role e filtram
`workspace_id` explicitamente em toda consulta.

SQL completo: `replica/schema.sql`. Validado num Postgres 17 (PGlite) com:
webhook duplicado rejeitado, agendamento sobreposto rejeitado, nota interna
só com `direction = 'internal'`, e RLS isolando dois workspaces.

| grupo | tabelas |
| --- | --- |
| conta | workspaces, profiles, memberships, subscriptions |
| conhecimento | knowledge_bases, knowledge_sources, faq_items, knowledge_chunks |
| agentes | agents, agent_versions, agent_version_knowledge, agent_media, tags, agent_tag_rules |
| canais | channels, widget_configs, entry_points |
| conversas | contacts, contact_identities, conversations, messages, contact_tags, follow_up_jobs |
| agenda | calendar_connections, bookings |
| automação | automations, automation_runs, tasks |
| operação | usage_events, webhook_events |

Restrições que resolvem problemas no banco, não na interface:
- `messages (channel_id, external_id)` único: o mesmo webhook duas vezes não
  vira duas mensagens nem duas respostas.
- `bookings` com exclusão `tstzrange &&` por agenda: dois contatos não pegam o
  mesmo horário.
- `channels (kind, external_id)` único: o mesmo bot ou número não entra em dois
  workspaces.
- `one_default_per_channel`: cada canal tem no máximo um agente padrão.
- `one_draft_per_agent`: um rascunho por agente; publicar cria uma versão
  imutável, e as mensagens guardam qual versão respondeu.
- Tokens de canal e de agenda ficam cifrados na aplicação (AES-256-GCM, chave
  em variável de ambiente), nunca em texto puro.

## API

Server actions do Next.js para o painel; rotas HTTP para webhooks, widget e
jobs. "membro" = qualquer pessoa do workspace; "admin" = owner/admin.

### F01 Criar o primeiro agente a partir do site

| método caminho | faz | quem | entrada | saída |
| --- | --- | --- | --- | --- |
| action `createWorkspace` | cria workspace + membership owner | logado | nome | workspace |
| action `startAgentFromWebsite` | cria agente, rascunho, base e fonte `website`; dispara `knowledge/ingest` | admin | url | agent_id |
| GET `/api/agents/:id/ingest-status` | progresso da leitura do site | membro | — | status, páginas, erros |
| action `updateDraft` | salva campos do rascunho (cada aba de S08) | admin | patch parcial | versão rascunho |
| POST `/api/agents/:id/playground` | conversa com o rascunho, em streaming; não envia a canal nenhum | admin | mensagens | stream SSE |
| action `publishAgent` | copia o rascunho para uma versão `published` | admin | agent_id | versão |
| action `setAgentActive` | liga/desliga o agente | admin | agent_id, bool | — |

### F02 Conectar WhatsApp (fase 2)

| método caminho | faz | quem | entrada | saída |
| --- | --- | --- | --- | --- |
| POST `/api/channels/whatsapp/connect` | troca o código do Embedded Signup por token; registra número e assinatura do webhook | admin | code, waba_id, phone_number_id | channel |
| GET/POST `/api/webhooks/meta` | verificação (GET) e eventos (POST) do WhatsApp e do Instagram; valida `X-Hub-Signature-256` | Meta | payload | 200 rápido |
| action `listWhatsappTemplates` / `sendTemplate` | templates aprovados para fora da janela de 24 h | membro | — / template, variáveis | — |

### F03 Instalar o widget no site

| método caminho | faz | quem | entrada | saída |
| --- | --- | --- | --- | --- |
| action `createWebChannel` | cria canal `web` + widget_config | admin | nome | channel, public_key |
| action `updateWidgetConfig` | cor, tema, canto, fonte, layout, boas-vindas, campos, domínios | admin | patch | config |
| GET `/widget.js` | script de instalação (estático, com cache); cria o iframe | público | `data-key` | JS |
| GET `/w/:publicKey` | página do iframe; envia `Content-Security-Policy: frame-ancestors` com os domínios permitidos | público | — | HTML |
| POST `/api/widget/session` | cria ou retoma visitante; devolve token assinado para o Realtime | público + public_key | visitor_id? | token, conversa |
| POST `/api/widget/messages` | mensagem do visitante; dispara `message/received` | visitante (token) | texto, anexos, campos do formulário | message |
| GET `/demo/:publicKey?site=` | link de demo: mostra o site do cliente com o widget por cima | admin (link assinado) | — | HTML |

### F04 Conectar Instagram + comment-to-DM (fase 2)

| método caminho | faz | quem | entrada | saída |
| --- | --- | --- | --- | --- |
| GET `/api/channels/instagram/oauth/start` e `/callback` | login Meta, escolhe a conta profissional, assina webhooks | admin | — | channel |
| action `upsertEntryPoint` | regras de comentário, palavra-chave, seguidor | admin | entry point | entry point |
| (webhook `/api/webhooks/meta`) | comentário → resposta pública + Private Reply → conversa | Meta | — | — |

### F05 Conectar Telegram

| método caminho | faz | quem | entrada | saída |
| --- | --- | --- | --- | --- |
| POST `/api/channels/telegram/connect` | valida o token (`getMe`), cifra e salva, chama `setWebhook` com `secret_token` | admin | bot token | channel |
| POST `/api/webhooks/telegram/:channelId` | recebe updates; confere `X-Telegram-Bot-Api-Secret-Token`; grava em `webhook_events`; dispara `message/received` | Telegram | update | 200 rápido |
| action `disconnectChannel` | `deleteWebhook`, apaga credenciais, status `disconnected` | admin | channel_id | — |

### F06 Mensagem chega e a IA responde (jobs)

| função Inngest | gatilho | faz |
| --- | --- | --- |
| `message/received` | evento | normaliza para `messages`; acha ou cria contato, identidade e conversa; escolhe o agente (entry points); atualiza `last_inbound_at`; cancela follow-ups pendentes; emite `conversation/reply.requested` |
| `conversation/reply` | `reply.requested`, com **debounce de ~8 s por conversa** (junta mensagens em sequência) e `cancelOn: conversation/ai.paused` | confere `bot_active` e agente ativo; se fora do horário, `sleepUntil` a próxima abertura (o status bar mostra); transcreve áudio; chama o Claude; executa ferramentas; divide a resposta; para cada parte, espera o atraso de digitação e envia pelo adaptador do canal; grava `usage_events`; agenda follow-ups |
| `media/transcribe` | chamado por `reply` | baixa o áudio do provedor, guarda no Storage, transcreve, salva `transcript` |
| `knowledge/ingest` | evento | lê o sitemap/páginas do site do cliente (respeita robots.txt, limite de páginas), extrai texto, gera chunks e um FAQ inicial com o Claude |

Adaptadores de canal, todos com a mesma interface `send(conversation, part)`:
`web` (insere a mensagem e manda broadcast no Realtime), `telegram`
(`sendMessage`, `sendChatAction typing`, `sendDocument`), `whatsapp` (Cloud API
`/messages`), `instagram` (Send API).

### F07 Humano assume

| método caminho | faz | quem |
| --- | --- | --- |
| action `setBotActive` | liga/pausa a IA no contato; ao pausar, emite `conversation/ai.paused` (cancela resposta e follow-ups em andamento) | membro |
| action `sendHumanMessage` | envia pelo adaptador do canal; respeita a janela de 24 h na Meta | membro |
| action `addNote` | mensagem `note`/`internal`; não envia, não pausa a IA | membro |
| action `assignConversation` | define `assigned_user_id` | membro |
| action `toggleFlag` | fixar / estrela | membro |
| action `autoTagWithAI` | aplica regras de tag do agente na conversa inteira | membro |

### F08 Follow-ups

| função Inngest | gatilho | faz |
| --- | --- | --- |
| `followup/schedule` | fim de `reply` | cria `follow_up_jobs` conforme a versão do agente; Instagram só ciclo rápido (<24 h); WhatsApp fora de 24 h só com template |
| `followup/send` | `sleepUntil send_at`; `cancelOn` mensagem do contato ou IA pausada | confere de novo `follow_ups_disabled` e a janela; gera e envia |

### F09 Agendamento no chat

| método caminho | faz | quem |
| --- | --- | --- |
| GET `/api/calendar/google/oauth/start` e `/callback` | conecta Google Calendar (escopo de eventos + free/busy) | admin |
| ferramenta `list_slots` | `freeBusy` do Google + bookings confirmados → slots livres no fuso do contato | IA |
| ferramenta `book_slot` | insere `bookings` (a exclusão impede duplicado), cria o evento no Google, responde no chat | IA |

### F10 Tags e automações

| método caminho | faz | quem |
| --- | --- | --- |
| ferramenta `apply_tag` | insere `contact_tags` com `applied_by = 'ai'`; emite `contact/tag.applied` | IA |
| action `upsertAutomation` | gatilho, filtros, passos | admin |
| função `automation/run` | `tag.applied` → atribuir agente/membro, aplicar tag; proteção contra loop: não reatribui o mesmo agente nem responde de novo | — |

### Outras

| método caminho | faz | quem |
| --- | --- | --- |
| POST `/api/webhooks/stripe` | assinatura → `subscriptions`, `workspaces.plan` | Stripe |
| action `createCheckout` / `openBillingPortal` | Stripe Checkout / portal | owner |
| actions de conhecimento | CRUD de bases, FAQ, upload de arquivo, re-sincronizar site | admin |
| actions de contatos, tags, tarefas, membros | CRUD | membro / admin |

Webhooks de entrada: Telegram, Meta (WhatsApp + Instagram), Stripe, Google (só
OAuth no v1).
Webhooks de saída: funções customizadas do cliente (habilidade `webhooks`),
chamadas pela IA com timeout de 10 s e assinatura HMAC.

Jobs agendados:
- `channels/health`, a cada hora: testa tokens (`getMe`, debug_token da Meta);
  marca `error` e manda e-mail se falhar.
- `knowledge/resync`, semanal, opcional por fonte: relê o site.
- `webhook_events/retry`, a cada 5 min: reprocessa eventos sem `processed_at`.
- `usage/rollup`, diário: soma o uso por workspace para os limites de plano.

Só APIs oficiais e públicas, com as chaves do próprio cliente ou do nosso app.
Nada de endpoints privados do DM Champ.

## As partes que mordem

- **Fusos horários:** horário ativo é guardado como hora local + fuso IANA da
  versão do agente; a próxima abertura é calculada com uma biblioteca de fuso
  (Temporal ou date-fns-tz), nunca somando horas. O horário de verão some ou
  repete uma hora; testar as duas transições. Slots de agenda são mostrados no
  fuso do contato, que a IA pergunta.
- **Idempotência:** Telegram e Meta reenviam webhooks. A rota grava
  `webhook_events (provider, external_id)` e responde 200 na hora; o
  processamento é no Inngest. `messages` tem índice único por id externo.
  O envio grava a mensagem antes, como `pending`, para um retry não mandar duas
  vezes.
- **Corridas:** o contato manda três mensagens seguidas → debounce por conversa
  junta tudo numa resposta. O humano pausa a IA enquanto ela "digita" →
  `cancelOn` interrompe antes da próxima parte. Dois contatos no mesmo slot →
  restrição de exclusão.
- **Janela de 24 h da Meta:** fora dela, WhatsApp só aceita template aprovado e
  o Instagram não aceita nada. O composer e os follow-ups leem
  `last_inbound_at` e bloqueiam com uma explicação.
- **Limites de taxa:** Telegram aceita cerca de 1 mensagem/s por chat e ~30/s
  por bot; WhatsApp e Instagram têm limites por número/conta. Os adaptadores
  usam a concorrência e o throttle do Inngest por canal.
- **Tamanho de arquivo:** a Bot API do Telegram baixa até 20 MB; WhatsApp tem
  limites por tipo de mídia. Acima disso, guardar o aviso e não chamar a IA com
  o arquivo.
- **Busca:** full-text com config `simple` (funciona em vários idiomas, sem
  stemming). Se a qualidade não bastar, adicionar `pgvector` + um provedor de
  embeddings depois.
- **Tempo real:** a caixa de entrada assina mudanças em `messages` e
  `conversations` filtradas pelo workspace (respeitando RLS). O widget usa um
  canal de broadcast privado por conversa, autorizado pelo token do visitante.
- **Multi-tenant:** toda tabela tem `workspace_id`; RLS para o painel; nos
  workers, um helper `forWorkspace(id)` que injeta o filtro, e um teste que
  falha se alguma consulta do worker não filtrar.
- **Segurança do widget:** `frame-ancestors` aplicado pelo navegador com os
  domínios permitidos; a `public_key` não dá acesso a nada além de abrir uma
  conversa nova; limite de taxa por IP e por visitante contra spam que gasta IA.
- **Prompt injection:** mensagens de contato e páginas do site são dados. As
  ferramentas que escrevem (agendar, webhook, tag) só agem na conversa atual;
  webhooks do cliente só vão para URLs que o admin cadastrou.
- **E-mail:** Resend com SPF/DKIM do nosso domínio; só e-mails transacionais.
- **LGPD/GDPR:** apagar contato apaga identidades, conversas, mensagens e mídia
  (cascata + limpeza do Storage). Exportação por contato. Aviso de IA na
  primeira resposta (configurável em `ai_disclosure`).
- **Aprovação da Meta:** não é código. Abrir o app Meta, verificação da empresa
  e App Review no início da fase 1, para estarem prontos quando a fase 2 chegar.

## Ordem de construção

### 1. Recorte vertical (feio, ponta a ponta)

Criar conta → colar site → agente gerado → publicar → instalar widget numa
página de teste → mandar mensagem → a IA responde com base no site → ver a
conversa no painel e pausar a IA.

- Telas: S01, S02, S07, S08a (só instruções), S15 (mínimo), S18, S04, S05
- Tabelas: workspaces, profiles, memberships, agents, agent_versions,
  knowledge_bases, knowledge_sources, knowledge_chunks, channels,
  widget_configs, contacts, contact_identities, conversations, messages,
  usage_events
- Rotas: `createWorkspace`, `startAgentFromWebsite`, `knowledge/ingest`,
  `publishAgent`, `/widget.js`, `/w/:key`, `/api/widget/session`,
  `/api/widget/messages`, `message/received`, `conversation/reply`,
  `setBotActive`

### 2. Essenciais da fase 1 (agente + widget + caixa de entrada + Telegram)

- Agente: S08 completo (tom, idioma, playbook, horário ativo, follow-ups,
  tags, mídia, conhecimento, habilidades), S09 playground, S10 FAQ e upload
- Widget: aparência completa, inline, domínios, campos de captura, mobile
- Caixa de entrada: filtros, busca, notas, anexos, atribuição, barra de status,
  S06 painel do contato
- Telegram: S14, adaptador, webhook, mídia, transcrição de áudio
- Entry points (S16): padrão do canal e palavra-chave
- Conta: membros, Stripe, limites de plano a partir de `usage_events`

### 3. Fase 2 (depende da aprovação da Meta)

- WhatsApp Cloud API: S12, Embedded Signup, templates, janela de 24 h,
  vários números
- Instagram: S13, DMs, respostas a stories, comment-to-DM (S17), seguidor novo
- Follow-ups com as regras de janela por canal

### 4. Importantes e opcionais

Agendamento com Google Calendar (S21, F09), webhooks do cliente, busca na web,
automações (S20), contatos (S19), tarefas, auto-tag sob demanda, link de demo,
reengajamento do visitante por e-mail/telefone.

### 5. Correções do `/replica-entrepreneur`

Depois que ele ler as avaliações públicas do DM Champ.
