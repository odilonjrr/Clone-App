# Log de construção

Fase 1 (agente + widget + caixa de entrada + Telegram), sobre o data layer
FALSO (`web/src/lib/data/index.ts`, em memória). Tudo que depende de serviço
externo (login, banco, IA real, Telegram real, transcrição, upload) entra no
`/replica-backend`, que troca esse arquivo mantendo as assinaturas.

Verificação, a mesma para todas as telas abaixo (2026-10-18):
- capturas em 1440px e 390px em `replica/clone-screens/` (35 arquivos);
- nenhuma rolagem horizontal e nenhum erro de console nas 35;
- nome de 60 caracteres testado na lista de conversas (trunca com reticências);
- build, TypeScript e lint sem erros.

Não houve capturas do original (rede bloqueada para dmchamp.com), então o
layout veio do mapa de recon e das especificações, não de referência visual.

| ID | tela | data | estado | o que falta | mais difícil que o esperado |
| --- | --- | --- | --- | --- | --- |
| — | shell + navegação + data layer | 18/10 | feito | — | `cacheComponents` do Next 16.4: leituras precisam de `connection()` e `<Suspense>`; o fallback da navegação não pode usar `usePathname()` |
| S01 | Entrar | 18/10 | parcial | login real (Supabase Auth) | — |
| S02 | Onboarding pelo site | 18/10 | parcial | leitura real do site (job `knowledge/ingest`) | — |
| S03 | Início | 18/10 | feito | — | — |
| S04 | Caixa de entrada | 18/10 | feito | tempo real (hoje: consulta a cada 2,5 s) | — |
| S05 | Conversa | 18/10 | feito | anexos ao responder; tempo real | — |
| S06 | Painel do contato | 18/10 | feito | — | — |
| S07 | Agentes | 18/10 | feito | — | — |
| S08 | Editor do agente (9 abas) | 18/10 | feito | upload de mídia (hoje por link); Google Agenda | marcar quais abas têm mudança não publicada exigiu comparar por campo |
| S09 | Playground | 18/10 | parcial | respostas da IA real | — |
| S10 | Conhecimento / FAQ | 18/10 | parcial | leitura real de site, upload de PDF | — |
| S11 | Canais | 18/10 | feito | WhatsApp e Instagram aparecem como "Em breve" | — |
| S12 | Conectar WhatsApp | — | não feito | fase 2 (aprovação da Meta) | — |
| S13 | Conectar Instagram | — | não feito | fase 2 (aprovação da Meta) | — |
| S14 | Conectar Telegram | 18/10 | parcial | `getMe` e `setWebhook` reais | — |
| S15 | Configurar widget | 18/10 | feito | escolha de fonte ficou de fora | isolar o widget do CSS do site exigiu iframe + `widget.js` próprio + CSP `frame-ancestors` no `proxy` |
| S16 | Roteamento | 18/10 | feito | regras de Instagram (fase 2) | — |
| S17 | Comment-to-DM | — | não feito | fase 2 | — |
| S18 | Widget do visitante | 18/10 | feito | tempo real (hoje: consulta a cada 1,5 s) | foco pelo teclado: o foco ficava na bolha ao abrir; corrigido |
| S19 | Contatos | 18/10 | parcial | filtros | — |
| S20 | Automações | 18/10 | adiado | tela de "em breve" (fora da fase 1) | — |
| S21 | Configurações | 18/10 | parcial | convites, Google Agenda, cobrança | — |

## Testes de ponta a ponta feitos à mão (scripts Playwright descartáveis)

- Recorte vertical: URL inválida → erro; site válido → agente criado →
  renomear → rascunho salvo → publicar → playground responde → visitante no
  site de demonstração preenche nome/e-mail e pergunta → conversa aparece na
  caixa de entrada → pausar IA → resposta humana chega ao visitante → IA fica
  calada → nota interna não chega ao visitante → trocar o agente padrão do site
  → novo visitante atendido pelo agente novo.
- Fora do horário: num domingo, o agente de exemplo não respondeu e a conversa
  mostrou "a IA responde segunda-feira, 08:00".
- Teclado: onboarding, publicar, abas por setas, pausar/retomar a IA (Space),
  widget inteiro (abrir, preencher, enviar).
- Foco e texto do campo de resposta sobrevivem à atualização automática.

Os testes oficiais e repetíveis ficam para o `/replica-test`.

## O que é falso e precisa do backend

- Dados em memória: somem quando o servidor reinicia.
- IA: responde pelo FAQ por sobreposição de palavras (`fake-ai.ts`), não pela
  API Claude.
- Esperas (debounce, digitação, horário ativo): `setTimeout` no processo, não
  Inngest. Um servidor com várias instâncias perderia isso.
- Tempo real: consulta periódica, não Supabase Realtime.
- Telegram: só valida o formato do token.
- A sessão do widget confia no `host` enviado pelo iframe; quem realmente
  impede outros sites é o CSP `frame-ancestors`, aplicado pelo navegador.
