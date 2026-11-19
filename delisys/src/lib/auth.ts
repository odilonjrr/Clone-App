import "server-only";
import { scrypt as _scrypt, randomBytes, timingSafeEqual } from "node:crypto";
import { promisify } from "node:util";
import { cookies } from "next/headers";
import { one, q } from "./db";
import { randomToken, sha256 } from "./tokens";

const scrypt = promisify(_scrypt) as (p: string, s: Buffer, n: number) => Promise<Buffer>;
const COOKIE = "dl_sess";
const DAYS = 30;

export async function hashPassword(pw: string) {
  const salt = randomBytes(16);
  const key = await scrypt(pw, salt, 64);
  return `scrypt$${salt.toString("base64")}$${key.toString("base64")}`;
}
export async function verifyPassword(pw: string, stored: string) {
  const [alg, s, k] = stored.split("$");
  if (alg !== "scrypt" || !s || !k) return false;
  const key = await scrypt(pw, Buffer.from(s, "base64"), 64);
  const expected = Buffer.from(k, "base64");
  return expected.length === key.length && timingSafeEqual(expected, key);
}

export type PanelUser = { id: string; name: string; email: string; role: string; store_id: string };

export async function startSession(userId: string) {
  const token = randomToken(32);
  await q(
    `insert into sessions (user_id, token_hash, expires_at) values ($1, $2, now() + make_interval(days => $3))`,
    [userId, sha256(token), DAYS],
  );
  (await cookies()).set(COOKIE, token, {
    httpOnly: true,
    sameSite: "lax",
    path: "/",
    secure: process.env.NODE_ENV === "production",
    maxAge: DAYS * 86400,
  });
}

export async function endSession() {
  const jar = await cookies();
  const t = jar.get(COOKIE)?.value;
  if (t) await q(`update sessions set expires_at = now() where token_hash = $1`, [sha256(t)]);
  jar.set(COOKIE, "", { path: "/", maxAge: 0 });
}

export async function currentUser(): Promise<PanelUser | null> {
  const t = (await cookies()).get(COOKIE)?.value;
  if (!t) return null;
  return one<PanelUser>(
    `select u.id, u.name, u.email, u.role, u.store_id from sessions s join users u on u.id = s.user_id
     where s.token_hash = $1 and s.expires_at > now()`,
    [sha256(t)],
  );
}

export class HttpError extends Error {
  constructor(
    public status: number,
    message: string,
  ) {
    super(message);
  }
}

export async function requireUser() {
  const u = await currentUser();
  if (!u) throw new HttpError(401, "Faça login de novo para continuar.");
  return u;
}
