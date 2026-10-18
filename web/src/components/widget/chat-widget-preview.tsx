"use client";

import { MessageSquare, Send, X } from "lucide-react";
import { useState } from "react";
import { onColor } from "@/lib/contrast";
import { cn } from "@/lib/cn";

type WidgetPreviewProps = {
  color: string;
  position: "bottom-right" | "bottom-left";
  title: string;
  welcome: string;
  defaultOpen?: boolean;
};

// Prévia do widget do visitante (S18), usada também na configuração (S15).
// A cor vem do cliente; o texto sobre ela é escolhido para manter o contraste.
export function ChatWidgetPreview({ color, position, title, welcome, defaultOpen = true }: WidgetPreviewProps) {
  const [open, setOpen] = useState(defaultOpen);
  const fg = onColor(color);
  const side = position === "bottom-right" ? "right-4 items-end" : "left-4 items-start";
  return (
    <div className="relative h-[560px] overflow-hidden rounded-lg border border-border bg-surface">
      <div className={cn("absolute bottom-4 flex flex-col gap-3", side)}>
        {open && (
          <div role="dialog" aria-label={title} className="flex h-[440px] w-[320px] max-w-[calc(100vw-4rem)] flex-col overflow-hidden rounded-lg bg-bg shadow-pop">
            <div className="flex items-center justify-between px-4 py-3" style={{ background: color, color: fg }}>
              <span className="text-sm font-semibold">{title}</span>
              <button type="button" aria-label="Fechar conversa" onClick={() => setOpen(false)} className="rounded-sm p-0.5 hover:opacity-80">
                <X className="size-4" />
              </button>
            </div>
            <div aria-live="polite" className="flex flex-1 flex-col gap-2 overflow-y-auto p-3">
              <p className="max-w-[85%] self-start rounded-lg rounded-bl-sm bg-surface px-3 py-2 text-sm text-fg">{welcome}</p>
              <p className="max-w-[85%] self-end rounded-lg rounded-br-sm px-3 py-2 text-sm" style={{ background: color, color: fg }}>
                Vocês atendem aos sábados?
              </p>
              <p className="flex items-center gap-1 self-start rounded-lg bg-surface px-3 py-2" aria-label="Digitando">
                {[0, 1, 2].map((i) => (
                  <span key={i} className="size-1.5 animate-bounce rounded-pill bg-muted" style={{ animationDelay: `${i * 120}ms` }} />
                ))}
              </p>
            </div>
            <form className="flex items-center gap-2 border-t border-border p-2" onSubmit={(e) => e.preventDefault()}>
              <label htmlFor="widget-input" className="sr-only">Sua mensagem</label>
              <input id="widget-input" placeholder="Escreva sua mensagem…" className="h-9 flex-1 rounded-md bg-surface px-3 text-sm text-fg placeholder:text-muted" />
              <button type="submit" aria-label="Enviar" className="inline-flex size-9 items-center justify-center rounded-md" style={{ background: color, color: fg }}>
                <Send className="size-4" />
              </button>
            </form>
          </div>
        )}
        <button
          type="button"
          aria-expanded={open}
          aria-label={open ? "Fechar conversa" : "Abrir conversa"}
          onClick={() => setOpen((v) => !v)}
          className="inline-flex size-14 items-center justify-center rounded-pill shadow-pop"
          style={{ background: color, color: fg }}
        >
          {open ? <X className="size-6" /> : <MessageSquare className="size-6" />}
        </button>
      </div>
    </div>
  );
}
