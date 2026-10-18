# Mapa de recon: DM Champ (web app)

Escopo: agentes de IA que atendem no WhatsApp, Instagram, Telegram e num widget
de chat no site do cliente, com todas as configurações do agente, a caixa de
entrada para humanos assumirem e o roteamento de conversas.
Para: a confirmar com o usuário (proposta: produto SaaS próprio para vender).
Data: 2026-10-18

> **Como este mapa foi feito.** A política de rede deste ambiente bloqueou o
> acesso direto a `dmchamp.com`, `app.dmchamp.com` e `help.dmchamp.com`. Tudo
> abaixo vem de trechos de páginas públicas obtidos por busca na web (central de
> ajuda pública, páginas de funcionalidades, FAQ, preços). Nenhum login, nenhum
> código e nenhuma API privada foram usados. Não há capturas de tela em
> `replica/screens/`. Cada item traz uma confiança: **alta** (a ajuda oficial
> descreve), **média** (página de marketing ou mais de uma fonte indireta),
> **palpite** (inferido, sem fonte direta).

## Fontes

| # | fonte | URL | o que deu |
| --- | --- | --- | --- |
| 1 | ajuda: interface do chat | https://help.dmchamp.com/chats/chat-interface | cabeçalho do chat, toggle da IA, notas, auto-tag, mobile |
| 2 | ajuda: horários do bot | https://help.dmchamp.com/ai-automation/availability/ | horário ativo por dia, 24/7, mensagens retidas |
| 3 | ajuda: follow-ups (FI) | https://help.dmchamp.com/fi/ai-automation/follow-ups/ | follow-ups rápidos, 1–3 mensagens, atrasos mínimos |
| 4 | ajuda: automações | https://help.dmchamp.com/automations/automations/ | gatilhos, "parar ao responder" |
| 5 | ajuda: atribuições | https://help.dmchamp.com/chats/assignments/ | tags do agente, regras, entry points, proteção contra loop |
| 6 | ajuda: trocar agente | https://help.dmchamp.com/automations/switch-ai-agent-mid-conversation/ | troca de agente no meio da conversa |
| 7 | ajuda: widget de chat | https://help.dmchamp.com/get-started/chat-widget | bolha/inline, whitelist de domínios, link de demo, limites de estilo |
| 8 | ajuda: widget de WhatsApp | https://help.dmchamp.com/get-started/whatsapp-widget/ | existe um botão de WhatsApp para sites (conteúdo não lido) |
| 9 | ajuda: WhatsApp Web | https://help.dmchamp.com/messaging-channels/whatsapp-web/ | conexão por QR (não oficial), 50 créditos/mês |
| 10 | ajuda: Telegram | https://help.dmchamp.com/messaging-channels/telegram/ | conta pessoal por telefone+código, beta, risco de ban |
| 11 | ajuda: API de contatos/mensagens | https://help.dmchamp.com/api/contacts , https://help.dmchamp.com/api/messages | `is_bot_active`, `follow_ups_disabled`, ignorar horário ativo |
| 12 | ajuda: white-label | https://help.dmchamp.com/agency/white-labeling/ | widget servido do domínio da agência |
| 13 | ajuda: planos | https://help.dmchamp.com/billing-and-credits/subscription-tiers | Business / Agency / Agency Unlimited, créditos |
| 14 | página: agentes | https://www.dmchamp.com/features/ai-agents/ | lista das abas do agente, rascunho + playground "Try Out" |
| 15 | página: web chat | https://www.dmchamp.com/features/web-chat/ | qualifica e agenda no widget, reengaja por e-mail/telefone |
| 16 | página: Instagram | https://www.dmchamp.com/features/instagram/ | DMs, comentários, respostas a stories, menções |
| 17 | página: comment-to-DM | https://dmchamp.com/features/comment-to-dm/ | palavra-chave ou qualquer comentário → DM |
| 18 | página: WhatsApp | https://www.dmchamp.com/features/whatsapp/ | 3 formas de API oficial (app deles, Twilio, app próprio), aluguel de número |
| 19 | página: Telegram | https://dmchamp.com/features/telegram/ | "em breve", versão via Bot API oficial |
| 20 | página: agendamento | https://www.dmchamp.com/features/ai-appointment-booking/ | fuso, slots do Google Calendar, deal no CRM |
| 21 | página: mídia | https://www.dmchamp.com/features/media-understanding/ | áudio, imagem, vídeo, PDF |
| 22 | página: IA humana | https://www.dmchamp.com/features/human-like-ai/ | ritmo de digitação, mensagens divididas |
| 23 | página: busca web | https://www.dmchamp.com/features/web-search/ | o agente pode pesquisar na web |
| 24 | preços | https://www.dmchamp.com/pricing/ | fontes conflitantes (ver ressalvas) |
| 25 | comparativo terceiro | https://instantdm.com/vs/dmchamp | planos antigos, limites por canal |

Não lidos (bloqueados): `https://app.dmchamp.com/help` (precisa de login ou
rede) e os artigos "Connect Through Our Meta App" / "Connect Your Own Meta App".

## Loop principal

Uma empresa cola o site, ganha um agente de IA treinado nele, conecta os canais
(WhatsApp, Instagram, Telegram, widget) e o agente responde sozinho a cada
mensagem que chega, qualifica o lead e agenda a reunião. Um humano acompanha
tudo numa caixa de entrada única e assume com um clique.

## Telas

| ID | tela | rota / como chegar | objetivo | componentes principais | estados vistos | confiança |
| --- | --- | --- | --- | --- | --- | --- |
| S01 | Entrar / criar conta | `/login`, `/signup` | acesso | formulário, OAuth Google | vazio, erro | palpite |
| S02 | Onboarding "cole seu site" | primeiro acesso | gerar um rascunho de agente a partir do site (~2 min) | input de URL, progresso, prévia do agente | carregando, pronto, erro de leitura | média (14) |
| S03 | Painel inicial | `/` | resumo: conversas, leads, agendamentos | cards de métricas, lista recente | vazio, preenchido | palpite |
| S04 | Caixa de entrada (lista) | menu Chats | todas as conversas de todos os canais | lista, filtros (canal, atribuído, IA on/off, não lidas), busca, badges de canal | vazio, preenchido, carregando | média (1, 5) |
| S05 | Conversa | clicar numa conversa | ler e responder; ver a IA em tempo real | balões por canal, cabeçalho com toggle **IA ligada / IA pausada**, fixar, estrela, tarefa, busca, ícone de info, menu ⋯ (Auto-tag com IA), composer com anexos e **nota interna** (card amarelo) | IA ligada, IA pausada, agente desligado (toggle cinza), "Fora do horário — abre às X", digitando | alta (1, 2) |
| S06 | Painel do contato | ícone de info em S05 | dados do contato e controles por contato | campos, tags, seção **Follow-up** (próximo envio), Preferências: toggle **Follow-ups**, toggle **Bot responde** / Retomar IA, agente atribuído, membro atribuído | sem agente (follow-up indisponível), com agenda | alta (1, 3) |
| S07 | Lista de agentes | AI Studio → AI Agents | criar/duplicar/ligar/desligar agentes | cards, toggle on/off por agente, botão novo | vazio, preenchido | alta (14, 2) |
| S08 | Editor do agente (abas) | abrir um agente | configurar tudo do agente; edita em **rascunho** e **publica** | abas abaixo, barra Rascunho/Publicar, botão Try Out | rascunho com mudanças, publicado | alta (14) |
| S08a | ↳ Instruções / persona | aba | nome, papel, objetivo, tom, regras | textarea, seletor de tom | — | média (14) |
| S08b | ↳ Horário ativo (passo 2) | aba | quando o bot responde | toggle **Ativo 24/7**; linhas por dia (início–fim) | 24/7 (linhas ocultas), por dia | alta (2) |
| S08c | ↳ Playbook | aba | roteiro de vendas: etapas, qualificação, objeções | lista de etapas editáveis | — | média (14) |
| S08d | ↳ Idioma principal | aba ou campo | idioma padrão das respostas | select | — | média (14) |
| S08e | ↳ Tags | aba | tags que o agente aplica sozinho, cada uma com uma regra em texto | lista tag + regra | — | alta (5) |
| S08f | ↳ Follow-ups (passo 7) | aba | reengajar quem sumiu | toggle, nº de mensagens 1–3 (padrão 1), atraso por mensagem (≥1 h; primeiro ≥24 h no ciclo longo), follow-ups rápidos (<24 h) | — | alta (3) |
| S08g | ↳ Mídia | aba | arquivos que o agente pode enviar (PDF, imagens) | upload, nome, quando enviar | — | média (14) |
| S08h | ↳ Conhecimento | aba | vincular FAQs e bases de conhecimento | seletor de bases, lista de FAQ | — | média (14, 20) |
| S08i | ↳ Habilidades | aba | agendar no calendário, funções customizadas (webhook), ferramentas MCP, busca na web | lista de habilidades com toggle e config | — | média (14, 23) |
| S09 | Playground "Try Out" | botão em S08 | conversar com o rascunho sem gastar créditos | chat de teste, reset | — | alta (14) |
| S10 | Bases de conhecimento / FAQ | AI Studio → Knowledge | criar bases; FAQ manual ou gerada do site | tabela pergunta/resposta, importar do site, upload de arquivo | vazio, gerando, preenchido | média (13) |
| S11 | Canais | Settings → Channels | conectar e ver o status de cada canal | cards por canal: WhatsApp (API oficial), Instagram, Telegram, Widget; status, botão Conectar / Adicionar número | não conectado, conectado, erro/expirado | alta (9) |
| S12 | Conectar WhatsApp (API oficial) | card WhatsApp | ligar um número via WhatsApp Cloud API | Embedded Signup da Meta, seleção de número, teste | conectando, conectado, falha | média (18) |
| S13 | Conectar Instagram | card Instagram | OAuth da Meta, escolher a conta profissional | botão Meta login, seleção de conta, checklist de permissões | conectado, permissão faltando | média (16) |
| S14 | Conectar Telegram | card Telegram | ligar um bot (token do BotFather) | campo de token, nome do bot, status do webhook | conectado, token inválido | palpite (ver §Fora de escopo) |
| S15 | Configurar widget do site | card Widget / Channels & Embed | aparência, instalação e segurança do widget | cor, tema, canto, fonte, **bolha ou inline**, mensagem de boas-vindas, **whitelist de domínios** (`example.com`, `*.example.com`; vazio = todos), **código de instalação**, **link de demo** | sem domínio, com domínios | alta (7) |
| S16 | Entry points | AI Studio → Entry Points | qual agente pega uma conversa nova | regras: padrão do canal, número de WhatsApp, palavra-chave (só 1ª msg), comentário IG, seguidor IG; agente destino | — | alta (5) |
| S17 | Comment-to-DM | Entry point tipo comentário IG | responder comentário e abrir DM | post/reel/anúncio ou todos, palavra-chave (vazia = qualquer), resposta pública, agente | — | média (17) |
| S18 | Widget no site (lado do visitante) | site do cliente | conversar com a IA | bolha, janela, input, coleta de nome/e-mail/telefone, slots de agendamento | fechado, aberto, digitando, offline | média (15) |
| S19 | Contatos | menu Contacts | lista e ficha de contatos | tabela, filtros por tag, importar | vazio, preenchido | média (11) |
| S20 | Automações | menu Automations | gatilho → ações (atribuir agente/membro, tag, mensagem) | construtor de passos, "parar ao responder" | — | alta (4, 5) |
| S21 | Configurações: equipe e integrações | Settings | membros, Google Calendar, chaves de API | lista de membros, conectar calendário | — | média (20) |

21 telas principais + 9 abas do editor do agente.

## Fluxos

```
F01 Criar o primeiro agente a partir do site
    S01 -> S02 (cola URL) -> S08 (revisa rascunho) -> S09 (testa) -> S08 Publicar
    caminho feliz: ~5 cliques
    exceções: site fora do ar / bloqueia leitura, site sem texto (só imagens),
              idioma diferente do esperado

F02 Conectar WhatsApp e ir ao ar
    S11 -> S12 (login Meta, escolhe número) -> S11 conectado -> S16 (padrão do canal = agente)
    caminho feliz: ~6 cliques
    exceções: empresa não verificada na Meta, número já usado no app WhatsApp,
              token expirado, janela de 24 h fechada (só template)

F03 Instalar o widget no site
    S11 -> S15 (cor, posição, domínios) -> copiar código -> colar no site -> S18 funcionando
    caminho feliz: ~4 cliques + colar no site
    exceções: domínio fora da whitelist (widget não carrega), conflito de CSS do
              site, modo inline num contêiner sem altura

F04 Conectar Instagram + comment-to-DM
    S11 -> S13 (Meta login, escolhe conta) -> S16/S17 (regra de comentário) -> visitante comenta -> resposta pública + DM
    caminho feliz: ~7 cliques
    exceções: conta pessoal (não profissional), "Permitir acesso às mensagens"
              desligado, comentário com mais de 7 dias (Private Reply expira),
              DM fora da janela de 24 h

F05 Conectar Telegram
    S11 -> S14 (cola token do BotFather) -> webhook registrado -> S16 padrão do canal
    caminho feliz: ~3 cliques
    exceções: token inválido/revogado, bot já com webhook em outro serviço

F06 Mensagem chega e a IA responde (fluxo do sistema)
    canal -> entry point escolhe agente -> verifica horário ativo -> lê mídia
    (transcreve áudio, lê imagem/PDF) -> busca conhecimento -> gera resposta ->
    divide em pedaços com atraso de digitação -> envia -> aplica tags
    exceções: fora do horário (guarda e responde na abertura; S05 mostra
              "Fora do horário"), IA pausada no contato, agente desligado,
              canal desconectado, falha do modelo (tentar de novo / avisar humano)

F07 Humano assume a conversa
    S04 -> S05 -> toggle "IA pausada" -> responde -> (opcional) "Retomar IA"
    caminho feliz: 2 cliques
    exceções: pausar a IA cancela o follow-up agendado; agente desligado deixa
              o toggle cinza

F08 Follow-up depois de silêncio
    contato some -> agenda conforme S08f -> envia 1–3 mensagens -> para se o
    contato responder
    exceções: Instagram só aceita dentro de 24 h (só follow-ups rápidos);
              WhatsApp fora de 24 h exige template; contato com follow-ups
              desligados em S06

F09 Agendamento dentro do chat
    IA pergunta fuso -> lê disponibilidade do Google Calendar -> oferece slots
    -> visitante escolhe -> evento criado -> deal criado -> confirmação no chat
    exceções: sem slots, slot ocupado entre a oferta e a escolha, fuso ambíguo

F10 Roteamento por tag + automação
    agente aplica tag (regra S08e) -> automação "tag aplicada" -> atribui
    membro ou outro agente (com proteção contra loop)
```

Número a bater: F07 em 2 cliques; F03 em menos de 5 minutos do início ao widget
funcionando.

## Componentes

| componente | variantes | estados | usado em |
| --- | --- | --- | --- |
| Botão | primário, secundário, fantasma, perigo | padrão, hover, foco, desabilitado, carregando | todas |
| Toggle | padrão, com rótulo de estado ("IA ligada / pausada") | ligado, desligado, desabilitado (cinza) | S05, S06, S07, S08b, S08f, S08i |
| Badge de canal | WhatsApp, Instagram, Telegram, Web | — | S04, S05, S11, S19 |
| Item de conversa | lida, não lida, fixada, com estrela | selecionado, hover | S04 |
| Balão de mensagem | recebida, enviada pela IA, enviada por humano, nota interna (amarela), sistema | enviando, enviada, entregue, lida, falhou | S05, S09, S18 |
| Anexo | áudio (com transcrição), imagem, vídeo, PDF | carregando, erro | S05, S18 |
| Barra de status do chat | fora do horário, IA pausada, canal desconectado | — | S05 |
| Composer | normal, nota interna | vazio, digitando, enviando | S05, S09 |
| Abas com passos | — | ativa, com mudanças não publicadas | S08 |
| Barra Rascunho/Publicar | — | sem mudanças, com mudanças, publicando | S08 |
| Editor de horários | linha por dia | ativo, inativo, inválido (fim < início) | S08b |
| Lista editável (tag+regra, FAQ, etapas) | — | vazio, preenchido, editando | S08c, S08e, S10 |
| Card de canal | — | não conectado, conectado, erro | S11 |
| Seletor de cor / tema / canto / fonte | — | — | S15 |
| Input de domínios (chips) | — | vazio, válido, inválido | S15 |
| Bloco de código copiável | — | copiado | S15 |
| Widget (bolha + janela) | bolha flutuante, inline | fechado, aberto, digitando, offline | S18 |
| Seletor de horário (slots) | — | carregando, sem slots, selecionado | S18, S05 |
| Modal / drawer | confirmar, formulário | — | várias |
| Toast | sucesso, erro, info | — | todas |
| Tabela | — | vazio, carregando, paginada | S10, S19 |
| Navegação lateral | desktop, mobile (menu ⋯ concentra ações) | ativo | todas |

## Modelo de dados inferido

```
Workspace     id, nome, plano, fuso_padrao, criado_em
              evidência: planos com assentos (13)            confiança: média
User          id, email, nome, avatar                         confiança: palpite
Membership    workspace_id, user_id, papel (owner|admin|agent) evidência: assentos (13), atribuições (5)  confiança: média

Agent         id, workspace_id, nome, ativo (bool), versao_publicada_id
              evidência: lista de agentes com on/off (1, 14)  confiança: alta
AgentVersion  id, agent_id, status (draft|published), instrucoes, tom,
              playbook (json: etapas), idioma, ativo_24_7 (bool),
              horarios (json: dia -> [inicio, fim]), follow_ups (json:
              ligado, qtd 1–3, atrasos[], rapidos), habilidades (json),
              publicado_em
              evidência: rascunho + publicar (14), passos 2 e 7 (2, 3)  confiança: alta
AgentTagRule  agent_id, tag_id, regra (texto)                  evidência: (5)  confiança: alta
AgentMedia    agent_id, arquivo_url, nome, quando_enviar       evidência: aba Mídia (14)  confiança: média
AgentKnowledge agent_id, knowledge_base_id                     evidência: (14, 20)  confiança: média

KnowledgeBase id, workspace_id, nome, origem (manual|site|arquivo), url_origem
FaqItem       id, knowledge_base_id, pergunta, resposta
KnowledgeChunk id, knowledge_base_id, texto, embedding, fonte_url
              evidência: FAQ manual ou gerada do site (13)    confiança: média (chunks = palpite)

Channel       id, workspace_id, tipo (whatsapp|instagram|telegram|web),
              nome_exibido, status (conectado|erro|desconectado),
              credenciais (cifradas), ids_externos (phone_number_id, ig_user_id,
              bot_username...), conectado_em
              evidência: cards de canais (9)                  confiança: alta
WidgetConfig  channel_id, cor, tema, canto, fonte, layout (bolha|inline),
              mensagem_boas_vindas, dominios_permitidos[], chave_publica
              evidência: (7)                                  confiança: alta
EntryPoint    id, workspace_id, tipo (padrao_canal|numero_whatsapp|palavra_chave|
              comentario_ig|seguidor_ig), channel_id, filtro (palavra, post_id),
              resposta_publica, agent_id, prioridade
              evidência: (5, 17)                              confiança: alta

Contact       id, workspace_id, nome, telefone, email, ig_username,
              telegram_id, fuso, bot_ativo (bool), follow_ups_desligados (bool),
              agent_id, membro_atribuido_id, campos_extras (json)
              evidência: API `is_bot_active`, `follow_ups_disabled` (11), painel (1)  confiança: alta
ContactIdentity contact_id, channel_id, id_externo            confiança: palpite (um contato em vários canais, 1 timeline)
Conversation  id, contact_id, channel_id, ultima_msg_em, ultima_msg_contato_em
              (para janela 24 h), fixada, estrela, nao_lidas
              evidência: S04/S05 (1)                          confiança: média
Message       id, conversation_id, direcao (in|out), autor (contato|ia|humano|sistema),
              tipo (texto|audio|imagem|video|arquivo|nota), conteudo, transcricao,
              midia_url, status (pendente|enviada|entregue|lida|falhou),
              agendada_para (horário ativo), id_externo, criado_em
              evidência: (1, 2, 21)                           confiança: alta
Tag           id, workspace_id, nome, cor
ContactTag    contact_id, tag_id, aplicada_por (ia|humano|automacao)
              evidência: filtro "aplicada por agente" (5)     confiança: alta
FollowUpJob   id, conversation_id, passo, enviar_em, status (agendado|enviado|cancelado)
              evidência: "próximo follow-up" no painel (1, 3) confiança: alta
CalendarConnection workspace_id ou user_id, provedor (google), tokens  confiança: média
Booking       id, contact_id, agent_id, inicio, fim, fuso, evento_externo_id, status
Deal          id, contact_id, booking_id, etapa, valor        evidência: (20)  confiança: média
Automation    id, gatilho (tag_aplicada|...), filtros, passos (json), parar_ao_responder
              evidência: (4, 5)                               confiança: alta
```

Relações: Workspace 1-n Agent, Channel, Contact, KnowledgeBase, Tag, EntryPoint,
Automation. Agent 1-n AgentVersion; Agent n-n KnowledgeBase. Channel 1-1
WidgetConfig (só web). Contact 1-n Conversation (uma por canal), Conversation 1-n
Message. Contact n-n Tag. EntryPoint n-1 Agent.

## Matriz de funcionalidades

Ver `features.csv`. Must: 46, should: 24, could: 12, skip: 8 (90 linhas).

## Fora de escopo (não pode ou não deve ser clonado)

- **WhatsApp Web por QR code.** É uma integração não oficial com o WhatsApp
  pessoal; viola os termos do WhatsApp e arrisca banir o número. O clone usa só a
  **WhatsApp Cloud API** oficial.
- **Telegram por conta pessoal (telefone + código).** O próprio DM Champ marca
  como beta e não oficial, com risco de restrição. O clone usa a **Bot API**
  oficial (token do BotFather), que é inclusive o que a página deles anuncia como
  "em breve".
- **Aluguel de números de telefone.** Depende de contratos com operadoras.
- **Revenda white-label para agências, subcontas e créditos.** É o modelo de
  negócio deles, não o recorte pedido. Pode voltar depois.
- **Messenger, LINE, Viber, SMS, e-mail.** Fora do recorte pedido.
- **As aprovações da Meta deles.** O app aprovado na Meta, a verificação da
  empresa e o status de Tech Provider são deles. O clone precisa passar pelos
  próprios processos (ver Tamanho).
- **Textos, prompts, playbooks, nome, logo e visual deles.**

## Tamanho

Telas 21 (+9 abas), fluxos 10, entidades 24.

Partes difíceis:
1. **Aprovação da Meta.** WhatsApp Cloud API e mensagens do Instagram exigem um
   app Meta com verificação da empresa e App Review das permissões
   (`whatsapp_business_messaging`, `instagram_manage_messages`,
   `instagram_manage_comments`). Leva dias a semanas e não depende de código.
   Dá para desenvolver em modo de desenvolvimento com números/contas de teste.
2. **Orquestração da IA.** Prompt montado a partir da versão publicada do agente,
   busca no conhecimento (RAG), ferramentas (agendar, webhook, busca web),
   transcrição de áudio, leitura de imagem/PDF, divisão em mensagens com atraso
   de digitação, e regras por canal (janela de 24 h, templates no WhatsApp).
3. **Fila e agendamento confiáveis.** Webhooks de três plataformas, mensagens
   retidas fora do horário, follow-ups com cancelamento, idempotência (a Meta
   reenvia webhooks), e caixa de entrada em tempo real.

Tamanho: **L** (um trimestre para uma pessoa) para tudo que é must+should. Um
recorte vertical — agente + widget do site + caixa de entrada + Telegram — cabe
em **M** (algumas semanas), porque não depende de aprovação da Meta.
