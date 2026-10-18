import { NextResponse, type NextRequest } from "next/server";
import { getFrameAncestors } from "@/lib/data";

// O iframe do widget só pode ser embutido nos domínios liberados pelo cliente.
// O navegador aplica a regra (CSP frame-ancestors); a lista vazia libera todos.
export function proxy(request: NextRequest) {
  const key = decodeURIComponent(request.nextUrl.pathname.split("/")[2] ?? "");
  const domains = getFrameAncestors(key);
  const sources = domains.length ? ["'self'", ...domains.flatMap((d) => [`https://${d}`, `http://${d}`])] : ["*"];
  const res = NextResponse.next();
  res.headers.set("Content-Security-Policy", `frame-ancestors ${sources.join(" ")}`);
  return res;
}

export const config = { matcher: "/w/:key*" };
