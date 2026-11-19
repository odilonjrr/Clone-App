import { NextResponse } from "next/server";
import { z } from "zod";
import { requireUser } from "@/lib/auth";
import { handle } from "@/lib/http";
import { assignCourier } from "@/lib/orders";

export const POST = handle(async (req: Request, ctx: { params: Promise<{ id: string }> }) => {
  const u = await requireUser();
  const { courierId } = z.object({ courierId: z.string().uuid().nullable() }).parse(await req.json());
  const id = z.string().uuid().parse((await ctx.params).id);
  await assignCourier(id, u.store_id, courierId);
  return NextResponse.json({ ok: true });
});
