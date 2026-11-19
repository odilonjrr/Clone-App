"use client";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { api } from "@/lib/client-api";

export function LoginForm() {
  const router = useRouter();
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  async function onSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const f = new FormData(e.currentTarget);
    setBusy(true); setError(null);
    try {
      await api("/api/painel/sessao", { body: { email: f.get("email"), password: f.get("password") } });
      router.replace("/painel");
      router.refresh();
    } catch (err) { setError((err as Error).message); setBusy(false); }
  }
  return (
    <form onSubmit={onSubmit} className="card grid gap-4 p-6" noValidate>
      <h1 className="text-xl font-bold">Entrar no painel</h1>
      <label className="field"><span>E-mail</span><input className="input" id="email" name="email" type="email" autoComplete="email" required /></label>
      <label className="field"><span>Senha</span><input className="input" id="password" name="password" type="password" autoComplete="current-password" required /></label>
      {error && <p role="alert" className="text-sm text-danger">{error}</p>}
      <button className="btn btn-primary" disabled={busy}>{busy ? "Entrando…" : "Entrar"}</button>
      <Link className="text-sm font-semibold text-accent underline" href="/painel/esqueci-senha">Esqueci minha senha</Link>
      <p className="text-sm text-muted">Ainda não tem loja? <Link className="font-semibold text-accent underline" href="/painel/criar-conta">Criar conta</Link></p>
    </form>
  );
}
