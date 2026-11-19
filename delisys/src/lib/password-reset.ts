import "server-only";
import { HttpError, hashPassword } from "./auth";
import { one, tx } from "./db";
import { emailConfigured, escapeHtml, sendEmail } from "./email";
import { randomToken, sha256 } from "./tokens";

const MINUTES = 30;

/**
 * Pede a redefinição. Sempre responde igual, exista ou não a conta, para não revelar
 * quais e-mails estão cadastrados. Em desenvolvimento sem e-mail configurado, devolve o
 * link para os testes; em produção, nunca.
 */
export async function requestReset(email: string, baseUrl: string): Promise<{ devLink?: string }> {
  const user = await one<{ id: string; name: string }>(`select id, name from users where email = $1`, [email]);
  if (!user) return {};
  const token = randomToken(32);
  await one(
    `insert into password_resets (user_id, token_hash, expires_at) values ($1, $2, now() + make_interval(mins => $3)) returning id`,
    [user.id, sha256(token), MINUTES],
  );
  const link = `${baseUrl}/painel/redefinir-senha?token=${encodeURIComponent(token)}`;
  if (emailConfigured()) {
    const first = user.name.split(" ")[0];
    await sendEmail(
      email,
      "Redefinir sua senha do Delisys",
      `Olá, ${first}!\n\nPara criar uma senha nova no painel do Delisys, abra este link em até ${MINUTES} minutos:\n${link}\n\nSe não foi você que pediu, ignore este e-mail: sua senha atual continua valendo.`,
      `<p>Olá, ${escapeHtml(first)}!</p><p>Para criar uma senha nova no painel do Delisys, abra este link em até ${MINUTES} minutos:</p><p><a href="${escapeHtml(link)}">Criar senha nova</a></p><p>Se não foi você que pediu, ignore este e-mail: sua senha atual continua valendo.</p>`,
    );
    return {};
  }
  if (process.env.NODE_ENV !== "production") return { devLink: link };
  throw new HttpError(503, "A recuperação de senha por e-mail ainda não foi configurada. Fale com quem administra o Delisys.");
}

/** Troca a senha com um link válido. Encerra todas as sessões abertas da conta. */
export async function resetPassword(token: string, password: string) {
  const hash = await hashPassword(password);
  await tx(async (t) => {
    const [r] = await t.query<{ id: string; user_id: string }>(
      `select id, user_id from password_resets
       where token_hash = $1 and used_at is null and expires_at > now() for update`,
      [sha256(token)],
    );
    if (!r) throw new HttpError(400, "Este link expirou ou já foi usado. Peça um novo.");
    await t.query(`update password_resets set used_at = now() where user_id = $1 and used_at is null`, [r.user_id]);
    await t.query(`update users set password_hash = $2 where id = $1`, [r.user_id, hash]);
    await t.query(`update sessions set expires_at = now() where user_id = $1 and expires_at > now()`, [r.user_id]);
  });
}
