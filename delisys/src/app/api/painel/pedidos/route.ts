import { NextResponse } from "next/server";
import { requireUser } from "@/lib/auth";
import { q } from "@/lib/db";
import { appUrl, handle } from "@/lib/http";
import { courierUrl, loadOrderDetails, manualLink, trackingUrl, type OrderRow } from "@/lib/orders";
import { whatsappMode } from "@/lib/whatsapp";

/** Pedidos do quadro: os em andamento e os finalizados/cancelados das últimas 12 horas. */
export const GET = handle(async (req: Request) => {
  const u = await requireUser();
  const base = appUrl(req);
  const rows = await q<OrderRow>(
    `select * from orders where store_id = $1
       and (status not in ('entregue','cancelado') or updated_at > now() - interval '12 hours')
     order by created_at asc limit 300`, [u.store_id]);
  const orders = await loadOrderDetails(rows);
  const notes = rows.length ? await q<{ id: string; order_id: string; stage: string; status: string; mode: string; body: string; to_phone: string; error: string | null }>(
    `select id, order_id, stage, status, mode, body, to_phone, error from notifications where order_id = any($1::uuid[]) order by created_at`, [rows.map((r) => r.id)]) : [];
  const couriers = await q<{ id: string; name: string; phone: string | null; access_token: string }>(
    `select id, name, phone, access_token from couriers where store_id = $1 and is_active order by name`, [u.store_id]);
  const store = await q<{ is_open: boolean; slug: string; name: string }>(`select is_open, slug, name from stores where id = $1`, [u.store_id]);
  return NextResponse.json({
    store: store[0],
    whatsappMode: whatsappMode(),
    couriers: couriers.map((c) => ({ id: c.id, name: c.name, phone: c.phone, link: courierUrl(base, c.access_token) })),
    orders: orders.map((o) => ({
      ...o,
      trackingUrl: trackingUrl(base, o.public_token),
      notifications: notes.filter((n) => n.order_id === o.id).map((n) => ({
        id: n.id, stage: n.stage, status: n.status, mode: n.mode, error: n.error,
        link: n.mode === "manual" || n.status === "failed" ? manualLink(n.to_phone, n.body) : null,
      })),
    })),
  }, { headers: { "cache-control": "no-store" } });
});
