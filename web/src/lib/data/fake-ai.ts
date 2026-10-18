// Substituto da IA enquanto o /replica-backend não liga a API Claude.
// Responde a partir do FAQ das bases ligadas ao agente, por sobreposição de
// palavras. Não é o comportamento final: serve para o fluxo funcionar ponta a
// ponta com a mesma forma de entrada e saída.
import type { AgentConfig, FaqItem } from "./types";

const STOP = new Set("a o e de da do das dos em no na nos nas um uma para por com que se é eu você vocês voces tem têm qual quais como meu minha".split(" "));

function words(s: string) {
  return s
    .toLowerCase()
    .normalize("NFD")
    .replace(/[̀-ͯ]/g, "")
    .split(/[^a-z0-9]+/)
    .filter((w) => w.length > 2 && !STOP.has(w));
}

export type FakeReply = { parts: string[]; matchedFaqId?: string };

export function fakeReply(config: AgentConfig, faq: FaqItem[], history: { author: string; body: string }[]): FakeReply {
  const last = [...history].reverse().find((m) => m.author === "contact")?.body ?? "";
  const q = new Set(words(last));
  let best: { item: FaqItem; score: number } | undefined;
  for (const item of faq) {
    const score = words(item.question).filter((w) => q.has(w)).length;
    if (score > 0 && (!best || score > best.score)) best = { item, score };
  }
  const isFirstReply = !history.some((m) => m.author === "ai");
  const greeting = /\b(oi|ola|olá|bom dia|boa tarde|boa noite)\b/i.test(last);
  let text: string;
  if (best) text = best.item.answer;
  else if (greeting) text = "Olá! Como posso ajudar?";
  else if (config.abilities.booking.enabled && /agend|marcar|horario|horário|reuni/i.test(last))
    text = "Posso agendar para você. Qual dia e período ficam melhor?";
  else text = "Boa pergunta. Vou confirmar com a equipe e já retorno por aqui.";
  if (isFirstReply && config.aiDisclosure) text = `${config.aiDisclosure}\n\n${text}`;
  return { parts: config.splitMessages ? splitReply(text) : [text], matchedFaqId: best?.item.id };
}

/** Divide por parágrafo e, se um parágrafo for longo, por frases. */
export function splitReply(text: string): string[] {
  const out: string[] = [];
  for (const para of text.split(/\n{2,}/).map((p) => p.trim()).filter(Boolean)) {
    if (para.length <= 220) {
      out.push(para);
      continue;
    }
    let cur = "";
    for (const s of para.split(/(?<=[.!?])\s+/)) {
      if ((cur + " " + s).trim().length > 220 && cur) {
        out.push(cur.trim());
        cur = s;
      } else cur = `${cur} ${s}`;
    }
    if (cur.trim()) out.push(cur.trim());
  }
  return out.length ? out : [text];
}

const cps = { instant: Infinity, fast: 40, natural: 18, slow: 9 } as const;

/** Atraso de "digitação" para uma parte, entre 0,6 s e 6 s. */
export function typingDelayMs(part: string, speed: AgentConfig["typingSpeed"]) {
  if (speed === "instant") return 0;
  return Math.min(6000, Math.max(600, (part.length / cps[speed]) * 1000));
}
