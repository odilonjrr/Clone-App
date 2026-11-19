"use client";
import { Bike, Copy, Link2, MessageCircle, Plus } from "lucide-react";
import { useCallback, useEffect, useState } from "react";
import { api } from "@/lib/client-api";
import { formatBrPhone } from "@/lib/phone";

type Courier = { id: string; name: string; phone: string | null; is_active: boolean; open_orders: number; link: string };

export function CouriersPage() {
  const [list, setList] = useState<Courier[] | null>(null);
  const [msg, setMsg] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const load = useCallback(async () => {
    try { setList((await api<{ couriers: Courier[] }>("/api/painel/motoboys")).couriers); } catch (e) { setError((e as Error).message); }
  }, []);
  useEffect(() => { load(); }, [load]);

  async function add(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const form = e.currentTarget; const f = new FormData(form);
    setError(null);
    try {
      await api("/api/painel/motoboys", { body: { name: f.get("name"), phone: (f.get("phone") as string) || undefined } });
      form.reset(); setMsg("Motoboy cadastrado. Envie o link para ele."); await load();
    } catch (err) { setError((err as Error).message); }
  }
  async function patch(id: string, body: Record<string, unknown>, ok: string) {
    try { await api(`/api/painel/motoboys/${id}`, { method: "PATCH", body }); setMsg(ok); await load(); }
    catch (err) { setError((err as Error).message); }
  }
  async function copy(link: string) {
    try { await navigator.clipboard.writeText(link); setMsg("Link copiado."); } catch { setMsg(link); }
  }

  return (
    <div className="grid max-w-3xl gap-5">
      <header>
        <h1 className="text-2xl font-bold">Motoboys</h1>
        <p className="text-sm text-muted">Cada motoboy recebe um link só dele. Pelo link ele vê os pedidos que você passou para ele, abre o endereço no Google Maps, compartilha a localização com o cliente e marca a entrega. Não precisa de senha nem de app.</p>
      </header>

      <form onSubmit={add} className="card grid gap-3 p-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end" noValidate>
        <label className="field"><span>Nome</span><input className="input" id="c-name" name="name" required maxLength={60} /></label>
        <label className="field"><span>Celular (opcional, para enviar o link)</span><input className="input" id="c-phone" name="phone" inputMode="tel" placeholder="(11) 98765-4321" /></label>
        <button className="btn btn-primary"><Plus className="size-4" aria-hidden />Cadastrar</button>
      </form>

      {error && <p role="alert" className="text-sm text-danger">{error}</p>}
      {msg && <p role="status" className="break-all text-sm text-success">{msg}</p>}

      {!list ? <div className="skeleton h-40" /> : list.length === 0 ? (
        <p className="card p-6 text-center text-muted">Nenhum motoboy ainda. Cadastre o primeiro acima.</p>
      ) : (
        <ul className="grid gap-3">
          {list.map((c) => (
            <li key={c.id} className={`card grid gap-3 p-4 ${c.is_active ? "" : "opacity-60"}`}>
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex items-center gap-3">
                  <span className="grid size-10 place-items-center rounded-full bg-accent-soft text-accent-strong"><Bike className="size-5" aria-hidden /></span>
                  <div>
                    <p className="font-semibold">{c.name}{!c.is_active && " (desativado)"}</p>
                    <p className="text-xs text-muted">{c.phone ? formatBrPhone(c.phone) : "sem celular"} · {c.open_orders} {c.open_orders === 1 ? "pedido com ele" : "pedidos com ele"}</p>
                  </div>
                </div>
                <button className="btn btn-sm btn-outline" onClick={() => patch(c.id, { isActive: !c.is_active }, c.is_active ? "Motoboy desativado." : "Motoboy reativado.")}>{c.is_active ? "Desativar" : "Reativar"}</button>
              </div>
              {c.is_active && (
                <div className="flex flex-wrap gap-2">
                  <button className="btn btn-sm btn-outline" onClick={() => copy(c.link)}><Copy className="size-4" aria-hidden />Copiar link</button>
                  {c.phone && (
                    <a className="btn btn-sm btn-success" target="_blank" rel="noreferrer"
                      href={`https://wa.me/${c.phone}?text=${encodeURIComponent(`Olá, ${c.name.split(" ")[0]}! Este é o seu link de entregas. Guarde nos favoritos e abra quando for sair: ${c.link}`)}`}>
                      <MessageCircle className="size-4" aria-hidden />Enviar link pelo WhatsApp
                    </a>
                  )}
                  <a className="btn btn-sm btn-outline" href={c.link} target="_blank" rel="noreferrer"><Link2 className="size-4" aria-hidden />Abrir</a>
                  <button className="btn btn-sm btn-outline" onClick={() => { if (confirm("Gerar um link novo? O link atual deste motoboy para de funcionar.")) patch(c.id, { newLink: true }, "Link novo gerado. Envie de novo para o motoboy."); }}>Trocar link</button>
                </div>
              )}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
