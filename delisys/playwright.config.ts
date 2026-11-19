import { defineConfig, devices } from "@playwright/test";

// Banco novo a cada execução (PGlite numa pasta temporária), servidor de dev na porta 3200.
const dir = `.data/e2e-${Date.now()}`;
export default defineConfig({
  testDir: "tests/e2e",
  timeout: 120_000,
  expect: { timeout: 15_000 },
  fullyParallel: false,
  workers: 1,
  reporter: [["list"]],
  use: { baseURL: "http://localhost:3200", trace: "off", locale: "pt-BR" },
  projects: [{ name: "celular", use: { ...devices["Pixel 7"] } }],
  webServer: {
    command: `PGLITE_DIR=${dir} PAYMENT_PROVIDER=mock APP_URL=http://localhost:3200 NEXT_TELEMETRY_DISABLED=1 npx next dev -p 3200`,
    url: "http://localhost:3200",
    reuseExistingServer: false,
    timeout: 180_000,
  },
});
