// Gera tokens.css (variáveis) e tailwind.css (@theme do Tailwind v4) a partir de tokens.json.
//   node brendi/replica/design/build-tokens.mjs
import fs from "node:fs"; import path from "node:path"; import { fileURLToPath } from "node:url";
const here = path.dirname(fileURLToPath(import.meta.url));
const t = JSON.parse(fs.readFileSync(path.join(here, "tokens.json"), "utf8"));
const px = (n) => `${n / 16}rem`;
const lines = [];
const colorVars = (c) => Object.entries(c).map(([k, v]) => `  --${k}: ${v};`).join("\n");
for (const [k, v] of Object.entries(t.gradient ?? {})) lines.push(`  --gradient-${k}: ${v};`);
for (const [k, v] of Object.entries(t.glass ?? {})) lines.push(`  --glass-${k}: ${v};`);
for (const [k, v] of Object.entries(t.type)) {
  lines.push(`  --type-${k}-size: ${px(v.size)};`, `  --type-${k}-line: ${px(v.line)};`, `  --type-${k}-weight: ${v.weight};`);
}
t.space.forEach((v, i) => lines.push(`  --space-${i}: ${px(v)};`));
for (const [k, v] of Object.entries(t.radius)) lines.push(`  --radius-${k}: ${v}px;`);
for (const [k, v] of Object.entries(t.shadow)) lines.push(`  --shadow-${k}: ${v};`);
lines.push(`  --ring-width: ${t.ring.width}px;`, `  --ring-color: var(--${t.ring.color});`);
for (const [k, v] of Object.entries(t.motion)) lines.push(`  --motion-${k}: ${v};`);
for (const [k, v] of Object.entries(t.layout)) if (typeof v === "number") lines.push(`  --layout-${k}: ${v}px;`);
lines.push(`  --font-display: ${t.font.display};`, `  --font-sans: ${t.font.sans};`, `  --font-script: ${t.font.script};`, `  --font-mono: ${t.font.mono};`);
fs.writeFileSync(path.join(here, "tokens.css"),
`/* Delisys — gerado por build-tokens.mjs a partir de tokens.json. Não edite à mão.
   Padrão: tema escuro (cardápio do cliente, marketing).
   data-theme="light": tema claro (painel do restaurante). */
:root {
  color-scheme: dark;
${colorVars(t.color)}
${lines.join("\n")}
}

[data-theme="light"] {
  color-scheme: light;
${colorVars(t.color_light)}
  --shadow-card: 0 8px 24px rgba(26,21,18,.08);
  --shadow-pop: 0 16px 40px rgba(26,21,18,.18);
  --glass-bg: rgba(255,255,255,.8);
  --glass-border: rgba(26,21,18,.08);
}
`);
// Tailwind v4: bg-surface, text-muted, bg-secondary, text-on-accent, rounded-xl, shadow-card, font-display
const tw = { text: "fg", "text-muted": "muted" };
const theme = [
  ...Object.keys(t.color).map((k) => `  --color-${tw[k] ?? k}: var(--${k});`),
  ...Object.entries(t.type).flatMap(([k]) => [`  --text-${k}: var(--type-${k}-size);`, `  --text-${k}--line-height: var(--type-${k}-line);`]),
  // raio, sombra e fonte: tokens.css já usa os nomes do Tailwind (--radius-*, --shadow-*, --font-*);
  // repetir aqui criaria referência circular. Fontes extras (display, script) entram no @theme do app.
  ...Object.entries(t.layout.breakpoints).map(([k, v]) => `  --breakpoint-${k}: ${px(v)};`),
  `  --spacing: 0.25rem; /* base 4px: p-4 = 16px */`,
];
fs.writeFileSync(path.join(here, "tailwind.css"),
`/* Delisys — mapeamento para o Tailwind v4. Importe depois de tokens.css:
   @import "tailwindcss"; @import "./tokens.css"; @import "./tailwind.css";
   Componentes usam papéis (bg-surface, text-muted, bg-secondary), nunca hex. */
@theme inline {
${theme.join("\n")}
}
`);
console.log("ok: tokens.css e tailwind.css");
