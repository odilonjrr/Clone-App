# Preços: Delisys (proposta, a validar)

Data: 2026-11-18. Prices change; check each source again before publishing.

## Referências públicas

| produto | modelo e preço | fonte | lido em | confiança |
| --- | --- | --- | --- | --- |
| Brendi (o original) | plano único com preço por faixa de faturamento; mínimo R$ 120/mês até R$ 3.000; Pix online R$ 0,40 + 0,5% por pedido; crédito 3,99% (D+30) ou 5,69% (D+0); tráfego pago R$ 60/semana à parte | https://brendi.com.br/planos | 2026-11-18 (página aberta) | alta |
| Anota AI (do iFood) | "Start" entre R$ 249,99 e R$ 299,99/mês, conforme a fonte | https://www.take.app/alternatives/anota-ai , https://saipos.com/cardapio-digital/anota-ai | 2026-11-18 (busca; fontes de terceiros, valores conflitantes) | baixa |
| Goomer | Grátis (30 pedidos), Básico R$ 99,90/mês (R$ 59,94 no anual), Automatizar R$ 184,90 (R$ 110,94 no anual), Integrar R$ 299,90 (R$ 224,93 no anual) | https://saipos.com/cardapio-digital/goomer | 2026-11-18 (busca; blog de concorrente, sem data) | baixa |
| Cardápio Web | "a partir de R$ 115"; planos mensal a anual; reajuste anual pelo IPCA; planos de ciclo fechado sem reembolso proporcional | https://saipos.com/cardapio-digital/cardapio-web , https://cardapioweb.com/termos-de-uso.pdf | 2026-11-18 (busca) | baixa / média |

**Reclamações sobre preço e cobrança:** ainda não foram levantadas. Isso depende do `/replica-entrepreneur`, que gera o `feedback.md`. O único sinal achado foi nos termos do Cardápio Web: planos de ciclo fechado sem reembolso proporcional. É um ponto a evitar.

## Modelo proposto

**Ângulo (hipótese até a pesquisa):** preço fixo e previsível.
- O Brendi cobra por faixa de faturamento, ou seja, quanto mais o restaurante vende, mais paga.
- O Delisys cobra um valor fixo por loja.
- Não há comissão por pedido.
- A taxa de pagamento online é a do provedor, repassada sem acréscimo.

**Tiers:**

| plano | para quem | mensal | anual (2 meses grátis) | inclui |
| --- | --- | --- | --- | --- |
| **Balcão** | quem está tirando o delivery do papel | R$ 89 | R$ 890/ano | cardápio digital com link e QR, pedidos sem comissão, gestor de pedidos ao vivo, áreas e taxas de entrega, pagamento na entrega, Pix online, cupons, 1 usuário extra |
| **Casa cheia** | quem já vende todo dia e quer recompra | R$ 169 | R$ 1.690/ano | tudo do Balcão + cashback, recuperador de clientes, avisos de status no WhatsApp, impressão automática, motoboys, relatórios, usuários ilimitados |
| **Rede** | 3 lojas ou mais | R$ 149 por loja | R$ 1.490 por loja/ano | tudo do Casa cheia + painel com várias lojas e cardápio compartilhado |

**Regras de cobrança:**
- 14 dias grátis, sem cartão.
- Cancelamento em um clique, no painel.
- E-mail 7 dias antes de cada renovação anual.
- Anual cancelado devolve os meses não usados.
- Nenhum valor muda por número de pedidos ou faturamento.
- Reajuste só com aviso de 60 dias.

**Produtos e preços a criar no provedor de cobrança (você cria):**
- `delisys_balcao`: R$ 89,00/mês e R$ 890,00/ano.
- `delisys_casa_cheia`: R$ 169,00/mês e R$ 1.690,00/ano.
- `delisys_rede`: R$ 149,00/mês por loja e R$ 1.490,00/ano por loja, com quantidade igual ao nº de lojas.

**Antes de fixar:**
- calcular o custo por loja (Supabase, Vercel, mensagens de WhatsApp, Google Maps);
- confirmar a taxa real do PSP escolhido.

Os valores acima são ponto de partida, não conta fechada.
