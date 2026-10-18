// Formatação com fuso explícito: servidor e navegador produzem o mesmo texto,
// sem erro de hidratação.
export const APP_TZ = "America/Sao_Paulo";

export function formatTime(iso: string, timeZone = APP_TZ) {
  return new Intl.DateTimeFormat("pt-BR", { timeZone, hour: "2-digit", minute: "2-digit" }).format(new Date(iso));
}

export function formatDateTime(iso: string, timeZone = APP_TZ) {
  return new Intl.DateTimeFormat("pt-BR", { timeZone, day: "2-digit", month: "2-digit", hour: "2-digit", minute: "2-digit" }).format(new Date(iso));
}

/** "14:02" se for hoje, "Ontem", ou "12/10". */
export function formatListTime(iso: string, now = new Date(), timeZone = APP_TZ) {
  const day = (d: Date) => new Intl.DateTimeFormat("en-CA", { timeZone, dateStyle: "short" }).format(d);
  const d = new Date(iso);
  if (day(d) === day(now)) return formatTime(iso, timeZone);
  if (day(d) === day(new Date(now.getTime() - 86_400_000))) return "Ontem";
  return new Intl.DateTimeFormat("pt-BR", { timeZone, day: "2-digit", month: "2-digit" }).format(d);
}

export function formatOpening(d: Date, timeZone = APP_TZ) {
  return new Intl.DateTimeFormat("pt-BR", { timeZone, weekday: "long", hour: "2-digit", minute: "2-digit" }).format(d);
}
