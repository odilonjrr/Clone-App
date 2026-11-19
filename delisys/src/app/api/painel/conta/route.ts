import { NextResponse } from "next/server";
import { z } from "zod";
import { HttpError, startSession } from "@/lib/auth";
import { one } from "@/lib/db";
import { createStoreWithOwner } from "@/lib/demo";
import { clientIp, handle} from "@/lib/http";
import { rateLimit } from "@/lib/rate-limit";
import { normalizeBrPhone } from "@/lib/phone";

const Body = z.object({
  storeName: z.string().trim().min(2, "Informe o nome da loja.").max(60),
  slug: z.string().trim().toLowerCase().regex(/^[a-z0-9]+(-[a-z0-9]+)*$/, "O endereço só aceita letras minúsculas, números e hífen.").min(3, "O endereço precisa de pelo menos 3 letras.").max(40),
  ownerName: z.string().trim().min(2, "Informe seu nome.").max(60),
  email: z.string().trim().toLowerCase().email("E-mail inválido."),
  password: z.string().min(8, "A senha precisa de pelo menos 8 caracteres.").max(200),
  whatsapp: z.string().optional(),
  withDemoMenu: z.boolean().default(true),
  setupCode: z.string().optional(),
});

export const POST = handle(async (req: Request) => {
  await rateLimit(`signup:${clientIp(req)}`, 5, 600);
  const b = Body.parse(await req.json());
  if (process.env.SETUP_CODE && b.setupCode !== process.env.SETUP_CODE)
    throw new HttpError(403, "Código de criação inválido. Peça o código a quem administra o Delisys.");
  if (["painel", "api", "r", "m", "demo", "_next"].includes(b.slug)) throw new HttpError(409, "Esse endereço é reservado. Escolha outro.");
  const phone = b.whatsapp ? normalizeBrPhone(b.whatsapp) : null;
  if (b.whatsapp && !phone) throw new HttpError(400, "WhatsApp inválido. Use DDD + número.");
  const { userId } = await createStoreWithOwner({ ...b, whatsapp: phone });
  await startSession(userId);
  const s = await one<{ slug: string }>(`select slug from stores s join users u on u.store_id = s.id where u.id = $1`, [userId]);
  return NextResponse.json({ ok: true, slug: s?.slug });
});
