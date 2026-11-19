import { NextResponse } from "next/server";
import { z } from "zod";
import { HttpError } from "@/lib/auth";
import { appUrl, clientIp, handle, rateLimit } from "@/lib/http";
import { createOrder } from "@/lib/orders";
import { normalizeBrPhone } from "@/lib/phone";
import { getStoreBySlug } from "@/lib/store";

const Address = z.object({
  street: z.string().trim().min(2, "Informe a rua.").max(120),
  number: z.string().trim().min(1, "Informe o número.").max(20),
  district: z.string().trim().min(2).max(80),
  city: z.string().trim().min(2, "Informe a cidade.").max(80),
  complement: z.string().trim().max(80).optional(),
  reference: z.string().trim().max(120).optional(),
  lat: z.number().min(-90).max(90).nullable().optional(),
  lng: z.number().min(-180).max(180).nullable().optional(),
});

const Body = z.object({
  idempotencyKey: z.string().min(8).max(80),
  customerName: z.string().trim().min(2, "Informe seu nome.").max(80),
  customerPhone: z.string().min(8, "Informe seu WhatsApp com DDD.").max(30),
  customerDocument: z.string().max(20).optional(),
  fulfillment: z.enum(["delivery", "pickup"]),
  address: Address.optional(),
  zoneId: z.string().uuid().optional(),
  paymentMethod: z.enum(["pix", "cash", "card_on_delivery"]),
  changeForCents: z.number().int().positive().max(10_000_00).nullable().optional(),
  notes: z.string().max(300).optional(),
  items: z.array(z.object({
    productId: z.string().uuid(),
    quantity: z.number().int().min(1).max(99),
    options: z.record(z.string().uuid(), z.array(z.string().uuid()).max(30)).default({}),
    notes: z.string().max(200).optional(),
  })).min(1, "Sua sacola está vazia.").max(50),
});

export const POST = handle(async (req: Request, ctx: { params: Promise<{ slug: string }> }) => {
  rateLimit(`order:${clientIp(req)}`, 12, 10 * 60_000);
  const store = await getStoreBySlug((await ctx.params).slug);
  if (!store) throw new HttpError(404, "Loja não encontrada.");
  const b = Body.parse(await req.json());
  const phone = normalizeBrPhone(b.customerPhone);
  if (!phone) throw new HttpError(400, "WhatsApp inválido. Use DDD + número, ex.: (11) 98765-4321.");
  const order = await createOrder(store, { ...b, customerPhone: phone }, appUrl(req));
  return NextResponse.json({ token: order.public_token, number: order.number });
});
