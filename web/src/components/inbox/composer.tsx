"use client";

import { Paperclip, Send } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/cn";

type ComposerProps = { onSend?: (text: string, mode: "reply" | "note") => void; blockedReason?: string };

export function Composer({ onSend, blockedReason }: ComposerProps) {
  const [mode, setMode] = useState<"reply" | "note">("reply");
  const [text, setText] = useState("");
  const blocked = mode === "reply" && !!blockedReason;
  const send = () => {
    if (!text.trim() || blocked) return;
    onSend?.(text, mode);
    setText("");
  };
  return (
    <div className={cn("border-t border-border p-3", mode === "note" && "bg-note-bg")}>
      <div role="tablist" aria-label="Tipo de mensagem" className="mb-2 flex gap-3 text-xs font-semibold">
        {(["reply", "note"] as const).map((m) => (
          <button
            key={m}
            role="tab"
            type="button"
            aria-selected={mode === m}
            onClick={() => setMode(m)}
            className={cn(
              "border-b-2 pb-1",
              mode === m ? (m === "note" ? "border-note-text text-note-text" : "border-accent text-fg") : "border-transparent text-muted hover:text-fg",
            )}
          >
            {m === "reply" ? "Responder" : "Nota interna"}
          </button>
        ))}
        {mode === "note" && <span className="ml-auto font-normal text-note-text">Só a equipe vê</span>}
      </div>
      {blocked ? (
        <p className="rounded-md bg-warning-soft px-3 py-2 text-sm text-warning">{blockedReason}</p>
      ) : (
        <div className="flex items-end gap-2">
          <Button variant="ghost" size="icon" aria-label="Anexar arquivo">
            <Paperclip />
          </Button>
          <label className="sr-only" htmlFor="composer-input">
            {mode === "reply" ? "Mensagem" : "Nota interna"}
          </label>
          <textarea
            id="composer-input"
            rows={1}
            value={text}
            onChange={(e) => setText(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === "Enter" && !e.shiftKey) {
                e.preventDefault();
                send();
              }
            }}
            placeholder={mode === "reply" ? "Escreva uma resposta…" : "Escreva uma nota para a equipe…"}
            className="max-h-40 min-h-10 flex-1 resize-none rounded-md border border-border-input bg-bg px-3 py-2 text-sm text-fg placeholder:text-muted [field-sizing:content]"
          />
          <Button size="icon" aria-label="Enviar" disabled={!text.trim()} onClick={send}>
            <Send />
          </Button>
        </div>
      )}
    </div>
  );
}
