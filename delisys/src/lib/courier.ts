import "server-only";
import { HttpError } from "./auth";
import { one, q } from "./db";
import { addressOneLine, googleMapsDirections, type DeliveryAddress } from "./maps";
import { formatBRL } from "./money";
import { loadOrderDetails, type OrderRow } from "./orders";

export async function courierByToken(token: string) {
  if (!token || token.length < 16) throw new HttpError(404, "Link inválido.");
  const c = await one<{ id: string; name: string; store_id: string; is_active: boolean; store_name: string }>(
    `select c.id, c.name, c.store_id, c.is_active, s.name as store_name from couriers c join stores s on s.id = c.store_id where c.access_token = $1`, [token]);
  if (!c || !c.is_active) throw new HttpError(404, "Este link não está mais ativo. Peça um novo para a loja.");
  return c;
}

export async function courierOrders(courierId: string) {
  const rows = await q<OrderRow>(
    `select * from orders where courier_id = $1 and (status in ('pronto','em_entrega')
       or (status = 'entregue' and delivered_at > now() - interval '12 hours'))
     order by case status when 'em_entrega' then 0 when 'pronto' then 1 else 2 end, created_at`, [courierId]);
  const det = await loadOrderDetails(rows);
  return det.map((o) => {
    const a = o.address as DeliveryAddress | null;
    const collect = o.payment_status === "aprovado" ? null
      : o.payment_method === "cash" ? `Cobrar ${formatBRL(o.total_cents)} em dinheiro${o.change_for_cents ? ` · levar troco para ${formatBRL(o.change_for_cents)} (troco de ${formatBRL(o.change_for_cents - o.total_cents)})` : " · sem troco"}`
      : o.payment_method === "card_on_delivery" ? `Cobrar ${formatBRL(o.total_cents)} no cartão (levar a maquininha)` : `Pix ainda não confirmado: confira com a loja`;
    return {
      id: o.id, number: o.number, status: o.status, customerName: o.customer_name, customerPhone: o.customer_phone,
      address: a ? addressOneLine(a) : null, reference: a?.reference ?? null, mapsUrl: a ? googleMapsDirections(a) : null,
      hasPin: a?.lat != null, itemsCount: o.items.reduce((s, i) => s + i.quantity, 0), totalCents: o.total_cents,
      paid: o.payment_status === "aprovado", collect, notes: o.notes,
    };
  });
}
