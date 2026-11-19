import { NextResponse } from "next/server";
import { z } from "zod";
import { courierByToken } from "@/lib/courier";
import { appUrl, handle } from "@/lib/http";
import { transition } from "@/lib/orders";

export const POST = handle(async (req: Request, ctx: { params: Promise<{ token: string; id: string }> }) => {
  const p = await ctx.params;
  const c = await courierByToken(p.token);
  const { to } = z.object({ to: z.enum(["em_entrega", "entregue"]) }).parse(await req.json());
  const o = await transition(z.string().uuid().parse(p.id), to, { kind: "motoboy", name: c.name, courierId: c.id }, appUrl(req));
  return NextResponse.json({ ok: true, status: o.status });
});
