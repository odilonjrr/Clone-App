"use client";
/** Chamada JSON às rotas do próprio app. Lança Error com a mensagem pronta para mostrar. */
export async function api<T = unknown>(path: string, opts: { method?: string; body?: unknown; headers?: Record<string, string> } = {}): Promise<T> {
  let res: Response;
  try {
    res = await fetch(path, {
      method: opts.method ?? (opts.body ? "POST" : "GET"),
      headers: { ...(opts.body ? { "content-type": "application/json" } : {}), ...(opts.headers ?? {}) },
      body: opts.body ? JSON.stringify(opts.body) : undefined,
      cache: "no-store",
    });
  } catch {
    throw new Error("Sem conexão. Confira a internet e tente de novo.");
  }
  const data = (await res.json().catch(() => ({}))) as { error?: string };
  if (!res.ok) throw new Error(data.error ?? "Não foi possível concluir. Tente de novo.");
  return data as T;
}
