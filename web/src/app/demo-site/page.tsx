import type { Metadata } from "next";
import { Suspense } from "react";

export const metadata: Metadata = { title: "Site de demonstração", robots: { index: false } };

async function Demo({ searchParams }: PageProps<"/demo-site">) {
  const sp = await searchParams;
  const key = typeof sp.key === "string" ? sp.key : "pk_demo_aurora";
  const inline = sp.mode === "inline";
  return (
    <>
      {inline && <div id="atendente-ia" className="mx-auto h-[600px] max-w-md" />}
      <script src="/widget.js" data-key={key} data-mode={inline ? "inline" : undefined} data-target="atendente-ia" async />
    </>
  );
}

// Página que simula o site de um cliente com o widget instalado. Usada no
// link "Abrir página de demonstração" e nos testes de ponta a ponta.
export default function DemoSite(props: PageProps<"/demo-site">) {
  return (
    <div className="min-h-dvh bg-surface">
      <header className="border-b border-border bg-bg px-6 py-4">
        <p className="text-base font-bold">Site de demonstração</p>
        <p className="text-sm text-muted">Esta página imita o site de um cliente com o widget instalado.</p>
      </header>
      <main className="mx-auto flex max-w-2xl flex-col gap-4 px-6 py-10">
        <h1 className="text-xl font-bold">Bem-vindo à nossa loja</h1>
        <p className="text-sm text-muted">Use o chat no canto da tela para conversar com o agente. As conversas aparecem em Conversas, no painel.</p>
        <Suspense fallback={null}>
          <Demo {...props} />
        </Suspense>
      </main>
    </div>
  );
}
