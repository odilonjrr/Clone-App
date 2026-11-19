import "server-only";
import { HttpError } from "./auth";
import { one, q, tx, type Queryable } from "./db";
import type { DeliveryAddress } from "./maps";
import { firstName, stageMessage, waMeLink } from "./messages";
import { createPixCharge } from "./payments";
import { priceCart, type CartItemInput, type MenuProduct } from "./pricing";
import { canMove, NOTIFY_STAGES, STATUS_LABEL, type Fulfillment, type NotifyStage, type OrderStatus } from "./status";
import { getMenu, getZones, type Store } from "./store";
import { randomToken } from "./tokens";
import { sendTemplate, whatsappMode } from "./whatsapp";

export type PaymentMethod = "pix" | "cash" | "card_on_delivery";

export type NewOrderInput = {
  idempotencyKey: string;
  customerName: string;
  customerPhone: string; // já normalizado: 55 + DDD + número
  customerDocument?: string;
  fulfillment: Fulfillment;
  address?: DeliveryAddress;
  zoneId?: string;
  paymentMethod: PaymentMethod;
  changeForCents?: number | null;
  notes?: string;
  items: CartItemInput[];
};

export type OrderRow = {
  id: string;
  store_id: string;
  number: number;
  public_token: string;
  customer_name: string;
  customer_phone: string;
  fulfillment: Fulfillment;
  address: DeliveryAddress | null;
  zone_id: string | null;
  status: OrderStatus;
  payment_method: PaymentMethod;
  payment_status: "pendente" | "aprovado" | "a_cobrar" | "cancelado";
  change_for_cents: number | null;
  subtotal_cents: number;
  delivery_fee_cents: number;
  total_cents: number;
  notes: string | null;
  courier_id: string | null;
  placed_at: string | null;
  preparing_at: string | null;
  ready_at: string | null;
  dispatched_at: string | null;
  delivered_at: string | null;
  canceled_at: string | null;
  cancel_reason: string | null;
  created_at: string;
};

const STATUS_TIME: Partial<Record<OrderStatus, string>> = {
  realizado: "placed_at",
  em_preparo: "preparing_at",
  pronto: "ready_at",
  em_entrega: "dispatched_at",
  entregue: "delivered_at",
  cancelado: "canceled_at",
};

export function trackingUrl(baseUrl: string, token: string) {
  return `${baseUrl}/r/${token}`;
}
export function courierUrl(baseUrl: string, token: string) {
  return `${baseUrl}/m/${token}`;
}

// ───────────────────────── criar pedido ─────────────────────────
export async function createOrder(store: Store, input: NewOrderInput, baseUrl: string) {
  if (!store.is_open) throw new HttpError(409, "A loja fechou agora há pouco. Tente de novo mais tarde.");
  if (!store.payment_methods.includes(input.paymentMethod))
    throw new HttpError(400, "Essa forma de pagamento não está disponível nesta loja.");
  if (input.fulfillment === "delivery" && !store.delivery_enabled)
    throw new HttpError(400, "Esta loja não está fazendo entregas agora. Escolha retirada.");
  if (input.fulfillment === "pickup" && !store.pickup_enabled)
    throw new HttpError(400, "Esta loja não aceita retirada no balcão.");

  const menu = await getMenu(store.id);
  const activeCats = new Set(menu.categories.map((c) => c.id));
  const products = new Map<string, MenuProduct>(
    menu.products.filter((p) => activeCats.has(p.category_id)).map((p) => [p.id, p]),
  );
  const priced = priceCart(products, input.items);
  if (!priced.ok) throw new HttpError(409, priced.errors.join(" "));

  let fee = 0;
  let zoneId: string | null = null;
  if (input.fulfillment === "delivery") {
    if (!input.address) throw new HttpError(400, "Informe o endereço de entrega.");
    const zones = await getZones(store.id);
    const zone = zones.find((z) => z.id === input.zoneId);
    if (!zone) throw new HttpError(400, "Escolha um bairro atendido pela loja.");
    fee = zone.fee_cents;
    zoneId = zone.id;
  }
  if (priced.subtotalCents < store.min_order_cents)
    throw new HttpError(409, "O pedido ainda não chegou ao valor mínimo da loja.");

  const total = priced.subtotalCents + fee;
  if (input.paymentMethod === "cash" && input.changeForCents != null && input.changeForCents < total)
    throw new HttpError(400, "O troco precisa ser para um valor maior que o total do pedido.");

  const isPix = input.paymentMethod === "pix";
  const status: OrderStatus = isPix ? "aguardando_pagamento" : "realizado";

  const result = await tx(async (t) => {
    const existing = await t.query<OrderRow>(
      `select * from orders where store_id = $1 and idempotency_key = $2`,
      [store.id, input.idempotencyKey],
    );
    if (existing[0]) return { order: existing[0], created: false };

    const [{ last_order_number: number }] = await t.query<{ last_order_number: number }>(
      `insert into store_counters (store_id, last_order_number) values ($1, 1001)
       on conflict (store_id) do update set last_order_number = store_counters.last_order_number + 1
       returning last_order_number`,
      [store.id],
    );
    const [order] = await t.query<OrderRow>(
      `insert into orders (store_id, number, public_token, customer_name, customer_phone, fulfillment, address, zone_id,
         status, payment_method, payment_status, change_for_cents, subtotal_cents, delivery_fee_cents, total_cents,
         notes, idempotency_key, placed_at)
       values ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13,$14,$15,$16,$17, $18)
       returning *`,
      [
        store.id, number, randomToken(18), input.customerName.trim().slice(0, 80), input.customerPhone,
        input.fulfillment, input.fulfillment === "delivery" ? JSON.stringify(input.address) : null, zoneId,
        status, input.paymentMethod, isPix ? "pendente" : "a_cobrar",
        input.paymentMethod === "cash" ? (input.changeForCents ?? null) : null,
        priced.subtotalCents, fee, total, input.notes?.trim().slice(0, 300) || null, input.idempotencyKey,
        isPix ? null : new Date().toISOString(),
      ],
    );
    let pos = 0;
    for (const line of priced.lines) {
      const [item] = await t.query<{ id: string }>(
        `insert into order_items (order_id, product_id, name, unit_price_cents, quantity, notes, position)
         values ($1,$2,$3,$4,$5,$6,$7) returning id`,
        [order.id, line.productId, line.name, line.unitPriceCents, line.quantity, line.notes ?? null, pos++],
      );
      for (const o of line.options)
        await t.query(
          `insert into order_item_options (order_item_id, group_name, name, price_cents) values ($1,$2,$3,$4)`,
          [item.id, o.groupName, o.name, o.priceCents],
        );
    }
    await t.query(`insert into order_events (order_id, from_status, to_status, actor) values ($1, null, $2, 'cliente')`, [
      order.id,
      status,
    ]);
    return { order, created: true };
  });

  const order = result.order;
  if (result.created && isPix) {
    await ensurePixCharge(order, store, input.customerDocument).catch((e) => {
      // o pedido fica aguardando pagamento; a página do pedido oferece gerar o Pix de novo
      console.error("pix", e);
    });
  }
  if (result.created && !isPix) await notify(order.id, "realizado", baseUrl);
  return order;
}

export async function ensurePixCharge(order: OrderRow, store: Store, customerDocument?: string) {
  const current = await one<{ id: string }>(
    `select id from payments where order_id = $1 and status = 'pending' and expires_at > now()`,
    [order.id],
  );
  if (current) return;
  const charge = await createPixCharge({
    orderId: order.id,
    orderNumber: order.number,
    storeName: store.name,
    amountCents: order.total_cents,
    customerName: order.customer_name,
    customerPhone: order.customer_phone,
    customerDocument,
  });
  await q(
    `insert into payments (order_id, provider, provider_payment_id, status, amount_cents, pix_payload, pix_qr_data_url, expires_at)
     values ($1,$2,$3,'pending',$4,$5,$6,$7) on conflict (provider, provider_payment_id) do nothing`,
    [order.id, charge.provider, charge.providerPaymentId, order.total_cents, charge.payload, charge.qrDataUrl, charge.expiresAt.toISOString()],
  );
}

// ───────────────────────── pagamento confirmado ─────────────────────────
export async function markPaid(provider: string, providerPaymentId: string, baseUrl: string) {
  const changed = await tx(async (t) => {
    const [p] = await t.query<{ order_id: string; status: string }>(
      `select order_id, status from payments where provider = $1 and provider_payment_id = $2 for update`,
      [provider, providerPaymentId],
    );
    if (!p) return null;
    if (p.status === "paid") return null; // repetido: nada a fazer
    await t.query(`update payments set status = 'paid', paid_at = now() where provider = $1 and provider_payment_id = $2`, [
      provider,
      providerPaymentId,
    ]);
    const [o] = await t.query<OrderRow>(`select * from orders where id = $1 for update`, [p.order_id]);
    if (!o || o.status !== "aguardando_pagamento") {
      await t.query(`update orders set payment_status = 'aprovado', updated_at = now() where id = $1`, [p.order_id]);
      return null;
    }
    await t.query(
      `update orders set status = 'realizado', payment_status = 'aprovado', placed_at = now(), updated_at = now() where id = $1`,
      [o.id],
    );
    await t.query(`insert into order_events (order_id, from_status, to_status, actor) values ($1,$2,'realizado','pagamento')`, [
      o.id,
      o.status,
    ]);
    return o.id;
  });
  if (changed) await notify(changed, "realizado", baseUrl);
  return changed;
}

// ───────────────────────── mudar etapa ─────────────────────────
export type TransitionActor =
  | { kind: "painel"; name: string; storeId: string }
  | { kind: "motoboy"; name: string; courierId: string };

export async function transition(
  orderId: string,
  to: OrderStatus,
  actor: TransitionActor,
  baseUrl: string,
  opts: { courierId?: string | null; reason?: string } = {},
) {
  const order = await tx(async (t: Queryable) => {
    const [o] = await t.query<OrderRow>(`select * from orders where id = $1 for update`, [orderId]);
    if (!o) throw new HttpError(404, "Pedido não encontrado.");
    if (actor.kind === "painel" && o.store_id !== actor.storeId) throw new HttpError(404, "Pedido não encontrado.");
    if (actor.kind === "motoboy" && o.courier_id !== actor.courierId)
      throw new HttpError(403, "Este pedido não está com você.");
    if (o.status === to) return { o, moved: false };
    if (!canMove(o.status, to, o.fulfillment, actor.kind))
      throw new HttpError(
        409,
        `Não dá para passar de "${STATUS_LABEL[o.status]}" para "${STATUS_LABEL[to]}". Atualize a tela.`,
      );

    let courierId = o.courier_id;
    if (actor.kind === "painel" && opts.courierId !== undefined) {
      if (opts.courierId) {
        const [c] = await t.query<{ id: string }>(`select id from couriers where id = $1 and store_id = $2 and is_active`, [
          opts.courierId,
          o.store_id,
        ]);
        if (!c) throw new HttpError(400, "Motoboy não encontrado.");
      }
      courierId = opts.courierId;
    }
    if (to === "em_entrega" && !courierId) throw new HttpError(400, "Escolha o motoboy antes de mandar para entrega.");

    const col = STATUS_TIME[to];
    const [updated] = await t.query<OrderRow>(
      `update orders set status = $2, courier_id = $3, updated_at = now()
         ${col ? `, ${col} = now()` : ""}
         ${to === "cancelado" ? ", cancel_reason = $4, payment_status = case when payment_status = 'aprovado' then payment_status else 'cancelado' end" : ""}
       where id = $1 returning *`,
      to === "cancelado" ? [o.id, to, courierId, opts.reason ?? null] : [o.id, to, courierId],
    );
    await t.query(`insert into order_events (order_id, from_status, to_status, actor) values ($1,$2,$3,$4)`, [
      o.id,
      o.status,
      to,
      `${actor.kind}:${actor.name}`.slice(0, 80),
    ]);
    return { o: updated, moved: true };
  });
  if (order.moved && (NOTIFY_STAGES as readonly string[]).includes(to))
    await notify(order.o.id, to as NotifyStage, baseUrl);
  return order.o;
}

export async function assignCourier(orderId: string, storeId: string, courierId: string | null) {
  if (courierId) {
    const c = await one(`select id from couriers where id = $1 and store_id = $2 and is_active`, [courierId, storeId]);
    if (!c) throw new HttpError(400, "Motoboy não encontrado.");
  }
  const o = await one<OrderRow>(
    `update orders set courier_id = $3, updated_at = now()
     where id = $1 and store_id = $2 and status not in ('em_entrega','entregue','cancelado') returning *`,
    [orderId, storeId, courierId],
  );
  if (!o) throw new HttpError(409, "Não dá para trocar o motoboy depois que o pedido saiu.");
  return o;
}

// ───────────────────────── avisos no WhatsApp ─────────────────────────
export async function notify(orderId: string, stage: NotifyStage, baseUrl: string) {
  const o = await one<OrderRow & { store_name: string; courier_name: string | null }>(
    `select o.*, s.name as store_name, c.name as courier_name
     from orders o join stores s on s.id = o.store_id left join couriers c on c.id = o.courier_id where o.id = $1`,
    [orderId],
  );
  if (!o) return;
  const msg = stageMessage(stage, {
    customerFirstName: firstName(o.customer_name),
    orderNumber: o.number,
    storeName: o.store_name,
    totalCents: o.total_cents,
    fulfillment: o.fulfillment,
    trackingUrl: trackingUrl(baseUrl, o.public_token),
    courierName: o.courier_name,
  });
  const mode = whatsappMode();
  const inserted = await one<{ id: string }>(
    `insert into notifications (store_id, order_id, stage, to_phone, body, mode, status)
     values ($1,$2,$3,$4,$5,$6,'pending') on conflict (order_id, stage) do nothing returning id`,
    [o.store_id, o.id, stage, o.customer_phone, msg.text, mode],
  );
  if (!inserted || mode !== "api") return; // já avisado, ou modo manual (o painel mostra o botão)
  const r = await sendTemplate(o.customer_phone, msg.template, msg.params);
  if (r.ok)
    await q(`update notifications set status = 'sent', provider_message_id = $2, sent_at = now() where id = $1`, [
      inserted.id,
      r.id,
    ]);
  else await q(`update notifications set status = 'failed', error = $2 where id = $1`, [inserted.id, r.error.slice(0, 500)]);
}

/** Tenta de novo um aviso que falhou (modo api) ou marca como enviado à mão (modo manual). */
export async function resolveNotification(id: string, storeId: string, action: "retry" | "mark_sent", baseUrl: string) {
  const n = await one<{ id: string; order_id: string; stage: NotifyStage; mode: string; status: string; to_phone: string; body: string }>(
    `select * from notifications where id = $1 and store_id = $2`,
    [id, storeId],
  );
  if (!n) throw new HttpError(404, "Aviso não encontrado.");
  if (action === "mark_sent") {
    await q(`update notifications set status = 'sent', sent_at = now(), error = null where id = $1`, [id]);
    return;
  }
  const o = await one<OrderRow & { store_name: string; courier_name: string | null }>(
    `select o.*, s.name as store_name, c.name as courier_name from orders o join stores s on s.id = o.store_id
     left join couriers c on c.id = o.courier_id where o.id = $1`,
    [n.order_id],
  );
  if (!o) return;
  const msg = stageMessage(n.stage, {
    customerFirstName: firstName(o.customer_name),
    orderNumber: o.number,
    storeName: o.store_name,
    totalCents: o.total_cents,
    fulfillment: o.fulfillment,
    trackingUrl: trackingUrl(baseUrl, o.public_token),
    courierName: o.courier_name,
  });
  const r = await sendTemplate(n.to_phone, msg.template, msg.params);
  if (r.ok) await q(`update notifications set status = 'sent', provider_message_id = $2, sent_at = now(), error = null where id = $1`, [id, r.id]);
  else {
    await q(`update notifications set status = 'failed', error = $2 where id = $1`, [id, r.error.slice(0, 500)]);
    throw new HttpError(502, `O WhatsApp recusou o envio: ${r.error}`);
  }
}

export function manualLink(phone: string, body: string) {
  return waMeLink(phone, body);
}

// ───────────────────────── leitura ─────────────────────────
export type OrderDetail = OrderRow & {
  items: { name: string; quantity: number; unit_price_cents: number; notes: string | null; options: { group_name: string; name: string; price_cents: number }[] }[];
  courier: { id: string; name: string; phone: string | null } | null;
  zone_name: string | null;
};

export async function loadOrderDetails(orders: OrderRow[]): Promise<OrderDetail[]> {
  if (!orders.length) return [];
  const ids = orders.map((o) => o.id);
  const items = await q<{ id: string; order_id: string; name: string; quantity: number; unit_price_cents: number; notes: string | null }>(
    `select id, order_id, name, quantity, unit_price_cents, notes from order_items where order_id = any($1::uuid[]) order by position`,
    [ids],
  );
  const opts = items.length
    ? await q<{ order_item_id: string; group_name: string; name: string; price_cents: number }>(
        `select order_item_id, group_name, name, price_cents from order_item_options where order_item_id = any($1::uuid[])`,
        [items.map((i) => i.id)],
      )
    : [];
  const couriers = await q<{ id: string; name: string; phone: string | null }>(
    `select id, name, phone from couriers where id = any($1::uuid[])`,
    [orders.map((o) => o.courier_id).filter(Boolean)],
  );
  const zones = await q<{ id: string; name: string }>(`select id, name from delivery_zones where id = any($1::uuid[])`, [
    orders.map((o) => o.zone_id).filter(Boolean),
  ]);
  return orders.map((o) => ({
    ...o,
    items: items
      .filter((i) => i.order_id === o.id)
      .map((i) => ({ ...i, options: opts.filter((x) => x.order_item_id === i.id) })),
    courier: couriers.find((c) => c.id === o.courier_id) ?? null,
    zone_name: zones.find((z) => z.id === o.zone_id)?.name ?? null,
  }));
}
