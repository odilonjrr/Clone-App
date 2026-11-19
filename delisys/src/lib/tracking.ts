import "server-only";
import { one } from "./db";
import { addressOneLine, type DeliveryAddress } from "./maps";
import { loadOrderDetails, type OrderRow } from "./orders";

/** O que o cliente vê pelo link do pedido. Nada de dados de outros pedidos ou da loja além do público. */
export async function getTracking(token: string) {
  const o = await one<OrderRow>(`select * from orders where public_token = $1`, [token]);
  if (!o) return null;
  const [detail] = await loadOrderDetails([o]);
  const store = await one<{ name: string; slug: string; whatsapp_phone: string | null; lat: number | null; lng: number | null }>(
    `select name, slug, whatsapp_phone, lat, lng from stores where id = $1`, [o.store_id]);
  const pix = o.status === "aguardando_pagamento"
    ? await one<{ provider: string; pix_payload: string; pix_qr_data_url: string; expires_at: string; status: string }>(
        `select provider, pix_payload, pix_qr_data_url, expires_at, status from payments where order_id = $1 order by created_at desc limit 1`, [o.id])
    : null;
  const pos = o.status === "em_entrega"
    ? await one<{ lat: number; lng: number; updated_at: string }>(`select lat, lng, updated_at from courier_positions where order_id = $1`, [o.id])
    : null;
  const addr = o.address as DeliveryAddress | null;
  return {
    number: o.number, status: o.status, fulfillment: o.fulfillment,
    times: { realizado: o.placed_at, em_preparo: o.preparing_at, pronto: o.ready_at, em_entrega: o.dispatched_at, entregue: o.delivered_at, cancelado: o.canceled_at },
    cancelReason: o.cancel_reason,
    customerName: o.customer_name,
    address: addr ? addressOneLine(addr) : null,
    destination: addr?.lat != null && addr?.lng != null ? { lat: addr.lat, lng: addr.lng } : null,
    payment: { method: o.payment_method, status: o.payment_status, changeForCents: o.change_for_cents },
    totals: { subtotal: o.subtotal_cents, fee: o.delivery_fee_cents, total: o.total_cents },
    items: detail.items.map((i) => ({ name: i.name, quantity: i.quantity, total: i.unit_price_cents * i.quantity, options: i.options.map((x) => x.name) })),
    store: store ? { name: store.name, slug: store.slug, whatsapp: store.whatsapp_phone, location: store.lat != null && store.lng != null ? { lat: store.lat, lng: store.lng } : null } : null,
    courier: detail.courier ? { firstName: detail.courier.name.split(" ")[0] } : null,
    courierPosition: pos ? { lat: pos.lat, lng: pos.lng, updatedAt: pos.updated_at } : null,
    pix: pix ? { testMode: pix.provider === "mock", payload: pix.pix_payload, qr: pix.pix_qr_data_url, expiresAt: pix.expires_at, expired: new Date(pix.expires_at) < new Date() } : null,
  };
}
export type Tracking = NonNullable<Awaited<ReturnType<typeof getTracking>>>;
