"use client";

import { RotateCcw, Send } from "lucide-react";
import { useRef, useState, useTransition } from "react";
import { playgroundReply } from "@/app/actions";
import { MessageBubble } from "@/components/inbox/message-bubble";
import { Button } from "@/components/ui/button";

type Turn = { author: "contact" | "ai"; body: string };

// S09: conversa com o RASCUNHO. Não envia a canal nenhum e não grava nada.
export function Playground({ agentId, beforeSend }: { agentId: string; beforeSend?: () => Promise<void> }) {
  const [turns, setTurns] = useState<Turn[]>([]);
  const [text, setText] = useState("");
  const [pending, start] = useTransition();
  const endRef = useRef<HTMLDivElement>(null);
  const send = () => {
    const body = text.trim();
    if (!body || pending) return;
    const next = [...turns, { author: "contact" as const, body }];
    setTurns(next);
    setText("");
    start(async () => {
      await beforeSend?.();
      const parts = await playgroundReply(agentId, next);
      setTurns((t) => [...t, ...parts.map((p) => ({ author: "ai" as const, body: p }))]);
      requestAnimationFrame(() => endRef.current?.scrollIntoView({ block: "end" }));
    });
  };
  return (
    <div className="flex h-full min-h-[60vh] flex-col gap-3">
      <p className="rounded-md bg-accent-soft px-3 py-2 text-xs text-accent">Testando o rascunho. Nada é enviado aos clientes.</p>
      <div aria-live="polite" className="flex flex-1 flex-col gap-3 overflow-y-auto">
        {turns.length === 0 && <p className="text-sm text-muted">Escreva como se fosse um cliente. Ex.: “Quanto custa?”</p>}
        {turns.map((t, i) => (
          <MessageBubble key={i} author={t.author} time="">{t.body}</MessageBubble>
        ))}
        {pending && <p className="self-end text-xs text-muted">A IA está digitando…</p>}
        <div ref={endRef} />
      </div>
      <form
        className="flex items-end gap-2"
        onSubmit={(e) => {
          e.preventDefault();
          send();
        }}
      >
        <Button variant="ghost" size="icon" type="button" aria-label="Recomeçar conversa" onClick={() => setTurns([])}><RotateCcw /></Button>
        <label htmlFor="playground-input" className="sr-only">Mensagem de teste</label>
        <input
          id="playground-input"
          value={text}
          onChange={(e) => setText(e.target.value)}
          placeholder="Mensagem de teste…"
          className="h-10 flex-1 rounded-md border border-border-input bg-bg px-3 text-sm text-fg placeholder:text-muted"
        />
        <Button size="icon" type="submit" aria-label="Enviar" disabled={!text.trim()}><Send /></Button>
      </form>
    </div>
  );
}
