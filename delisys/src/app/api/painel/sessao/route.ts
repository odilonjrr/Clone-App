import { NextResponse } from "next/server";
import { z } from "zod";
import { endSession, HttpError, startSession, verifyPassword } from "@/lib/auth";
import { one } from "@/lib/db";
import { clientIp, handle, rateLimit } from "@/lib/http";

const Body = z.object({ email: z.string().trim().toLowerCase().email("E-mail inválido."), password: z.string().min(1, "Informe a senha.") });

export const POST = handle(async (req: Request) => {
  rateLimit(`login:${clientIp(req)}`, 10, 10 * 60_000);
  const b = Body.parse(await req.json());
  const u = await one<{ id: string; password_hash: string }>(`select id, password_hash from users where email = $1`, [b.email]);
  if (!u || !(await verifyPassword(b.password, u.password_hash))) throw new HttpError(401, "E-mail ou senha não conferem.");
  await startSession(u.id);
  return NextResponse.json({ ok: true });
});

export const DELETE = handle(async () => {
  await endSession();
  return NextResponse.json({ ok: true });
});
