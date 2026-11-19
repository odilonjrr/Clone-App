import "server-only";
import { z } from "zod";
import { HttpError } from "./auth";
import { one, tx, type Queryable } from "./db";

export const ProductInput = z.object({
  categoryId: z.string().uuid(),
  name: z.string().trim().min(2, "Informe o nome do produto.").max(80),
  description: z.string().trim().max(400).nullable(),
  imageUrl: z.string().trim().max(500).refine((s) => s === "" || /^(https:\/\/|\/)/.test(s), "A imagem precisa ser um link https://").nullable(),
  priceCents: z.number().int().min(0).max(1_000_000),
  compareAtCents: z.number().int().min(0).max(1_000_000).nullable(),
  badge: z.enum(["favorito", "promo"]).nullable(),
  isAvailable: z.boolean(),
  groups: z.array(z.object({
    name: z.string().trim().min(1, "Dê um nome a cada grupo de opções.").max(60),
    minSelect: z.number().int().min(0).max(20),
    maxSelect: z.number().int().min(1).max(20),
    pricing: z.enum(["sum", "max"]),
    options: z.array(z.object({
      name: z.string().trim().min(1, "Toda opção precisa de nome.").max(80),
      description: z.string().trim().max(200).nullable().optional(),
      priceCents: z.number().int().min(0).max(1_000_000),
      isAvailable: z.boolean(),
    })).min(1, "Cada grupo precisa de pelo menos uma opção.").max(60),
  }).refine((g) => g.maxSelect >= g.minSelect, "O máximo de um grupo não pode ser menor que o mínimo.")
    .refine((g) => g.minSelect <= g.options.length, "O mínimo de um grupo não pode passar do número de opções.")).max(15),
});
export type ProductInput = z.infer<typeof ProductInput>;

async function assertCategory(t: Queryable, storeId: string, categoryId: string) {
  const r = await t.query(`select 1 from categories where id = $1 and store_id = $2`, [categoryId, storeId]);
  if (!r.length) throw new HttpError(400, "Categoria não encontrada.");
}

async function writeGroups(t: Queryable, productId: string, groups: ProductInput["groups"]) {
  await t.query(`delete from option_groups where product_id = $1`, [productId]);
  let gi = 0;
  for (const g of groups) {
    const [row] = await t.query<{ id: string }>(
      `insert into option_groups (product_id, name, min_select, max_select, pricing, position) values ($1,$2,$3,$4,$5,$6) returning id`,
      [productId, g.name, g.minSelect, g.maxSelect, g.pricing, gi++]);
    let oi = 0;
    for (const o of g.options)
      await t.query(`insert into options (group_id, name, description, price_cents, is_available, position) values ($1,$2,$3,$4,$5,$6)`,
        [row.id, o.name, o.description ?? null, o.priceCents, o.isAvailable, oi++]);
  }
}

export async function createProduct(storeId: string, p: ProductInput) {
  return tx(async (t) => {
    await assertCategory(t, storeId, p.categoryId);
    const [{ pos }] = await t.query<{ pos: number }>(`select coalesce(max(position), -1) + 1 as pos from products where category_id = $1`, [p.categoryId]);
    const [row] = await t.query<{ id: string }>(
      `insert into products (store_id, category_id, name, description, image_url, price_cents, compare_at_cents, badge, is_available, position)
       values ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10) returning id`,
      [storeId, p.categoryId, p.name, p.description || null, p.imageUrl || null, p.priceCents, p.compareAtCents, p.badge, p.isAvailable, pos]);
    await writeGroups(t, row.id, p.groups);
    return row.id;
  });
}

export async function updateProduct(storeId: string, id: string, p: ProductInput) {
  return tx(async (t) => {
    await assertCategory(t, storeId, p.categoryId);
    const r = await t.query(
      `update products set category_id=$3, name=$4, description=$5, image_url=$6, price_cents=$7, compare_at_cents=$8, badge=$9,
         is_available=$10, updated_at=now() where id=$1 and store_id=$2 returning id`,
      [id, storeId, p.categoryId, p.name, p.description || null, p.imageUrl || null, p.priceCents, p.compareAtCents, p.badge, p.isAvailable]);
    if (!r.length) throw new HttpError(404, "Produto não encontrado.");
    await writeGroups(t, id, p.groups);
  });
}

export async function setProductAvailable(storeId: string, id: string, isAvailable: boolean) {
  const r = await one(`update products set is_available = $3, updated_at = now() where id = $1 and store_id = $2 returning id`, [id, storeId, isAvailable]);
  if (!r) throw new HttpError(404, "Produto não encontrado.");
}

export async function removeProduct(storeId: string, id: string) {
  // pedidos antigos guardam nome e preço copiados, então o histórico continua inteiro
  const r = await one(`delete from products where id = $1 and store_id = $2 returning id`, [id, storeId]);
  if (!r) throw new HttpError(404, "Produto não encontrado.");
}

export async function createCategory(storeId: string, name: string) {
  return one<{ id: string }>(
    `insert into categories (store_id, name, position)
     values ($1, $2, (select coalesce(max(position), -1) + 1 from categories where store_id = $1)) returning id`, [storeId, name]);
}

export async function updateCategory(storeId: string, id: string, patch: { name?: string; isActive?: boolean; move?: "up" | "down" }) {
  await tx(async (t) => {
    const [c] = await t.query<{ id: string; position: number }>(`select id, position from categories where id = $1 and store_id = $2`, [id, storeId]);
    if (!c) throw new HttpError(404, "Categoria não encontrada.");
    if (patch.name !== undefined || patch.isActive !== undefined)
      await t.query(`update categories set name = coalesce($2, name), is_active = coalesce($3, is_active) where id = $1`, [id, patch.name ?? null, patch.isActive ?? null]);
    if (patch.move) {
      const all = await t.query<{ id: string }>(`select id from categories where store_id = $1 order by position, name`, [storeId]);
      const i = all.findIndex((x) => x.id === id);
      const j = patch.move === "up" ? i - 1 : i + 1;
      if (j >= 0 && j < all.length) {
        [all[i], all[j]] = [all[j], all[i]];
        for (let k = 0; k < all.length; k++) await t.query(`update categories set position = $2 where id = $1`, [all[k].id, k]);
      }
    }
  });
}

export async function removeCategory(storeId: string, id: string) {
  const used = await one<{ n: number }>(`select count(*)::int as n from products where category_id = $1`, [id]);
  if (used && used.n > 0) throw new HttpError(409, "Mova ou exclua os produtos desta categoria antes de excluí-la.");
  const r = await one(`delete from categories where id = $1 and store_id = $2 returning id`, [id, storeId]);
  if (!r) throw new HttpError(404, "Categoria não encontrada.");
}
