import { NextResponse } from "next/server";
import { z } from "zod";
import { HttpError } from "@/lib/auth";
import { one } from "@/lib/db";
import { clientIp, handle, rateLimit } from "@/lib/http";
import { ensurePixCharge, type OrderRow } from "@/lib/orders";
import { getStoreById } from "@/lib/store";

/** Gera um Pix novo para um pedido que ainda aguarda pagamento (o anterior expirou ou falhou). */
export const POST = handle(async (req: Request, ctx: { params: Promise<{ token: string }> }) => {
  rateLimit(`pix:${clientIp(req)}`, 6, 10 * 60_000);
  const body = z.object({ document: z.string().max(20).optional() }).parse(await req.json().catch(() => ({})));
  const o = await one<OrderRow>(`select * from orders where public_token = $1`, [(await ctx.params).token]);
  if (!o) throw new HttpError(404, "Pedido não encontrado.");
  if (o.status !== "aguardando_pagamento") throw new HttpError(409, "Este pedido não está aguardando pagamento.");
  const store = await getStoreById(o.store_id);
  await ensurePixCharge(o, store!, body.document);
  return NextResponse.json({ ok: true });
});
