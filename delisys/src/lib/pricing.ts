/**
 * Cálculo do preço no servidor. O navegador nunca manda preço: manda o que o
 * cliente escolheu, e o total é refeito aqui com o cardápio do banco.
 */
export type MenuOption = { id: string; name: string; price_cents: number; is_available: boolean };
export type MenuGroup = {
  id: string; name: string; min_select: number; max_select: number;
  pricing: "sum" | "max"; options: MenuOption[];
};
export type MenuProduct = {
  id: string; name: string; price_cents: number; is_available: boolean; groups: MenuGroup[];
};
export type CartItemInput = {
  productId: string;
  quantity: number;
  options: Record<string, string[]>; // groupId → optionIds
  notes?: string;
};
export type PricedLine = {
  productId: string; name: string; quantity: number; unitPriceCents: number; totalCents: number;
  notes?: string; options: { groupName: string; name: string; priceCents: number }[];
};
export type PriceResult =
  | { ok: true; lines: PricedLine[]; subtotalCents: number }
  | { ok: false; errors: string[] };

/** Preço de um grupo: 'sum' soma as escolhidas; 'max' cobra a mais cara (meio a meio). */
export function groupPrice(group: MenuGroup, chosen: MenuOption[]) {
  if (chosen.length === 0) return 0;
  if (group.pricing === "max") return Math.max(...chosen.map((o) => o.price_cents));
  return chosen.reduce((s, o) => s + o.price_cents, 0);
}

export function priceItem(product: MenuProduct, item: CartItemInput): { line?: PricedLine; errors: string[] } {
  const errors: string[] = [];
  if (!product.is_available) errors.push(`"${product.name}" está em falta no momento.`);
  if (!Number.isInteger(item.quantity) || item.quantity < 1 || item.quantity > 99)
    errors.push(`Quantidade inválida para "${product.name}".`);
  const known = new Set(product.groups.map((g) => g.id));
  for (const gid of Object.keys(item.options ?? {}))
    if (!known.has(gid)) errors.push(`Opção que não existe mais em "${product.name}". Abra o item de novo.`);

  let unit = product.price_cents;
  const opts: PricedLine["options"] = [];
  for (const g of product.groups) {
    const ids = [...new Set(item.options?.[g.id] ?? [])];
    const chosen = ids.map((id) => g.options.find((o) => o.id === id));
    if (chosen.some((o) => !o)) { errors.push(`Uma opção de "${g.name}" não existe mais. Abra o item de novo.`); continue; }
    const valid = chosen as MenuOption[];
    const gone = valid.filter((o) => !o.is_available);
    if (gone.length) errors.push(`"${gone[0].name}" está em falta.`);
    if (valid.length < g.min_select)
      errors.push(g.min_select === 1 ? `Escolha uma opção em "${g.name}".` : `Escolha pelo menos ${g.min_select} em "${g.name}".`);
    if (valid.length > g.max_select) errors.push(`Escolha no máximo ${g.max_select} em "${g.name}".`);
    const gp = groupPrice(g, valid);
    unit += gp;
    for (const o of valid)
      opts.push({ groupName: g.name, name: o.name, priceCents: g.pricing === "max" ? 0 : o.price_cents });
  }
  if (errors.length) return { errors };
  return {
    errors,
    line: {
      productId: product.id, name: product.name, quantity: item.quantity, unitPriceCents: unit,
      totalCents: unit * item.quantity, notes: item.notes?.trim().slice(0, 200) || undefined, options: opts,
    },
  };
}

export function priceCart(products: Map<string, MenuProduct>, items: CartItemInput[]): PriceResult {
  if (!items.length) return { ok: false, errors: ["Sua sacola está vazia."] };
  if (items.length > 50) return { ok: false, errors: ["Pedido grande demais. Fale com a loja."] };
  const errors: string[] = [];
  const lines: PricedLine[] = [];
  for (const it of items) {
    const p = products.get(it.productId);
    if (!p) { errors.push("Um item da sacola saiu do cardápio. Remova e adicione de novo."); continue; }
    const r = priceItem(p, it);
    errors.push(...r.errors);
    if (r.line) lines.push(r.line);
  }
  if (errors.length) return { ok: false, errors: [...new Set(errors)] };
  return { ok: true, lines, subtotalCents: lines.reduce((s, l) => s + l.totalCents, 0) };
}
