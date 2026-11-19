import "server-only";
import fs from "node:fs";
import path from "node:path";

/**
 * Acesso ao banco. Produção: Postgres pela DATABASE_URL (pg).
 * Desenvolvimento e testes sem DATABASE_URL: PGlite em .data/pglite, migrado sozinho.
 * Toda consulta usa parâmetros ($1, $2...), nunca texto concatenado.
 */
export type Row = Record<string, unknown>;
export interface Queryable {
  query<T = Row>(sql: string, params?: unknown[]): Promise<T[]>;
}
interface Db extends Queryable {
  tx<T>(fn: (q: Queryable) => Promise<T>): Promise<T>;
}

const g = globalThis as unknown as { __delisysDb?: Promise<Db> };

export function migrationsDir() {
  return path.join(process.cwd(), "src", "db", "migrations");
}

export async function runMigrations(q: Queryable) {
  await q.query(`create table if not exists _migrations (name text primary key, applied_at timestamptz not null default now())`);
  const done = new Set((await q.query<{ name: string }>(`select name from _migrations`)).map((r) => r.name));
  const files = fs.readdirSync(migrationsDir()).filter((f) => f.endsWith(".sql")).sort();
  const applied: string[] = [];
  for (const f of files) {
    if (done.has(f)) continue;
    const sql = fs.readFileSync(path.join(migrationsDir(), f), "utf8");
    await execScript(q, sql);
    await q.query(`insert into _migrations (name) values ($1)`, [f]);
    applied.push(f);
  }
  return applied;
}

// PGlite e pg executam vários comandos de uma vez quando não há parâmetros.
async function execScript(q: Queryable, sql: string) {
  const anyQ = q as unknown as { exec?: (s: string) => Promise<unknown> };
  if (anyQ.exec) await anyQ.exec(sql);
  else await q.query(sql);
}

async function createPg(url: string): Promise<Db> {
  const { Pool } = await import("pg");
  const pool = new Pool({
    connectionString: url,
    max: Number(process.env.DB_POOL_MAX ?? 5),
    ssl: /localhost|127\.0\.0\.1/.test(url) ? undefined : { rejectUnauthorized: false },
  });
  const wrap = (c: { query: (s: string, p?: unknown[]) => Promise<{ rows: unknown[] }> }): Queryable => ({
    async query<T>(s: string, p?: unknown[]) { return (await c.query(s, p)).rows as T[]; },
  });
  return {
    ...wrap(pool),
    async tx(fn) {
      const client = await pool.connect();
      try {
        await client.query("begin");
        const out = await fn(wrap(client));
        await client.query("commit");
        return out;
      } catch (e) {
        await client.query("rollback").catch(() => {});
        throw e;
      } finally {
        client.release();
      }
    },
  };
}

async function createPglite(): Promise<Db> {
  const { PGlite } = await import("@electric-sql/pglite");
  const dir = process.env.PGLITE_DIR ?? path.join(process.cwd(), ".data", "pglite");
  fs.mkdirSync(path.dirname(dir), { recursive: true });
  const pg = new PGlite(dir);
  await pg.waitReady;
  const base: Queryable & { exec: (s: string) => Promise<unknown> } = {
    async query<T>(s: string, p?: unknown[]) { return (await pg.query<T>(s, p)).rows; },
    exec: (s: string) => pg.exec(s),
  };
  await runMigrations(base);
  // PGlite tem uma conexão só: as transações rodam em fila.
  let chain: Promise<unknown> = Promise.resolve();
  return {
    query: base.query,
    tx<T>(fn: (q: Queryable) => Promise<T>) {
      const run = chain.then(() =>
        pg.transaction(async (t) => fn({ async query<U>(s: string, p?: unknown[]) { return (await t.query<U>(s, p)).rows; } })),
      );
      chain = run.catch(() => {});
      return run as Promise<T>;
    },
  };
}

export function db(): Promise<Db> {
  if (!g.__delisysDb) {
    const url = process.env.DATABASE_URL;
    if (!url && (process.env.VERCEL || process.env.NODE_ENV === "production" && !process.env.ALLOW_LOCAL_DB))
      throw new Error("DATABASE_URL não configurada. Conecte um banco Postgres (veja delisys/docs/colocar-no-ar.md).");
    g.__delisysDb = url ? createPg(url) : createPglite();
  }
  return g.__delisysDb;
}

export async function q<T = Row>(sql: string, params?: unknown[]) {
  return (await db()).query<T>(sql, params);
}
export async function one<T = Row>(sql: string, params?: unknown[]) {
  return (await q<T>(sql, params))[0] ?? null;
}
export async function tx<T>(fn: (q: Queryable) => Promise<T>) {
  return (await db()).tx(fn);
}
