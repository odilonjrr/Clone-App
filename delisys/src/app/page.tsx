import Link from "next/link";
import { ShoppingBag } from "lucide-react";

export default function Home() {
  return (
    <main className="mx-auto flex min-h-dvh max-w-xl flex-col items-start justify-center gap-6 px-4 py-16">
      <span className="grid size-12 place-items-center rounded-xl" style={{ background: "var(--gradient-accent)", boxShadow: "var(--shadow-glow)" }}>
        <ShoppingBag className="size-6 text-white" aria-hidden />
      </span>
      <h1 className="text-3xl font-extrabold">Deli<span className="text-accent-bright">sys</span></h1>
      <p className="text-base text-muted">Cardápio digital e gestor de pedidos sem comissão. Esta é a versão de teste.</p>
      <div className="flex flex-wrap gap-3">
        <Link className="btn btn-cta" href="/painel">Entrar no painel</Link>
        <Link className="btn btn-outline" href="/painel/criar-conta">Criar minha loja</Link>
      </div>
    </main>
  );
}
