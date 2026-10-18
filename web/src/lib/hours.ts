import type { ActiveHours, DayKey } from "@/lib/data/types";

const dayKeys: DayKey[] = ["sun", "mon", "tue", "wed", "thu", "fri", "sat"];

function localParts(at: Date, timeZone: string) {
  const parts = new Intl.DateTimeFormat("en-US", {
    timeZone,
    weekday: "short",
    hour: "2-digit",
    minute: "2-digit",
    hourCycle: "h23",
  }).formatToParts(at);
  const get = (t: string) => parts.find((p) => p.type === t)?.value ?? "";
  const weekday = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"].indexOf(get("weekday"));
  return { day: dayKeys[weekday], time: `${get("hour")}:${get("minute")}` };
}

export function isOpen(at: Date, alwaysOn: boolean, hours: ActiveHours, timeZone: string) {
  if (alwaysOn) return true;
  const { day, time } = localParts(at, timeZone);
  const range = hours[day];
  return !!range && range[0] < range[1] && time >= range[0] && time < range[1];
}

/**
 * Próximo instante em que o agente abre, ou null se já está aberto.
 * Anda de 15 em 15 minutos por até 8 dias, lendo a hora local pelo Intl,
 * então o horário de verão é tratado sem somar horas à mão.
 */
export function nextOpening(at: Date, alwaysOn: boolean, hours: ActiveHours, timeZone: string): Date | null | "never" {
  if (isOpen(at, alwaysOn, hours, timeZone)) return null;
  const step = 15 * 60 * 1000;
  let t = Math.ceil(at.getTime() / step) * step;
  for (let i = 0; i < 8 * 24 * 4; i++, t += step) {
    if (isOpen(new Date(t), alwaysOn, hours, timeZone)) return new Date(t);
  }
  return "never";
}
