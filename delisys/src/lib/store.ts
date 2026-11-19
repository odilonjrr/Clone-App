import "server-only";
import { one, q } from "./db";
import type { MenuGroup, MenuProduct } from "./pricing";

export type Store = {
  id: string;
  slug: string;
  name: string;
  whatsapp_phone: string | null;
  address_line: string | null;
  district: string | null;
  city: string | null;
  lat: number | null;
  lng: number | null;
  is_open: boolean;
  min_order_cents: number;
  eta_min_minutes: number;
  eta_max_minutes: number;
  pickup_enabled: boolean;
  delivery_enabled: boolean;
  payment_methods: string[];
};
export type Zone = { id: string; name: string; fee_cents: number; is_active: boolean; position: number };
export type Category = { id: string; name: string; position: number; is_active: boolean };
export type Product = MenuProduct & {
  category_id: string;
  description: string | null;
  image_url: string | null;
  compare_at_cents: number | null;
  badge: string | null;
  position: number;
};

export function getStoreBySlug(slug: string) {
  return one<Store>(`select * from stores where slug = $1`, [slug]);
}
export function getStoreById(id: string) {
  return one<Store>(`select * from stores where id = $1`, [id]);
}

export async function getMenu(storeId: string, opts: { includeInactive?: boolean } = {}) {
  const cats = await q<Category>(
    `select id, name, position, is_active from categories
     where store_id = $1 and ($2::boolean or is_active) order by position, name`,
    [storeId, !!opts.includeInactive],
  );
  const prods = await q<Omit<Product, "groups">>(
    `select id, category_id, name, description, image_url, price_cents, compare_at_cents, badge, is_available, position
     from products where store_id = $1 order by position, name`,
    [storeId],
  );
  const groups = await q<MenuGroup & { product_id: string; position: number }>(
    `select g.id, g.product_id, g.name, g.min_select, g.max_select, g.pricing, g.position
     from option_groups g join products p on p.id = g.product_id where p.store_id = $1 order by g.position`,
    [storeId],
  );
  const options = await q<{
    id: string;
    group_id: string;
    name: string;
    description: string | null;
    price_cents: number;
    is_available: boolean;
  }>(
    `select o.id, o.group_id, o.name, o.description, o.price_cents, o.is_available
     from options o join option_groups g on g.id = o.group_id join products p on p.id = g.product_id
     where p.store_id = $1 order by o.position, o.name`,
    [storeId],
  );
  const byGroup = new Map<string, typeof options>();
  for (const o of options) byGroup.set(o.group_id, [...(byGroup.get(o.group_id) ?? []), o]);
  const byProduct = new Map<string, MenuGroup[]>();
  for (const g of groups)
    byProduct.set(g.product_id, [...(byProduct.get(g.product_id) ?? []), { ...g, options: byGroup.get(g.id) ?? [] }]);
  const products: Product[] = prods.map((p) => ({ ...p, groups: byProduct.get(p.id) ?? [] }));
  return { categories: cats, products };
}

export function getZones(storeId: string, activeOnly = true) {
  return q<Zone>(
    `select id, name, fee_cents, is_active, position from delivery_zones
     where store_id = $1 and (not $2::boolean or is_active) order by position, name`,
    [storeId, activeOnly],
  );
}
