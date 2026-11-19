"use client";
import Link from "next/link";
import { ArrowRight, Clock, MapPin, MessageCircle, Plus, Search, ShoppingBag, Bike } from "lucide-react";
import { useMemo, useState } from "react";
import { useCart } from "@/lib/cart";
import { formatBRL } from "@/lib/money";
import type { MenuGroup } from "@/lib/pricing";
import { ProductSheet } from "./product-sheet";

export type MenuStore = {
  slug: string; name: string; isOpen: boolean; district: string | null; city: string | null;
  minOrderCents: number; etaMin: number; etaMax: number; whatsapp: string | null; deliveryEnabled: boolean;
  minFeeCents: number | null;
};
export type MenuCategory = { id: string; name: string };
export type MenuItem = {
  id: string; category_id: string; name: string; description: string | null; image_url: string | null;
  price_cents: number; compare_at_cents: number | null; badge: string | null; is_available: boolean; groups: MenuGroup[];
};

/** "A partir de" quando o preço depende de escolhas obrigatórias (ex.: sabor da pizza). */
export function displayPrice(p: MenuItem) {
  let min = p.price_cents;
  let from = false;
  for (const g of p.groups) {
    const prices = g.options.filter((o) => o.is_available).map((o) => o.price_cents);
    if (prices.length && g.min_select > 0) {
      const sorted = [...prices].sort((a, b) => a - b);
      min += g.pricing === "max" ? sorted[0] : sorted.slice(0, g.min_select).reduce((s, x) => s + x, 0);
    }
    if (prices.some((x) => x > 0)) from = true;
  }
  return { cents: min, from };
}

export function MenuApp({ store, categories, products }: { store: MenuStore; categories: MenuCategory[]; products: MenuItem[] }) {
  const cart = useCart(store.slug);
  const [open, setOpen] = useState<MenuItem | null>(null);
  const [query, setQuery] = useState("");
  const [activeCat, setActiveCat] = useState(categories[0]?.id);

  const filtered = useMemo(() => {
    const s = query.trim().toLowerCase();
    return s ? products.filter((p) => `${p.name} ${p.description ?? ""}`.toLowerCase().includes(s)) : products;
  }, [products, query]);
  const favorites = products.filter((p) => p.badge === "favorito" && p.is_available).slice(0, 8);
  const sections = categories.map((c) => ({ ...c, items: filtered.filter((p) => p.category_id === c.id) })).filter((c) => c.items.length);

  return (
    <div className="min-h-dvh" style={{ background: "var(--gradient-page), var(--bg)", backgroundRepeat: "no-repeat" }}>
      <div className="mx-auto max-w-2xl pb-40">
        <header className="px-4 pt-6">
          <div className="flex items-center gap-3">
            <div className="grid size-16 shrink-0 place-items-center rounded-full text-2xl font-extrabold"
              style={{ background: "radial-gradient(circle at 30% 30%, #FFB547, #E2560C 70%)", boxShadow: "0 0 0 3px var(--bg), 0 0 0 5px var(--accent), var(--shadow-glow)" }}
              aria-hidden>
              {store.name.slice(0, 1)}
            </div>
            <div className="min-w-0">
              {store.isOpen ? (
                <p className="flex items-center gap-1.5 text-xs font-semibold text-success">
                  <span className="size-2 rounded-full bg-success" style={{ boxShadow: "var(--shadow-glow-success)" }} />Aberto agora
                </p>
              ) : (
                <p className="text-xs font-semibold text-warning">Fechado no momento</p>
              )}
              <h1 className="truncate text-xl font-bold">{store.name}</h1>
              {(store.district || store.city) && (
                <p className="mt-0.5 flex items-center gap-1 text-xs text-muted"><MapPin className="size-3.5 text-highlight" aria-hidden />{[store.district, store.city].filter(Boolean).join(", ")}</p>
              )}
            </div>
          </div>

          <dl className="mt-4 grid grid-cols-3 gap-2 text-xs">
            <div className="glass rounded-md p-2.5"><dt className="text-muted">Entrega</dt><dd className="mt-0.5 flex items-center gap-1 font-semibold"><Clock className="size-3.5" aria-hidden />{store.etaMin}–{store.etaMax} min</dd></div>
            <div className="glass rounded-md p-2.5"><dt className="text-muted">Pedido mínimo</dt><dd className="mt-0.5 font-semibold">{store.minOrderCents ? formatBRL(store.minOrderCents) : "Sem mínimo"}</dd></div>
            <div className="glass rounded-md p-2.5"><dt className="text-muted">Taxa</dt><dd className="mt-0.5 flex items-center gap-1 font-semibold"><Bike className="size-3.5" aria-hidden />{store.minFeeCents == null ? "Só retirada" : `desde ${formatBRL(store.minFeeCents)}`}</dd></div>
          </dl>

          {!store.isOpen && (
            <p role="status" className="mt-4 rounded-md border border-warning/40 bg-warning-soft p-3 text-sm">
              A loja não está recebendo pedidos agora. Você pode olhar o cardápio e voltar mais tarde.
            </p>
          )}

          <label className="glass mt-4 flex h-12 items-center gap-2 rounded-full px-4">
            <Search className="size-5 text-muted" aria-hidden />
            <span className="sr-only">Buscar no cardápio</span>
            <input id="busca" type="search" value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Buscar no cardápio"
              className="h-full flex-1 bg-transparent text-sm outline-none placeholder:text-muted" />
          </label>
        </header>

        {!query && (
          <nav aria-label="Categorias" className="sticky top-0 z-10 mt-4 flex gap-2 overflow-x-auto bg-bg/85 px-4 py-2 backdrop-blur [scrollbar-width:none]">
            {categories.map((c) => (
              <a key={c.id} href={`#cat-${c.id}`} onClick={() => setActiveCat(c.id)}
                aria-current={activeCat === c.id ? "true" : undefined}
                className="shrink-0 rounded-full border border-border px-4 py-2 text-sm font-semibold aria-[current=true]:border-transparent aria-[current=true]:text-white"
                style={activeCat === c.id ? { background: "var(--gradient-accent)", boxShadow: "var(--shadow-glow)" } : undefined}>
                {c.name}
              </a>
            ))}
          </nav>
        )}

        {!query && favorites.length > 0 && (
          <section aria-labelledby="fav" className="mt-4">
            <h2 id="fav" className="px-4 text-lg font-bold">Mais pedidos 🔥</h2>
            <div className="mt-3 flex gap-3 overflow-x-auto px-4 pb-1 [scrollbar-width:none]">
              {favorites.map((p) => {
                const price = displayPrice(p);
                return (
                  <button key={p.id} onClick={() => setOpen(p)} className="glass w-36 shrink-0 overflow-hidden rounded-lg text-left" style={{ boxShadow: "var(--shadow-card)" }}>
                    <div className="grid h-24 place-items-center" style={{ background: "radial-gradient(circle at 50% 70%, #5A2810 0%, #241D18 70%)" }}>
                      {p.image_url ? <img src={p.image_url} alt="" className="size-16 object-contain drop-shadow-lg" /> : <ShoppingBag className="size-8 text-muted" aria-hidden />}
                    </div>
                    <div className="p-2.5">
                      <p className="line-clamp-2 min-h-9 text-[13px] font-semibold leading-tight">{p.name}</p>
                      <div className="mt-1.5 flex items-center justify-between">
                        <span className="leading-tight">{price.from && <small className="block font-sans text-[10px] font-normal text-muted">a partir de</small>}<span className="whitespace-nowrap font-display text-sm font-bold">{formatBRL(price.cents)}</span></span>
                        <span className="grid size-7 place-items-center rounded-lg bg-add text-on-add"><Plus className="size-4" strokeWidth={3} aria-hidden /></span>
                      </div>
                    </div>
                  </button>
                );
              })}
            </div>
          </section>
        )}

        <div className="mt-2">
          {sections.length === 0 && (
            <p className="px-4 py-10 text-center text-muted">{query ? `Nada encontrado para "${query}".` : "O cardápio desta loja ainda está vazio."}</p>
          )}
          {sections.map((c) => (
            <section key={c.id} id={`cat-${c.id}`} aria-labelledby={`h-${c.id}`} className="scroll-mt-16 px-4 pt-6">
              <h2 id={`h-${c.id}`} className="text-lg font-bold">{c.name}</h2>
              <ul className="mt-2 divide-y divide-border">
                {c.items.map((p) => {
                  const price = displayPrice(p);
                  return (
                    <li key={p.id}>
                      <button onClick={() => setOpen(p)} disabled={!p.is_available} className="flex w-full items-center gap-3 py-3.5 text-left disabled:opacity-50">
                        <div className="min-w-0 flex-1">
                          <p className="font-semibold">{p.name}{p.badge === "promo" && <span className="chip ml-2 bg-danger-solid align-middle text-on-danger">PROMO</span>}</p>
                          {p.description && <p className="mt-0.5 line-clamp-2 text-[13px] text-muted">{p.description}</p>}
                          <p className="mt-1.5 text-sm">
                            {!p.is_available ? <span className="font-semibold text-muted">Em falta</span> : (
                              <>
                                {p.compare_at_cents && <s className="mr-1.5 text-xs text-muted">{formatBRL(p.compare_at_cents)}</s>}
                                {price.from && <span className="text-xs text-muted">A partir de </span>}
                                <span className="font-display font-bold text-highlight">{formatBRL(price.cents)}</span>
                              </>
                            )}
                          </p>
                        </div>
                        {p.image_url && (
                          <div className="grid size-20 shrink-0 place-items-center rounded-md" style={{ background: "radial-gradient(circle at 50% 70%, #5A2810 0%, #241D18 70%)" }}>
                            <img src={p.image_url} alt="" className="size-14 object-contain" />
                          </div>
                        )}
                      </button>
                    </li>
                  );
                })}
              </ul>
            </section>
          ))}
        </div>
        <footer className="px-4 pt-10 text-center text-xs text-muted">
          Cardápio digital Delisys · <a className="underline" href="/privacidade">Política de privacidade</a>
        </footer>
      </div>

      {open && (
        <ProductSheet product={open} storeOpen={store.isOpen} onClose={() => setOpen(null)}
          onAdd={(line) => { cart.add(line); setOpen(null); }} />
      )}

      <div className="fixed inset-x-0 bottom-0 z-20 px-4" style={{ paddingBottom: "max(12px, env(safe-area-inset-bottom))" }}>
        <div className="mx-auto flex max-w-2xl items-center gap-2">
          {store.whatsapp && (
            <a className="btn glass size-[52px] shrink-0 px-0" aria-label="Tirar dúvidas no WhatsApp da loja"
              href={`https://wa.me/${store.whatsapp}`} target="_blank" rel="noreferrer">
              <MessageCircle className="size-5" aria-hidden />
            </a>
          )}
          {cart.hydrated && cart.count > 0 ? (
            <Link href={`/${store.slug}/checkout`} className="btn btn-cta flex-1 justify-between">
              <span className="flex items-center gap-2"><ShoppingBag className="size-5" aria-hidden />Ver sacola <small className="font-sans text-xs font-semibold opacity-90">({cart.count})</small></span>
              <span className="flex items-center gap-1">{formatBRL(cart.subtotalCents)}<ArrowRight className="size-5" aria-hidden /></span>
            </Link>
          ) : null}
        </div>
      </div>
    </div>
  );
}
