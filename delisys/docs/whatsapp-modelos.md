# Modelos de mensagem do WhatsApp (Meta)

A Meta só deixa a loja iniciar conversa com **modelos aprovados**. Crie estes 4 em *WhatsApp
Manager → Message templates*:
- **Categoria:** Utility.
- **Idioma:** Portuguese (BR).
- **Nome:** exatamente o que está abaixo.

As variáveis `{{1}}`, `{{2}}`... são preenchidas pelo Delisys, nessa ordem. O texto do arquivo
`src/lib/messages.ts` é o mesmo usado no modo manual.

### 1. `delisys_pedido_realizado`

```
Olá, {{1}}! Recebemos seu pedido {{2}} na {{3}}. Total: {{4}}. Acompanhe aqui: {{5}} Vamos avisar cada etapa por esta conversa.
```

| variável | exemplo para a aprovação |
| --- | --- |
| {{1}} | Ana |
| {{2}} | #1042 |
| {{3}} | Brasa e Pão |
| {{4}} | R$ 59,80 |
| {{5}} | https://seu-projeto.vercel.app/r/abc123 |

### 2. `delisys_pedido_em_preparo`

```
Olá, {{1}}! Seu pedido {{2}} já está sendo preparado na {{3}}.
```

Exemplos: Ana · #1042 · Brasa e Pão

### 3. `delisys_pedido_pronto`

```
Olá, {{1}}! Seu pedido {{2}} está pronto. {{3}} Obrigado pela preferência!
```

| variável | o que é | exemplo |
| --- | --- | --- |
| {{1}} | nome | Ana |
| {{2}} | número | #1042 |
| {{3}} | próximo passo | "Logo ele sai para entrega." ou "Pode vir retirar no balcão." |

### 4. `delisys_pedido_saiu_entrega`

```
Olá, {{1}}! Seu pedido {{2}} saiu para entrega com {{3}}. Acompanhe a entrega em tempo real: {{4}} Bom apetite!
```

Exemplos: Ana · #1042 · Rafael · https://seu-projeto.vercel.app/r/abc123

---

**Notas**
- Mensagens de modelo de utilidade são cobradas pela Meta por conversa. Veja os preços atuais no painel da Meta antes de abrir para clientes.
- Se um modelo for recusado, ajuste o texto **aqui e em `src/lib/messages.ts`** juntos, mantendo a ordem das variáveis.
- Se o envio automático falhar, o aviso fica vermelho no quadro, com o botão *Avisar no WhatsApp* para mandar à mão e o botão *Tentar pela API de novo*.
