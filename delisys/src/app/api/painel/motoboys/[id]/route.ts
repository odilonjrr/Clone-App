import { NextResponse } from "next/server";
import { z } from "zod";
import { HttpError, requireUser } from "@/lib/auth";
import { one } from "@/lib/db";
import { handle } from "@/lib/http";
import { normalizeBrPhone } from "@/lib/phone";
import { randomToken } from "@/lib/tokens";

/** Editar, ativar/desativar ou trocar o link secreto (o link antigo para de funcionar). */
export const PATCH = handle(async (req: Request, ctx: { params: Promise<{ id: string }> }) => {
  const u = await requireUser();
  const id = z.string().uuid().parse((await ctx.params).id);
  const b = z.object({
    name: z.string().trim().min(2).max(60).optional(),
    phone: z.string().nullable().optional(),
    isActive: z.boolean().optional(),
    newLink: z.boolean().optional(),
  }).parse(await req.json());
  const phone = b.phone === undefined ? undefined : b.phone ? normalizeBrPhone(b.phone) : null;
  if (b.phone && !phone) throw new HttpError(400, "Celular inválido. Use DDD + número.");
  const row = await one(
    `update couriers set
       name = coalesce($3, name),
       phone = case when $4::boolean then $5 else phone end,
       is_active = coalesce($6, is_active),
       access_token = case when $7::boolean then $8 else access_token end
     where id = $1 and store_id = $2 returning id`,
    [id, u.store_id, b.name ?? null, phone !== undefined, phone ?? null, b.isActive ?? null, !!b.newLink, randomToken(18)]);
  if (!row) throw new HttpError(404, "Motoboy não encontrado.");
  return NextResponse.json({ ok: true });
});
