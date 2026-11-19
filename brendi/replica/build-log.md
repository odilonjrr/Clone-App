# Build log: Delisys

App: `delisys/`. Screenshots: `brendi/replica/delisys-screens/`. Tests: `delisys/tests/`.

| ID | data | estado | o que falta | o que foi mais difícil |
| --- | --- | --- | --- | --- |
| base | 2026-11-19 | done | — | Next.js 16.4.2 tinha alertas de segurança: atualizado para 16.4.6 e zod 4 antes de seguir |
| S01 cardápio | 2026-11-19 | done | capa e logo enviados pela loja; "Serve N" | — |
| S02/S03/S04 produto e pizza | 2026-11-19 | partial | tela de nº de sabores separada (hoje é um grupo "Sabores" 1–N) | dialog nativo fechava sozinho no modo estrito do React |
| S05 login do cliente | — | not started | código por WhatsApp/SMS | depende da API do WhatsApp aprovada |
| S06–S09 sacola e checkout | 2026-11-19 | partial | autocompletar de endereço (Google Places), passo de resumo separado | — |
| S10 Pix | 2026-11-19 | partial | testar Asaas de verdade no sandbox | — |
| S11 acompanhamento | 2026-11-19 | done | — | mapa: OpenStreetMap bloqueado neste ambiente, funciona no celular |
| S21 quadro de pedidos | 2026-11-19 | done | impressão da comanda | — |
| S18 motoboys | 2026-11-19 | done | — | novo: link por motoboy, rota no Maps, localização ao vivo (pedido do usuário) |
| S27 editor de cardápio | 2026-11-19 | done | upload de foto | — |
| S31 loja e entrega | 2026-11-19 | partial | grade de horários | — |
| WhatsApp | 2026-11-19 | done | aprovar os 4 modelos na Meta | modelos precisam de aprovação; modo manual cobre o teste |
| deploy | 2026-11-19 | pending | contas Vercel + Neon do usuário | ver delisys/docs/colocar-no-ar.md |
