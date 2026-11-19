import { NextResponse } from "next/server";
import { z } from "zod";
import { requireUser } from "@/lib/auth";
import { handle } from "@/lib/http";
import { createCategory } from "@/lib/menu-admin";

export const POST = handle(async (req: Request) => {
  const u = await requireUser();
  const { name } = z.object({ name: z.string().trim().min(2, "Informe o nome da categoria.").max(40) }).parse(await req.json());
  return NextResponse.json(await createCategory(u.store_id, name));
});
