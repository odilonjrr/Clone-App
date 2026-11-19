"use client";
import { Check, Minus, Plus, X } from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";
import type { CartLine } from "@/lib/cart";
import { formatBRL } from "@/lib/money";
import { priceItem } from "@/lib/pricing";
import type { MenuItem } from "./menu-app";

export function ProductSheet({
  product, storeOpen, onClose, onAdd,
}: { product: MenuItem; storeOpen: boolean; onClose: () => void; onAdd: (line: Omit<CartLine, "key">) => void }) {
  const ref = useRef<HTMLDialogElement>(null);
  const [sel, setSel] = useState<Record<string, string[]>>({});
  const [qty, setQty] = useState(1);
  const [notes, setNotes] = useState("");
  const [tried, setTried] = useState(false);

  useEffect(() => {
    const d = ref.current;
    if (d && !d.open) d.showModal();
    // sem close() na limpeza: no modo estrito do React a limpeza roda logo após montar,
    // e close() dispararia onClose e fecharia a janela recém-aberta
  }, []);

  const result = useMemo(
    () => priceItem(product, { productId: product.id, quantity: qty, options: sel, notes }),
    [product, qty, sel, notes],
  );
  const previewUnit = useMemo(() => {
    // prévia mesmo com escolhas faltando: soma o que já foi escolhido
    let unit = product.price_cents;
    for (const g of product.groups) {
      const chosen = g.options.filter((o) => sel[g.id]?.includes(o.id));
      if (!chosen.length) continue;
      unit += g.pricing === "max" ? Math.max(...chosen.map((o) => o.price_cents)) : chosen.reduce((s, o) => s + o.price_cents, 0);
    }
    return unit;
  }, [product, sel]);

  function toggle(gid: string, oid: string, max: number) {
    setSel((s) => {
      const cur = s[gid] ?? [];
      if (max === 1) return { ...s, [gid]: cur[0] === oid ? [] : [oid] };
      if (cur.includes(oid)) return { ...s, [gid]: cur.filter((x) => x !== oid) };
      if (cur.length >= max) return s;
      return { ...s, [gid]: [...cur, oid] };
    });
  }

  function add() {
    setTried(true);
    if (!result.line || !storeOpen) return;
    onAdd({
      productId: product.id, name: product.name, image: product.image_url, quantity: qty, options: sel,
      optionLabels: product.groups.flatMap((g) => g.options.filter((o) => sel[g.id]?.includes(o.id)).map((o) => o.name)),
      unitPriceCents: result.line.unitPriceCents, notes: notes.trim() || undefined,
    });
  }

  return (
    <dialog ref={ref} onClose={onClose} aria-labelledby="ps-title"
      className="m-0 mt-auto max-h-[92dvh] w-full max-w-none overflow-hidden rounded-t-xl bg-surface p-0 text-fg backdrop:bg-black/70 sm:m-auto sm:max-w-lg sm:rounded-xl"
      style={{ boxShadow: "var(--shadow-pop)" }}>
      <div className="flex max-h-[92dvh] flex-col">
        <div className="relative grid h-40 shrink-0 place-items-center" style={{ background: "radial-gradient(circle at 50% 70%, #5A2810 0%, #241D18 70%)" }}>
          {product.image_url && <img src={product.image_url} alt="" className="size-28 object-contain drop-shadow-xl" />}
          <button onClick={() => ref.current?.close()} className="absolute right-3 top-3 grid size-11 place-items-center rounded-full bg-black/45 text-white" aria-label="Fechar">
            <X className="size-5" aria-hidden />
          </button>
        </div>
        <div className="flex-1 overflow-y-auto px-4 pb-4 pt-4">
          <h2 id="ps-title" className="text-xl font-bold">{product.name}</h2>
          {product.description && <p className="mt-1 text-sm text-muted">{product.description}</p>}

          {product.groups.map((g) => {
            const chosen = sel[g.id] ?? [];
            const missing = chosen.length < g.min_select;
            const full = chosen.length >= g.max_select;
            return (
              <fieldset key={g.id} className="mt-5">
                <legend className="flex w-full items-center justify-between gap-2 rounded-md bg-surface-raised px-3 py-2.5">
                  <span>
                    <span className="block text-sm font-bold">{g.name}</span>
                    <span className="block text-xs text-muted">
                      {g.max_select === 1 ? "Escolha 1" : g.min_select > 0 ? `Escolha de ${g.min_select} a ${g.max_select}` : `Escolha até ${g.max_select}`}
                      {g.pricing === "max" && " · cobra pelo mais caro"}
                    </span>
                  </span>
                  <span className="flex items-center gap-2">
                    <span className="text-xs tabular-nums text-muted" aria-live="polite">{chosen.length}/{g.max_select}</span>
                    {g.min_select > 0 && (
                      <span className={`chip ${missing ? "bg-warning-soft text-warning" : "bg-success-soft text-success"}`}>
                        {missing ? "OBRIGATÓRIO" : <><Check className="size-3" aria-hidden />OK</>}
                      </span>
                    )}
                  </span>
                </legend>
                {tried && missing && <p className="mt-1 text-xs text-danger" role="alert">Faltou escolher em &quot;{g.name}&quot;.</p>}
                <ul className="mt-1 divide-y divide-border">
                  {g.options.map((o) => {
                    const on = chosen.includes(o.id);
                    const disabled = !o.is_available || (!on && full && g.max_select > 1);
                    return (
                      <li key={o.id}>
                        <label className={`flex min-h-12 items-center gap-3 py-2 ${disabled ? "opacity-45" : "cursor-pointer"}`}>
                          <input type={g.max_select === 1 ? "radio" : "checkbox"} name={`g-${g.id}`} checked={on} disabled={disabled}
                            onChange={() => toggle(g.id, o.id, g.max_select)} onClick={() => { if (g.max_select === 1 && on) toggle(g.id, o.id, 1); }}
                            className="size-5 shrink-0 accent-[var(--accent)]" />
                          <span className="min-w-0 flex-1">
                            <span className="block text-sm font-medium">{o.name}</span>
                            {!o.is_available && <span className="text-xs text-muted">Em falta</span>}
                          </span>
                          {o.price_cents > 0 && <span className="text-sm text-muted">{g.pricing === "max" ? formatBRL(o.price_cents) : `+${formatBRL(o.price_cents)}`}</span>}
                        </label>
                      </li>
                    );
                  })}
                </ul>
              </fieldset>
            );
          })}

          <label className="field mt-5"><span>Alguma observação?</span>
            <textarea id="obs" value={notes} onChange={(e) => setNotes(e.target.value)} maxLength={200} rows={2}
              placeholder="Ex.: sem cebola, maionese à parte" className="input min-h-0 resize-none" />
          </label>
        </div>

        <div className="flex shrink-0 items-center gap-3 border-t border-border bg-surface px-4 py-3" style={{ paddingBottom: "max(12px, env(safe-area-inset-bottom))" }}>
          <div className="flex items-center rounded-full border border-border">
            <button className="grid size-11 place-items-center" onClick={() => setQty((n) => Math.max(1, n - 1))} aria-label="Diminuir quantidade" disabled={qty <= 1}><Minus className="size-4" aria-hidden /></button>
            <span className="w-6 text-center font-semibold tabular-nums" aria-live="polite" aria-label={`Quantidade ${qty}`}>{qty}</span>
            <button className="grid size-11 place-items-center" onClick={() => setQty((n) => Math.min(99, n + 1))} aria-label="Aumentar quantidade"><Plus className="size-4" aria-hidden /></button>
          </div>
          <button className="btn btn-cta flex-1 justify-between" onClick={add} disabled={!storeOpen || !product.is_available}>
            <span>{storeOpen ? "Adicionar" : "Loja fechada"}</span>
            <span>{formatBRL(previewUnit * qty)}</span>
          </button>
        </div>
      </div>
    </dialog>
  );
}
