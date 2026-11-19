import { NextResponse } from "next/server";
import { HttpError } from "@/lib/auth";
import { one } from "@/lib/db";
import { appUrl, handle } from "@/lib/http";
import { markPaid } from "@/lib/orders";
import { paymentProvider } from "@/lib/payments";

/** Só no modo teste (PAYMENT_PROVIDER=mock): confirma o Pix fictício como se o banco tivesse pago. */
export const POST = handle(async (req: Request, ctx: { params: Promise<{ token: string }> }) => {
  if (paymentProvider() !== "mock") throw new HttpError(404, "Indisponível.");
  const p = await one<{ provider_payment_id: string }>(
    `select p.provider_payment_id from payments p join orders o on o.id = p.order_id
     where o.public_token = $1 and p.provider = 'mock' order by p.created_at desc limit 1`, [(await ctx.params).token]);
  if (!p) throw new HttpError(404, "Pix de teste não encontrado.");
  await markPaid("mock", p.provider_payment_id, appUrl(req));
  return NextResponse.json({ ok: true });
});
