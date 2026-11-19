"use client";
import dynamic from "next/dynamic";
import Link from "next/link";
import { Check, Copy, MessageCircle, RefreshCw } from "lucide-react";
import { useCallback, useEffect, useState } from "react";
import { api } from "@/lib/client-api";
import { formatBRL } from "@/lib/money";
import { STATUS_LABEL, timelineFor } from "@/lib/status";
import type { Tracking } from "@/lib/tracking";

const LiveMap = dynamic(() => import("./live-map").then((m) => m.LiveMap), {
  ssr: false,
  loading: () => <div className="skeleton h-[220px]" />,
});

const PAY_TEXT = { pix: "Pix", cash: "Dinheiro", card_on_delivery: "Cartão na entrega" } as const;
const STEP_TEXT: Record<string, string> = {
  realizado: "Pedido realizado",
  em_preparo: "Em preparação",
  pronto: "Pronto",
  em_entrega: "Saiu para entrega",
  entregue: "Entregue",
};

function time(iso: string | null) {
  return iso ? new Date(iso).toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" }) : null;
}

export function TrackingView({ token, initial }: { token: string; initial: Tracking }) {
  const [t, setT] = useState(initial);
  const [copied, setCopied] = useState(false);
  const [msg, setMsg] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const refresh = useCallback(async () => {
    try { setT(await api<Tracking>(`/api/pedidos/${token}`)); } catch { /* tenta de novo no próximo ciclo */ }
  }, [token]);

  useEffect(() => {
    if (t.status === "entregue" || t.status === "cancelado") return;
    const ms = t.status === "em_entrega" ? 5000 : t.status === "aguardando_pagamento" ? 4000 : 8000;
    const id = setInterval(refresh, ms);
    const onFocus = () => refresh();
    window.addEventListener("focus", onFocus);
    return () => { clearInterval(id); window.removeEventListener("focus", onFocus); };
  }, [t.status, refresh]);

  async function copyPix() {
    if (!t.pix) return;
    try { await navigator.clipboard.writeText(t.pix.payload); setCopied(true); setTimeout(() => setCopied(false), 2500); }
    catch { setMsg("Não deu para copiar automaticamente. Toque e segure no código para copiar."); }
  }
  async function act(path: string) {
    setBusy(true); setMsg(null);
    try { await api(path, { method: "POST", body: {} }); await refresh(); }
    catch (e) { setMsg((e as Error).message); }
    finally { setBusy(false); }
  }

  const steps = timelineFor(t.fulfillment);
  const currentIdx = steps.indexOf(t.status as (typeof steps)[number]);

  return (
    <main className="mx-auto max-w-lg px-4 pb-16 pt-6" style={{ background: "var(--gradient-page)", backgroundRepeat: "no-repeat" }}>
      <p className="text-sm text-muted">{t.store?.name}</p>
      <h1 className="text-2xl font-bold">Pedido #{t.number}</h1>
      <p className="mt-1 text-sm" aria-live="polite">
        <span className={`chip ${t.status === "cancelado" ? "bg-danger-soft text-danger" : t.status === "entregue" ? "bg-success-soft text-success" : "bg-accent-soft text-accent-bright"}`}>
          {STATUS_LABEL[t.status]}
        </span>
      </p>

      {t.status === "aguardando_pagamento" && (
        <section aria-labelledby="h-pix" className="card mt-5 grid gap-3 p-4">
          <h2 id="h-pix" className="text-lg font-bold">Pague com Pix para enviar o pedido</h2>
          {t.pix && !t.pix.expired ? (
            <>
              {t.pix.testMode && <p className="rounded-md bg-warning-soft p-2 text-xs text-warning">Modo teste: este Pix é fictício. Não pague. Use o botão abaixo para simular.</p>}
              <img src={t.pix.qr} alt="QR code do Pix" className="mx-auto size-56 rounded-md bg-white p-2" />
              <p className="text-xs text-muted">Ou copie o código e cole no app do seu banco, em Pix Copia e Cola.</p>
              <code className="block max-h-20 overflow-y-auto break-all rounded-md bg-surface-sunken p-2 text-[11px]">{t.pix.payload}</code>
              <button className="btn btn-primary" onClick={copyPix}>{copied ? <><Check className="size-4" aria-hidden />Código copiado</> : <><Copy className="size-4" aria-hidden />Copiar código Pix</>}</button>
              <p className="text-xs text-muted">Total: <b className="text-fg">{formatBRL(t.totals.total)}</b>. Vale até {time(t.pix.expiresAt)}. Esta página confirma sozinha quando o pagamento cair.</p>
              {t.pix.testMode && <button className="btn btn-success" disabled={busy} onClick={() => act(`/api/pedidos/${token}/simular-pagamento`)}>Simular pagamento (teste)</button>}
            </>
          ) : (
            <>
              <p className="text-sm text-muted">{t.pix ? "O Pix expirou." : "Não conseguimos gerar o Pix agora."} Gere um novo para continuar.</p>
              <button className="btn btn-primary" disabled={busy} onClick={() => act(`/api/pedidos/${token}/pix`)}><RefreshCw className="size-4" aria-hidden />Gerar novo Pix</button>
            </>
          )}
        </section>
      )}

      {t.status === "cancelado" && (
        <p className="card mt-5 p-4 text-sm">Este pedido foi cancelado{t.cancelReason ? `: ${t.cancelReason}` : "."} Se tiver dúvida, fale com a loja.</p>
      )}

      {t.status !== "aguardando_pagamento" && t.status !== "cancelado" && (
        <section aria-labelledby="h-etapas" className="card mt-5 p-4">
          <h2 id="h-etapas" className="sr-only">Etapas do pedido</h2>
          <ol className="grid gap-0">
            {steps.map((s, i) => {
              const done = i < currentIdx || t.status === "entregue";
              const now = i === currentIdx && t.status !== "entregue";
              const at = time(t.times[s as keyof typeof t.times] ?? null);
              return (
                <li key={s} className="relative flex gap-3 pb-5 last:pb-0" aria-current={now ? "step" : undefined}>
                  {i < steps.length - 1 && <span className={`absolute left-[13px] top-7 h-[calc(100%-20px)] w-0.5 ${done ? "bg-success" : "bg-border"}`} aria-hidden />}
                  <span className={`grid size-7 shrink-0 place-items-center rounded-full border-2 ${done ? "border-success bg-success text-[#062012]" : now ? "border-success bg-success-soft text-success" : "border-border text-muted"}`}
                    style={done || now ? { boxShadow: "var(--shadow-glow-success)" } : undefined}>
                    {done ? <Check className="size-4" strokeWidth={3} aria-hidden /> : <span className="text-xs font-bold">{i + 1}</span>}
                  </span>
                  <span className="pt-0.5">
                    <span className={`block text-sm font-semibold ${done || now ? "" : "text-muted"}`}>{s === "pronto" && t.fulfillment === "pickup" ? "Pronto para retirar" : s === "entregue" && t.fulfillment === "pickup" ? "Retirado" : STEP_TEXT[s]}</span>
                    {at && <span className="text-xs text-muted">{at}</span>}
                  </span>
                </li>
              );
            })}
          </ol>
        </section>
      )}

      {t.status === "em_entrega" && (
        <section aria-labelledby="h-entrega" className="mt-5 grid gap-3 rounded-lg border p-4" style={{ background: "var(--gradient-live)", borderColor: "rgba(43,212,107,.35)" }}>
          <h2 id="h-entrega" className="text-base font-bold">{t.courier ? `${t.courier.firstName} está a caminho` : "Seu pedido está a caminho"}</h2>
          {t.courierPosition ? (
            <>
              <LiveMap courier={t.courierPosition} destination={t.destination} />
              <p className="text-xs text-muted">Posição atualizada às {time(t.courierPosition.updatedAt)}.</p>
            </>
          ) : (
            <p className="text-sm text-muted">A localização do motoboy aparece aqui assim que ele ativar o compartilhamento.</p>
          )}
        </section>
      )}

      <section aria-labelledby="h-itens" className="card mt-5 p-4 text-sm">
        <h2 id="h-itens" className="text-base font-bold">Resumo</h2>
        <ul className="mt-2 grid gap-2">
          {t.items.map((i, k) => (
            <li key={k} className="flex justify-between gap-3">
              <span><b>{i.quantity}×</b> {i.name}{i.options.length > 0 && <span className="block text-xs text-muted">{i.options.join(", ")}</span>}</span>
              <span className="shrink-0">{formatBRL(i.total)}</span>
            </li>
          ))}
        </ul>
        <div className="mt-3 grid gap-1 border-t border-border pt-3">
          <p className="flex justify-between"><span className="text-muted">Entrega</span><span>{t.fulfillment === "pickup" ? "Retirada" : formatBRL(t.totals.fee)}</span></p>
          <p className="flex justify-between font-bold"><span>Total</span><span>{formatBRL(t.totals.total)}</span></p>
          <p className="flex justify-between"><span className="text-muted">Pagamento</span><span>{PAY_TEXT[t.payment.method]}{t.payment.status === "aprovado" ? " · pago" : ""}{t.payment.changeForCents ? ` · troco p/ ${formatBRL(t.payment.changeForCents)}` : ""}</span></p>
          {t.address && <p className="mt-1 text-xs text-muted">Entregar em: {t.address}</p>}
        </div>
      </section>

      {msg && <p role="alert" className="mt-4 text-sm text-danger">{msg}</p>}

      <div className="mt-6 flex flex-wrap gap-2">
        {t.store?.whatsapp && <a className="btn btn-outline" href={`https://wa.me/${t.store.whatsapp}?text=${encodeURIComponent(`Olá! Sobre o pedido #${t.number}:`)}`} target="_blank" rel="noreferrer"><MessageCircle className="size-4" aria-hidden />Falar com a loja</a>}
        {t.store && <Link className="btn btn-outline" href={`/${t.store.slug}`}>Ver o cardápio</Link>}
      </div>
      <p className="mt-8 text-xs text-muted">Este link é pessoal: quem o tiver vê os dados deste pedido. Não repasse. <a className="underline" href="/privacidade">Política de privacidade</a></p>
    </main>
  );
}
