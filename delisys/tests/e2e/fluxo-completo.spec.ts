import { expect, test, type Page } from "@playwright/test";

/**
 * Fluxo pedido pelo usuário, de ponta a ponta:
 * cliente pede → painel aceita/prepara/pronto e escolhe motoboy → motoboy abre o link,
 * vê o endereço com o link do Google Maps, sai para entrega e compartilha a localização →
 * cliente vê o motoboy no mapa pelo link → entregue. Confere os 4 avisos de WhatsApp.
 */
test("pedido completo: cardápio → painel → motoboy → rastreio → WhatsApp", async ({ browser, baseURL }) => {
  // 1) dono cria a loja com cardápio de exemplo
  const owner = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const panel = await owner.newPage();
  await panel.goto("/painel/criar-conta");
  await panel.getByLabel("Nome da loja").fill("Brasa e Pão Teste");
  await panel.getByLabel("Seu nome").fill("Dona Teste");
  await panel.getByLabel("E-mail").fill(`dono${Date.now()}@exemplo.com`);
  await panel.getByLabel(/^Senha/).fill("senha-forte-123");
  await panel.getByRole("button", { name: "Criar loja" }).click();
  await expect(panel.getByRole("heading", { name: "Pedidos" })).toBeVisible();

  // 2) cliente pede pelo celular (dinheiro com troco, entrega, localização anexada)
  const customer = await browser.newContext({ ...devicesPhone(), permissions: ["geolocation"], geolocation: { latitude: -23.5899, longitude: -46.6345 } });
  const c = await customer.newPage();
  await c.goto("/brasa-e-pao-teste");
  await c.getByRole("button", { name: /Smash Clássico/ }).first().click();
  await c.getByRole("radio", { name: "Ao ponto" }).check();
  await c.getByRole("checkbox", { name: /Bacon crocante/ }).check();
  await c.getByRole("button", { name: /^Adicionar/ }).click();
  await c.getByRole("link", { name: /Ver sacola/ }).click();
  await c.getByLabel("Bairro").selectOption({ index: 1 });
  await c.getByLabel("Rua").fill("Rua Vergueiro");
  await c.getByLabel("Número").fill("3000");
  await c.getByLabel(/Ponto de referência/).fill("Prédio azul");
  await c.getByRole("button", { name: /Anexar minha localização/ }).click();
  await expect(c.getByText("Localização anexada.", { exact: false })).toBeVisible();
  await c.getByLabel("Nome").fill("Ana Cliente");
  await c.getByLabel("WhatsApp").fill("(11) 91234-5678");
  await c.getByRole("radio", { name: /Dinheiro/ }).click();
  await c.getByLabel("Vou precisar de troco").check();
  await c.getByLabel("Troco para quanto?").fill("100,00");
  await c.getByRole("button", { name: /^Fazer pedido/ }).click();
  await c.waitForURL(/\/r\//);
  const trackingUrl = c.url();
  await expect(c.getByText("Pedido realizado").first()).toBeVisible();

  // 3) painel: novo pedido chega; aceitar → pronto → escolher motoboy
  const card = panel.locator("li.card", { hasText: "Ana Cliente" });
  await expect(card).toBeVisible();
  await expect(card.getByText("Dinheiro · cobrar · troco p/ R$ 100,00")).toBeVisible();
  await expectNoticeButton(card, "Recebido");
  await card.getByRole("button", { name: "Aceitar e preparar" }).click();
  await expect(card.getByRole("button", { name: "Marcar como pronto" })).toBeVisible();
  await card.getByRole("button", { name: "Marcar como pronto" }).click();
  await card.getByLabel("Motoboy").selectOption({ label: "Rafael (exemplo)" });
  await expect(panel.getByText(/Motoboy definido/)).toBeVisible();

  // 4) motoboy abre o link dele (vem da tela Motoboys)
  await panel.goto("/painel/motoboys");
  const courierLink = await panel.getByRole("link", { name: "Abrir" }).first().getAttribute("href");
  expect(courierLink).toMatch(/\/m\//);
  const rider = await browser.newContext({ ...devicesPhone(), permissions: ["geolocation"], geolocation: { latitude: -23.5870, longitude: -46.6380 } });
  const m = await rider.newPage();
  await m.goto(courierLink!);
  const job = m.locator("li.card", { hasText: "Ana Cliente" });
  await expect(job).toBeVisible();
  await expect(job.getByText(/Rua Vergueiro, 3000/)).toBeVisible();
  await expect(job.getByText("Referência: Prédio azul")).toBeVisible();
  await expect(job.getByText(/Cobrar R\$ 39,90 em dinheiro/)).toBeVisible();
  const maps = await job.getByRole("link", { name: /Google Maps/ }).getAttribute("href");
  expect(maps).toContain("https://www.google.com/maps/dir/?api=1");
  expect(maps).toContain("destination=-23.5899%2C-46.6345");
  await job.getByRole("button", { name: /Saí para entrega/ }).click();
  await expect(m.getByRole("heading", { name: "Compartilhando sua localização" })).toBeVisible();
  await expect(m.getByText(/Último envio às/)).toBeVisible();

  // 5) cliente acompanha pelo link: etapa "Saiu para entrega" e motoboy no mapa
  await c.goto(trackingUrl);
  await expect(c.getByRole("heading", { name: /Rafael está a caminho/ })).toBeVisible();
  await expect(c.getByText(/Posição atualizada às/)).toBeVisible();
  await expect(c.locator(".leaflet-container")).toBeVisible();

  // 6) motoboy entrega
  m.on("dialog", (d) => d.accept());
  await job.getByRole("button", { name: /Entreguei/ }).click();
  await expect(m.getByText("Nenhuma entrega com você agora.", { exact: false })).toBeVisible();
  await c.reload();
  await expect(c.getByText("Entregue").first()).toBeVisible();

  // 7) avisos de WhatsApp: as 4 etapas pedidas, com o link de rastreio na de entrega
  await panel.goto("/painel/mensagens");
  for (const stage of ["Pedido realizado", "Em preparação", "Pronto", "Saiu para entrega"])
    await expect(panel.getByText(new RegExp(`#\\d+ · ${stage}$`))).toBeVisible();
  await expect(panel.getByText(new RegExp(`saiu para entrega com Rafael\\. Acompanhe a entrega em tempo real: ${escape(trackingUrl)}`))).toBeVisible();
  expect(baseURL).toBeTruthy();
});

test("pedido com Pix de teste: aguarda pagamento e entra no quadro só depois de pago", async ({ browser }) => {
  const owner = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const panel = await owner.newPage();
  await panel.goto("/painel/criar-conta");
  await panel.getByLabel("Nome da loja").fill("Pizzaria Pix Teste");
  await panel.getByLabel("Seu nome").fill("Dono Pix");
  await panel.getByLabel("E-mail").fill(`pix${Date.now()}@exemplo.com`);
  await panel.getByLabel(/^Senha/).fill("senha-forte-123");
  await panel.getByRole("button", { name: "Criar loja" }).click();
  await expect(panel.getByRole("heading", { name: "Pedidos" })).toBeVisible();

  const c = await (await browser.newContext(devicesPhone())).newPage();
  await c.goto("/pizzaria-pix-teste");
  await c.getByRole("button", { name: /Pizza Grande/ }).first().click();
  await c.getByLabel("Calabresa").check();
  await c.getByLabel("Quatro queijos").check();
  await expect(c.getByRole("button", { name: /^Adicionar/ })).toContainText("R$ 62,90");
  await c.getByRole("button", { name: /^Adicionar/ }).click();
  await c.getByRole("link", { name: /Ver sacola/ }).click();
  await c.getByRole("radio", { name: /Retirada/ }).click();
  await c.getByLabel("Nome").fill("Bruno Pix");
  await c.getByLabel("WhatsApp").fill("(21) 99876-5432");
  await c.getByRole("radio", { name: /Pix/ }).click();
  await c.getByRole("button", { name: /Fazer pedido e pagar/ }).click();
  await c.waitForURL(/\/r\//);
  await expect(c.getByRole("img", { name: "QR code do Pix" })).toBeVisible();

  // antes de pagar, não aparece nas colunas do quadro, só no aviso de "aguardando"
  await expect(panel.getByText(/1 pedido aguardando o pagamento do Pix/)).toBeVisible();
  await expect(panel.locator("li.card", { hasText: "Bruno Pix" })).toHaveCount(0);

  await c.getByRole("button", { name: /Simular pagamento/ }).click();
  await expect(c.getByText("Pedido realizado").first()).toBeVisible();
  const card = panel.locator("li.card", { hasText: "Bruno Pix" });
  await expect(card).toBeVisible();
  await expect(card.getByText("Pix · pago")).toBeVisible();
  await expect(card.getByText("RETIRADA")).toBeVisible();
});

function devicesPhone() {
  return { viewport: { width: 412, height: 915 }, isMobile: true, hasTouch: true, deviceScaleFactor: 1 };
}
function escape(s: string) { return s.replace(/[.*+?^${}()|[\]\\]/g, "\\$&"); }
async function expectNoticeButton(card: ReturnType<Page["locator"]>, stage: string) {
  await expect(card.getByRole("link", { name: `Avisar no WhatsApp: ${stage}` })).toHaveAttribute("href", /^https:\/\/wa\.me\/5511912345678\?text=/);
}
