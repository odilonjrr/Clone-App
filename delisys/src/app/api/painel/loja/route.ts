import { NextResponse } from "next/server";
import { z } from "zod";
import { HttpError, requireUser } from "@/lib/auth";
import { q, tx } from "@/lib/db";
import { handle } from "@/lib/http";
import { normalizeBrPhone } from "@/lib/phone";
import { getStoreById, getZones } from "@/lib/store";

export const GET = handle(async () => {
  const u = await requireUser();
  const [store, zones] = await Promise.all([getStoreById(u.store_id), getZones(u.store_id, false)]);
  return NextResponse.json({ store, zones });
});

const Body = z.object({
  name: z.string().trim().min(2).max(60),
  whatsapp: z.string().nullable(),
  addressLine: z.string().trim().max(120).nullable(),
  district: z.string().trim().max(80).nullable(),
  city: z.string().trim().max(80).nullable(),
  lat: z.number().min(-90).max(90).nullable(),
  lng: z.number().min(-180).max(180).nullable(),
  isOpen: z.boolean(),
  minOrderCents: z.number().int().min(0).max(100000),
  etaMin: z.number().int().min(5).max(240),
  etaMax: z.number().int().min(5).max(300),
  pickupEnabled: z.boolean(),
  deliveryEnabled: z.boolean(),
  paymentMethods: z.array(z.enum(["pix", "cash", "card_on_delivery"])).min(1, "Deixe pelo menos uma forma de pagamento."),
  zones: z.array(z.object({ id: z.string().uuid().optional(), name: z.string().trim().min(2).max(60), feeCents: z.number().int().min(0).max(100000), isActive: z.boolean() })).max(200),
});

export const PUT = handle(async (req: Request) => {
  const u = await requireUser();
  const b = Body.parse(await req.json());
  if (b.etaMax < b.etaMin) throw new HttpError(400, "O tempo máximo de entrega precisa ser maior que o mínimo.");
  const phone = b.whatsapp ? normalizeBrPhone(b.whatsapp) : null;
  if (b.whatsapp && !phone) throw new HttpError(400, "WhatsApp da loja inválido. Use DDD + número.");
  await tx(async (t) => {
    await t.query(
      `update stores set name=$2, whatsapp_phone=$3, address_line=$4, district=$5, city=$6, lat=$7, lng=$8, is_open=$9,
         min_order_cents=$10, eta_min_minutes=$11, eta_max_minutes=$12, pickup_enabled=$13, delivery_enabled=$14,
         payment_methods=$15, updated_at=now() where id=$1`,
      [u.store_id, b.name, phone, b.addressLine, b.district, b.city, b.lat, b.lng, b.isOpen, b.minOrderCents, b.etaMin, b.etaMax,
       b.pickupEnabled, b.deliveryEnabled, b.paymentMethods]);
    const keep = b.zones.filter((z) => z.id).map((z) => z.id!);
    // bairros removidos da lista: ficam inativos (pedidos antigos continuam apontando para eles)
    await t.query(`update delivery_zones set is_active = false where store_id = $1 and not (id = any($2::uuid[]))`, [u.store_id, keep]);
    let pos = 0;
    for (const z of b.zones) {
      if (z.id) await t.query(`update delivery_zones set name=$3, fee_cents=$4, is_active=$5, position=$6 where id=$1 and store_id=$2`, [z.id, u.store_id, z.name, z.feeCents, z.isActive, pos++]);
      else await t.query(`insert into delivery_zones (store_id, name, fee_cents, is_active, position) values ($1,$2,$3,$4,$5)`, [u.store_id, z.name, z.feeCents, z.isActive, pos++]);
    }
  });
  return NextResponse.json({ ok: true });
});

/** Abrir/fechar rápido pelo quadro de pedidos. */
export const PATCH = handle(async (req: Request) => {
  const u = await requireUser();
  const { isOpen } = z.object({ isOpen: z.boolean() }).parse(await req.json());
  await q(`update stores set is_open = $2, updated_at = now() where id = $1`, [u.store_id, isOpen]);
  return NextResponse.json({ ok: true });
});
