import { NextResponse } from "next/server";
import { timingSafeEqual } from "node:crypto";
import { handle } from "@/lib/http";
import { cleanup } from "@/lib/rate-limit";

/** Limpeza diária (Vercel Cron, ver vercel.json). A Vercel envia "Authorization: Bearer CRON_SECRET". */
export const GET = handle(async (req: Request) => {
  const secret = process.env.CRON_SECRET;
  const got = req.headers.get("authorization") ?? "";
  const want = `Bearer ${secret}`;
  if (!secret || got.length !== want.length || !timingSafeEqual(Buffer.from(got), Buffer.from(want)))
    return NextResponse.json({ error: "não autorizado" }, { status: 401 });
  await cleanup();
  return NextResponse.json({ ok: true });
});
