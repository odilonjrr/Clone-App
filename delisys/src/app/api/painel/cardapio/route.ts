import { NextResponse } from "next/server";
import { requireUser } from "@/lib/auth";
import { handle } from "@/lib/http";
import { getMenu } from "@/lib/store";

export const GET = handle(async () => {
  const u = await requireUser();
  return NextResponse.json(await getMenu(u.store_id, { includeInactive: true }), { headers: { "cache-control": "no-store" } });
});
