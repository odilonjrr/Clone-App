"use client";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { ArrowLeft, Banknote, CreditCard, LocateFixed, Minus, Plus, QrCode, Store as StoreIcon, Bike } from "lucide-react";
import { useMemo, useRef, useState } from "react";
import { useCart } from "@/lib/cart";
import { api } from "@/lib/client-api";
import { formatBRL, parseBRL } from "@/lib/money";
import { normalizeBrPhone } from "@/lib/phone";

type Pay = "pix" | "cash" | "card_on_delivery";
type Props = {
  store: { slug: string; name: string; isOpen: boolean; minOrderCents: number; pickupEnabled: boolean; deliveryEnabled: boolean;
    paymentMethods: Pay[]; pickupAddress: string | null; city: string | null };
  zones: { id: string; name: string; feeCents: number }[];
  pixNeedsDocument: boolean;
  pixTestMode: boolean;
};

const PAY_LABEL: Record<Pay, { title: string; hint: string; Icon: typeof QrCode }> = {
  pix: { title: "Pix", hint: "Pague agora pelo app do banco", Icon: QrCode },
  cash: { title: "Dinheiro", hint: "Na entrega ou na retirada", Icon: Banknote },
  card_on_delivery: { title: "Cartão na entrega", hint: "Crédito ou débito na maquininha", Icon: CreditCard },
};

const SAVED = "delisys:cliente";
function loadSaved() {
  try { return JSON.parse(localStorage.getItem(SAVED) ?? "{}") as Record<string, string>; } catch { return {}; }
}

export function Checkout({ store, zones, pixNeedsDocument, pixTestMode }: Props) {
  const router = useRouter();
  const cart = useCart(store.slug);
  const saved = useMemo(() => (typeof window === "undefined" ? {} : loadSaved()), []);
  const [fulfillment, setFulfillment] = useState<"delivery" | "pickup">(store.deliveryEnabled ? "delivery" : "pickup");
  const [zoneId, setZoneId] = useState(saved.zoneId && zones.some((z) => z.id === saved.zoneId) ? saved.zoneId : "");
  const [pay, setPay] = useState<Pay>(store.paymentMethods[0] ?? "cash");
  const [needChange, setNeedChange] = useState(false);
  const [coords, setCoords] = useState<{ lat: number; lng: number } | null>(null);
  const [locMsg, setLocMsg] = useState<string | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const idem = useRef<string>(crypto.randomUUID());

  const zone = zones.find((z) => z.id === zoneId);
  const fee = fulfillment === "delivery" ? (zone?.feeCents ?? 0) : 0;
  const total = cart.subtotalCents + fee;
  const belowMin = cart.subtotalCents < store.minOrderCents;

  function locate() {
    if (!navigator.geolocation) { setLocMsg("Este aparelho não informa a localização. Preencha o endereço."); return; }
    setLocMsg("Buscando sua localização…");
    navigator.geolocation.getCurrentPosition(
      (p) => { setCoords({ lat: p.coords.latitude, lng: p.coords.longitude }); setLocMsg("Localização anexada. O motoboy vai receber o ponto exato no mapa."); },
      () => setLocMsg("Não foi possível pegar a localização. O endereço escrito já basta."),
      { enableHighAccuracy: true, timeout: 10000 },
    );
  }

  async function submit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const f = new FormData(e.currentTarget);
    const v = (k: string) => String(f.get(k) ?? "").trim();
    const errs: Record<string, string> = {};
    if (v("name").length < 2) errs.name = "Informe seu nome.";
    const phone = normalizeBrPhone(v("phone"));
    if (!phone) errs.phone = "WhatsApp com DDD, ex.: (11) 98765-4321.";
    if (fulfillment === "delivery") {
      if (!zone) errs.zone = "Escolha o seu bairro.";
      if (v("street").length < 2) errs.street = "Informe a rua.";
      if (!v("number")) errs.number = "Informe o número.";
    }
    const change = pay === "cash" && needChange ? parseBRL(v("change")) : null;
    if (pay === "cash" && needChange && (!change || change <= total)) errs.change = `Informe um valor maior que ${formatBRL(total)}.`;
    if (pay === "pix" && pixNeedsDocument && ![11, 14].includes(v("doc").replace(/\D/g, "").length)) errs.doc = "Informe um CPF válido.";
    setErrors(errs);
    if (Object.keys(errs).length) {
      document.getElementById(Object.keys(errs)[0])?.focus();
      return;
    }
    try { localStorage.setItem(SAVED, JSON.stringify({ name: v("name"), phone: v("phone"), street: v("street"), number: v("number"), complement: v("complement"), reference: v("reference"), zoneId })); } catch { /* opcional */ }
    setBusy(true); setFormError(null);
    try {
      const r = await api<{ token: string }>(`/api/lojas/${store.slug}/pedidos`, {
        body: {
          idempotencyKey: idem.current,
          customerName: v("name"), customerPhone: v("phone"), customerDocument: v("doc") || undefined,
          fulfillment,
          zoneId: fulfillment === "delivery" ? zoneId : undefined,
          address: fulfillment === "delivery" ? {
            street: v("street"), number: v("number"), district: zone!.name, city: store.city || v("city") || "—",
            complement: v("complement") || undefined, reference: v("reference") || undefined, lat: coords?.lat ?? null, lng: coords?.lng ?? null,
          } : undefined,
          paymentMethod: pay, changeForCents: change, notes: v("notes") || undefined,
          items: cart.lines.map((l) => ({ productId: l.productId, quantity: l.quantity, options: l.options, notes: l.notes })),
        },
      });
      cart.clear();
      router.replace(`/r/${r.token}`);
    } catch (err) {
      setFormError((err as Error).message);
      setBusy(false);
    }
  }

  if (!cart.hydrated) {
    return (
      <main className="mx-auto grid max-w-lg gap-4 px-4 pt-16" aria-busy="true" aria-label="Carregando sua sacola">
        <div className="skeleton h-8 w-48" /><div className="skeleton h-32" /><div className="skeleton h-48" />
      </main>
    );
  }

  if (cart.lines.length === 0) {
    return (
      <main className="mx-auto flex min-h-dvh max-w-lg flex-col items-center justify-center gap-4 px-4 text-center">
        <h1 className="text-2xl font-bold">Sua sacola está vazia</h1>
        <p className="text-muted">Escolha alguma coisa no cardápio para continuar.</p>
        <Link className="btn btn-cta" href={`/${store.slug}`}>Ver o cardápio</Link>
      </main>
    );
  }

  const err = (k: string) => errors[k] ? <p id={`${k}-err`} className="text-xs text-danger">{errors[k]}</p> : null;
  const inv = (k: string) => ({ "aria-invalid": errors[k] ? true : undefined, "aria-describedby": errors[k] ? `${k}-err` : undefined });

  return (
    <main className="mx-auto max-w-lg px-4 pb-36 pt-4">
      <Link href={`/${store.slug}`} className="inline-flex min-h-11 items-center gap-1 text-sm font-semibold text-link"><ArrowLeft className="size-4" aria-hidden />Voltar ao cardápio</Link>
      <h1 className="mt-1 text-2xl font-bold">Finalizar pedido</h1>
      <p className="text-sm text-muted">{store.name}</p>

      <form onSubmit={submit} noValidate className="mt-5 grid gap-6">
        <section aria-labelledby="h-sacola" className="card p-4">
          <h2 id="h-sacola" className="text-base font-bold">Sua sacola</h2>
          <ul className="mt-2 divide-y divide-border">
            {!cart.hydrated && <li className="skeleton my-2 h-12" />}
            {cart.lines.map((l) => (
              <li key={l.key} className="flex items-start gap-3 py-3">
                <div className="min-w-0 flex-1">
                  <p className="font-semibold">{l.name}</p>
                  {l.optionLabels.length > 0 && <p className="text-xs text-muted">{l.optionLabels.join(", ")}</p>}
                  {l.notes && <p className="text-xs text-muted">Obs.: {l.notes}</p>}
                  <p className="mt-1 text-sm font-semibold">{formatBRL(l.unitPriceCents * l.quantity)}</p>
                </div>
                <div className="flex items-center rounded-full border border-border">
                  <button type="button" className="grid size-10 place-items-center" aria-label={`Tirar um ${l.name}`} onClick={() => cart.setQuantity(l.key, l.quantity - 1)}><Minus className="size-4" aria-hidden /></button>
                  <span className="w-5 text-center text-sm tabular-nums">{l.quantity}</span>
                  <button type="button" className="grid size-10 place-items-center" aria-label={`Mais um ${l.name}`} onClick={() => cart.setQuantity(l.key, l.quantity + 1)}><Plus className="size-4" aria-hidden /></button>
                </div>
              </li>
            ))}
          </ul>
          <Link href={`/${store.slug}`} className="mt-1 inline-flex min-h-11 items-center text-sm font-semibold text-link">+ Adicionar mais itens</Link>
        </section>

        <section aria-labelledby="h-receber" className="grid gap-3">
          <h2 id="h-receber" className="text-base font-bold">Como você quer receber?</h2>
          <div role="radiogroup" aria-label="Entrega ou retirada" className="grid grid-cols-2 gap-1 rounded-full bg-surface-sunken p-1">
            {([["delivery", "Entrega", Bike, store.deliveryEnabled], ["pickup", "Retirada", StoreIcon, store.pickupEnabled]] as const).map(([k, label, Icon, enabled]) => (
              <button key={k} type="button" role="radio" aria-checked={fulfillment === k} disabled={!enabled} onClick={() => setFulfillment(k)}
                className="flex min-h-11 items-center justify-center gap-2 rounded-full text-sm font-semibold text-muted aria-checked:bg-surface-raised aria-checked:text-fg">
                <Icon className="size-4" aria-hidden />{label}
              </button>
            ))}
          </div>

          {fulfillment === "delivery" ? (
            <div className="card grid gap-3 p-4">
              <label className="field"><span>Bairro</span>
                <select id="zone" className="input" value={zoneId} onChange={(e) => setZoneId(e.target.value)} {...inv("zone")}>
                  <option value="">Escolha o bairro</option>
                  {zones.map((z) => <option key={z.id} value={z.id}>{z.name} · entrega {formatBRL(z.feeCents)}</option>)}
                </select>
                {err("zone")}
              </label>
              <p className="-mt-1 text-xs text-muted">Não achou o seu bairro? A loja ainda não entrega aí. Escolha retirada.</p>
              <div className="grid grid-cols-[1fr_6rem] gap-2">
                <label className="field"><span>Rua</span><input id="street" name="street" className="input" autoComplete="address-line1" defaultValue={saved.street} {...inv("street")} />{err("street")}</label>
                <label className="field"><span>Número</span><input id="number" name="number" className="input" inputMode="numeric" defaultValue={saved.number} {...inv("number")} />{err("number")}</label>
              </div>
              <label className="field"><span>Complemento (opcional)</span><input id="complement" name="complement" className="input" placeholder="Apto, bloco, casa 2" defaultValue={saved.complement} /></label>
              <label className="field"><span>Ponto de referência (opcional)</span><input id="reference" name="reference" className="input" placeholder="Ex.: portão azul, ao lado da padaria" defaultValue={saved.reference} /></label>
              {!store.city && <label className="field"><span>Cidade</span><input id="city" name="city" className="input" autoComplete="address-level2" /></label>}
              <button type="button" onClick={locate} className="btn btn-outline btn-sm justify-self-start"><LocateFixed className="size-4" aria-hidden />{coords ? "Localização anexada" : "Anexar minha localização (ajuda o motoboy)"}</button>
              {locMsg && <p className="text-xs text-muted" role="status">{locMsg}</p>}
            </div>
          ) : (
            <div className="card p-4 text-sm">
              <p className="font-semibold">Retirar na loja</p>
              <p className="mt-1 text-muted">{store.pickupAddress ?? "Combine o local com a loja pelo WhatsApp."}</p>
            </div>
          )}
        </section>

        <section aria-labelledby="h-voce" className="card grid gap-3 p-4">
          <h2 id="h-voce" className="text-base font-bold">Seus dados</h2>
          <label className="field"><span>Nome</span><input id="name" name="name" className="input" autoComplete="name" defaultValue={saved.name} {...inv("name")} />{err("name")}</label>
          <label className="field"><span>WhatsApp</span><input id="phone" name="phone" className="input" inputMode="tel" autoComplete="tel" placeholder="(11) 98765-4321" defaultValue={saved.phone} {...inv("phone")} />{err("phone")}
            <small className="text-xs text-muted">Você recebe aqui cada etapa do pedido e o link para acompanhar a entrega.</small>
          </label>
        </section>

        <section aria-labelledby="h-pagar" className="grid gap-3">
          <h2 id="h-pagar" className="text-base font-bold">Pagamento</h2>
          <div role="radiogroup" aria-label="Forma de pagamento" className="grid gap-2">
            {store.paymentMethods.map((m) => {
              const { title, hint, Icon } = PAY_LABEL[m];
              return (
                <button key={m} type="button" role="radio" aria-checked={pay === m} onClick={() => setPay(m)}
                  className="card flex min-h-14 items-center gap-3 p-3 text-left aria-checked:border-2 aria-checked:border-accent-bright">
                  <Icon className="size-5 text-accent-bright" aria-hidden />
                  <span className="flex-1"><span className="block font-semibold">{title}{m === "pix" && pixTestMode && <span className="chip ml-2 bg-warning-soft text-warning">TESTE</span>}</span><span className="block text-xs text-muted">{hint}</span></span>
                  <span className={`size-5 rounded-full border-2 ${pay === m ? "border-accent-bright bg-accent-bright shadow-[inset_0_0_0_3px_var(--surface)]" : "border-border-input"}`} aria-hidden />
                </button>
              );
            })}
          </div>
          {pay === "cash" && (
            <div className="card grid gap-3 p-4">
              <label className="flex items-center gap-3 text-sm"><input type="checkbox" id="needChange" checked={needChange} onChange={(e) => setNeedChange(e.target.checked)} className="size-5 accent-[var(--accent)]" />Vou precisar de troco</label>
              {needChange && <label className="field"><span>Troco para quanto?</span><input id="change" name="change" className="input" inputMode="decimal" placeholder="Ex.: 100,00" {...inv("change")} />{err("change")}</label>}
            </div>
          )}
          {pay === "pix" && pixNeedsDocument && (
            <label className="field card p-4"><span>CPF (pedido pelo banco para gerar o Pix)</span><input id="doc" name="doc" className="input" inputMode="numeric" autoComplete="off" {...inv("doc")} />{err("doc")}</label>
          )}
        </section>

        <label className="field"><span>Observação para a loja (opcional)</span><textarea id="notes" name="notes" maxLength={300} rows={2} className="input min-h-0 resize-none" /></label>

        <section aria-labelledby="h-resumo" className="card grid gap-1.5 p-4 text-sm">
          <h2 id="h-resumo" className="sr-only">Resumo</h2>
          <p className="flex justify-between"><span className="text-muted">Subtotal</span><span>{formatBRL(cart.subtotalCents)}</span></p>
          <p className="flex justify-between"><span className="text-muted">Entrega</span><span>{fulfillment === "pickup" ? "Grátis (retirada)" : zone ? formatBRL(fee) : "Escolha o bairro"}</span></p>
          <p className="mt-1 flex justify-between font-display text-lg font-bold"><span>Total</span><span>{formatBRL(total)}</span></p>
          {belowMin && <p className="text-xs text-warning">Pedido mínimo da loja: {formatBRL(store.minOrderCents)}.</p>}
        </section>

        <p className="text-xs text-muted">Seu nome, WhatsApp e endereço são usados pela {store.name} para preparar, entregar e avisar sobre este pedido. Veja a <a className="underline" href="/privacidade" target="_blank">Política de privacidade</a>.</p>

        {formError && <p role="alert" className="rounded-md border border-danger/50 bg-danger-soft p-3 text-sm">{formError}</p>}

        <div className="fixed inset-x-0 bottom-0 z-20 border-t border-border bg-bg/95 px-4 pt-3 backdrop-blur" style={{ paddingBottom: "max(12px, env(safe-area-inset-bottom))" }}>
          <div className="mx-auto max-w-lg">
            <button className="btn btn-cta w-full justify-between" disabled={busy || !store.isOpen || belowMin || !cart.hydrated}>
              <span>{!store.isOpen ? "Loja fechada" : busy ? "Enviando…" : pay === "pix" ? "Fazer pedido e pagar" : "Fazer pedido"}</span>
              <span>{formatBRL(total)}</span>
            </button>
          </div>
        </div>
      </form>
    </main>
  );
}
