import "server-only";
import QRCode from "qrcode";
import { HttpError } from "./auth";

/**
 * Pagamento online (Pix). Dois provedores:
 *  - mock:  modo teste. Gera um "Pix" fictício e o painel/página do pedido mostra
 *           o botão "Simular pagamento". Nada é cobrado.
 *  - asaas: Asaas API v3. Use a chave do sandbox (https://sandbox.asaas.com) para testar.
 *           O Asaas exige CPF ou CNPJ do pagador para gerar a cobrança.
 *
 * ATENÇÃO: a integração Asaas foi escrita a partir da documentação pública da API v3
 * conhecida até aqui e ainda NÃO foi testada contra o sandbox. Teste com a sua chave
 * antes de usar com clientes reais.
 */
export type PixCharge = {
  provider: "mock" | "asaas";
  providerPaymentId: string;
  payload: string;
  qrDataUrl: string;
  expiresAt: Date;
};

export function paymentProvider(): "mock" | "asaas" {
  return process.env.PAYMENT_PROVIDER === "asaas" ? "asaas" : "mock";
}
export function pixNeedsDocument() {
  return paymentProvider() === "asaas";
}

export async function createPixCharge(input: {
  orderId: string;
  orderNumber: number;
  storeName: string;
  amountCents: number;
  customerName: string;
  customerPhone: string;
  customerDocument?: string;
}): Promise<PixCharge> {
  if (paymentProvider() === "asaas") return createAsaasPix(input);
  const payload = `PIX-DE-TESTE|DELISYS|pedido ${input.orderNumber}|${(input.amountCents / 100).toFixed(2)}|${input.orderId}`;
  return {
    provider: "mock",
    providerPaymentId: `mock_${input.orderId}`,
    payload,
    qrDataUrl: await QRCode.toDataURL(payload, { margin: 1, width: 320 }),
    expiresAt: new Date(Date.now() + 30 * 60 * 1000),
  };
}

// ───────────────────────── Asaas ─────────────────────────
function asaasBase() {
  return (process.env.ASAAS_BASE_URL || "https://sandbox.asaas.com/api/v3").replace(/\/$/, "");
}
async function asaas<T>(path: string, init?: RequestInit): Promise<T> {
  const key = process.env.ASAAS_API_KEY;
  if (!key) throw new HttpError(503, "Pagamento online indisponível: a loja ainda não configurou o Pix.");
  const res = await fetch(asaasBase() + path, {
    ...init,
    headers: {
      "content-type": "application/json",
      access_token: key,
      "user-agent": "Delisys",
      ...(init?.headers ?? {}),
    },
    cache: "no-store",
  });
  const body = await res.json().catch(() => ({}));
  if (!res.ok) {
    console.error("Asaas", res.status, JSON.stringify(body).slice(0, 500));
    const desc = (body as { errors?: { description?: string }[] }).errors?.[0]?.description;
    throw new HttpError(502, desc ? `Não foi possível gerar o Pix: ${desc}` : "Não foi possível gerar o Pix agora.");
  }
  return body as T;
}

async function createAsaasPix(input: Parameters<typeof createPixCharge>[0]): Promise<PixCharge> {
  const doc = (input.customerDocument ?? "").replace(/\D/g, "");
  if (doc.length !== 11 && doc.length !== 14) throw new HttpError(400, "Informe um CPF válido para pagar com Pix.");
  const customer = await asaas<{ id: string }>("/customers", {
    method: "POST",
    body: JSON.stringify({
      name: input.customerName,
      cpfCnpj: doc,
      mobilePhone: input.customerPhone.replace(/^55/, ""),
      notificationDisabled: true,
    }),
  });
  const today = new Date().toISOString().slice(0, 10);
  const payment = await asaas<{ id: string }>("/payments", {
    method: "POST",
    body: JSON.stringify({
      customer: customer.id,
      billingType: "PIX",
      value: Number((input.amountCents / 100).toFixed(2)),
      dueDate: today,
      description: `Pedido #${input.orderNumber} - ${input.storeName}`.slice(0, 500),
      externalReference: input.orderId,
    }),
  });
  const qr = await asaas<{ encodedImage: string; payload: string; expirationDate?: string }>(
    `/payments/${payment.id}/pixQrCode`,
  );
  return {
    provider: "asaas",
    providerPaymentId: payment.id,
    payload: qr.payload,
    qrDataUrl: `data:image/png;base64,${qr.encodedImage}`,
    expiresAt: qr.expirationDate ? new Date(qr.expirationDate) : new Date(Date.now() + 24 * 3600 * 1000),
  };
}

/** Eventos do webhook do Asaas que significam "pago". */
export const ASAAS_PAID_EVENTS = new Set(["PAYMENT_RECEIVED", "PAYMENT_CONFIRMED"]);
