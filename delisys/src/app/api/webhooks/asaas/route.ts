import { NextResponse } from "next/server";
import { timingSafeEqual } from "node:crypto";
import { q } from "@/lib/db";
import { appUrl, handle } from "@/lib/http";
import { markPaid } from "@/lib/orders";
import { ASAAS_PAID_EVENTS } from "@/lib/payments";

function sameSecret(a: string, b: string) {
  const x = Buffer.from(a), y = Buffer.from(b);
  return x.length === y.length && timingSafeEqual(x, y);
}

/**
 * Webhook do Asaas. Cadastre no painel do Asaas a URL https://SEU-APP/api/webhooks/asaas
 * e um token de autenticação igual a ASAAS_WEBHOOK_TOKEN.
 * Idempotente: cada evento é gravado uma vez em webhook_events.
 */
export const POST = handle(async (req: Request) => {
  const expected = process.env.ASAAS_WEBHOOK_TOKEN;
  const got = req.headers.get("asaas-access-token") ?? "";
  if (!expected || !sameSecret(got, expected)) return NextResponse.json({ error: "não autorizado" }, { status: 401 });
  const body = (await req.json()) as { id?: string; event?: string; payment?: { id?: string; externalReference?: string } };
  const paymentId = body.payment?.id;
  if (!body.event || !paymentId) return NextResponse.json({ ok: true });
  const eventId = body.id ?? `${body.event}:${paymentId}`;
  const fresh = await q(`insert into webhook_events (provider, event_id, payload) values ('asaas', $1, $2) on conflict do nothing returning id`, [eventId, JSON.stringify(body)]);
  if (!fresh.length) return NextResponse.json({ ok: true, duplicate: true });
  if (ASAAS_PAID_EVENTS.has(body.event)) await markPaid("asaas", paymentId, appUrl(req));
  return NextResponse.json({ ok: true });
});
