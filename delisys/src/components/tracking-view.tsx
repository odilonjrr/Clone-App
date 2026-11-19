"use client";
import dynamic from "next/dynamic";
import Link from "next/link";
import { Check, Copy, Crosshair, MessageCircle, ReceiptText, RefreshCw, X } from "lucide-react";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { api } from "@/lib/client-api";
import { estimate, hhmm, TIME_ZONE } from "@/lib/eta";
import { formatBRL } from "@/lib/money";
import { STATUS_LABEL, timelineFor } from "@/lib/status";
import type { Tracking } from "@/lib/tracking";
import type { MapApi, MapBubble } from "./tracking-map";

const TrackingMap = dynamic(() => import("./tracking-map").then((m) => m.TrackingMap), {
  ssr: false,
  loading: () => <div className="h-full w-full bg-surface-sunken" />,
});

const PAY_TEXT = { pix: "Pix", cash: "Dinheiro", card_on_delivery: "Cartão na entrega" } as const;
const STEP_TEXT: Record<string, string> = {
  realizado: "Pedido realizado", em_preparo: "Em preparação", pronto: "Pronto", em_entrega: "Saiu para entrega", entregue: "Entregue",
};
// Ilustrações: Twemoji (CC-BY 4.0)
const ART: Record<string, string> = {
  aguardando_pagamento: "1f4b8", realizado: "1f9fe", em_preparo: "1f373", pronto: "1f961",
  em_entrega: "1f6f5", entregue: "1f60b", cancelado: "1f614",
};

function at(iso: string | null) {
  return iso ? new Date(iso).toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit", timeZone: TIME_ZONE }) : null;
}

function headline(t: Tracking) {
  const courier = t.courier?.firstName;
  const pickup = t.fulfillment === "pickup";
  switch (t.status) {
    case "aguardando_pagamento": return { title: "Falta pagar", text: `Pague ${formatBRL(t.totals.total)} com Pix para a ${t.store?.name ?? "loja"} receber seu pedido.` };
    case "realizado": return { title: "Pedido recebido", text: pickup ? "A loja recebeu seu pedido. Este é o horário previsto para retirar." : "A loja recebeu seu pedido. Este é o horário previsto de chegada." };
    case "em_preparo": return { title: "Em preparação", text: pickup ? "Seu pedido está sendo preparado. Retire no balcão a partir deste horário." : "Seu pedido está sendo preparado. Previsão de chegada acima." };
    case "pronto": return { title: "Pronto", text: pickup ? "Seu pedido está pronto! Confira os itens antes de sair do balcão." : "Seu pedido está pronto e logo sai para entrega." };
    case "em_entrega": return { title: courier ? `${courier} está a caminho` : "Seu pedido está a caminho", text: "Previsão de chegada acima. Fique de olho no celular e no interfone." };
    case "entregue": return { title: pickup ? "Pedido retirado" : "Pedido entregue", text: "Bom apetite! Obrigado por pedir direto com a loja." };
    default: return { title: "Pedido cancelado", text: t.cancelReason ? `Motivo: ${t.cancelReason}. Se tiver dúvida, fale com a loja.` : "Se tiver dúvida, fale com a loja." };
  }
}

export function TrackingView({ token, initial }: { token: string; initial: Tracking }) {
  const [t, setT] = useState(initial);
  const [now, setNow] = useState(() => new Date());
  const [copied, setCopied] = useState(false);
  const [msg, setMsg] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const mapApi = useRef<MapApi | null>(null);
  // a previsão depende do relógio do aparelho: só aparece depois de carregar no navegador
  const [mounted, setMounted] = useState(false);
  useEffect(() => setMounted(true), []);

  const refresh = useCallback(async () => {
    try { setT(await api<Tracking>(`/api/pedidos/${token}`)); setNow(new Date()); } catch { /* tenta de novo no próximo ciclo */ }
  }, [token]);

  useEffect(() => {
    if (t.status === "entregue" || t.status === "cancelado") return;
    const ms = t.status === "em_entrega" ? 5000 : t.status === "aguardando_pagamento" ? 4000 : 8000;
    const id = setInterval(refresh, ms);
    const onFocus = () => refresh();
    window.addEventListener("focus", onFocus);
    return () => { clearInterval(id); window.removeEventListener("focus", onFocus); };
  }, [t.status, refresh]);
  useEffect(() => { const id = setInterval(() => setNow(new Date()), 30000); return () => clearInterval(id); }, []);

  const eta = useMemo(() => estimate({
    status: t.status, fulfillment: t.fulfillment, placedAt: t.times.realizado, readyAt: t.times.pronto, deliveredAt: t.times.entregue,
    etaMinMinutes: t.store?.etaMin ?? 30, etaMaxMinutes: t.store?.etaMax ?? 50,
    courier: t.courierPosition, destination: t.destination, now,
  }), [t, now]);

  const courierPos = t.status === "em_entrega" && t.courierPosition ? { lat: t.courierPosition.lat, lng: t.courierPosition.lng } : null;
  const storeDirections = t.store
    ? `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(t.store.location ? `${t.store.location.lat},${t.store.location.lng}` : t.store.address ?? t.store.name)}`
    : undefined;
  const bubble: MapBubble | null =
    t.fulfillment === "pickup" && t.status !== "entregue" && t.status !== "cancelado" && t.store?.location
      ? { at: "store", label: "Como chegar", href: storeDirections }
      : courierPos ? { at: "courier", label: t.courier?.firstName ?? "Motoboy" }
      : t.destination && t.status !== "cancelado" ? { at: "destination", label: t.status === "entregue" ? "Entregue aqui" : "Entrega aqui" }
      : null;

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

  const h = headline(t);
  const steps = timelineFor(t.fulfillment);
  const currentIdx = steps.indexOf(t.status as (typeof steps)[number]);
  const helpLink = t.store?.whatsapp ? `https://wa.me/${t.store.whatsapp}?text=${encodeURIComponent(`Olá! Preciso de ajuda com o pedido #${t.number}.`)}` : null;
  const bigTime = !mounted && eta.at && eta.kind !== "entregue" ? "--:--" : eta.at ? hhmm(eta.at) : t.status === "aguardando_pagamento" ? formatBRL(t.totals.total) : "—";
  const timeLabel = eta.kind === "chegada" ? "Previsão de chegada" : eta.kind === "retirada" ? (t.status === "pronto" ? "Pronto desde" : "Pronto para retirar às") : eta.kind === "entregue" ? (t.fulfillment === "pickup" ? "Retirado às" : "Entregue às") : eta.kind === "pagamento" ? "Valor do Pix" : "Situação";

  return (
    <div data-theme="light" className="relative min-h-dvh bg-surface-sunken text-fg">
      {/* mapa em tela cheia, atrás de tudo */}
      <div className="fixed inset-0 z-0">
        <TrackingMap store={t.store?.location ?? null} destination={t.destination} courier={courierPos} bubble={bubble}
          onReady={(a) => { mapApi.current = a; }} />
      </div>

      {/* topo: fechar e ajuda */}
      <div className="pointer-events-none fixed inset-x-0 top-0 z-20 flex items-start justify-between px-4"
        style={{ paddingTop: "max(14px, env(safe-area-inset-top))" }}>
        <Link href={t.store ? `/${t.store.slug}` : "/"} aria-label="Fechar e voltar ao cardápio"
          className="pointer-events-auto grid size-12 place-items-center rounded-full bg-surface" style={{ boxShadow: "var(--shadow-card)" }}>
          <X className="size-6" aria-hidden />
        </Link>
        {helpLink && (
          <a href={helpLink} target="_blank" rel="noreferrer"
            className="pointer-events-auto flex min-h-12 items-center rounded-[16px] border-2 border-fg bg-surface px-5 font-display text-lg font-bold" style={{ boxShadow: "var(--shadow-card)" }}>
            Ajuda
          </a>
        )}
      </div>

      {/* conteúdo: começa perto do pé da tela; ao rolar, sobe por cima do mapa */}
      <div className="pointer-events-none relative z-10 mx-auto max-w-lg" style={{ paddingTop: "max(46dvh, calc(100dvh - 420px))" }}>
        <div className="flex justify-end px-4 pb-3">
          <button onClick={() => mapApi.current?.recenter()} aria-label="Centralizar o mapa"
            className="pointer-events-auto grid size-13 place-items-center rounded-full bg-surface" style={{ boxShadow: "var(--shadow-card)" }}>
            <Crosshair className="size-6" aria-hidden />
          </button>
        </div>

        {/* cartão principal */}
        <section aria-labelledby="h-status" className="pointer-events-auto mx-3 rounded-[28px] bg-surface p-5 sm:p-6" style={{ boxShadow: "0 10px 40px rgba(26,21,18,.18)" }}>
          <div className="flex gap-3">
            <div className="min-w-0 flex-1">
              <p className="text-xs font-semibold uppercase tracking-wide text-muted">{timeLabel}</p>
              <p className="font-display text-[44px] font-extrabold leading-[1.05] tracking-tight tabular-nums sm:text-5xl" aria-live="polite">{bigTime}</p>
              <h1 id="h-status" className="mt-2 text-lg font-bold leading-snug">{h.title}</h1>
              <p className="mt-1 text-[15px] leading-snug text-muted">{h.text}</p>
            </div>
            <div className="relative grid size-28 shrink-0 place-items-center sm:size-32" aria-hidden>
              <span className="absolute inset-0 rounded-full bg-surface-sunken" />
              <span className="absolute -right-1 -top-1 h-12 w-20 rotate-[24deg] rounded-[50%] bg-accent opacity-90" />
              <span className="relative grid size-20 place-items-center rounded-full bg-surface" style={{ boxShadow: "var(--shadow-sm)" }}>
                <img src={`/ilustracoes/${ART[t.status] ?? "1f9fe"}.svg`} alt="" className="size-12" />
              </span>
            </div>
          </div>
          <div className="mt-4 flex flex-wrap items-center gap-3">
            <span className="inline-flex min-h-12 items-center gap-2 rounded-[16px] bg-fg px-4 font-display text-lg font-bold text-bg">
              <ReceiptText className="size-5" aria-hidden />{t.fulfillment === "pickup" ? "Retirada" : "Pedido"}: #{t.number}
            </span>
            {t.courierPosition && t.status === "em_entrega" && (
              <span className="text-xs text-muted">Posição atualizada às {at(t.courierPosition.updatedAt)}.</span>
            )}
          </div>
          {t.status === "em_entrega" && !t.courierPosition && (
            <p className="mt-3 text-xs text-muted">A localização do motoboy aparece no mapa assim que ele ativar o compartilhamento.</p>
          )}
        </section>

        {/* folha inferior */}
        <section aria-label="Detalhes do pedido" className="pointer-events-auto mt-4 min-h-[60dvh] rounded-t-[28px] bg-surface px-5 pb-16 pt-3" style={{ boxShadow: "0 -6px 24px rgba(26,21,18,.10)", paddingBottom: "max(64px, env(safe-area-inset-bottom))" }}>
          <div className="mx-auto mb-4 h-1.5 w-12 rounded-full bg-border" aria-hidden />

          {t.status === "aguardando_pagamento" && (
            <div className="mb-6 grid gap-3 rounded-[16px] border border-border p-4">
              <h2 className="text-lg font-bold">Pague com Pix</h2>
              {t.pix && !t.pix.expired ? (
                <>
                  {t.pix.testMode && <p className="rounded-md bg-warning-soft p-2 text-xs text-warning">Modo teste: este Pix é fictício. Não pague. Use o botão abaixo para simular.</p>}
                  <img src={t.pix.qr} alt="QR code do Pix" className="mx-auto size-56 rounded-md bg-white p-2" />
                  <p className="text-xs text-muted">Ou copie o código e cole no app do seu banco, em Pix Copia e Cola.</p>
                  <code className="block max-h-20 overflow-y-auto break-all rounded-md bg-surface-sunken p-2 text-[11px]">{t.pix.payload}</code>
                  <button className="btn btn-primary" onClick={copyPix}>{copied ? <><Check className="size-4" aria-hidden />Código copiado</> : <><Copy className="size-4" aria-hidden />Copiar código Pix</>}</button>
                  <p className="text-xs text-muted">Vale até {at(t.pix.expiresAt)}. Esta tela confirma sozinha quando o pagamento cair.</p>
                  {t.pix.testMode && <button className="btn btn-success" disabled={busy} onClick={() => act(`/api/pedidos/${token}/simular-pagamento`)}>Simular pagamento (teste)</button>}
                </>
              ) : (
                <>
                  <p className="text-sm text-muted">{t.pix ? "O Pix expirou." : "Não conseguimos gerar o Pix agora."} Gere um novo para continuar.</p>
                  <button className="btn btn-primary" disabled={busy} onClick={() => act(`/api/pedidos/${token}/pix`)}><RefreshCw className="size-4" aria-hidden />Gerar novo Pix</button>
                </>
              )}
            </div>
          )}

          {t.status !== "aguardando_pagamento" && t.status !== "cancelado" && (
            <>
              <h2 className="text-base font-bold">Andamento</h2>
              <ol className="mt-3 grid">
                {steps.map((s, i) => {
                  const done = i < currentIdx || t.status === "entregue";
                  const current = i === currentIdx && t.status !== "entregue";
                  const when = at(t.times[s as keyof typeof t.times] ?? null);
                  return (
                    <li key={s} className="relative flex gap-3 pb-4 last:pb-0" aria-current={current ? "step" : undefined}>
                      {i < steps.length - 1 && <span className={`absolute left-[13px] top-7 h-[calc(100%-20px)] w-0.5 ${done ? "bg-success" : "bg-border"}`} aria-hidden />}
                      <span className={`grid size-7 shrink-0 place-items-center rounded-full border-2 ${done ? "border-success bg-success text-white" : current ? "border-success bg-success-soft text-success" : "border-border text-muted"}`}>
                        {done ? <Check className="size-4" strokeWidth={3} aria-hidden /> : <span className="text-xs font-bold">{i + 1}</span>}
                      </span>
                      <span className="pt-0.5">
                        <span className={`block text-sm font-semibold ${done || current ? "" : "text-muted"}`}>
                          {s === "pronto" && t.fulfillment === "pickup" ? "Pronto para retirar" : s === "entregue" && t.fulfillment === "pickup" ? "Retirado" : STEP_TEXT[s]}
                        </span>
                        {when && <span className="text-xs text-muted">{when}</span>}
                      </span>
                    </li>
                  );
                })}
              </ol>
            </>
          )}

          <h2 className="mt-7 text-base font-bold">Resumo · {t.store?.name}</h2>
          <ul className="mt-2 grid gap-2 text-sm">
            {t.items.map((i, k) => (
              <li key={k} className="flex justify-between gap-3">
                <span><b>{i.quantity}×</b> {i.name}{i.options.length > 0 && <span className="block text-xs text-muted">{i.options.join(", ")}</span>}</span>
                <span className="shrink-0">{formatBRL(i.total)}</span>
              </li>
            ))}
          </ul>
          <div className="mt-3 grid gap-1 border-t border-border pt-3 text-sm">
            <p className="flex justify-between"><span className="text-muted">Entrega</span><span>{t.fulfillment === "pickup" ? "Retirada" : formatBRL(t.totals.fee)}</span></p>
            <p className="flex justify-between font-bold"><span>Total</span><span>{formatBRL(t.totals.total)}</span></p>
            <p className="flex justify-between gap-3"><span className="text-muted">Pagamento</span><span className="text-right">{PAY_TEXT[t.payment.method]}{t.payment.status === "aprovado" ? " · pago" : ""}{t.payment.changeForCents ? ` · troco p/ ${formatBRL(t.payment.changeForCents)}` : ""}</span></p>
            {t.address && <p className="mt-1 text-xs text-muted">Entregar em: {t.address}</p>}
            {t.fulfillment === "pickup" && t.store?.address && <p className="mt-1 text-xs text-muted">Retirar em: {t.store.address}</p>}
          </div>

          {msg && <p role="alert" className="mt-4 text-sm text-danger">{msg}</p>}

          <div className="mt-6 grid gap-2">
            {helpLink && (
              <a className="btn btn-cta w-full" href={helpLink} target="_blank" rel="noreferrer"><MessageCircle className="size-5" aria-hidden />Falar com a loja</a>
            )}
            {t.fulfillment === "pickup" && storeDirections && t.status !== "entregue" && (
              <a className="btn btn-outline w-full" href={storeDirections} target="_blank" rel="noreferrer">Como chegar na loja</a>
            )}
            {t.store && <Link className="btn btn-outline w-full" href={`/${t.store.slug}`}>Ver o cardápio</Link>}
          </div>
          <p className="mt-8 text-xs text-muted">
            {STATUS_LABEL[t.status]} · Este link é pessoal: quem o tiver vê os dados deste pedido. Não repasse.{" "}
            <a className="underline" href="/privacidade">Política de privacidade</a>. Ilustrações: Twemoji (CC-BY 4.0).
          </p>
        </section>
      </div>
    </div>
  );
}
