import { NextResponse } from "next/server";
import { z } from "zod";
import { HttpError, requireUser } from "@/lib/auth";
import { q } from "@/lib/db";
import { appUrl, handle } from "@/lib/http";
import { courierUrl } from "@/lib/orders";
import { normalizeBrPhone } from "@/lib/phone";
import { randomToken } from "@/lib/tokens";

export const GET = handle(async (req: Request) => {
  const u = await requireUser();
  const rows = await q<{ id: string; name: string; phone: string | null; access_token: string; is_active: boolean; open_orders: number }>(
    `select c.id, c.name, c.phone, c.access_token, c.is_active,
       (select count(*)::int from orders o where o.courier_id = c.id and o.status in ('pronto','em_entrega')) as open_orders
     from couriers c where c.store_id = $1 order by c.is_active desc, c.name`, [u.store_id]);
  const base = appUrl(req);
  return NextResponse.json({ couriers: rows.map(({ access_token, ...c }) => ({ ...c, link: courierUrl(base, access_token) })) });
});

export const POST = handle(async (req: Request) => {
  const u = await requireUser();
  const b = z.object({ name: z.string().trim().min(2, "Informe o nome do motoboy.").max(60), phone: z.string().optional() }).parse(await req.json());
  const phone = b.phone ? normalizeBrPhone(b.phone) : null;
  if (b.phone && !phone) throw new HttpError(400, "Celular inválido. Use DDD + número.");
  await q(`insert into couriers (store_id, name, phone, access_token) values ($1,$2,$3,$4)`, [u.store_id, b.name, phone, randomToken(18)]);
  return NextResponse.json({ ok: true });
});
