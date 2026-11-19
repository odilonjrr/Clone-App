import { formatBRL } from "./money";
import type { Fulfillment, NotifyStage } from "./status";

export type MessageContext = {
  customerFirstName: string;
  orderNumber: number;
  storeName: string;
  totalCents: number;
  fulfillment: Fulfillment;
  trackingUrl: string;
  courierName?: string | null;
};

/**
 * Texto de cada aviso e os parâmetros do modelo aprovado na Meta.
 * Os modelos (templates) precisam existir com estes nomes e na mesma ordem de variáveis:
 * veja delisys/docs/whatsapp-modelos.md.
 */
export function stageMessage(stage: NotifyStage, c: MessageContext) {
  const n = `#${c.orderNumber}`;
  switch (stage) {
    case "realizado":
      return {
        template: "delisys_pedido_realizado",
        params: [c.customerFirstName, n, c.storeName, formatBRL(c.totalCents), c.trackingUrl],
        text: `Olá, ${c.customerFirstName}! Recebemos seu pedido ${n} na ${c.storeName}. Total: ${formatBRL(c.totalCents)}. Acompanhe aqui: ${c.trackingUrl} Vamos avisar cada etapa por esta conversa.`,
      };
    case "em_preparo":
      return {
        template: "delisys_pedido_em_preparo",
        params: [c.customerFirstName, n, c.storeName],
        text: `Olá, ${c.customerFirstName}! Seu pedido ${n} já está sendo preparado na ${c.storeName}.`,
      };
    case "pronto": {
      const next = c.fulfillment === "delivery" ? "Logo ele sai para entrega." : "Pode vir retirar no balcão.";
      return {
        template: "delisys_pedido_pronto",
        params: [c.customerFirstName, n, next],
        text: `Olá, ${c.customerFirstName}! Seu pedido ${n} está pronto. ${next} Obrigado pela preferência!`,
      };
    }
    case "em_entrega": {
      const who = c.courierName ? c.courierName.split(" ")[0] : "nosso entregador";
      return {
        template: "delisys_pedido_saiu_entrega",
        params: [c.customerFirstName, n, who, c.trackingUrl],
        text: `Olá, ${c.customerFirstName}! Seu pedido ${n} saiu para entrega com ${who}. Acompanhe a entrega em tempo real: ${c.trackingUrl} Bom apetite!`,
      };
    }
  }
}

export function waMeLink(phoneDigits: string, text: string) {
  return `https://wa.me/${phoneDigits}?text=${encodeURIComponent(text)}`;
}

export function firstName(full: string) {
  return full.trim().split(/\s+/)[0]?.slice(0, 30) || "cliente";
}
