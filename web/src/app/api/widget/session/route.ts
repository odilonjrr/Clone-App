import { DataError, startWidgetSession } from "@/lib/data";

export async function POST(req: Request) {
  const { key, visitorId, host } = (await req.json().catch(() => ({}))) as { key?: string; visitorId?: string; host?: string };
  if (!key) return Response.json({ error: "missing key" }, { status: 400 });
  try {
    return Response.json(await startWidgetSession(key, visitorId, host));
  } catch (e) {
    if (e instanceof DataError) return Response.json({ error: e.code }, { status: e.code === "forbidden_domain" ? 403 : 404 });
    throw e;
  }
}
