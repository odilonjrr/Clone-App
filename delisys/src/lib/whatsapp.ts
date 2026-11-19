import "server-only";

/**
 * WhatsApp pela API oficial da Meta (WhatsApp Cloud API).
 * Mensagens iniciadas pela loja precisam de MODELOS aprovados (templates), com os nomes
 * e as variáveis de src/lib/messages.ts (lista em docs/whatsapp-modelos.md).
 *
 * Sem WHATSAPP_TOKEN e WHATSAPP_PHONE_NUMBER_ID o app fica em modo manual: cada aviso
 * aparece no painel com um botão que abre o WhatsApp da loja com a mensagem pronta.
 */
export function whatsappMode(): "api" | "manual" {
  return process.env.WHATSAPP_TOKEN && process.env.WHATSAPP_PHONE_NUMBER_ID ? "api" : "manual";
}

export async function sendTemplate(
  toDigits: string,
  template: string,
  params: string[],
): Promise<{ ok: true; id: string } | { ok: false; error: string }> {
  const version = process.env.WHATSAPP_API_VERSION || "v21.0";
  const url = `https://graph.facebook.com/${version}/${process.env.WHATSAPP_PHONE_NUMBER_ID}/messages`;
  try {
    const res = await fetch(url, {
      method: "POST",
      headers: {
        authorization: `Bearer ${process.env.WHATSAPP_TOKEN}`,
        "content-type": "application/json",
      },
      body: JSON.stringify({
        messaging_product: "whatsapp",
        to: toDigits,
        type: "template",
        template: {
          name: template,
          language: { code: process.env.WHATSAPP_TEMPLATE_LANG || "pt_BR" },
          components: [
            {
              type: "body",
              // a Meta recusa variáveis com quebra de linha ou mais de 4 espaços seguidos
              parameters: params.map((text) => ({ type: "text", text: text.replace(/\s+/g, " ").slice(0, 900) })),
            },
          ],
        },
      }),
      cache: "no-store",
      signal: AbortSignal.timeout(10_000),
    });
    const body = (await res.json().catch(() => ({}))) as {
      messages?: { id: string }[];
      error?: { message?: string; code?: number };
    };
    if (!res.ok || !body.messages?.[0]?.id) {
      return { ok: false, error: `${res.status} ${body.error?.code ?? ""} ${body.error?.message ?? "erro desconhecido"}`.trim() };
    }
    return { ok: true, id: body.messages[0].id };
  } catch (e) {
    return { ok: false, error: e instanceof Error ? e.message : String(e) };
  }
}
