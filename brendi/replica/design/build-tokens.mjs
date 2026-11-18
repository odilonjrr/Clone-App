// Gera tokens.css (variáveis) e tailwind.css (@theme do Tailwind v4) a partir de tokens.json.
//   node brendi/replica/design/build-tokens.mjs
import fs from "node:fs"; import path from "node:path"; import { fileURLToPath } from "node:url";
const here = path.dirname(fileURLToPath(import.meta.url));
const t = JSON.parse(fs.readFileSync(path.join(here, "tokens.json"), "utf8"));
const px = (n) => `${n / 16}rem`;
const lines = [];
for (const [k, v] of Object.entries(t.color)) lines.push(`  --${k}: ${v};`);
for (const [k, v] of Object.entries(t.type)) {
  lines.push(`  --type-${k}-size: ${px(v.size)};`, `  --type-${k}-line: ${px(v.line)};`, `  --type-${k}-weight: ${v.weight};`);
}
t.space.forEach((v, i) => lines.push(`  --space-${i}: ${px(v)};`));
for (const [k, v] of Object.entries(t.radius)) lines.push(`  --radius-${k}: ${v}px;`);
for (const [k, v] of Object.entries(t.shadow)) lines.push(`  --shadow-${k}: ${v};`);
lines.push(`  --ring-width: ${t.ring.width}px;`, `  --ring-color: var(--${t.ring.color});`);
for (const [k, v] of Object.entries(t.motion)) lines.push(`  --motion-${k}: ${v};`);
for (const [k, v] of Object.entries(t.layout)) if (typeof v === "number") lines.push(`  --layout-${k}: ${v}px;`);
lines.push(`  --font-display: ${t.font.display};`, `  --font-sans: ${t.font.sans};`, `  --font-mono: ${t.font.mono};`);
fs.writeFileSync(path.join(here, "tokens.css"),
`/* Delisys — gerado por build-tokens.mjs a partir de tokens.json. Não edite à mão. */
:root {
  color-scheme: light;
${lines.join("\n")}
}
`);
// Tailwind v4: bg-surface, text-muted, bg-secondary, text-on-accent, rounded-xl, shadow-card, font-display
const tw = { text: "fg", "text-muted": "muted" };
const theme = [
  ...Object.keys(t.color).map((k) => `  --color-${tw[k] ?? k}: var(--${k});`),
  ...Object.entries(t.type).flatMap(([k]) => [`  --text-${k}: var(--type-${k}-size);`, `  --text-${k}--line-height: var(--type-${k}-line);`]),
  ...Object.keys(t.radius).map((k) => `  --radius-${k}: var(--radius-${k});`),
  ...Object.keys(t.shadow).map((k) => `  --shadow-${k}: var(--shadow-${k});`),
  `  --font-display: var(--font-display);`, `  --font-sans: var(--font-sans);`,
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
