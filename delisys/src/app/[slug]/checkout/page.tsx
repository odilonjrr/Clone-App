import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { Checkout } from "@/components/menu/checkout";
import { pixNeedsDocument, paymentProvider } from "@/lib/payments";
import { getStoreBySlug, getZones } from "@/lib/store";

export const dynamic = "force-dynamic";
export const metadata: Metadata = { title: "Finalizar pedido" };

export default async function CheckoutPage({ params }: { params: Promise<{ slug: string }> }) {
  const store = await getStoreBySlug((await params).slug);
  if (!store) notFound();
  const zones = await getZones(store.id);
  return (
    <Checkout
      store={{
        slug: store.slug, name: store.name, isOpen: store.is_open, minOrderCents: store.min_order_cents,
        pickupEnabled: store.pickup_enabled, deliveryEnabled: store.delivery_enabled && zones.length > 0,
        paymentMethods: store.payment_methods as ("pix" | "cash" | "card_on_delivery")[],
        pickupAddress: [store.address_line, store.district, store.city].filter(Boolean).join(" - ") || null,
        city: store.city,
      }}
      zones={zones.map((z) => ({ id: z.id, name: z.name, feeCents: z.fee_cents }))}
      pixNeedsDocument={pixNeedsDocument()}
      pixTestMode={paymentProvider() === "mock"}
    />
  );
}
