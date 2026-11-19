export type OrderStatus =
  | "aguardando_pagamento" | "realizado" | "em_preparo" | "pronto" | "em_entrega" | "entregue" | "cancelado";
export type Fulfillment = "delivery" | "pickup";

export const STATUS_LABEL: Record<OrderStatus, string> = {
  aguardando_pagamento: "Aguardando pagamento",
  realizado: "Pedido realizado",
  em_preparo: "Em preparação",
  pronto: "Pronto",
  em_entrega: "Saiu para entrega",
  entregue: "Entregue",
  cancelado: "Cancelado",
};

/** Etapas que geram aviso no WhatsApp (pedido do usuário: realizado → preparo → pronto → saiu). */
export const NOTIFY_STAGES = ["realizado", "em_preparo", "pronto", "em_entrega"] as const;
export type NotifyStage = (typeof NOTIFY_STAGES)[number];

export type Actor = "cliente" | "pagamento" | "painel" | "motoboy";

/** Próximas etapas permitidas, por quem pode mover. */
export function allowedNext(from: OrderStatus, fulfillment: Fulfillment, actor: Actor): OrderStatus[] {
  if (actor === "pagamento") return from === "aguardando_pagamento" ? ["realizado"] : [];
  if (actor === "cliente") return [];
  if (actor === "motoboy") {
    if (fulfillment !== "delivery") return [];
    if (from === "pronto") return ["em_entrega"];
    if (from === "em_entrega") return ["entregue"];
    return [];
  }
  // painel
  switch (from) {
    case "aguardando_pagamento": return ["cancelado"];
    case "realizado": return ["em_preparo", "cancelado"];
    case "em_preparo": return ["pronto", "cancelado"];
    case "pronto": return fulfillment === "delivery" ? ["em_entrega", "cancelado"] : ["entregue", "cancelado"];
    case "em_entrega": return ["entregue"];
    default: return [];
  }
}

export function canMove(from: OrderStatus, to: OrderStatus, fulfillment: Fulfillment, actor: Actor) {
  return allowedNext(from, fulfillment, actor).includes(to);
}

/** Linha do tempo mostrada ao cliente. */
export function timelineFor(fulfillment: Fulfillment): OrderStatus[] {
  return fulfillment === "delivery"
    ? ["realizado", "em_preparo", "pronto", "em_entrega", "entregue"]
    : ["realizado", "em_preparo", "pronto", "entregue"];
}
