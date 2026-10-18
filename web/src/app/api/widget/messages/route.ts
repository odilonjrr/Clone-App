import { DataError, getWidgetMessages, postWidgetMessage } from "@/lib/data";

function fail(e: unknown) {
  if (e instanceof DataError) return Response.json({ error: e.code }, { status: e.code === "forbidden_domain" ? 403 : e.code === "invalid" ? 400 : 404 });
  throw e;
}

export async function GET(req: Request) {
  const u = new URL(req.url);
  const key = u.searchParams.get("key");
  const visitor = u.searchParams.get("visitor");
  if (!key || !visitor) return Response.json({ error: "missing params" }, { status: 400 });
  try {
    return Response.json(await getWidgetMessages(key, visitor, u.searchParams.get("host") ?? undefined), { headers: { "cache-control": "no-store" } });
  } catch (e) {
    return fail(e);
  }
}

export async function POST(req: Request) {
  const b = (await req.json().catch(() => ({}))) as { key?: string; visitorId?: string; body?: string; host?: string; fields?: { name?: string; email?: string; phone?: string } };
  if (!b.key || !b.visitorId || typeof b.body !== "string") return Response.json({ error: "missing params" }, { status: 400 });
  try {
    await postWidgetMessage(b.key, b.visitorId, b.body, b.host, b.fields);
    return Response.json({ ok: true });
  } catch (e) {
    return fail(e);
  }
}
