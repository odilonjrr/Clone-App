import "server-only";
import { NextResponse } from "next/server";
import { ZodError } from "zod";
import { HttpError } from "./auth";

/** Envolve um Route Handler: erros conhecidos viram JSON com mensagem para a pessoa. */
export function handle<A extends unknown[]>(fn: (...a: A) => Promise<Response>) {
  return async (...a: A) => {
    try {
      return await fn(...a);
    } catch (e) {
      if (e instanceof HttpError) return NextResponse.json({ error: e.message }, { status: e.status });
      if (e instanceof ZodError)
        return NextResponse.json({ error: e.issues[0]?.message ?? "Dados inválidos." }, { status: 400 });
      console.error(e);
      return NextResponse.json(
        { error: "Algo deu errado do nosso lado. Tente de novo em instantes." },
        { status: 500 },
      );
    }
  };
}

export function appUrl(req?: Request) {
  if (process.env.APP_URL) return process.env.APP_URL.replace(/\/$/, "");
  if (process.env.VERCEL_PROJECT_PRODUCTION_URL) return `https://${process.env.VERCEL_PROJECT_PRODUCTION_URL}`;
  if (req) return new URL(req.url).origin;
  return "http://localhost:3100";
}

/** Limite simples por chave (memória do processo). Em servidor sem estado é só um freio leve. */
const hits = new Map<string, number[]>();
export function rateLimit(key: string, max: number, windowMs: number) {
  const now = Date.now();
  const list = (hits.get(key) ?? []).filter((t) => now - t < windowMs);
  if (list.length >= max)
    throw new HttpError(429, "Muitas tentativas seguidas. Espere um pouco e tente de novo.");
  list.push(now);
  hits.set(key, list);
}

export function clientIp(req: Request) {
  return req.headers.get("x-forwarded-for")?.split(",")[0]?.trim() || "local";
}
