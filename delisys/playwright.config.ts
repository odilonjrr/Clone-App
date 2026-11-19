import { defineConfig, devices } from "@playwright/test";

// Banco novo a cada execução (PGlite numa pasta temporária), servidor de dev na porta 3200.
const dir = `.data/e2e-${Date.now()}`;
// E2E_BASE_URL aponta para um servidor já rodando (ex.: build de produção); sem ela, sobe o de dev.
const external = process.env.E2E_BASE_URL;
export default defineConfig({
  testDir: "tests/e2e",
  timeout: 120_000,
  expect: { timeout: 15_000 },
  fullyParallel: false,
  workers: 1,
  reporter: [["list"]],
  use: { baseURL: external ?? "http://localhost:3200", trace: "off", locale: "pt-BR" },
  projects: [{ name: "celular", use: { ...devices["Pixel 7"] } }],
  webServer: external ? undefined : {
    command: `PGLITE_DIR=${dir} PAYMENT_PROVIDER=mock APP_URL=http://localhost:3200 NEXT_TELEMETRY_DISABLED=1 npx next dev -p 3200`,
    url: "http://localhost:3200",
    reuseExistingServer: false,
    timeout: 180_000,
  },
});
