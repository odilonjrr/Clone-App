import { NextResponse } from "next/server";
import { HttpError } from "@/lib/auth";
import { handle } from "@/lib/http";
import { getTracking } from "@/lib/tracking";

export const GET = handle(async (_req: Request, ctx: { params: Promise<{ token: string }> }) => {
  const t = await getTracking((await ctx.params).token);
  if (!t) throw new HttpError(404, "Pedido não encontrado.");
  return NextResponse.json(t, { headers: { "cache-control": "no-store" } });
});
