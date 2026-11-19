import { NextResponse } from "next/server";
import { z } from "zod";
import { clientIp, handle } from "@/lib/http";
import { resetPassword } from "@/lib/password-reset";
import { rateLimit } from "@/lib/rate-limit";

export const POST = handle(async (req: Request) => {
  await rateLimit(`reset-use:${clientIp(req)}`, 10, 900);
  const b = z.object({
    token: z.string().min(20).max(200),
    password: z.string().min(8, "A senha precisa de pelo menos 8 caracteres.").max(200),
  }).parse(await req.json());
  await resetPassword(b.token, b.password);
  return NextResponse.json({ ok: true });
});
