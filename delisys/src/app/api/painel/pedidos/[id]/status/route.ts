import { NextResponse } from "next/server";
import { z } from "zod";
import { requireUser } from "@/lib/auth";
import { appUrl, handle } from "@/lib/http";
import { transition } from "@/lib/orders";

const Body = z.object({
  to: z.enum(["em_preparo", "pronto", "em_entrega", "entregue", "cancelado"]),
  courierId: z.string().uuid().nullable().optional(),
  reason: z.string().trim().max(200).optional(),
});

export const POST = handle(async (req: Request, ctx: { params: Promise<{ id: string }> }) => {
  const u = await requireUser();
  const b = Body.parse(await req.json());
  const id = z.string().uuid().parse((await ctx.params).id);
  const o = await transition(id, b.to, { kind: "painel", name: u.name, storeId: u.store_id }, appUrl(req), { courierId: b.courierId, reason: b.reason });
  return NextResponse.json({ ok: true, status: o.status });
});
