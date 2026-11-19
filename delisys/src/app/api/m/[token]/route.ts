import { NextResponse } from "next/server";
import { courierByToken, courierOrders } from "@/lib/courier";
import { handle } from "@/lib/http";

export const GET = handle(async (_req: Request, ctx: { params: Promise<{ token: string }> }) => {
  const c = await courierByToken((await ctx.params).token);
  return NextResponse.json({ courier: { name: c.name, storeName: c.store_name }, orders: await courierOrders(c.id) }, { headers: { "cache-control": "no-store" } });
});
