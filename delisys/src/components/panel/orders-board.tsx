"use client";
import { Bell, BellOff, Bike, Check, CircleAlert, Clock, Copy, MapPin, MessageCircle, Phone, RefreshCw, Store as StoreIcon } from "lucide-react";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { api } from "@/lib/client-api";
import { addressOneLine, googleMapsDirections, type DeliveryAddress } from "@/lib/maps";
import { formatBRL } from "@/lib/money";
import { formatBrPhone } from "@/lib/phone";
import type { OrderStatus } from "@/lib/status";

type Notice = { id: string; stage: string; status: "pending" | "sent" | "failed"; mode: "api" | "manual"; error: string | null; link: string | null };
type Order = {
  id: string; number: number; status: OrderStatus; fulfillment: "delivery" | "pickup"; customer_name: string; customer_phone: string;
  address: DeliveryAddress | null; zone_name: string | null; payment_method: "pix" | "cash" | "card_on_delivery";
  payment_status: string; change_for_cents: number | null; subtotal_cents: number; delivery_fee_cents: number; total_cents: number;
  notes: string | null; courier_id: string | null; courier: { id: string; name: string } | null; created_at: string; placed_at: string | null;
  trackingUrl: string;
  items: { name: string; quantity: number; unit_price_cents: number; notes: string | null; options: { group_name: string; name: string }[] }[];
  notifications: Notice[];
};
type Board = {
  store: { is_open: boolean; slug: string; name: string };
  whatsappMode: "api" | "manual";
  couriers: { id: string; name: string; phone: string | null; link: string }[];
  orders: Order[];
};

const COLUMNS: { status: OrderStatus; title: string; tone: string }[] = [
  { status: "realizado", title: "Novos", tone: "analise" },
  { status: "em_preparo", title: "Em preparação", tone: "preparo" },
  { status: "pronto", title: "Prontos", tone: "pronto" },
  { status: "em_entrega", title: "Saiu para entrega", tone: "entrega" },
];
const STAGE_SHORT: Record<string, string> = { realizado: "Recebido", em_preparo: "Preparo", pronto: "Pronto", em_entrega: "Saiu" };

function minutesAgo(iso: string | null, now: number) {
  if (!iso) return "";
  const m = Math.max(0, Math.floor((now - new Date(iso).getTime()) / 60000));
  return m < 1 ? "agora" : m < 60 ? `há ${m} min` : `há ${Math.floor(m / 60)}h${String(m % 60).padStart(2, "0")}`;
}

function beep() {
  try {
    const ctx = new AudioContext();
    [0, 0.25, 0.5].forEach((t) => {
      const o = ctx.createOscillator(); const g = ctx.createGain();
      o.frequency.value = 880; o.connect(g); g.connect(ctx.destination);
      g.gain.setValueAtTime(0.0001, ctx.currentTime + t);
      g.gain.exponentialRampToValueAtTime(0.4, ctx.currentTime + t + 0.02);
      g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + t + 0.2);
      o.start(ctx.currentTime + t); o.stop(ctx.currentTime + t + 0.22);
    });
  } catch { /* navegador sem áudio */ }
}

export function OrdersBoard() {
  const [data, setData] = useState<Board | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [toast, setToast] = useState<string | null>(null);
  const [busy, setBusy] = useState<string | null>(null);
  const [sound, setSound] = useState(false);
  const [now, setNow] = useState(() => Date.now());
  const seen = useRef<Set<string> | null>(null);

  const load = useCallback(async () => {
    try {
      const d = await api<Board>("/api/painel/pedidos");
      const fresh = d.orders.filter((o) => o.status === "realizado").map((o) => o.id);
      if (seen.current) {
        const news = fresh.filter((id) => !seen.current!.has(id));
        if (news.length) {
          if (sound) beep();
          setToast(news.length === 1 ? "Chegou um pedido novo" : `Chegaram ${news.length} pedidos novos`);
        }
      }
      seen.current = new Set([...(seen.current ?? []), ...fresh]);
      setData(d); setError(null);
    } catch (e) { setError((e as Error).message); }
  }, [sound]);

  useEffect(() => { load(); const id = setInterval(load, 4000); return () => clearInterval(id); }, [load]);
  useEffect(() => { const id = setInterval(() => setNow(Date.now()), 30000); return () => clearInterval(id); }, []);
  useEffect(() => { if (!toast) return; const id = setTimeout(() => setToast(null), 4000); return () => clearTimeout(id); }, [toast]);

  async function run(key: string, fn: () => Promise<unknown>, ok?: string) {
    setBusy(key);
    try { await fn(); if (ok) setToast(ok); await load(); }
    catch (e) { setToast((e as Error).message); }
    finally { setBusy(null); }
  }
  const move = (o: Order, to: OrderStatus, extra: Record<string, unknown> = {}) =>
    run(o.id + to, () => api(`/api/painel/pedidos/${o.id}/status`, { body: { to, ...extra } }));

  const awaiting = useMemo(() => data?.orders.filter((o) => o.status === "aguardando_pagamento") ?? [], [data]);
  const done = useMemo(() => data?.orders.filter((o) => o.status === "entregue" || o.status === "cancelado").reverse() ?? [], [data]);

  if (!data) {
    return error ? <p role="alert" className="card p-4 text-danger">{error}</p> : (
      <div className="grid gap-4" aria-busy="true" aria-label="Carregando pedidos"><div className="skeleton h-10 w-60" /><div className="grid gap-4 md:grid-cols-4">{[0, 1, 2, 3].map((i) => <div key={i} className="skeleton h-64" />)}</div></div>
    );
  }

  return (
    <div className="grid gap-4">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold">Pedidos</h1>
          <p className="text-sm text-muted">Atualiza sozinho a cada 4 segundos.{error && <span className="text-danger"> Sem conexão no momento.</span>}</p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <button className={`btn btn-sm ${data.store.is_open ? "btn-success" : "btn-outline"}`} aria-pressed={data.store.is_open}
            onClick={() => run("open", () => api("/api/painel/loja", { method: "PATCH", body: { isOpen: !data.store.is_open } }), data.store.is_open ? "Loja fechada para novos pedidos" : "Loja aberta")}>
            <StoreIcon className="size-4" aria-hidden />{data.store.is_open ? "Loja aberta" : "Loja fechada"}
          </button>
          <button className="btn btn-sm btn-outline" aria-pressed={sound} onClick={() => { setSound((s) => !s); if (!sound) beep(); }}>
            {sound ? <Bell className="size-4" aria-hidden /> : <BellOff className="size-4" aria-hidden />}{sound ? "Som ligado" : "Ativar som"}
          </button>
          <button className="btn btn-sm btn-outline" onClick={() => load()} aria-label="Atualizar agora"><RefreshCw className="size-4" aria-hidden /></button>
        </div>
      </header>

      {data.whatsappMode === "manual" && (
        <p className="rounded-md border border-warning/40 bg-warning-soft p-3 text-sm">
          <b>WhatsApp em modo manual.</b> Enquanto a API oficial não estiver configurada, cada mudança de etapa mostra o botão <b>Avisar no WhatsApp</b> no pedido: ele abre o WhatsApp com a mensagem pronta para você enviar.
        </p>
      )}

      {awaiting.length > 0 && (
        <details className="card p-3 text-sm">
          <summary className="cursor-pointer font-semibold">{awaiting.length} {awaiting.length === 1 ? "pedido aguardando" : "pedidos aguardando"} o pagamento do Pix</summary>
          <ul className="mt-2 grid gap-2">
            {awaiting.map((o) => (
              <li key={o.id} className="flex flex-wrap items-center justify-between gap-2">
                <span>#{o.number} · {o.customer_name} · {formatBRL(o.total_cents)} · {minutesAgo(o.created_at, now)}</span>
                <button className="btn btn-sm btn-danger" disabled={busy !== null} onClick={() => { if (confirm(`Cancelar o pedido #${o.number}?`)) move(o, "cancelado", { reason: "Pix não foi pago" }); }}>Cancelar</button>
              </li>
            ))}
          </ul>
        </details>
      )}

      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        {COLUMNS.map((col) => {
          const list = data.orders.filter((o) => o.status === col.status);
          const sum = list.reduce((s, o) => s + o.total_cents, 0);
          return (
            <section key={col.status} aria-labelledby={`col-${col.status}`} className="min-w-0 rounded-lg p-2" style={{ background: `var(--status-${col.tone}-soft)` }}>
              <h2 id={`col-${col.status}`} className="flex items-center justify-between px-1.5 py-1 text-base font-bold" style={{ color: `var(--status-${col.tone})` }}>
                <span>{col.title} <span className="ml-1 inline-grid min-w-6 place-items-center rounded-full px-1.5 text-xs text-white" style={{ background: `var(--status-${col.tone})` }}>{list.length}</span></span>
                <span className="text-xs font-semibold">{formatBRL(sum)}</span>
              </h2>
              <ul className="mt-1 grid gap-2">
                {list.length === 0 && <li className="px-2 py-6 text-center text-xs text-muted">Nenhum pedido aqui.</li>}
                {list.map((o) => (
                  <OrderCard key={o.id} o={o} now={now} couriers={data.couriers} busy={busy} onMove={move} run={run} />
                ))}
              </ul>
            </section>
          );
        })}
      </div>

      {done.length > 0 && (
        <details className="card p-3 text-sm">
          <summary className="cursor-pointer font-semibold">Finalizados nas últimas 12 horas ({done.length})</summary>
          <ul className="mt-2 divide-y divide-border">
            {done.map((o) => (
              <li key={o.id} className="flex flex-wrap justify-between gap-2 py-2">
                <span>#{o.number} · {o.customer_name}</span>
                <span className={o.status === "cancelado" ? "text-danger" : "text-success"}>{o.status === "cancelado" ? "Cancelado" : "Entregue"} · {formatBRL(o.total_cents)}</span>
              </li>
            ))}
          </ul>
        </details>
      )}

      <div aria-live="polite" className="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex justify-center px-4">
        {toast && <p className="rounded-md bg-surface-dark px-4 py-3 text-sm text-on-dark" style={{ boxShadow: "var(--shadow-pop)" }}>{toast}</p>}
      </div>
    </div>
  );
}

function OrderCard({ o, now, couriers, busy, onMove, run }: {
  o: Order; now: number; couriers: Board["couriers"]; busy: string | null;
  onMove: (o: Order, to: OrderStatus, extra?: Record<string, unknown>) => void;
  run: (key: string, fn: () => Promise<unknown>, ok?: string) => void;
}) {
  const [open, setOpen] = useState(o.status === "realizado");
  const [courierId, setCourierId] = useState(o.courier_id ?? "");
  useEffect(() => setCourierId(o.courier_id ?? ""), [o.courier_id]);
  const late = o.placed_at && now - new Date(o.placed_at).getTime() > 45 * 60000;
  const pay = o.payment_method === "pix" ? (o.payment_status === "aprovado" ? "Pix · pago" : "Pix · pendente")
    : o.payment_method === "cash" ? `Dinheiro · cobrar${o.change_for_cents ? ` · troco p/ ${formatBRL(o.change_for_cents)}` : ""}` : "Cartão na entrega · cobrar";
  const pendingNotice = o.notifications.find((n) => n.status !== "sent");
  const isBusy = busy?.startsWith(o.id);

  return (
    <li className="card p-3 text-sm" style={{ boxShadow: "var(--shadow-sm)", borderLeft: late ? "4px solid var(--danger)" : undefined }}>
      <div className="flex items-start justify-between gap-2">
        <button className="text-left" onClick={() => setOpen((v) => !v)} aria-expanded={open}>
          <span className="block font-display text-lg font-extrabold leading-tight">#{o.number}</span>
          <span className="flex items-center gap-1 text-xs text-muted"><Clock className="size-3" aria-hidden />{minutesAgo(o.placed_at ?? o.created_at, now)}{late && <b className="text-danger"> · atrasado</b>}</span>
        </button>
        <span className={`chip ${o.fulfillment === "delivery" ? "bg-info-soft text-info" : "bg-surface-sunken text-fg"}`}>{o.fulfillment === "delivery" ? "ENTREGA" : "RETIRADA"}</span>
      </div>

      <p className="mt-2 font-semibold">{o.customer_name}</p>
      <p className="flex flex-wrap items-center gap-x-3 text-xs text-muted">
        <a className="inline-flex items-center gap-1 underline" href={`tel:+${o.customer_phone}`}><Phone className="size-3" aria-hidden />{formatBrPhone(o.customer_phone)}</a>
        <a className="inline-flex items-center gap-1 underline" href={`https://wa.me/${o.customer_phone}`} target="_blank" rel="noreferrer"><MessageCircle className="size-3" aria-hidden />WhatsApp</a>
      </p>
      {o.address && (
        <a className="mt-1 flex items-start gap-1 text-xs underline" href={googleMapsDirections(o.address)} target="_blank" rel="noreferrer">
          <MapPin className="mt-0.5 size-3 shrink-0" aria-hidden />{addressOneLine(o.address)}{o.address.reference ? ` (ref.: ${o.address.reference})` : ""}
        </a>
      )}

      {open && (
        <ul className="mt-2 grid gap-1 border-t border-border pt-2">
          {o.items.map((i, k) => (
            <li key={k}>
              <b>{i.quantity}×</b> {i.name}
              {i.options.length > 0 && <span className="block pl-4 text-xs text-muted">{i.options.map((x) => x.name).join(", ")}</span>}
              {i.notes && <span className="block pl-4 text-xs font-semibold text-warning">Obs.: {i.notes}</span>}
            </li>
          ))}
          {o.notes && <li className="text-xs font-semibold text-warning">Obs. do pedido: {o.notes}</li>}
        </ul>
      )}

      <p className="mt-2 flex items-center justify-between border-t border-border pt-2">
        <span className="text-xs">{pay}</span><b>{formatBRL(o.total_cents)}</b>
      </p>

      {o.notifications.length > 0 && (
        <ul className="mt-2 flex flex-wrap gap-1" aria-label="Avisos no WhatsApp">
          {o.notifications.map((n) => (
            <li key={n.id} className={`chip ${n.status === "sent" ? "bg-success-soft text-success" : n.status === "failed" ? "bg-danger-soft text-danger" : "bg-warning-soft text-warning"}`}
              title={n.error ?? undefined}>
              {n.status === "sent" ? <Check className="size-3" aria-hidden /> : n.status === "failed" ? <CircleAlert className="size-3" aria-hidden /> : null}
              {STAGE_SHORT[n.stage] ?? n.stage}
            </li>
          ))}
        </ul>
      )}
      {pendingNotice?.link && (
        <div className="mt-2 grid gap-1">
          <a className="btn btn-sm btn-success" href={pendingNotice.link} target="_blank" rel="noreferrer"
            onClick={() => run(pendingNotice.id + "ms", () => api(`/api/painel/avisos/${pendingNotice.id}`, { body: { action: "mark_sent" } }))}>
            <MessageCircle className="size-4" aria-hidden />Avisar no WhatsApp: {STAGE_SHORT[pendingNotice.stage]}
          </a>
          {pendingNotice.status === "failed" && (
            <button className="btn btn-sm btn-outline" onClick={() => run(pendingNotice.id + "rt", () => api(`/api/painel/avisos/${pendingNotice.id}`, { body: { action: "retry" } }), "Aviso enviado")}>Tentar pela API de novo</button>
          )}
        </div>
      )}

      <div className="mt-3 grid gap-2">
        {o.status === "realizado" && (
          <div className="flex gap-2">
            <button className="btn btn-sm btn-danger" disabled={!!isBusy} onClick={() => {
              const reason = prompt(`Motivo da recusa do pedido #${o.number} (o cliente verá esta mensagem):`, "Não conseguimos atender agora");
              if (reason !== null) onMove(o, "cancelado", { reason });
            }}>Recusar</button>
            <button className="btn btn-sm btn-primary flex-1" disabled={!!isBusy} onClick={() => onMove(o, "em_preparo")}>Aceitar e preparar</button>
          </div>
        )}
        {o.status === "em_preparo" && <button className="btn btn-sm btn-primary" disabled={!!isBusy} onClick={() => onMove(o, "pronto")}>Marcar como pronto</button>}
        {o.status === "pronto" && o.fulfillment === "pickup" && <button className="btn btn-sm btn-primary" disabled={!!isBusy} onClick={() => onMove(o, "entregue")}>Cliente retirou</button>}
        {o.status === "pronto" && o.fulfillment === "delivery" && (
          <>
            <label className="field"><span>Motoboy</span>
              <select className="input min-h-10 py-1" value={courierId} onChange={(e) => {
                setCourierId(e.target.value);
                run(o.id + "c", () => api(`/api/painel/pedidos/${o.id}/motoboy`, { body: { courierId: e.target.value || null } }), e.target.value ? "Motoboy definido: o pedido já aparece no link dele" : undefined);
              }}>
                <option value="">Escolha o motoboy</option>
                {couriers.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
              </select>
            </label>
            {couriers.length === 0 && <p className="text-xs text-muted">Cadastre um motoboy em Motoboys.</p>}
            <button className="btn btn-sm btn-primary" disabled={!!isBusy || !courierId} onClick={() => onMove(o, "em_entrega", { courierId })}><Bike className="size-4" aria-hidden />Saiu para entrega</button>
          </>
        )}
        {o.status === "em_entrega" && (
          <>
            <p className="flex items-center gap-1 text-xs"><Bike className="size-3.5" aria-hidden />Com {o.courier?.name ?? "motoboy"}</p>
            <button className="btn btn-sm btn-primary" disabled={!!isBusy} onClick={() => onMove(o, "entregue")}>Marcar como entregue</button>
          </>
        )}
        <button className="inline-flex items-center gap-1 justify-self-start text-xs text-muted underline" onClick={() => {
          navigator.clipboard.writeText(o.trackingUrl).then(() => run("cp", async () => {}, "Link de acompanhamento copiado"), () => {});
        }}><Copy className="size-3" aria-hidden />Copiar link do cliente</button>
      </div>
    </li>
  );
}
