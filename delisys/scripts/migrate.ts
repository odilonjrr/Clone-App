// Aplica as migrações pendentes na DATABASE_URL. Uso: npm run db:migrate
import fs from "node:fs";
import path from "node:path";
import pg from "pg";

async function main() {
  const url = process.env.DATABASE_URL;
  if (!url) { console.log("DATABASE_URL vazia: o banco local (PGlite) é migrado sozinho ao abrir o app."); return; }
  const client = new pg.Client({ connectionString: url, ssl: /localhost|127\.0\.0\.1/.test(url) ? undefined : { rejectUnauthorized: false } });
  await client.connect();
  try {
    await client.query("select pg_advisory_lock(424242)");
    await client.query(`create table if not exists _migrations (name text primary key, applied_at timestamptz not null default now())`);
    const done = new Set((await client.query(`select name from _migrations`)).rows.map((r) => r.name));
    const dir = path.join(process.cwd(), "src", "db", "migrations");
    for (const f of fs.readdirSync(dir).filter((x) => x.endsWith(".sql")).sort()) {
      if (done.has(f)) continue;
      await client.query("begin");
      await client.query(fs.readFileSync(path.join(dir, f), "utf8"));
      await client.query(`insert into _migrations (name) values ($1)`, [f]);
      await client.query("commit");
      console.log("aplicada:", f);
    }
    console.log("migrações em dia");
  } finally {
    await client.query("select pg_advisory_unlock(424242)").catch(() => {});
    await client.end();
  }
}
main().catch((e) => { console.error(e); process.exit(1); });
