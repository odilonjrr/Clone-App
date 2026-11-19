/**
 * Previsão de chegada (entrega) ou de retirada, mostrada em letras grandes no rastreio.
 * Função pura: roda no navegador a cada atualização, com o relógio do cliente.
 */
export type Pt = { lat: number; lng: number };

export function haversineKm(a: Pt, b: Pt) {
  const R = 6371;
  const rad = (d: number) => (d * Math.PI) / 180;
  const dLat = rad(b.lat - a.lat);
  const dLng = rad(b.lng - a.lng);
  const h = Math.sin(dLat / 2) ** 2 + Math.cos(rad(a.lat)) * Math.cos(rad(b.lat)) * Math.sin(dLng / 2) ** 2;
  return 2 * R * Math.asin(Math.sqrt(h));
}

/** Minutos de moto até o destino: distância em linha reta × 1,4 (ruas) a 22 km/h, mínimo 2 min. */
export function rideMinutes(from: Pt, to: Pt) {
  return Math.max(2, Math.round(((haversineKm(from, to) * 1.4) / 22) * 60));
}

export type EtaInput = {
  status: string;
  fulfillment: "delivery" | "pickup";
  placedAt: string | null;
  readyAt: string | null;
  deliveredAt: string | null;
  etaMinMinutes: number;
  etaMaxMinutes: number;
  courier: Pt | null;
  destination: Pt | null;
  now: Date;
};

export type Eta = { at: Date | null; kind: "chegada" | "retirada" | "entregue" | "pagamento" | "cancelado" };

const addMin = (d: Date, m: number) => new Date(d.getTime() + m * 60000);

export function estimate(i: EtaInput): Eta {
  if (i.status === "cancelado") return { at: null, kind: "cancelado" };
  if (i.status === "aguardando_pagamento") return { at: null, kind: "pagamento" };
  if (i.status === "entregue") return { at: i.deliveredAt ? new Date(i.deliveredAt) : null, kind: "entregue" };

  const placed = i.placedAt ? new Date(i.placedAt) : i.now;
  // nunca mostrar um horário que já passou: no mínimo, daqui a alguns minutos
  const floor = (d: Date, min: number) => (d.getTime() < addMin(i.now, min).getTime() ? addMin(i.now, min) : d);

  if (i.fulfillment === "pickup") {
    if (i.status === "pronto") return { at: i.readyAt ? new Date(i.readyAt) : i.now, kind: "retirada" };
    // retirada: a loja costuma levar a parte "mínima" do prazo para preparar
    return { at: floor(addMin(placed, i.etaMinMinutes), 3), kind: "retirada" };
  }

  if (i.status === "em_entrega" && i.courier && i.destination)
    return { at: addMin(i.now, rideMinutes(i.courier, i.destination)), kind: "chegada" };
  if (i.status === "em_entrega") return { at: floor(addMin(placed, i.etaMaxMinutes), 5), kind: "chegada" };
  return { at: floor(addMin(placed, i.etaMaxMinutes), 10), kind: "chegada" };
}

/** Fuso usado nos horários mostrados (servidor e celular formatam igual, sem divergência). */
export const TIME_ZONE = "America/Sao_Paulo";

export function hhmm(d: Date) {
  return d.toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit", timeZone: TIME_ZONE });
}
