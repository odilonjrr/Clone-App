import { describe, expect, it } from "vitest";
import { estimate, haversineKm, rideMinutes } from "@/lib/eta";

const now = new Date("2026-11-19T20:00:00-03:00");
const base = { placedAt: "2026-11-19T19:50:00-03:00", readyAt: null, deliveredAt: null, etaMinMinutes: 30, etaMaxMinutes: 50, courier: null, destination: null, now };

describe("previsão", () => {
  it("distância e tempo de moto", () => {
    const km = haversineKm({ lat: -23.5899, lng: -46.6345 }, { lat: -23.5810, lng: -46.6345 });
    expect(km).toBeGreaterThan(0.9); expect(km).toBeLessThan(1.1);
    expect(rideMinutes({ lat: -23.5899, lng: -46.6345 }, { lat: -23.5810, lng: -46.6345 })).toBe(4);
    expect(rideMinutes({ lat: 0, lng: 0 }, { lat: 0, lng: 0 })).toBe(2);
  });
  it("entrega em andamento usa a posição do motoboy", () => {
    const e = estimate({ ...base, status: "em_entrega", fulfillment: "delivery", courier: { lat: -23.5899, lng: -46.6345 }, destination: { lat: -23.5810, lng: -46.6345 } });
    expect(e.kind).toBe("chegada");
    expect(e.at!.getTime() - now.getTime()).toBe(4 * 60000);
  });
  it("antes de sair usa o prazo máximo da loja a partir do pedido", () => {
    const e = estimate({ ...base, status: "em_preparo", fulfillment: "delivery" });
    expect(e.at!.toISOString()).toBe(new Date("2026-11-19T20:40:00-03:00").toISOString());
  });
  it("nunca mostra horário que já passou", () => {
    const e = estimate({ ...base, placedAt: "2026-11-19T18:00:00-03:00", status: "em_preparo", fulfillment: "delivery" });
    expect(e.at!.getTime()).toBe(now.getTime() + 10 * 60000);
  });
  it("retirada: prazo mínimo; pronto usa a hora em que ficou pronto", () => {
    expect(estimate({ ...base, status: "realizado", fulfillment: "pickup" }).at!.toISOString()).toBe(new Date("2026-11-19T20:20:00-03:00").toISOString());
    expect(estimate({ ...base, status: "pronto", fulfillment: "pickup", readyAt: "2026-11-19T19:58:00-03:00" }).kind).toBe("retirada");
  });
  it("pagamento pendente e cancelado não têm horário", () => {
    expect(estimate({ ...base, status: "aguardando_pagamento", fulfillment: "delivery" }).at).toBeNull();
    expect(estimate({ ...base, status: "cancelado", fulfillment: "delivery" }).at).toBeNull();
  });
});
