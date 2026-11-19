import "server-only";

/**
 * E-mail transacional pela API do Resend (resend.com). O usuário cria a conta, verifica o
 * domínio e define RESEND_API_KEY e EMAIL_FROM (ex.: "Delisys <nao-responda@seudominio.com.br>").
 */
export function emailConfigured() {
  return !!(process.env.RESEND_API_KEY && process.env.EMAIL_FROM);
}

export async function sendEmail(to: string, subject: string, text: string, html: string) {
  const res = await fetch("https://api.resend.com/emails", {
    method: "POST",
    headers: { authorization: `Bearer ${process.env.RESEND_API_KEY}`, "content-type": "application/json" },
    body: JSON.stringify({ from: process.env.EMAIL_FROM, to: [to], subject, text, html }),
    cache: "no-store",
    signal: AbortSignal.timeout(10_000),
  });
  if (!res.ok) {
    // só o status: o corpo pode repetir o e-mail do destinatário
    throw new Error(`Resend respondeu ${res.status}`);
  }
}

export function escapeHtml(s: string) {
  return s.replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]!);
}
