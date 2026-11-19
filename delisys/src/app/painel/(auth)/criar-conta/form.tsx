"use client";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { api } from "@/lib/client-api";

function slugify(s: string) {
  return s.normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase().replace(/&/g, " e ").replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "").slice(0, 40);
}

export function SignupForm({ needsCode }: { needsCode: boolean }) {
  const router = useRouter();
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [slug, setSlug] = useState("");
  const [slugTouched, setSlugTouched] = useState(false);
  async function onSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const f = new FormData(e.currentTarget);
    setBusy(true); setError(null);
    try {
      await api("/api/painel/conta", { body: {
        storeName: f.get("storeName"), slug, ownerName: f.get("ownerName"), email: f.get("email"),
        password: f.get("password"), whatsapp: (f.get("whatsapp") as string) || undefined,
        withDemoMenu: f.get("demo") === "on", setupCode: (f.get("setupCode") as string) || undefined,
      } });
      router.replace("/painel");
      router.refresh();
    } catch (err) { setError((err as Error).message); setBusy(false); }
  }
  return (
    <form onSubmit={onSubmit} className="card grid gap-4 p-6" noValidate>
      <h1 className="text-xl font-bold">Criar minha loja</h1>
      <label className="field"><span>Nome da loja</span>
        <input className="input" id="storeName" name="storeName" required maxLength={60}
          onChange={(e) => { if (!slugTouched) setSlug(slugify(e.target.value)); }} />
      </label>
      <label className="field"><span>Endereço do cardápio</span>
        <div className="flex items-center gap-1 rounded-md">
          <span className="text-sm text-muted">/</span>
          <input className="input" id="slug" name="slug" required value={slug} onChange={(e) => { setSlugTouched(true); setSlug(slugify(e.target.value)); }} />
        </div>
      </label>
      <label className="field"><span>Seu nome</span><input className="input" id="ownerName" name="ownerName" autoComplete="name" required /></label>
      <label className="field"><span>E-mail</span><input className="input" id="email" name="email" type="email" autoComplete="email" required /></label>
      <label className="field"><span>Senha (mínimo 8 caracteres)</span><input className="input" id="password" name="password" type="password" autoComplete="new-password" minLength={8} required /></label>
      <label className="field"><span>WhatsApp da loja (opcional)</span><input className="input" id="whatsapp" name="whatsapp" inputMode="tel" placeholder="(11) 98765-4321" /></label>
      {needsCode && <label className="field"><span>Código de criação</span><input className="input" id="setupCode" name="setupCode" required /></label>}
      <label className="flex items-start gap-3 text-sm">
        <input type="checkbox" id="demo" name="demo" defaultChecked className="mt-1 size-4 accent-[var(--accent)]" />
        <span>Começar com um cardápio de exemplo (lanches, pizza meio a meio, bairros e um motoboy). Dá para editar ou apagar depois.</span>
      </label>
      {error && <p role="alert" className="text-sm text-danger">{error}</p>}
      <p className="text-xs text-muted">Ao criar a loja você declara ter lido a <a className="underline" href="/privacidade" target="_blank">Política de privacidade</a>. Sua loja será a controladora dos dados dos seus clientes.</p>
      <button className="btn btn-primary" disabled={busy}>{busy ? "Criando…" : "Criar loja"}</button>
      <p className="text-sm text-muted">Já tem conta? <Link className="font-semibold text-accent underline" href="/painel/entrar">Entrar</Link></p>
    </form>
  );
}
