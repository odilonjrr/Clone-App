import "server-only";
import { HttpError } from "./auth";
import { q } from "./db";
import { sha256 } from "./tokens";

/**
 * Limite de tentativas guardado no Postgres: vale para todas as instâncias da Vercel.
 * Janela fixa: no máximo `max` acessos por `windowSec` para a mesma chave.
 * A chave é guardada como hash, então nem IP nem e-mail ficam salvos em texto.
 */
export async function rateLimit(key: string, max: number, windowSec: number) {
  const now = Math.floor(Date.now() / 1000);
  const windowStart = new Date((now - (now % windowSec)) * 1000).toISOString();
  const [row] = await q<{ hits: number }>(
    `insert into rate_limits (key_hash, window_start, hits) values ($1, $2, 1)
     on conflict (key_hash, window_start) do update set hits = rate_limits.hits + 1
     returning hits`,
    [sha256(key), windowStart],
  );
  if (Math.random() < 0.02) void cleanup().catch(() => {});
  if (row && row.hits > max) {
    const wait = Math.ceil((windowSec - (now % windowSec)) / 60);
    throw new HttpError(429, `Muitas tentativas seguidas. Tente de novo em ${wait} ${wait === 1 ? "minuto" : "minutos"}.`);
  }
}

/** Limpeza de dados vencidos, rodada de vez em quando (ver política de privacidade). */
export async function cleanup() {
  await q(`delete from rate_limits where window_start < now() - interval '1 day'`);
  await q(`delete from sessions where expires_at < now() - interval '7 days'`);
  await q(`delete from password_resets where expires_at < now() - interval '7 days'`);
  // posição do motoboy: apagada 24 h depois que a entrega termina (ou 48 h após a última atualização)
  await q(`delete from courier_positions cp using orders o
           where o.id = cp.order_id and (o.status in ('entregue','cancelado') and coalesce(o.delivered_at, o.canceled_at, o.updated_at) < now() - interval '24 hours'
                                         or cp.updated_at < now() - interval '48 hours')`);
}
