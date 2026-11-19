"use client";
import { LocateFixed, Plus } from "lucide-react";
import { useEffect, useState } from "react";
import { api } from "@/lib/client-api";
import { formatBRL, parseBRL } from "@/lib/money";
import { formatBrPhone } from "@/lib/phone";

type Zone = { id?: string; name: string; fee: string; isActive: boolean };
type StoreData = {
  name: string; slug: string; whatsapp_phone: string | null; address_line: string | null; district: string | null; city: string | null;
  lat: number | null; lng: number | null; is_open: boolean; min_order_cents: number; eta_min_minutes: number; eta_max_minutes: number;
  pickup_enabled: boolean; delivery_enabled: boolean; payment_methods: string[];
};
const PAY = [["pix", "Pix online"], ["cash", "Dinheiro"], ["card_on_delivery", "Cartão na entrega"]] as const;

export function StoreSettings() {
  const [s, setS] = useState<StoreData | null>(null);
  const [zones, setZones] = useState<Zone[]>([]);
  const [msg, setMsg] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    api<{ store: StoreData; zones: { id: string; name: string; fee_cents: number; is_active: boolean }[] }>("/api/painel/loja")
      .then((d) => { setS(d.store); setZones(d.zones.map((z) => ({ id: z.id, name: z.name, fee: (z.fee_cents / 100).toFixed(2).replace(".", ","), isActive: z.is_active }))); })
      .catch((e) => setError((e as Error).message));
  }, []);

  if (!s) return error ? <p role="alert" className="text-danger">{error}</p> : <div className="skeleton h-96 max-w-3xl" />;
  const set = <K extends keyof StoreData>(k: K, v: StoreData[K]) => setS({ ...s, [k]: v });

  async function save(e: React.FormEvent) {
    e.preventDefault();
    if (!s) return;
    setBusy(true); setMsg(null); setError(null);
    const badZone = zones.find((z) => z.name.trim().length < 2 || parseBRL(z.fee) === null);
    if (badZone) { setError("Confira os bairros: cada um precisa de nome e taxa (ex.: 5,00)."); setBusy(false); return; }
    try {
      await api("/api/painel/loja", { method: "PUT", body: {
        name: s.name, whatsapp: s.whatsapp_phone || null, addressLine: s.address_line || null, district: s.district || null, city: s.city || null,
        lat: s.lat, lng: s.lng, isOpen: s.is_open, minOrderCents: s.min_order_cents, etaMin: s.eta_min_minutes, etaMax: s.eta_max_minutes,
        pickupEnabled: s.pickup_enabled, deliveryEnabled: s.delivery_enabled, paymentMethods: s.payment_methods,
        zones: zones.map((z) => ({ id: z.id, name: z.name.trim(), feeCents: parseBRL(z.fee)!, isActive: z.isActive })),
      } });
      setMsg("Alterações salvas. O cardápio já mostra as novas informações.");
      const d = await api<{ zones: { id: string; name: string; fee_cents: number; is_active: boolean }[] }>("/api/painel/loja");
      setZones(d.zones.map((z) => ({ id: z.id, name: z.name, fee: (z.fee_cents / 100).toFixed(2).replace(".", ","), isActive: z.is_active })));
    } catch (err) { setError((err as Error).message); }
    finally { setBusy(false); }
  }

  return (
    <form onSubmit={save} className="grid max-w-3xl gap-5" noValidate>
      <header className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold">Loja e entrega</h1>
          <p className="text-sm text-muted">Seu cardápio: <a className="font-semibold text-accent underline" href={`/${s.slug}`} target="_blank">/{s.slug}</a></p>
        </div>
        <label className="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" className="size-5 accent-[var(--success)]" checked={s.is_open} onChange={(e) => set("is_open", e.target.checked)} />Recebendo pedidos agora</label>
      </header>

      <section className="card grid gap-3 p-4 sm:grid-cols-2">
        <h2 className="text-base font-bold sm:col-span-2">Dados da loja</h2>
        <label className="field"><span>Nome</span><input className="input" id="s-name" value={s.name} onChange={(e) => set("name", e.target.value)} /></label>
        <label className="field"><span>WhatsApp da loja</span><input className="input" id="s-wa" inputMode="tel" value={s.whatsapp_phone ? (s.whatsapp_phone.startsWith("55") ? formatBrPhone(s.whatsapp_phone) : s.whatsapp_phone) : ""} onChange={(e) => set("whatsapp_phone", e.target.value)} placeholder="(11) 98765-4321" /></label>
        <label className="field sm:col-span-2"><span>Endereço (para retirada)</span><input className="input" id="s-addr" value={s.address_line ?? ""} onChange={(e) => set("address_line", e.target.value)} placeholder="Rua e número" /></label>
        <label className="field"><span>Bairro</span><input className="input" id="s-dist" value={s.district ?? ""} onChange={(e) => set("district", e.target.value)} /></label>
        <label className="field"><span>Cidade</span><input className="input" id="s-city" value={s.city ?? ""} onChange={(e) => set("city", e.target.value)} /></label>
        <div className="sm:col-span-2">
          <button type="button" className="btn btn-sm btn-outline" onClick={() => navigator.geolocation?.getCurrentPosition((p) => setS({ ...s, lat: p.coords.latitude, lng: p.coords.longitude }), () => setError("Não foi possível pegar a localização."))}>
            <LocateFixed className="size-4" aria-hidden />{s.lat != null ? "Localização da loja salva" : "Usar a localização daqui como endereço da loja"}
          </button>
        </div>
      </section>

      <section className="card grid gap-3 p-4 sm:grid-cols-3">
        <h2 className="text-base font-bold sm:col-span-3">Pedidos</h2>
        <label className="field"><span>Pedido mínimo (R$)</span><input className="input" id="s-min" inputMode="decimal" defaultValue={(s.min_order_cents / 100).toFixed(2).replace(".", ",")} onBlur={(e) => set("min_order_cents", parseBRL(e.target.value) ?? 0)} /></label>
        <label className="field"><span>Entrega: mínimo (min)</span><input className="input" id="s-etamin" type="number" min={5} value={s.eta_min_minutes} onChange={(e) => set("eta_min_minutes", Number(e.target.value))} /></label>
        <label className="field"><span>Entrega: máximo (min)</span><input className="input" id="s-etamax" type="number" min={5} value={s.eta_max_minutes} onChange={(e) => set("eta_max_minutes", Number(e.target.value))} /></label>
        <fieldset className="grid gap-2 sm:col-span-3">
          <legend className="text-xs font-semibold text-muted">Formas de pagamento</legend>
          <div className="flex flex-wrap gap-4">
            {PAY.map(([k, label]) => (
              <label key={k} className="flex items-center gap-2 text-sm"><input type="checkbox" className="size-5 accent-[var(--accent)]" checked={s.payment_methods.includes(k)}
                onChange={(e) => set("payment_methods", e.target.checked ? [...s.payment_methods, k] : s.payment_methods.filter((x) => x !== k))} />{label}</label>
            ))}
          </div>
        </fieldset>
        <div className="flex flex-wrap gap-4 sm:col-span-3">
          <label className="flex items-center gap-2 text-sm"><input type="checkbox" className="size-5 accent-[var(--accent)]" checked={s.delivery_enabled} onChange={(e) => set("delivery_enabled", e.target.checked)} />Fazer entregas</label>
          <label className="flex items-center gap-2 text-sm"><input type="checkbox" className="size-5 accent-[var(--accent)]" checked={s.pickup_enabled} onChange={(e) => set("pickup_enabled", e.target.checked)} />Aceitar retirada no balcão</label>
        </div>
      </section>

      <section className="card grid gap-3 p-4">
        <h2 className="text-base font-bold">Bairros atendidos e taxa de entrega</h2>
        <p className="-mt-2 text-xs text-muted">O cliente escolhe o bairro no checkout. Bairro desmarcado some do cardápio.</p>
        <ul className="grid gap-2">
          {zones.map((z, i) => (
            <li key={z.id ?? `n${i}`} className="grid grid-cols-[1fr_7rem_auto] items-center gap-2">
              <input className="input" aria-label="Bairro" value={z.name} onChange={(e) => setZones(zones.map((x, k) => (k === i ? { ...x, name: e.target.value } : x)))} placeholder="Nome do bairro" />
              <input className="input" aria-label={`Taxa de ${z.name || "entrega"}`} inputMode="decimal" value={z.fee} onChange={(e) => setZones(zones.map((x, k) => (k === i ? { ...x, fee: e.target.value } : x)))} placeholder="5,00" />
              <label className="flex items-center gap-1 text-xs"><input type="checkbox" className="size-5 accent-[var(--success)]" checked={z.isActive} onChange={(e) => setZones(zones.map((x, k) => (k === i ? { ...x, isActive: e.target.checked } : x)))} />Ativo</label>
            </li>
          ))}
        </ul>
        <button type="button" className="btn btn-sm btn-outline justify-self-start" onClick={() => setZones([...zones, { name: "", fee: "", isActive: true }])}><Plus className="size-4" aria-hidden />Adicionar bairro</button>
        {zones.length > 0 && <p className="text-xs text-muted">Menor taxa: {formatBRL(Math.min(...zones.map((z) => parseBRL(z.fee) ?? 0)))}</p>}
      </section>

      {error && <p role="alert" className="text-sm text-danger">{error}</p>}
      {msg && <p role="status" className="text-sm text-success">{msg}</p>}
      <div className="sticky bottom-0 -mx-4 border-t border-border bg-bg/95 px-4 py-3 backdrop-blur lg:-mx-6 lg:px-6">
        <button className="btn btn-primary" disabled={busy}>{busy ? "Salvando…" : "Salvar alterações"}</button>
      </div>
    </form>
  );
}
