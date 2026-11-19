"use client";
import Link from "next/link";
import { useState } from "react";
import { api } from "@/lib/client-api";

export function ForgotForm() {
  const [sent, setSent] = useState(false);
  const [devLink, setDevLink] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  async function onSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const email = new FormData(e.currentTarget).get("email");
    setBusy(true); setError(null);
    try {
      const r = await api<{ devLink?: string }>("/api/painel/senha/esqueci", { body: { email } });
      setDevLink(r.devLink ?? null); setSent(true);
    } catch (err) { setError((err as Error).message); }
    finally { setBusy(false); }
  }
  if (sent) {
    return (
      <div className="card grid gap-3 p-6" role="status">
        <h1 className="text-xl font-bold">Confira seu e-mail</h1>
        <p className="text-sm text-muted">Se existir uma conta com esse e-mail, mandamos um link para criar uma senha nova. Ele vale por 30 minutos e funciona uma vez só. Olhe também a caixa de spam.</p>
        {devLink && <p className="rounded-md bg-warning-soft p-2 text-xs">Desenvolvimento (e-mail não configurado): <a className="break-all font-semibold underline" href={devLink}>abrir o link de redefinição</a></p>}
        <Link className="text-sm font-semibold text-accent underline" href="/painel/entrar">Voltar para o login</Link>
      </div>
    );
  }
  return (
    <form onSubmit={onSubmit} className="card grid gap-4 p-6" noValidate>
      <h1 className="text-xl font-bold">Esqueci minha senha</h1>
      <p className="text-sm text-muted">Informe o e-mail da sua conta. Vamos mandar um link para criar uma senha nova.</p>
      <label className="field"><span>E-mail</span><input className="input" id="email" name="email" type="email" autoComplete="email" required /></label>
      {error && <p role="alert" className="text-sm text-danger">{error}</p>}
      <button className="btn btn-primary" disabled={busy}>{busy ? "Enviando…" : "Enviar link"}</button>
      <Link className="text-sm font-semibold text-accent underline" href="/painel/entrar">Voltar para o login</Link>
    </form>
  );
}
