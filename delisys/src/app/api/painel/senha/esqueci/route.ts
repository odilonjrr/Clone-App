import { NextResponse } from "next/server";
import { z } from "zod";
import { appUrl, clientIp, handle } from "@/lib/http";
import { requestReset } from "@/lib/password-reset";
import { rateLimit } from "@/lib/rate-limit";

export const POST = handle(async (req: Request) => {
  await rateLimit(`reset-ip:${clientIp(req)}`, 5, 900);
  const { email } = z.object({ email: z.string().trim().toLowerCase().email("E-mail inválido.") }).parse(await req.json());
  await rateLimit(`reset-email:${email}`, 3, 900);
  const r = await requestReset(email, appUrl(req));
  return NextResponse.json({ ok: true, ...(r.devLink ? { devLink: r.devLink } : {}) });
});
