// Gera o CSS dos tokens a partir de replica/design/tokens.json.
//   node replica/design/build-tokens.mjs
// Saídas:
//   replica/design/tokens.css       variáveis CSS (claro, escuro, data-theme)
//   replica/design/tokens.dark.json mesmo arquivo com o tema escuro em "color",
//                                   para o contrast.py
//   web/src/app/tokens.css          cópia usada pelo app, com o @theme do Tailwind
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, "../..");
const tokens = JSON.parse(fs.readFileSync(path.join(here, "tokens.json"), "utf8"));

const vars = (colors) =>
  Object.entries(colors).map(([k, v]) => `  --${k}: ${v};`).join("\n");

const scale = [
  ...Object.entries(tokens.type).flatMap(([k, t]) => [
    `  --type-${k}-size: ${t.size / 16}rem;`,
    `  --type-${k}-line: ${t.line / 16}rem;`,
    `  --type-${k}-weight: ${t.weight};`,
  ]),
  ...Object.entries(tokens.radius).map(([k, v]) => `  --size-radius-${k}: ${v}px;`),
  ...Object.entries(tokens.shadow).map(([k, v]) => `  --elev-${k}: ${v};`),
  `  --motion-fast: ${tokens.motion.fast};`,
  `  --motion-base: ${tokens.motion.base};`,
  `  --motion-ease: ${tokens.motion.ease};`,
  ...Object.entries(tokens.layout)
    .filter(([, v]) => typeof v === "number")
    .map(([k, v]) => `  --layout-${k}: ${v}px;`),
  `  --font-family-sans: ${tokens.font.sans};`,
  `  --font-family-mono: ${tokens.font.mono};`,
].join("\n");

const variablesCss = `/* Gerado por replica/design/build-tokens.mjs. Não edite à mão. */
:root {
  color-scheme: light;
${vars(tokens.color)}
${scale}
}

@media (prefers-color-scheme: dark) {
  :root:not([data-theme="light"]) {
    color-scheme: dark;
${vars(tokens.color_dark).replace(/^/gm, "  ")}
    --elev-card: 0 1px 2px rgba(0,0,0,.4);
    --elev-pop: 0 12px 32px rgba(0,0,0,.5);
  }
}

:root[data-theme="dark"] {
  color-scheme: dark;
${vars(tokens.color_dark)}
  --elev-card: 0 1px 2px rgba(0,0,0,.4);
  --elev-pop: 0 12px 32px rgba(0,0,0,.5);
}
`;

// Nomes no Tailwind: text -> fg, text-muted -> muted (para escrever
// "text-fg", "text-muted", "bg-surface"). O resto mantém o nome do papel.
const twName = { text: "fg", "text-muted": "muted" };
const themeColors = Object.keys(tokens.color)
  .map((k) => `  --color-${twName[k] ?? k}: var(--${k});`)
  .join("\n");
const themeType = Object.keys(tokens.type)
  .flatMap((k) => [
    `  --text-${k}: var(--type-${k}-size);`,
    `  --text-${k}--line-height: var(--type-${k}-line);`,
  ])
  .join("\n");
const bp = Object.entries(tokens.layout.breakpoints)
  .map(([k, v]) => `  --breakpoint-${k}: ${v / 16}rem;`)
  .join("\n");

const tailwindCss = `
/* Mapeamento para o Tailwind v4: classes como bg-surface, text-muted,
   border-input, rounded-md, shadow-pop. Nunca hex direto nos componentes. */
@theme inline {
  --color-*: initial;
  --color-transparent: transparent;
  --color-current: currentColor;
  --color-black: #000;
  --color-white: #fff;
${themeColors}
  --font-sans: var(--font-family-sans);
  --font-mono: var(--font-family-mono);
${themeType}
  --radius-sm: var(--size-radius-sm);
  --radius-md: var(--size-radius-md);
  --radius-lg: var(--size-radius-lg);
  --radius-pill: var(--size-radius-pill);
  --shadow-card: var(--elev-card);
  --shadow-pop: var(--elev-pop);
  --ease-standard: var(--motion-ease);
${bp}
}
`;

fs.writeFileSync(path.join(here, "tokens.css"), variablesCss);
fs.writeFileSync(
  path.join(here, "tokens.dark.json"),
  JSON.stringify({ ...tokens, _about: "Gerado: tema escuro em 'color' para o contrast.py.", color: tokens.color_dark, color_dark: undefined }, null, 2) + "\n",
);
fs.writeFileSync(path.join(root, "web/src/app/tokens.css"), variablesCss + tailwindCss);
console.log("ok: tokens.css, tokens.dark.json, web/src/app/tokens.css");
