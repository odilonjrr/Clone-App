import { NextResponse } from "next/server";
import { z } from "zod";
import { requireUser } from "@/lib/auth";
import { handle } from "@/lib/http";
import { ProductInput, removeProduct, setProductAvailable, updateProduct } from "@/lib/menu-admin";

type Ctx = { params: Promise<{ id: string }> };
const id = async (ctx: Ctx) => z.string().uuid().parse((await ctx.params).id);

export const PUT = handle(async (req: Request, ctx: Ctx) => {
  const u = await requireUser();
  await updateProduct(u.store_id, await id(ctx), ProductInput.parse(await req.json()));
  return NextResponse.json({ ok: true });
});

export const PATCH = handle(async (req: Request, ctx: Ctx) => {
  const u = await requireUser();
  const { isAvailable } = z.object({ isAvailable: z.boolean() }).parse(await req.json());
  await setProductAvailable(u.store_id, await id(ctx), isAvailable);
  return NextResponse.json({ ok: true });
});

export const DELETE = handle(async (_req: Request, ctx: Ctx) => {
  const u = await requireUser();
  await removeProduct(u.store_id, await id(ctx));
  return NextResponse.json({ ok: true });
});
