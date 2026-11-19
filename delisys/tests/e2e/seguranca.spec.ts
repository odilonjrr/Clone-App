import { expect, test } from "@playwright/test";

async function createStore(page: import("@playwright/test").Page, name: string) {
  const email = `${name.toLowerCase().replace(/\W+/g, "")}${Date.now()}@exemplo.com`;
  await page.goto("/painel/criar-conta");
  await page.getByLabel("Nome da loja").fill(name);
  await page.getByLabel("Seu nome").fill("Dona Segura");
  await page.getByLabel("E-mail").fill(email);
  await page.getByLabel(/^Senha/).fill("senha-antiga-123");
  await page.getByRole("button", { name: "Criar loja" }).click();
  await expect(page.getByRole("heading", { name: "Pedidos" })).toBeVisible();
  return email;
}

test("cabeçalhos de segurança e CSP sem bloquear o próprio app", async ({ page, request }) => {
  const violations: string[] = [];
  page.on("console", (m) => { if (/Content Security Policy|Refused to/.test(m.text())) violations.push(m.text()); });

  const home = await request.get("/");
  const h = home.headers();
  expect(h["content-security-policy"]).toContain("script-src 'self' 'nonce-");
  expect(h["content-security-policy"]).toContain("frame-ancestors 'none'");
  expect(h["strict-transport-security"]).toContain("max-age=63072000");
  expect(h["x-frame-options"]).toBe("DENY");
  expect(h["x-content-type-options"]).toBe("nosniff");
  expect(h["referrer-policy"]).toBe("strict-origin-when-cross-origin");
  expect(h["permissions-policy"]).toContain("geolocation=(self)");
  expect(h["x-powered-by"]).toBeUndefined();
  // nonce muda a cada acesso
  const again = (await request.get("/")).headers()["content-security-policy"];
  expect(again).not.toBe(h["content-security-policy"]);
  // rotas com dados pessoais não ficam em cache compartilhado
  expect((await request.get("/api/pedidos/naoexiste")).headers()["cache-control"]).toContain("no-store");

  // as telas carregam e funcionam com a CSP ligada (hidratação, cliques)
  await createStore(page, "Loja CSP");
  await page.goto("/loja-csp");
  await page.getByRole("button", { name: /Smash Clássico/ }).first().click();
  await expect(page.getByRole("dialog")).toBeVisible();
  await page.goto("/privacidade");
  await expect(page.getByRole("heading", { name: "Política de privacidade" })).toBeVisible();
  expect(violations).toEqual([]);
});

test("recuperação de senha: link de uso único, derruba sessões antigas", async ({ browser }) => {
  // o link aparece na tela só em desenvolvimento sem e-mail configurado; em produção vai por e-mail
  test.skip(!!process.env.E2E_BASE_URL, "precisa do servidor de desenvolvimento");
  const ctxA = await browser.newContext();
  const a = await ctxA.newPage();
  const email = await createStore(a, "Loja Senha");

  const b = await (await browser.newContext()).newPage();
  await b.goto("/painel/entrar");
  await b.getByRole("link", { name: "Esqueci minha senha" }).click();
  await expect(b.getByRole("heading", { name: "Esqueci minha senha" })).toBeVisible();
  await b.getByLabel("E-mail").fill(email);
  await b.getByRole("button", { name: "Enviar link" }).click();
  await expect(b.getByText("Confira seu e-mail")).toBeVisible();
  const link = await b.getByRole("link", { name: "abrir o link de redefinição" }).getAttribute("href");
  expect(link).toMatch(/\/painel\/redefinir-senha\?token=/);

  await b.goto(link!);
  await expect(b).toHaveURL(/\/painel\/redefinir-senha$/); // código sai da barra de endereço
  await b.getByLabel(/^Senha nova/).fill("senha-nova-456");
  await b.getByLabel("Repita a senha nova").fill("senha-nova-456");
  await b.getByRole("button", { name: "Salvar senha nova" }).click();
  await expect(b.getByText("Senha alterada")).toBeVisible();

  // sessão antiga do outro aparelho foi encerrada
  await a.goto("/painel");
  await expect(a).toHaveURL(/\/painel\/entrar/);

  // senha antiga não entra; a nova entra
  await b.goto("/painel/entrar");
  await b.getByLabel("E-mail").fill(email);
  await b.getByLabel("Senha").fill("senha-antiga-123");
  await b.getByRole("button", { name: "Entrar" }).click();
  await expect(b.getByText("E-mail ou senha não conferem.")).toBeVisible();
  await b.getByLabel("Senha").fill("senha-nova-456");
  await b.getByRole("button", { name: "Entrar" }).click();
  await expect(b.getByRole("heading", { name: "Pedidos" })).toBeVisible();

  // o mesmo link não funciona de novo
  await b.goto(link!);
  await b.getByLabel(/^Senha nova/).fill("outra-senha-789");
  await b.getByLabel("Repita a senha nova").fill("outra-senha-789");
  await b.getByRole("button", { name: "Salvar senha nova" }).click();
  await expect(b.getByText(/expirou ou já foi usado/)).toBeVisible();
});

test("em produção sem e-mail configurado, o link nunca aparece na resposta", async ({ request }) => {
  test.skip(!process.env.E2E_BASE_URL, "só no build de produção");
  const r = await request.post("/api/painel/senha/esqueci", { data: { email: "qualquer@exemplo.com" } });
  const body = await r.json();
  expect(body.devLink).toBeUndefined();
});

test("pedido de redefinição não revela se o e-mail existe", async ({ request }) => {
  const r = await request.post("/api/painel/senha/esqueci", { data: { email: `ninguem${Date.now()}@exemplo.com` } });
  expect(r.status()).toBe(200);
  expect(await r.json()).toEqual({ ok: true });
});

test("limite de tentativas de login por conta (guardado no banco)", async ({ request }) => {
  const email = `alvo${Date.now()}@exemplo.com`;
  const codes: number[] = [];
  for (let i = 0; i < 10; i++) {
    const r = await request.post("/api/painel/sessao", { data: { email, password: `errada-${i}` }, headers: { "x-forwarded-for": `10.0.0.${i}` } });
    codes.push(r.status());
  }
  // 8 tentativas por conta a cada 15 min, mesmo trocando de IP
  expect(codes.slice(0, 8).every((c) => c === 401)).toBe(true);
  expect(codes.slice(8)).toEqual([429, 429]);
});
