import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { MenuApp } from "@/components/menu/menu-app";
import { getMenu, getStoreBySlug, getZones } from "@/lib/store";

export const dynamic = "force-dynamic";

type Props = { params: Promise<{ slug: string }> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const store = await getStoreBySlug((await params).slug);
  return { title: store ? `${store.name} · Cardápio` : "Loja não encontrada" };
}

export default async function StorePage({ params }: Props) {
  const store = await getStoreBySlug((await params).slug);
  if (!store) notFound();
  const [menu, zones] = await Promise.all([getMenu(store.id), getZones(store.id)]);
  const activeCats = new Set(menu.categories.map((c) => c.id));
  return (
    <MenuApp
      store={{
        slug: store.slug, name: store.name, isOpen: store.is_open, district: store.district, city: store.city,
        minOrderCents: store.min_order_cents, etaMin: store.eta_min_minutes, etaMax: store.eta_max_minutes,
        whatsapp: store.whatsapp_phone, deliveryEnabled: store.delivery_enabled,
        minFeeCents: zones.length ? Math.min(...zones.map((z) => z.fee_cents)) : null,
      }}
      categories={menu.categories}
      products={menu.products.filter((p) => activeCats.has(p.category_id))}
    />
  );
}
