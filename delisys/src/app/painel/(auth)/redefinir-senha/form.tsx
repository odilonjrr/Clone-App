"use client";
import Link from "next/link";
import { useEffect, useState } from "react";
import { api } from "@/lib/client-api";

export function ResetForm({ token }: { token: string }) {
  const [done, setDone] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  // tira o código do endereço da barra, para não ficar no histórico nem ser compartilhado sem querer
  useEffect(() => { if (token) window.history.replaceState(null, "", "/painel/redefinir-senha"); }, [token]);

  async function onSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const f = new FormData(e.currentTarget);
    const password = String(f.get("password") ?? ""), confirm = String(f.get("confirm") ?? "");
    if (password.length < 8) { setError("A senha precisa de pelo menos 8 caracteres."); return; }
    if (password !== confirm) { setError("As duas senhas não são iguais."); return; }
    setBusy(true); setError(null);
    try { await api("/api/painel/senha/redefinir", { body: { token, password } }); setDone(true); }
    catch (err) { setError((err as Error).message); setBusy(false); }
  }
  if (!token) {
    return <div className="card grid gap-3 p-6"><h1 className="text-xl font-bold">Link incompleto</h1><p className="text-sm text-muted">Abra o link exatamente como chegou no e-mail, ou peça um novo.</p><Link className="text-sm font-semibold text-accent underline" href="/painel/esqueci-senha">Pedir novo link</Link></div>;
  }
  if (done) {
    return <div className="card grid gap-3 p-6" role="status"><h1 className="text-xl font-bold">Senha alterada</h1><p className="text-sm text-muted">Por segurança, encerramos as sessões abertas em outros aparelhos. Entre com a senha nova.</p><Link className="btn btn-primary" href="/painel/entrar">Entrar</Link></div>;
  }
  return (
    <form onSubmit={onSubmit} className="card grid gap-4 p-6" noValidate>
      <h1 className="text-xl font-bold">Criar senha nova</h1>
      <label className="field"><span>Senha nova (mínimo 8 caracteres)</span><input className="input" id="password" name="password" type="password" autoComplete="new-password" minLength={8} required /></label>
      <label className="field"><span>Repita a senha nova</span><input className="input" id="confirm" name="confirm" type="password" autoComplete="new-password" minLength={8} required /></label>
      {error && <p role="alert" className="text-sm text-danger">{error}{/expirou|usado/.test(error) && <> <Link className="underline" href="/painel/esqueci-senha">Pedir novo link</Link></>}</p>}
      <button className="btn btn-primary" disabled={busy}>{busy ? "Salvando…" : "Salvar senha nova"}</button>
    </form>
  );
}
