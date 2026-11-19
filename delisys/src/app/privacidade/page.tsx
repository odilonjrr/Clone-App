import type { Metadata } from "next";
import Link from "next/link";

export const metadata: Metadata = { title: "Política de privacidade" };

const UPDATED = "19 de novembro de 2026";

/**
 * Modelo de política de privacidade conforme a LGPD (Lei 13.709/2018).
 * Os dados da empresa vêm das variáveis LEGAL_NAME, LEGAL_DOCUMENT e PRIVACY_EMAIL.
 * Os prazos aqui são os que o sistema cumpre de fato (src/lib/rate-limit.ts → cleanup).
 * Peça a um advogado para revisar antes de abrir para clientes.
 */
export default function PrivacyPage() {
  const company = process.env.LEGAL_NAME || "[nome da empresa: preencher em LEGAL_NAME]";
  const doc = process.env.LEGAL_DOCUMENT || "[CNPJ: preencher em LEGAL_DOCUMENT]";
  const email = process.env.PRIVACY_EMAIL || "[e-mail de privacidade: preencher em PRIVACY_EMAIL]";
  const H2 = ({ id, children }: { id: string; children: React.ReactNode }) => <h2 id={id} className="mt-10 scroll-mt-4 text-xl font-bold">{children}</h2>;

  return (
    <div data-theme="light" className="min-h-dvh bg-bg text-fg">
      <main className="mx-auto max-w-3xl px-4 py-10 text-[15px] leading-7 [&_li]:mt-1.5 [&_p]:mt-3 [&_ul]:mt-2 [&_ul]:list-disc [&_ul]:pl-5">
        <p className="text-sm text-muted"><Link className="underline" href="/">Delisys</Link></p>
        <h1 className="mt-2 text-3xl font-extrabold">Política de privacidade</h1>
        <p className="text-sm text-muted">Atualizada em {UPDATED}.</p>

        <p>Esta política explica quais dados pessoais o Delisys trata, por que, por quanto tempo e como você exerce seus direitos, conforme a Lei Geral de Proteção de Dados (LGPD, Lei nº 13.709/2018).</p>

        <nav aria-label="Nesta página" className="card mt-6 p-4 text-sm">
          <ul className="!mt-0">
            <li><a className="underline" href="#quem">Quem é responsável pelos seus dados</a></li>
            <li><a className="underline" href="#dados">Quais dados tratamos e para quê</a></li>
            <li><a className="underline" href="#compartilhamento">Com quem compartilhamos</a></li>
            <li><a className="underline" href="#prazos">Por quanto tempo guardamos</a></li>
            <li><a className="underline" href="#seguranca">Como protegemos</a></li>
            <li><a className="underline" href="#direitos">Seus direitos e como exercê-los</a></li>
          </ul>
        </nav>

        <H2 id="quem">Quem é responsável pelos seus dados</H2>
        <p>O Delisys é um sistema de cardápio digital e gestão de pedidos usado por restaurantes. Por isso, a responsabilidade muda conforme quem você é:</p>
        <ul>
          <li><b>Se você fez um pedido num cardápio:</b> o <b>restaurante</b> é o <i>controlador</i> dos seus dados (ele decide como usá-los para preparar e entregar o pedido). O Delisys, operado por {company} ({doc}), é o <i>operador</i>: guarda e processa os dados em nome do restaurante, seguindo as instruções dele e esta política.</li>
          <li><b>Se você tem uma conta no painel (dono ou equipe de restaurante):</b> {company} é o <i>controlador</i> dos dados da sua conta.</li>
          <li><b>Se você é motoboy de um restaurante:</b> o <b>restaurante</b> é o controlador; o Delisys é o operador.</li>
        </ul>
        <p>Contato para assuntos de privacidade (encarregado): <b>{email}</b>.</p>

        <H2 id="dados">Quais dados tratamos e para quê</H2>
        <h3 className="mt-5 font-bold">Clientes que fazem pedidos</h3>
        <ul>
          <li><b>Nome e número de WhatsApp:</b> identificar o pedido e enviar avisos de cada etapa (pedido realizado, em preparação, pronto e saiu para entrega). Base legal: execução do contrato de compra com o restaurante (art. 7º, V).</li>
          <li><b>Endereço de entrega e ponto de referência:</b> entregar o pedido. Mesma base legal.</li>
          <li><b>Localização do aparelho (opcional):</b> só quando você toca em &quot;Anexar minha localização&quot;, para o motoboy achar o ponto exato. Base legal: consentimento (art. 7º, I); você pode simplesmente não usar.</li>
          <li><b>Itens, valores, forma de pagamento e observações do pedido:</b> preparar, cobrar e manter o histórico do restaurante. Base legal: execução de contrato e cumprimento de obrigação legal (art. 7º, II e V).</li>
          <li><b>CPF (só no Pix online, quando o restaurante usa o Asaas):</b> exigido pela instituição de pagamento para gerar a cobrança. É enviado direto ao Asaas e <b>não é guardado</b> no Delisys.</li>
          <li><b>Dados guardados no seu aparelho:</b> a sacola e os dados do último pedido (nome, WhatsApp, endereço) ficam no armazenamento do seu navegador para você não digitar de novo. Você apaga limpando os dados do site no navegador.</li>
        </ul>
        <h3 className="mt-5 font-bold">Motoboys</h3>
        <ul>
          <li><b>Nome e celular:</b> cadastrados pelo restaurante para enviar o link de entregas.</li>
          <li><b>Localização em tempo real:</b> só enquanto você ativa &quot;Compartilhar localização&quot; e há uma entrega em andamento. O cliente daquele pedido vê sua posição pelo link de acompanhamento. Base legal: execução do contrato de entrega e legítimo interesse do cliente em acompanhar a entrega (art. 7º, V e IX). Você pode parar a qualquer momento.</li>
        </ul>
        <h3 className="mt-5 font-bold">Donos e equipe dos restaurantes</h3>
        <ul>
          <li><b>Nome, e-mail e senha</b> (guardada só como resumo criptográfico, nunca em texto): acesso ao painel e recuperação de senha. Base legal: execução de contrato.</li>
          <li><b>Registros de segurança:</b> para barrar tentativas repetidas de login e de pedidos, guardamos um resumo criptográfico (hash) do IP ou do e-mail e a contagem de tentativas. O IP e o e-mail em si não são salvos nesses registros. Base legal: legítimo interesse em proteger o serviço (art. 7º, IX).</li>
        </ul>
        <p>Não usamos seus dados para publicidade, não vendemos dados e não fazemos decisões automatizadas que afetem você.</p>

        <H2 id="compartilhamento">Com quem compartilhamos</H2>
        <ul>
          <li><b>O restaurante do seu pedido</b> e o <b>motoboy</b> escolhido por ele: veem nome, WhatsApp, endereço, itens e forma de pagamento para preparar e entregar.</li>
          <li><b>Meta (WhatsApp):</b> recebe seu número e o texto dos avisos do pedido, quando o restaurante usa o envio automático.</li>
          <li><b>Asaas</b> (instituição de pagamento): nome, celular, CPF e valor, só no Pix online.</li>
          <li><b>Vercel</b> (hospedagem) e o <b>provedor do banco de dados</b> (como Neon ou Supabase): guardam e processam os dados para o sistema funcionar.</li>
          <li><b>Resend</b> (e-mail): e-mail e nome de quem pede redefinição de senha do painel.</li>
          <li><b>OpenStreetMap:</b> ao abrir o mapa de acompanhamento, seu navegador baixa as imagens do mapa desses servidores, que recebem o seu IP.</li>
          <li><b>Google Maps:</b> quando o motoboy toca em &quot;Abrir rota&quot;, o endereço de entrega é aberto no Google Maps do celular dele.</li>
          <li><b>Autoridades:</b> quando houver obrigação legal ou ordem judicial.</li>
        </ul>
        <p><b>Transferência internacional:</b> alguns desses fornecedores podem processar dados fora do Brasil. Essas transferências seguem as hipóteses permitidas pelo art. 33 da LGPD.</p>

        <H2 id="prazos">Por quanto tempo guardamos</H2>
        <ul>
          <li><b>Posição do motoboy:</b> apagada automaticamente 24 horas depois que a entrega termina (ou 48 horas após a última atualização).</li>
          <li><b>Registros de tentativas (segurança):</b> apagados automaticamente depois de 1 dia.</li>
          <li><b>Links de redefinição de senha:</b> valem 30 minutos e uma única vez; apagados em até 7 dias depois de vencerem.</li>
          <li><b>Sessões do painel:</b> vencem em 30 dias ou ao sair; apagadas em até 7 dias depois de vencerem.</li>
          <li><b>Pedidos e dados de clientes:</b> mantidos enquanto o restaurante usar o Delisys, para o histórico e as obrigações fiscais dele. O restaurante pode pedir a exclusão a qualquer momento. Se a conta do restaurante for encerrada, os dados são excluídos ou anonimizados, salvo o que a lei obrigar a guardar.</li>
        </ul>

        <H2 id="seguranca">Como protegemos</H2>
        <ul>
          <li>Conexão sempre criptografada (HTTPS), com navegação forçada em HTTPS.</li>
          <li>Senhas guardadas só como resumo criptográfico (scrypt); sessões em cookie protegido, invisível para scripts.</li>
          <li>Cada restaurante só acessa os próprios pedidos e clientes; motoboys só veem os pedidos entregues a eles.</li>
          <li>Os links de acompanhamento e do motoboy são códigos aleatórios impossíveis de adivinhar. <b>Não repasse esses links:</b> quem tiver o link de um pedido vê o nome e o endereço daquele pedido.</li>
          <li>Limite de tentativas contra adivinhação de senha e abuso, e política de segurança de conteúdo contra injeção de scripts.</li>
        </ul>
        <p>Se ocorrer um incidente de segurança que possa trazer risco ou dano relevante, os afetados e a Autoridade Nacional de Proteção de Dados (ANPD) serão comunicados, como manda a LGPD (art. 48).</p>

        <H2 id="direitos">Seus direitos e como exercê-los</H2>
        <p>Pela LGPD (art. 18), você pode pedir: confirmação de que tratamos seus dados; acesso a eles; correção de dados incompletos ou errados; anonimização, bloqueio ou eliminação de dados desnecessários; portabilidade; informação sobre com quem compartilhamos; e revogação do consentimento (por exemplo, da localização).</p>
        <ul>
          <li><b>Clientes e motoboys:</b> fale primeiro com o restaurante (o contato de WhatsApp dele aparece no cardápio). Ele é o controlador e pode pedir a correção ou exclusão ao Delisys. Você também pode escrever para <b>{email}</b>.</li>
          <li><b>Contas do painel:</b> escreva para <b>{email}</b>.</li>
        </ul>
        <p>Respondemos em até 15 dias. Você também pode reclamar à ANPD (gov.br/anpd).</p>

        <p className="mt-10 text-sm text-muted">Mudanças nesta política serão publicadas nesta página com nova data de atualização.</p>
      </main>
    </div>
  );
}
