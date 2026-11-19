import { NextResponse } from "next/server";
import { requireUser } from "@/lib/auth";
import { handle } from "@/lib/http";
import { createProduct, ProductInput } from "@/lib/menu-admin";

export const POST = handle(async (req: Request) => {
  const u = await requireUser();
  const id = await createProduct(u.store_id, ProductInput.parse(await req.json()));
  return NextResponse.json({ id });
});
