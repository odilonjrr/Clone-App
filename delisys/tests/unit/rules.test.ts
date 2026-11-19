import { describe, expect, it } from "vitest";
import { priceCart, type MenuProduct } from "@/lib/pricing";
import { allowedNext, canMove } from "@/lib/status";
import { normalizeBrPhone, formatBrPhone } from "@/lib/phone";
import { formatBRL, parseBRL } from "@/lib/money";
import { stageMessage } from "@/lib/messages";
import { googleMapsDirections } from "@/lib/maps";

const pizza: MenuProduct = {
  id: "pz", name: "Pizza Grande", price_cents: 0, is_available: true,
  groups: [
    { id: "sab", name: "Sabores", min_select: 1, max_select: 2, pricing: "max", options: [
      { id: "cal", name: "Calabresa", price_cents: 5490, is_available: true },
      { id: "4q", name: "Quatro queijos", price_cents: 6290, is_available: true },
      { id: "x", name: "Esgotada", price_cents: 5000, is_available: false },
    ] },
    { id: "borda", name: "Borda", min_select: 0, max_select: 1, pricing: "sum", options: [
      { id: "cat", name: "Catupiry", price_cents: 900, is_available: true },
    ] },
  ],
};
const menu = new Map([[pizza.id, pizza]]);

describe("preço", () => {
  it("meio a meio cobra o sabor mais caro e soma a borda", () => {
    const r = priceCart(menu, [{ productId: "pz", quantity: 2, options: { sab: ["cal", "4q"], borda: ["cat"] } }]);
    expect(r.ok).toBe(true);
    if (r.ok) { expect(r.lines[0].unitPriceCents).toBe(6290 + 900); expect(r.subtotalCents).toBe(2 * 7190); }
  });
  it("exige o mínimo de sabores", () => {
    const r = priceCart(menu, [{ productId: "pz", quantity: 1, options: {} }]);
    expect(r.ok).toBe(false);
  });
  it("recusa acima do máximo, opção esgotada e opção inexistente", () => {
    expect(priceCart(menu, [{ productId: "pz", quantity: 1, options: { sab: ["cal", "4q", "x"] } }]).ok).toBe(false);
    expect(priceCart(menu, [{ productId: "pz", quantity: 1, options: { sab: ["x"] } }]).ok).toBe(false);
    expect(priceCart(menu, [{ productId: "pz", quantity: 1, options: { sab: ["nao-existe"] } }]).ok).toBe(false);
  });
  it("recusa quantidade inválida e sacola vazia", () => {
    expect(priceCart(menu, [{ productId: "pz", quantity: 0, options: { sab: ["cal"] } }]).ok).toBe(false);
    expect(priceCart(menu, []).ok).toBe(false);
  });
});

describe("etapas", () => {
  it("segue realizado → preparo → pronto → saiu → entregue no painel", () => {
    expect(canMove("realizado", "em_preparo", "delivery", "painel")).toBe(true);
    expect(canMove("em_preparo", "pronto", "delivery", "painel")).toBe(true);
    expect(canMove("pronto", "em_entrega", "delivery", "painel")).toBe(true);
    expect(canMove("em_entrega", "entregue", "delivery", "painel")).toBe(true);
  });
  it("não pula etapas nem volta", () => {
    expect(canMove("realizado", "pronto", "delivery", "painel")).toBe(false);
    expect(canMove("pronto", "em_preparo", "delivery", "painel")).toBe(false);
  });
  it("retirada não passa por entrega", () => {
    expect(allowedNext("pronto", "pickup", "painel")).toEqual(["entregue", "cancelado"]);
  });
  it("motoboy só move entrega e pagamento só confirma", () => {
    expect(allowedNext("pronto", "delivery", "motoboy")).toEqual(["em_entrega"]);
    expect(allowedNext("em_preparo", "delivery", "motoboy")).toEqual([]);
    expect(allowedNext("aguardando_pagamento", "delivery", "pagamento")).toEqual(["realizado"]);
    expect(allowedNext("realizado", "delivery", "cliente")).toEqual([]);
  });
});

describe("telefone e dinheiro", () => {
  it("normaliza celulares brasileiros", () => {
    expect(normalizeBrPhone("(11) 98765-4321")).toBe("5511987654321");
    expect(normalizeBrPhone("+55 11 98765-4321")).toBe("5511987654321");
    expect(normalizeBrPhone("1133334444")).toBe("551133334444");
    expect(normalizeBrPhone("98765-4321")).toBeNull();
    expect(normalizeBrPhone("11 88765-4321")).toBeNull();
    expect(formatBrPhone("5511987654321")).toBe("(11) 98765-4321");
  });
  it("formata e lê reais", () => {
    expect(formatBRL(2990)).toBe("R$ 29,90");
    expect(parseBRL("1.234,56")).toBe(123456);
    expect(parseBRL("50")).toBe(5000);
    expect(parseBRL("abc")).toBeNull();
  });
});

describe("mensagens e mapa", () => {
  const ctx = { customerFirstName: "Ana", orderNumber: 1042, storeName: "Brasa", totalCents: 5980, fulfillment: "delivery" as const, trackingUrl: "https://x.app/r/abc", courierName: "Rafael Souza" };
  it("cada etapa tem texto e modelo, e o de entrega leva o link", () => {
    for (const s of ["realizado", "em_preparo", "pronto", "em_entrega"] as const) {
      const m = stageMessage(s, ctx);
      expect(m.template).toMatch(/^delisys_/);
      expect(m.text).toContain("#1042");
    }
    expect(stageMessage("em_entrega", ctx).text).toContain("https://x.app/r/abc");
    expect(stageMessage("em_entrega", ctx).text).toContain("Rafael");
  });
  it("monta o link do Google Maps por coordenada ou endereço", () => {
    expect(googleMapsDirections({ street: "Rua A", number: "10", district: "Centro", city: "SP", lat: -23.5, lng: -46.6 })).toContain("destination=-23.5%2C-46.6");
    expect(googleMapsDirections({ street: "Rua A", number: "10", district: "Centro", city: "SP" })).toContain("Rua%20A%2C%2010");
  });
});
