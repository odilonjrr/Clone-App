const brl = new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" });
/** 2990 → "R$ 29,90" (com espaço comum, não o espaço fino do Intl) */
export function formatBRL(cents: number) {
  return brl.format(cents / 100).replace(/ /g, " ");
}
/** "29,90" | "29.90" | "R$ 29,90" → 2990; inválido → null */
export function parseBRL(input: string): number | null {
  const s = input.replace(/[^\d,.-]/g, "").trim();
  if (!s) return null;
  const normalized = s.includes(",") ? s.replace(/\./g, "").replace(",", ".") : s;
  const n = Number(normalized);
  if (!Number.isFinite(n) || n < 0) return null;
  return Math.round(n * 100);
}
