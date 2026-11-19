import { NextResponse } from "next/server";
import { z } from "zod";
import { requireUser } from "@/lib/auth";
import { appUrl, handle } from "@/lib/http";
import { resolveNotification } from "@/lib/orders";

export const POST = handle(async (req: Request, ctx: { params: Promise<{ id: string }> }) => {
  const u = await requireUser();
  const { action } = z.object({ action: z.enum(["retry", "mark_sent"]) }).parse(await req.json());
  const id = z.string().uuid().parse((await ctx.params).id);
  await resolveNotification(id, u.store_id, action, appUrl(req));
  return NextResponse.json({ ok: true });
});
