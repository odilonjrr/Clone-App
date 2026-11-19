# Colocar o Delisys no ar (teste)

Tempo: uns 15 minutos. Tudo pelo navegador, também funciona no celular.
Você cria as contas e cola as chaves **nos painéis da Vercel, do banco, da Meta e do Asaas**,
nunca no chat nem no código.

## 1. Hospedagem + banco (obrigatório)

1. Entre em **vercel.com** com a sua conta do GitHub.
2. **Add New → Project** → importe o repositório **Clone-App**.
   Se ele não aparecer, clique em *Adjust GitHub App Permissions* e libere esse repositório.
3. Na tela de configuração:
   - **Root Directory**: `delisys`.
   - **Framework**: Next.js (vem sozinho).
   - **Environment Variables**: adicione
     - `PAYMENT_PROVIDER` = `mock` (Pix de teste, nada é cobrado);
     - `SETUP_CODE` = um código só seu, por exemplo `minha-loja-2026`. Ele é pedido para criar a primeira loja e impede que um estranho crie uma loja no seu app.
     - `CRON_SECRET` = um texto longo e aleatório (ex.: gere em um gerador de senhas). A Vercel usa para chamar a limpeza diária de dados vencidos.
     - `LEGAL_NAME`, `LEGAL_DOCUMENT` (CNPJ) e `PRIVACY_EMAIL`: dados da sua empresa, mostrados na Política de privacidade (`/privacidade`).
4. Clique em **Deploy**. O primeiro deploy **vai falhar** por falta de banco. É esperado.
5. No projeto, abra **Storage → Create Database → Neon (Postgres)**. Escolha a região mais perto (São Paulo, se aparecer), o plano gratuito, e conecte ao projeto.
   Isso cria a variável `DATABASE_URL` sozinho.
6. **Deployments → ⋯ → Redeploy.** O build cria as tabelas e o app sobe.
7. **Qual versão vai para o ar:** o código novo está na branch `ccr-a5ddc5f7-d94thf` (PR #2).
   Faça uma destas:
   - junte o PR #2 na branch principal; ou
   - em **Settings → Environments → Production**, mude a branch para `ccr-a5ddc5f7-d94thf`.

   > Use o endereço de **produção** (`seu-projeto.vercel.app`). Os endereços de prévia da
   > Vercel pedem login, e o cliente e o motoboy não conseguiriam abrir.

Pronto: abra `https://SEU-PROJETO.vercel.app/painel/criar-conta`, use o `SETUP_CODE` e marque
"cardápio de exemplo". O cardápio fica em `https://SEU-PROJETO.vercel.app/<endereço-da-loja>`.

## 2. E-mail para recuperar senha (recomendado)

Sem isso, "Esqueci minha senha" avisa que a recuperação ainda não foi configurada.

1. Crie uma conta em **resend.com** e verifique o seu domínio (eles mostram os registros de DNS).
2. Gere uma **API key**.
3. Na Vercel:
   - `RESEND_API_KEY` = a chave;
   - `EMAIL_FROM` = `Delisys <nao-responda@seudominio.com.br>`.

   Depois, faça **Redeploy**.

## 3. WhatsApp automático (opcional, liga depois)

Sem isso, o app funciona em **modo manual**: o quadro de pedidos mostra o botão *Avisar no
WhatsApp* em cada etapa, que abre o WhatsApp com a mensagem pronta.

Para o envio automático pela API oficial da Meta:

1. **developers.facebook.com** → *My Apps* → *Create App* → tipo **Business** → adicione o produto **WhatsApp**.
2. Em *WhatsApp → API Setup*:
   - anote o **Phone number ID** (do número de teste da Meta ou do seu número);
   - no número de teste, cadastre até 5 celulares que vão receber as mensagens no teste.
3. Crie um **token permanente**: *Business Settings → System users → Add* → gere um token com as permissões `whatsapp_business_messaging` e `whatsapp_business_management`.
4. Em *WhatsApp Manager → Message templates*, crie os **4 modelos** de [whatsapp-modelos.md](whatsapp-modelos.md), com o mesmo nome, o idioma *Portuguese (BR)* e a categoria *Utility*. Espere a aprovação, que costuma levar de minutos a horas.
5. Na Vercel, em **Settings → Environment Variables**, adicione:
   - `WHATSAPP_TOKEN`;
   - `WHATSAPP_PHONE_NUMBER_ID`;
   - `WHATSAPP_API_VERSION` = `v21.0`, ou a versão atual que aparece no painel da Meta.

   Depois, faça **Redeploy**.

## 4. Pix de verdade (opcional)

1. Crie uma conta no **sandbox do Asaas** (`sandbox.asaas.com`). É ambiente de teste, sem dinheiro real.
2. Gere a **chave de API** em *Integrações*.
3. Cadastre o **webhook** de cobranças:
   - URL: `https://SEU-PROJETO.vercel.app/api/webhooks/asaas`;
   - token de autenticação: invente um.
4. Na Vercel:
   - `PAYMENT_PROVIDER` = `asaas`;
   - `ASAAS_API_KEY` = a chave do sandbox;
   - `ASAAS_WEBHOOK_TOKEN` = o token que você inventou;
   - `ASAAS_BASE_URL` = `https://sandbox.asaas.com/api/v3`.

   Depois, faça **Redeploy**.

> A integração com o Asaas foi escrita a partir da documentação pública da API v3 e **ainda não
> foi testada contra o sandbox**. Teste com um pedido seu antes de abrir para clientes. Com o
> Asaas, o checkout pede o CPF do cliente para gerar o Pix.

## Variáveis (resumo)

| variável | obrigatória | o que é |
| --- | --- | --- |
| `DATABASE_URL` | sim | Postgres (Neon ou Supabase, porta do pooler) |
| `SETUP_CODE` | recomendada | código para criar loja |
| `APP_URL` | fora da Vercel: sim | endereço público usado nos links de rastreio e de senha; na Vercel, usa o domínio de produção |
| `CRON_SECRET` | recomendada | protege a limpeza diária (`/api/cron/limpeza`) |
| `RESEND_API_KEY`, `EMAIL_FROM` | recomendada | e-mail de recuperação de senha |
| `LEGAL_NAME`, `LEGAL_DOCUMENT`, `PRIVACY_EMAIL` | sim, antes de abrir para clientes | dados da empresa na política de privacidade |
| `PAYMENT_PROVIDER` | não | `mock` (padrão) ou `asaas` |
| `ASAAS_API_KEY`, `ASAAS_WEBHOOK_TOKEN`, `ASAAS_BASE_URL` | se `asaas` | Pix real |
| `WHATSAPP_TOKEN`, `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_API_VERSION` | não | envio automático |

## Antes de receber clientes de verdade

- [ ] Troque `PAYMENT_PROVIDER` de `mock` para `asaas`. No modo teste, qualquer cliente consegue "simular" o pagamento do Pix.
- [ ] Preencha `LEGAL_NAME`, `LEGAL_DOCUMENT` e `PRIVACY_EMAIL`, e peça a um advogado para revisar `/privacidade`.
- [ ] Configure o e-mail (Resend) para a recuperação de senha.
- [ ] Confira em **Settings → Cron Jobs** se a limpeza diária aparece.
