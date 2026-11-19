import { NextResponse } from "next/server";
import { z } from "zod";
import { courierByToken } from "@/lib/courier";
import { q } from "@/lib/db";
import { handle, rateLimit } from "@/lib/http";

/** Posição do motoboy: vale para todos os pedidos dele que estão "saiu para entrega". */
export const POST = handle(async (req: Request, ctx: { params: Promise<{ token: string }> }) => {
  const c = await courierByToken((await ctx.params).token);
  rateLimit(`loc:${c.id}`, 30, 60_000);
  const b = z.object({ lat: z.number().min(-90).max(90), lng: z.number().min(-180).max(180), accuracy: z.number().min(0).max(100000).nullable().optional() }).parse(await req.json());
  const rows = await q(
    `insert into courier_positions (order_id, courier_id, lat, lng, accuracy_m, updated_at)
     select id, $1, $2, $3, $4, now() from orders where courier_id = $1 and status = 'em_entrega'
     on conflict (order_id) do update set lat = excluded.lat, lng = excluded.lng, accuracy_m = excluded.accuracy_m, updated_at = now()
     returning order_id`, [c.id, b.lat, b.lng, b.accuracy ?? null]);
  return NextResponse.json({ ok: true, orders: rows.length });
});
