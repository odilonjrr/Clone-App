import type { Metadata } from "next";
import { Showcase } from "./showcase";
import { ThemeToggle } from "./theme-toggle";

export const metadata: Metadata = { title: "Sistema de design" };

const colorRoles = [
  "bg", "surface", "surface-raised", "surface-hover", "border", "border-input", "text", "text-muted",
  "accent", "accent-hover", "on-accent", "accent-soft", "danger", "danger-soft", "success", "success-soft",
  "warning", "warning-soft", "note-bg", "note-text", "channel-whatsapp", "channel-instagram", "channel-telegram", "channel-web",
];

const typeScale = [
  ["display", "text-display font-bold", "Atendimento que não dorme"],
  ["xl", "text-xl font-bold", "Seus agentes"],
  ["lg", "text-lg font-semibold", "Horário de atendimento"],
  ["base", "text-base", "A IA responde dentro do horário que você definir."],
  ["sm", "text-sm", "Texto padrão do painel, listas e formulários."],
  ["xs", "text-xs font-medium", "Rótulos, horários e selos"],
] as const;

export default function DesignPage() {
  return (
    <main className="mx-auto flex max-w-[var(--layout-content-max)] flex-col gap-10 px-4 py-10 sm:px-6">
      <header className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-xl font-bold">Sistema de design</h1>
          <p className="text-sm text-muted">Tokens e componentes do painel e do widget. Gerado a partir de replica/design/tokens.json.</p>
        </div>
        <ThemeToggle />
      </header>

      <section aria-labelledby="colors" className="flex flex-col gap-4">
        <h2 id="colors" className="text-lg font-semibold">Cores (papéis)</h2>
        <ul className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
          {colorRoles.map((r) => (
            <li key={r} className="flex flex-col gap-1.5">
              <span className="h-12 rounded-md border border-border" style={{ background: `var(--${r})` }} />
              <code className="text-xs text-muted">{r}</code>
            </li>
          ))}
        </ul>
      </section>

      <section aria-labelledby="type" className="flex flex-col gap-3 border-t border-border pt-8">
        <h2 id="type" className="text-lg font-semibold">Tipografia</h2>
        {typeScale.map(([name, cls, sample]) => (
          <div key={name} className="flex items-baseline gap-4">
            <code className="w-16 shrink-0 text-xs text-muted">{name}</code>
            <span className={cls}>{sample}</span>
          </div>
        ))}
      </section>

      <section aria-labelledby="shape" className="flex flex-col gap-3 border-t border-border pt-8">
        <h2 id="shape" className="text-lg font-semibold">Raio e sombra</h2>
        <div className="flex flex-wrap gap-4">
          {["rounded-sm", "rounded-md", "rounded-lg", "rounded-pill"].map((r) => (
            <div key={r} className={`flex h-16 w-24 items-center justify-center border border-border bg-surface text-xs text-muted ${r}`}>{r}</div>
          ))}
          <div className="flex h-16 w-28 items-center justify-center rounded-lg bg-surface-raised text-xs text-muted shadow-card">shadow-card</div>
          <div className="flex h-16 w-28 items-center justify-center rounded-lg bg-surface-raised text-xs text-muted shadow-pop">shadow-pop</div>
        </div>
      </section>

      <Showcase />
    </main>
  );
}
