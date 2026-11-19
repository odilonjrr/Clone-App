"use client";
import { Bike, Check, MapPin, MessageCircle, Navigation, Phone, Radio, RefreshCw } from "lucide-react";
import { useCallback, useEffect, useRef, useState } from "react";
import { api } from "@/lib/client-api";
import { formatBRL } from "@/lib/money";
import { formatBrPhone } from "@/lib/phone";

type CourierOrder = {
  id: string; number: number; status: "pronto" | "em_entrega" | "entregue"; customerName: string; customerPhone: string;
  address: string | null; reference: string | null; mapsUrl: string | null; hasPin: boolean; itemsCount: number;
  totalCents: number; paid: boolean; collect: string | null; notes: string | null;
};
type Data = { courier: { name: string; storeName: string }; orders: CourierOrder[] };

export function CourierApp({ token }: { token: string }) {
  const [data, setData] = useState<Data | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [toast, setToast] = useState<string | null>(null);
  const [busy, setBusy] = useState<string | null>(null);
  const [sharing, setSharing] = useState(false);
  const [lastSent, setLastSent] = useState<Date | null>(null);
  const [geoError, setGeoError] = useState<string | null>(null);
  const watch = useRef<number | null>(null);
  const lastPost = useRef(0);
  const wake = useRef<WakeLockSentinel | null>(null);

  const load = useCallback(async () => {
    try { setData(await api<Data>(`/api/m/${token}`)); setError(null); } catch (e) { setError((e as Error).message); }
  }, [token]);
  useEffect(() => { load(); const id = setInterval(load, 10000); return () => clearInterval(id); }, [load]);
  useEffect(() => { if (!toast) return; const id = setTimeout(() => setToast(null), 3500); return () => clearTimeout(id); }, [toast]);

  const onTheWay = data?.orders.filter((o) => o.status === "em_entrega") ?? [];

  const stopSharing = useCallback(() => {
    if (watch.current != null) navigator.geolocation.clearWatch(watch.current);
    watch.current = null;
    wake.current?.release().catch(() => {});
    wake.current = null;
    setSharing(false);
  }, []);

  function startSharing() {
    if (!("geolocation" in navigator)) { setGeoError("Este celular não informa a localização pelo navegador."); return; }
    setGeoError(null);
    watch.current = navigator.geolocation.watchPosition(
      (p) => {
        const now = Date.now();
        if (now - lastPost.current < 8000) return; // no máximo a cada 8 s
        lastPost.current = now;
        api(`/api/m/${token}/localizacao`, { body: { lat: p.coords.latitude, lng: p.coords.longitude, accuracy: p.coords.accuracy } })
          .then(() => setLastSent(new Date()))
          .catch((e) => setGeoError((e as Error).message));
      },
      (err) => {
        setGeoError(err.code === err.PERMISSION_DENIED
          ? "Você não permitiu a localização. Libere nas configurações do navegador para o cliente acompanhar."
          : "Não foi possível pegar a localização agora. Tentando de novo…");
        if (err.code === err.PERMISSION_DENIED) stopSharing();
      },
      { enableHighAccuracy: true, maximumAge: 5000, timeout: 20000 },
    );
    setSharing(true);
    // mantém a tela acesa enquanto compartilha (quando o celular permite)
    navigator.wakeLock?.request("screen").then((w) => { wake.current = w; }).catch(() => {});
  }

  // para de compartilhar sozinho quando não houver mais entrega em andamento
  useEffect(() => { if (sharing && data && onTheWay.length === 0) stopSharing(); }, [sharing, data, onTheWay.length, stopSharing]);
  useEffect(() => () => stopSharing(), [stopSharing]);

  async function move(o: CourierOrder, to: "em_entrega" | "entregue") {
    setBusy(o.id);
    try {
      await api(`/api/m/${token}/pedidos/${o.id}`, { body: { to } });
      setToast(to === "em_entrega" ? `Pedido #${o.number}: cliente avisado que saiu para entrega` : `Pedido #${o.number} entregue`);
      await load();
      if (to === "em_entrega" && !sharing) startSharing();
    } catch (e) { setToast((e as Error).message); }
    finally { setBusy(null); }
  }

  if (!data) {
    return (
      <main className="mx-auto max-w-lg px-4 py-10">
        {error ? <p role="alert" className="card p-4">{error}</p> : <div className="grid gap-3" aria-busy="true"><div className="skeleton h-10 w-48" /><div className="skeleton h-40" /></div>}
      </main>
    );
  }

  const active = data.orders.filter((o) => o.status !== "entregue");
  const done = data.orders.filter((o) => o.status === "entregue");

  return (
    <main className="mx-auto grid max-w-lg gap-4 px-4 pb-16 pt-5">
      <header className="flex items-start justify-between gap-3">
        <div>
          <p className="text-sm text-muted">{data.courier.storeName}</p>
          <h1 className="text-2xl font-bold">Olá, {data.courier.name.split(" ")[0]}</h1>
        </div>
        <button className="btn btn-sm btn-outline" onClick={load} aria-label="Atualizar"><RefreshCw className="size-4" aria-hidden /></button>
      </header>

      <section aria-labelledby="h-loc" className="rounded-lg border p-4" style={{ background: sharing ? "var(--gradient-live)" : "var(--surface)", borderColor: sharing ? "rgba(43,212,107,.4)" : "var(--border)" }}>
        <h2 id="h-loc" className="flex items-center gap-2 text-base font-bold"><Radio className={`size-5 ${sharing ? "text-success" : "text-muted"}`} aria-hidden />{sharing ? "Compartilhando sua localização" : "Localização desligada"}</h2>
        <p className="mt-1 text-sm text-muted">
          {sharing ? `O cliente vê você no mapa pelo link de acompanhamento.${lastSent ? ` Último envio às ${lastSent.toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit", second: "2-digit" })}.` : ""} Deixe esta tela aberta durante a entrega.`
            : onTheWay.length ? "Ligue para o cliente acompanhar a entrega no mapa." : "Liga sozinha quando você marcar que saiu para entrega."}
        </p>
        {geoError && <p role="alert" className="mt-2 text-sm text-danger">{geoError}</p>}
        {onTheWay.length > 0 && (
          <button className={`btn btn-sm mt-3 ${sharing ? "btn-outline" : "btn-success"}`} onClick={sharing ? stopSharing : startSharing}>{sharing ? "Parar de compartilhar" : "Compartilhar localização"}</button>
        )}
      </section>

      {active.length === 0 ? (
        <p className="card p-6 text-center text-muted">Nenhuma entrega com você agora. Quando a loja passar um pedido, ele aparece aqui sozinho.</p>
      ) : (
        <ul className="grid gap-3">
          {active.map((o) => (
            <li key={o.id} className="card grid gap-3 p-4" style={{ boxShadow: "var(--shadow-card)" }}>
              <div className="flex items-center justify-between">
                <p className="font-display text-xl font-extrabold">#{o.number}</p>
                <span className={`chip ${o.status === "em_entrega" ? "bg-success-soft text-success" : "bg-status-pronto-soft text-status-pronto"}`}>{o.status === "em_entrega" ? "A CAMINHO" : "PRONTO PARA SAIR"}</span>
              </div>
              <div>
                <p className="font-semibold">{o.customerName}</p>
                {o.address && <p className="mt-1 flex items-start gap-1.5 text-sm"><MapPin className="mt-0.5 size-4 shrink-0 text-highlight" aria-hidden />{o.address}</p>}
                {o.reference && <p className="ml-5.5 text-xs text-muted">Referência: {o.reference}</p>}
                {o.hasPin && <p className="ml-5.5 text-xs text-success">O cliente marcou o ponto exato no mapa.</p>}
              </div>
              <p className={`rounded-md p-2.5 text-sm font-semibold ${o.paid ? "bg-success-soft text-success" : "bg-warning-soft text-warning"}`}>
                {o.paid ? `Já pago (${formatBRL(o.totalCents)}). Não cobrar.` : o.collect}
              </p>
              {o.notes && <p className="text-xs text-muted">Obs.: {o.notes}</p>}
              <p className="text-xs text-muted">{o.itemsCount} {o.itemsCount === 1 ? "item" : "itens"}</p>
              <div className="grid grid-cols-3 gap-2">
                {o.mapsUrl && <a className="btn btn-sm btn-primary col-span-3" href={o.mapsUrl} target="_blank" rel="noreferrer"><Navigation className="size-4" aria-hidden />Abrir rota no Google Maps</a>}
                <a className="btn btn-sm btn-outline" href={`tel:+${o.customerPhone}`} aria-label={`Ligar para ${o.customerName}, ${formatBrPhone(o.customerPhone)}`}><Phone className="size-4" aria-hidden />Ligar</a>
                <a className="btn btn-sm btn-outline col-span-2" href={`https://wa.me/${o.customerPhone}?text=${encodeURIComponent(`Olá, ${o.customerName.split(" ")[0]}! Aqui é o entregador do pedido #${o.number}.`)}`} target="_blank" rel="noreferrer"><MessageCircle className="size-4" aria-hidden />WhatsApp do cliente</a>
              </div>
              {o.status === "pronto" ? (
                <button className="btn btn-cta" disabled={busy === o.id} onClick={() => move(o, "em_entrega")}><Bike className="size-5" aria-hidden />Saí para entrega</button>
              ) : (
                <button className="btn btn-cta" disabled={busy === o.id} onClick={() => { if (confirm(`Confirmar a entrega do pedido #${o.number}?`)) move(o, "entregue"); }}><Check className="size-5" aria-hidden />Entreguei</button>
              )}
            </li>
          ))}
        </ul>
      )}

      {done.length > 0 && (
        <details className="card p-3 text-sm">
          <summary className="cursor-pointer font-semibold">Entregues hoje ({done.length})</summary>
          <ul className="mt-2 grid gap-1">{done.map((o) => <li key={o.id}>#{o.number} · {o.customerName}</li>)}</ul>
        </details>
      )}

      <div aria-live="polite" className="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex justify-center px-4">
        {toast && <p className="rounded-md bg-surface-raised px-4 py-3 text-sm" style={{ boxShadow: "var(--shadow-pop)" }}>{toast}</p>}
      </div>
    </main>
  );
}
