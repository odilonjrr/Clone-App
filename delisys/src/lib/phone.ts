/**
 * Celular brasileiro → só dígitos com DDI 55 ("5511987654321").
 * Aceita "(11) 98765-4321", "11987654321", "+55 11 98765-4321". Inválido → null.
 */
export function normalizeBrPhone(input: string): string | null {
  let d = input.replace(/\D/g, "");
  if (d.startsWith("55") && (d.length === 12 || d.length === 13)) d = d.slice(2);
  if (d.startsWith("0")) d = d.slice(1);
  if (d.length !== 10 && d.length !== 11) return null;
  const ddd = Number(d.slice(0, 2));
  if (ddd < 11 || ddd > 99) return null;
  if (d.length === 11 && d[2] !== "9") return null;
  return "55" + d;
}
/** "5511987654321" → "(11) 98765-4321" */
export function formatBrPhone(e164digits: string) {
  const d = e164digits.startsWith("55") ? e164digits.slice(2) : e164digits;
  if (d.length === 11) return `(${d.slice(0, 2)}) ${d.slice(2, 7)}-${d.slice(7)}`;
  if (d.length === 10) return `(${d.slice(0, 2)}) ${d.slice(2, 6)}-${d.slice(6)}`;
  return e164digits;
}
