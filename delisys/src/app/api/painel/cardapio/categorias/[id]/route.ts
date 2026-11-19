import { NextResponse } from "next/server";
import { z } from "zod";
import { requireUser } from "@/lib/auth";
import { handle } from "@/lib/http";
import { removeCategory, updateCategory } from "@/lib/menu-admin";

type Ctx = { params: Promise<{ id: string }> };

export const PATCH = handle(async (req: Request, ctx: Ctx) => {
  const u = await requireUser();
  const body = z.object({
    name: z.string().trim().min(2).max(40).optional(),
    isActive: z.boolean().optional(),
    move: z.enum(["up", "down"]).optional(),
  }).parse(await req.json());
  await updateCategory(u.store_id, z.string().uuid().parse((await ctx.params).id), body);
  return NextResponse.json({ ok: true });
});

export const DELETE = handle(async (_req: Request, ctx: Ctx) => {
  const u = await requireUser();
  await removeCategory(u.store_id, z.string().uuid().parse((await ctx.params).id));
  return NextResponse.json({ ok: true });
});
