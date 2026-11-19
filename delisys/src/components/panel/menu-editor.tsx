"use client";
import { ArrowDown, ArrowUp, Pencil, Plus, Trash2, X } from "lucide-react";
import { useCallback, useEffect, useRef, useState } from "react";
import { api } from "@/lib/client-api";
import { formatBRL, parseBRL } from "@/lib/money";

type Opt = { id?: string; name: string; description?: string | null; price_cents: number; is_available: boolean };
type Group = { id?: string; name: string; min_select: number; max_select: number; pricing: "sum" | "max"; options: Opt[] };
type Product = {
  id: string; category_id: string; name: string; description: string | null; image_url: string | null; price_cents: number;
  compare_at_cents: number | null; badge: string | null; is_available: boolean; groups: Group[];
};
type Category = { id: string; name: string; is_active: boolean };
type Menu = { categories: Category[]; products: Product[] };

export function MenuEditor() {
  const [menu, setMenu] = useState<Menu | null>(null);
  const [editing, setEditing] = useState<Product | "new" | null>(null);
  const [newCat, setNewCat] = useState("");
  const [msg, setMsg] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    try { setMenu(await api<Menu>("/api/painel/cardapio")); } catch (e) { setError((e as Error).message); }
  }, []);
  useEffect(() => { load(); }, [load]);

  async function call(fn: () => Promise<unknown>, ok?: string) {
    setError(null); setMsg(null);
    try { await fn(); if (ok) setMsg(ok); await load(); } catch (e) { setError((e as Error).message); }
  }

  if (!menu) return error ? <p role="alert" className="text-danger">{error}</p> : <div className="skeleton h-96 max-w-4xl" />;

  return (
    <div className="grid max-w-4xl gap-5">
      <header className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold">Cardápio</h1>
          <p className="text-sm text-muted">Mudanças aparecem no cardápio na hora.</p>
        </div>
        <button className="btn btn-primary" disabled={!menu.categories.length} onClick={() => setEditing("new")}><Plus className="size-4" aria-hidden />Novo produto</button>
      </header>

      <form className="card flex flex-wrap items-end gap-2 p-3" onSubmit={(e) => { e.preventDefault(); if (newCat.trim()) call(() => api("/api/painel/cardapio/categorias", { body: { name: newCat } }).then(() => setNewCat("")), "Categoria criada."); }}>
        <label className="field min-w-48 flex-1"><span>Nova categoria</span><input className="input" id="new-cat" value={newCat} onChange={(e) => setNewCat(e.target.value)} placeholder="Ex.: Sobremesas" /></label>
        <button className="btn btn-outline">Criar categoria</button>
      </form>

      {error && <p role="alert" className="text-sm text-danger">{error}</p>}
      {msg && <p role="status" className="text-sm text-success">{msg}</p>}

      {menu.categories.length === 0 && <p className="card p-6 text-center text-muted">Crie a primeira categoria para começar o cardápio.</p>}

      {menu.categories.map((c, ci) => {
        const items = menu.products.filter((p) => p.category_id === c.id);
        return (
          <section key={c.id} className={`card p-4 ${c.is_active ? "" : "opacity-70"}`} aria-labelledby={`cat-${c.id}`}>
            <div className="flex flex-wrap items-center justify-between gap-2">
              <h2 id={`cat-${c.id}`} className="text-lg font-bold">{c.name}{!c.is_active && <span className="chip ml-2 bg-surface-sunken text-muted">OCULTA</span>}</h2>
              <div className="flex flex-wrap gap-1">
                <button className="btn btn-sm btn-outline" aria-label={`Subir ${c.name}`} disabled={ci === 0} onClick={() => call(() => api(`/api/painel/cardapio/categorias/${c.id}`, { method: "PATCH", body: { move: "up" } }))}><ArrowUp className="size-4" aria-hidden /></button>
                <button className="btn btn-sm btn-outline" aria-label={`Descer ${c.name}`} disabled={ci === menu.categories.length - 1} onClick={() => call(() => api(`/api/painel/cardapio/categorias/${c.id}`, { method: "PATCH", body: { move: "down" } }))}><ArrowDown className="size-4" aria-hidden /></button>
                <button className="btn btn-sm btn-outline" onClick={() => { const name = prompt("Novo nome da categoria:", c.name); if (name && name.trim().length >= 2) call(() => api(`/api/painel/cardapio/categorias/${c.id}`, { method: "PATCH", body: { name } }), "Categoria renomeada."); }}>Renomear</button>
                <button className="btn btn-sm btn-outline" onClick={() => call(() => api(`/api/painel/cardapio/categorias/${c.id}`, { method: "PATCH", body: { isActive: !c.is_active } }), c.is_active ? "Categoria oculta do cardápio." : "Categoria visível de novo.")}>{c.is_active ? "Ocultar" : "Mostrar"}</button>
                {items.length === 0 && <button className="btn btn-sm btn-danger" onClick={() => { if (confirm(`Excluir a categoria ${c.name}?`)) call(() => api(`/api/painel/cardapio/categorias/${c.id}`, { method: "DELETE" }), "Categoria excluída."); }}>Excluir</button>}
              </div>
            </div>
            {items.length === 0 ? <p className="mt-3 text-sm text-muted">Nenhum produto nesta categoria.</p> : (
              <ul className="mt-2 divide-y divide-border">
                {items.map((p) => (
                  <li key={p.id} className="flex flex-wrap items-center gap-3 py-2.5">
                    {p.image_url ? <img src={p.image_url} alt="" className="size-10 rounded-md object-contain" /> : <span className="size-10 rounded-md bg-surface-sunken" aria-hidden />}
                    <div className="min-w-0 flex-1">
                      <p className="font-semibold">{p.name}</p>
                      <p className="text-xs text-muted">{formatBRL(p.price_cents)}{p.groups.length ? ` · ${p.groups.length} ${p.groups.length === 1 ? "grupo de opções" : "grupos de opções"}` : ""}</p>
                    </div>
                    <label className="flex items-center gap-2 text-xs font-semibold">
                      <input type="checkbox" className="size-5 accent-[var(--success)]" checked={p.is_available}
                        onChange={(e) => call(() => api(`/api/painel/cardapio/produtos/${p.id}`, { method: "PATCH", body: { isAvailable: e.target.checked } }), e.target.checked ? `${p.name} disponível.` : `${p.name} marcado como em falta.`)} />
                      {p.is_available ? "Disponível" : "Em falta"}
                    </label>
                    <button className="btn btn-sm btn-outline" onClick={() => setEditing(p)}><Pencil className="size-4" aria-hidden />Editar</button>
                  </li>
                ))}
              </ul>
            )}
          </section>
        );
      })}

      {editing && (
        <ProductForm
          product={editing === "new" ? null : editing}
          categories={menu.categories}
          onClose={() => setEditing(null)}
          onSaved={async (text) => { setEditing(null); setMsg(text); await load(); }}
          onRemoved={async () => { setEditing(null); setMsg("Produto excluído."); await load(); }}
        />
      )}
    </div>
  );
}

const money = (c: number) => (c / 100).toFixed(2).replace(".", ",");

function ProductForm({ product, categories, onClose, onSaved, onRemoved }: {
  product: Product | null; categories: Category[]; onClose: () => void; onSaved: (msg: string) => void; onRemoved: () => void;
}) {
  const ref = useRef<HTMLDialogElement>(null);
  useEffect(() => { const d = ref.current; if (d && !d.open) d.showModal(); }, []);
  const [f, setF] = useState({
    categoryId: product?.category_id ?? categories[0]?.id ?? "",
    name: product?.name ?? "", description: product?.description ?? "", imageUrl: product?.image_url ?? "",
    price: money(product?.price_cents ?? 0), compareAt: product?.compare_at_cents ? money(product.compare_at_cents) : "",
    badge: product?.badge ?? "", isAvailable: product?.is_available ?? true,
  });
  const [groups, setGroups] = useState(() => (product?.groups ?? []).map((g) => ({ ...g, options: g.options.map((o) => ({ ...o, price: money(o.price_cents) })) })));
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  type G = (typeof groups)[number];
  const setG = (i: number, patch: Partial<G>) => setGroups(groups.map((g, k) => (k === i ? { ...g, ...patch } : g)));

  async function save(e: React.FormEvent) {
    e.preventDefault();
    const price = parseBRL(f.price);
    if (price === null) { setError("Preço inválido. Use o formato 29,90."); return; }
    const compareAt = f.compareAt ? parseBRL(f.compareAt) : null;
    for (const g of groups) for (const o of g.options) if (parseBRL(o.price) === null) { setError(`Preço inválido na opção "${o.name || "sem nome"}".`); return; }
    setBusy(true); setError(null);
    const body = {
      categoryId: f.categoryId, name: f.name, description: f.description || null, imageUrl: f.imageUrl || null,
      priceCents: price, compareAtCents: compareAt, badge: f.badge || null, isAvailable: f.isAvailable,
      groups: groups.map((g) => ({ name: g.name, minSelect: g.min_select, maxSelect: g.max_select, pricing: g.pricing,
        options: g.options.map((o) => ({ name: o.name, description: o.description || null, priceCents: parseBRL(o.price)!, isAvailable: o.is_available })) })),
    };
    try {
      if (product) await api(`/api/painel/cardapio/produtos/${product.id}`, { method: "PUT", body });
      else await api("/api/painel/cardapio/produtos", { body });
      onSaved(product ? "Produto atualizado." : "Produto criado.");
    } catch (err) { setError((err as Error).message); setBusy(false); }
  }

  return (
    <dialog ref={ref} onClose={onClose} aria-labelledby="pf-title" className="m-auto max-h-[92dvh] w-[min(720px,calc(100%-24px))] overflow-y-auto rounded-xl bg-surface p-0 text-fg backdrop:bg-black/50" style={{ boxShadow: "var(--shadow-pop)" }}>
      <form onSubmit={save} className="grid gap-4 p-5" noValidate>
        <div className="flex items-center justify-between">
          <h2 id="pf-title" className="text-xl font-bold">{product ? "Editar produto" : "Novo produto"}</h2>
          <button type="button" className="grid size-10 place-items-center rounded-full hover:bg-surface-sunken" aria-label="Fechar" onClick={() => ref.current?.close()}><X className="size-5" aria-hidden /></button>
        </div>
        <div className="grid gap-3 sm:grid-cols-2">
          <label className="field sm:col-span-2"><span>Nome</span><input className="input" id="pf-name" value={f.name} onChange={(e) => setF({ ...f, name: e.target.value })} required /></label>
          <label className="field sm:col-span-2"><span>Descrição</span><textarea className="input min-h-0" id="pf-desc" rows={2} value={f.description} onChange={(e) => setF({ ...f, description: e.target.value })} /></label>
          <label className="field"><span>Categoria</span><select className="input" id="pf-cat" value={f.categoryId} onChange={(e) => setF({ ...f, categoryId: e.target.value })}>{categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}</select></label>
          <label className="field"><span>Preço base (R$)</span><input className="input" id="pf-price" inputMode="decimal" value={f.price} onChange={(e) => setF({ ...f, price: e.target.value })} />
            <small className="text-xs text-muted">Pizza por sabor: deixe 0,00 e coloque os preços nos sabores.</small></label>
          <label className="field"><span>Preço &quot;de&quot; riscado (opcional)</span><input className="input" id="pf-compare" inputMode="decimal" value={f.compareAt} onChange={(e) => setF({ ...f, compareAt: e.target.value })} placeholder="Ex.: 69,90" /></label>
          <label className="field"><span>Selo</span><select className="input" id="pf-badge" value={f.badge} onChange={(e) => setF({ ...f, badge: e.target.value })}><option value="">Nenhum</option><option value="favorito">Mais pedidos (destaque)</option><option value="promo">Promoção</option></select></label>
          <label className="field sm:col-span-2"><span>Link da foto (opcional)</span><input className="input" id="pf-img" value={f.imageUrl} onChange={(e) => setF({ ...f, imageUrl: e.target.value })} placeholder="https://…" /></label>
          <label className="flex items-center gap-2 text-sm"><input type="checkbox" className="size-5 accent-[var(--success)]" checked={f.isAvailable} onChange={(e) => setF({ ...f, isAvailable: e.target.checked })} />Disponível</label>
        </div>

        <section className="grid gap-3">
          <h3 className="text-base font-bold">Opções e adicionais</h3>
          {groups.length === 0 && <p className="text-sm text-muted">Sem opções. Use grupos para tamanho, ponto da carne, adicionais ou sabores de pizza.</p>}
          {groups.map((g, gi) => (
            <fieldset key={gi} className="grid gap-2 rounded-md border border-border p-3">
              <legend className="sr-only">Grupo {gi + 1}</legend>
              <div className="grid gap-2 sm:grid-cols-[1fr_5rem_5rem]">
                <label className="field"><span>Nome do grupo</span><input className="input" value={g.name} onChange={(e) => setG(gi, { name: e.target.value })} placeholder="Ex.: Sabores" /></label>
                <label className="field"><span>Mínimo</span><input className="input" type="number" min={0} value={g.min_select} onChange={(e) => setG(gi, { min_select: Number(e.target.value) })} /></label>
                <label className="field"><span>Máximo</span><input className="input" type="number" min={1} value={g.max_select} onChange={(e) => setG(gi, { max_select: Number(e.target.value) })} /></label>
              </div>
              <label className="flex items-center gap-2 text-sm"><input type="checkbox" className="size-5 accent-[var(--accent)]" checked={g.pricing === "max"} onChange={(e) => setG(gi, { pricing: e.target.checked ? "max" : "sum" })} />Cobrar só a opção mais cara (pizza meio a meio)</label>
              <ul className="grid gap-2">
                {g.options.map((o, oi) => (
                  <li key={oi} className="grid grid-cols-[1fr_6rem_auto_auto] items-center gap-2">
                    <input className="input" aria-label="Nome da opção" value={o.name} onChange={(e) => setG(gi, { options: g.options.map((x, k) => (k === oi ? { ...x, name: e.target.value } : x)) })} placeholder="Nome" />
                    <input className="input" aria-label="Preço da opção" inputMode="decimal" value={o.price} onChange={(e) => setG(gi, { options: g.options.map((x, k) => (k === oi ? { ...x, price: e.target.value } : x)) })} />
                    <label className="flex items-center gap-1 text-xs"><input type="checkbox" className="size-4 accent-[var(--success)]" checked={o.is_available} onChange={(e) => setG(gi, { options: g.options.map((x, k) => (k === oi ? { ...x, is_available: e.target.checked } : x)) })} />Tem</label>
                    <button type="button" className="grid size-9 place-items-center rounded-md hover:bg-surface-sunken" aria-label={`Remover opção ${o.name}`} onClick={() => setG(gi, { options: g.options.filter((_, k) => k !== oi) })}><Trash2 className="size-4" aria-hidden /></button>
                  </li>
                ))}
              </ul>
              <div className="flex flex-wrap gap-2">
                <button type="button" className="btn btn-sm btn-outline" onClick={() => setG(gi, { options: [...g.options, { name: "", price: "0,00", price_cents: 0, is_available: true }] })}><Plus className="size-4" aria-hidden />Opção</button>
                <button type="button" className="btn btn-sm btn-danger" onClick={() => setGroups(groups.filter((_, k) => k !== gi))}>Remover grupo</button>
              </div>
            </fieldset>
          ))}
          <button type="button" className="btn btn-sm btn-outline justify-self-start" onClick={() => setGroups([...groups, { name: "", min_select: 0, max_select: 1, pricing: "sum", options: [{ name: "", price: "0,00", price_cents: 0, is_available: true }] }])}><Plus className="size-4" aria-hidden />Grupo de opções</button>
        </section>

        {error && <p role="alert" className="text-sm text-danger">{error}</p>}
        <div className="flex flex-wrap justify-between gap-2 border-t border-border pt-4">
          {product ? <button type="button" className="btn btn-danger" onClick={async () => {
            if (!confirm(`Excluir ${product.name}? Pedidos antigos continuam com o nome e o preço.`)) return;
            try { await api(`/api/painel/cardapio/produtos/${product.id}`, { method: "DELETE" }); onRemoved(); } catch (err) { setError((err as Error).message); }
          }}><Trash2 className="size-4" aria-hidden />Excluir</button> : <span />}
          <div className="flex gap-2">
            <button type="button" className="btn btn-outline" onClick={() => ref.current?.close()}>Cancelar</button>
            <button className="btn btn-primary" disabled={busy}>{busy ? "Salvando…" : "Salvar"}</button>
          </div>
        </div>
      </form>
    </dialog>
  );
}
