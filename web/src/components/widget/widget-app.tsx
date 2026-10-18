"use client";

import { MessageSquare, Send, X } from "lucide-react";
import { useCallback, useEffect, useRef, useState } from "react";
import type { Message, WidgetConfig } from "@/lib/data/types";
import { onColor } from "@/lib/contrast";
import { cn } from "@/lib/cn";

type Props = { publicKey: string; host?: string; mode: "bubble" | "inline" };
type Fields = { name?: string; email?: string; phone?: string };

const POLL_MS = 1500;
const storageKey = (k: string) => `atendente-ia:${k}:visitor`;

function readVisitor(k: string) {
  try {
    return localStorage.getItem(storageKey(k)) ?? undefined;
  } catch {
    return undefined;
  }
}

/** Widget do visitante (S18), dentro do iframe criado pelo widget.js. */
export function WidgetApp({ publicKey, host, mode }: Props) {
  const [config, setConfig] = useState<WidgetConfig>();
  const [visitorId, setVisitorId] = useState<string>();
  const [messages, setMessages] = useState<Message[]>([]);
  const [typing, setTyping] = useState(false);
  const [open, setOpen] = useState(mode === "inline");
  const [text, setText] = useState("");
  const [fields, setFields] = useState<Fields>({});
  const [fieldsDone, setFieldsDone] = useState(false);
  const [error, setError] = useState<string>();
  const [sending, setSending] = useState(false);
  const endRef = useRef<HTMLDivElement>(null);
  const panelRef = useRef<HTMLDivElement>(null);
  const bubbleRef = useRef<HTMLButtonElement>(null);
  const wasOpen = useRef(open);

  // Sessão
  useEffect(() => {
    let cancelled = false;
    fetch("/api/widget/session", {
      method: "POST",
      headers: { "content-type": "application/json" },
      body: JSON.stringify({ key: publicKey, visitorId: readVisitor(publicKey), host }),
    })
      .then(async (r) => {
        if (cancelled) return;
        if (r.status === 403) return setError("Este site não está autorizado a usar este chat.");
        if (!r.ok) return setError("Chat indisponível no momento.");
        const s = (await r.json()) as { visitorId: string; config: WidgetConfig; messages: Message[] };
        try {
          localStorage.setItem(storageKey(publicKey), s.visitorId);
        } catch {}
        setVisitorId(s.visitorId);
        setConfig(s.config);
        setMessages(s.messages);
        if (s.messages.length) setFieldsDone(true);
        const theme = s.config.theme === "auto" ? undefined : s.config.theme;
        if (theme) document.documentElement.dataset.theme = theme;
      })
      .catch(() => !cancelled && setError("Sem conexão. Tente de novo em instantes."));
    return () => {
      cancelled = true;
    };
  }, [publicKey, host]);

  // Avisa o site para redimensionar o iframe (modo bolha).
  useEffect(() => {
    if (mode !== "bubble" || !config) return;
    window.parent.postMessage({ type: "atendente:layout", open, position: config.position }, "*");
  }, [open, config, mode]);

  const pollOnce = useCallback(async () => {
    if (!visitorId) return;
    const u = `/api/widget/messages?key=${encodeURIComponent(publicKey)}&visitor=${encodeURIComponent(visitorId)}${host ? `&host=${encodeURIComponent(host)}` : ""}`;
    const r = await fetch(u, { cache: "no-store" }).catch(() => undefined);
    if (!r?.ok) return;
    const d = (await r.json()) as { messages: Message[]; typing: boolean };
    setMessages(d.messages);
    setTyping(d.typing);
  }, [publicKey, visitorId, host]);

  // Substituto do Realtime: consulta enquanto o chat está aberto.
  useEffect(() => {
    if (!open || !visitorId) return;
    const t = setInterval(() => void pollOnce(), POLL_MS);
    return () => clearInterval(t);
  }, [open, visitorId, pollOnce]);

  useEffect(() => {
    endRef.current?.scrollIntoView({ block: "end" });
  }, [messages.length, typing]);

  // Ao abrir, o foco vai para o primeiro campo; ao fechar, volta para a bolha.
  useEffect(() => {
    if (mode !== "bubble" || !config) return;
    if (open && !wasOpen.current) panelRef.current?.querySelector<HTMLElement>("input")?.focus();
    if (!open && wasOpen.current) bubbleRef.current?.focus();
    wasOpen.current = open;
  }, [open, mode, config]);

  const needsFields = !!config && config.collectFields.length > 0 && !fieldsDone;

  const send = async () => {
    const body = text.trim();
    if (!body || !visitorId || sending) return;
    setSending(true);
    setText("");
    setMessages((m) => [...m, { id: `local-${Date.now()}`, conversationId: "", direction: "in", author: "contact", kind: "text", body, status: "pending", createdAt: new Date().toISOString() }]);
    const r = await fetch("/api/widget/messages", {
      method: "POST",
      headers: { "content-type": "application/json" },
      body: JSON.stringify({ key: publicKey, visitorId, body, host, fields: messages.length === 0 ? fields : undefined }),
    }).catch(() => undefined);
    setSending(false);
    if (!r?.ok) {
      setError("Não foi possível enviar. Tente de novo.");
      setText(body);
      setMessages((m) => m.filter((x) => !x.id.startsWith("local-")));
      return;
    }
    setError(undefined);
    void pollOnce();
  };

  if (!config) {
    return error && open ? <p className="p-4 text-sm text-danger">{error}</p> : null;
  }

  const color = config.primaryColor;
  const fg = onColor(color);
  const side = config.position === "bottom-left" ? "left-0 items-start" : "right-0 items-end";

  const panel = (
    <div ref={panelRef} role={mode === "bubble" ? "dialog" : undefined} aria-label={config.title} onKeyDown={(e) => mode === "bubble" && e.key === "Escape" && setOpen(false)} className={cn("flex flex-col overflow-hidden bg-bg", mode === "bubble" ? "h-[calc(100%-80px)] w-full rounded-lg shadow-pop max-[479px]:h-full max-[479px]:rounded-none" : "h-full w-full rounded-lg border border-border")}>
      <div className="flex items-center justify-between gap-2 px-4 py-3" style={{ background: color, color: fg }}>
        <span className="truncate text-sm font-semibold">{config.title}</span>
        {mode === "bubble" && (
          <button type="button" aria-label="Fechar conversa" onClick={() => setOpen(false)} className="rounded-sm p-0.5 hover:opacity-80">
            <X className="size-4" />
          </button>
        )}
      </div>
      <div aria-live="polite" className="flex flex-1 flex-col gap-2 overflow-y-auto p-3">
        {config.welcomeMessage && <p className="max-w-[85%] self-start rounded-lg rounded-bl-sm bg-surface px-3 py-2 text-sm whitespace-pre-wrap text-fg">{config.welcomeMessage}</p>}
        {messages.map((m) =>
          m.author === "contact" ? (
            <p key={m.id} className={cn("max-w-[85%] self-end rounded-lg rounded-br-sm px-3 py-2 text-sm whitespace-pre-wrap", m.status === "pending" && "opacity-70")} style={{ background: color, color: fg }}>
              {m.body}
            </p>
          ) : (
            <p key={m.id} className="max-w-[85%] self-start rounded-lg rounded-bl-sm bg-surface px-3 py-2 text-sm whitespace-pre-wrap text-fg">{m.body}</p>
          ),
        )}
        {typing && (
          <p className="flex items-center gap-1 self-start rounded-lg bg-surface px-3 py-2.5">
            <span className="sr-only">Digitando</span>
            {[0, 1, 2].map((i) => <span key={i} aria-hidden className="size-1.5 animate-bounce rounded-pill bg-muted" style={{ animationDelay: `${i * 120}ms` }} />)}
          </p>
        )}
        <div ref={endRef} />
      </div>
      {error && <p role="alert" className="px-3 pb-1 text-xs text-danger">{error}</p>}
      {needsFields ? (
        <form
          className="flex flex-col gap-2 border-t border-border p-3"
          onSubmit={(e) => {
            e.preventDefault();
            if (config.collectFields.includes("email") && fields.email && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(fields.email)) return setError("Confira o e-mail.");
            setError(undefined);
            setFieldsDone(true);
            requestAnimationFrame(() => document.getElementById("widget-input")?.focus());
          }}
        >
          <p className="text-xs text-muted">Antes de começar, como podemos te chamar?</p>
          {config.collectFields.includes("name") && <input aria-label="Seu nome" required placeholder="Seu nome" value={fields.name ?? ""} onChange={(e) => setFields({ ...fields, name: e.target.value })} className="h-9 rounded-md border border-border-input bg-bg px-3 text-sm text-fg placeholder:text-muted" />}
          {config.collectFields.includes("email") && <input aria-label="Seu e-mail" type="email" required placeholder="Seu e-mail" value={fields.email ?? ""} onChange={(e) => setFields({ ...fields, email: e.target.value })} className="h-9 rounded-md border border-border-input bg-bg px-3 text-sm text-fg placeholder:text-muted" />}
          {config.collectFields.includes("phone") && <input aria-label="Seu telefone" type="tel" placeholder="Seu telefone" value={fields.phone ?? ""} onChange={(e) => setFields({ ...fields, phone: e.target.value })} className="h-9 rounded-md border border-border-input bg-bg px-3 text-sm text-fg placeholder:text-muted" />}
          <button type="submit" className="h-9 rounded-md text-sm font-semibold" style={{ background: color, color: fg }}>Começar conversa</button>
        </form>
      ) : (
        <form
          className="flex items-center gap-2 border-t border-border p-2"
          onSubmit={(e) => {
            e.preventDefault();
            void send();
          }}
        >
          <label htmlFor="widget-input" className="sr-only">Sua mensagem</label>
          <input id="widget-input" value={text} maxLength={2000} onChange={(e) => setText(e.target.value)} placeholder="Escreva sua mensagem…" className="h-10 min-w-0 flex-1 rounded-md bg-surface px-3 text-sm text-fg placeholder:text-muted" />
          <button type="submit" aria-label="Enviar" disabled={!text.trim() || sending} className="inline-flex size-10 shrink-0 items-center justify-center rounded-md disabled:opacity-50" style={{ background: color, color: fg }}>
            <Send className="size-4" />
          </button>
        </form>
      )}
    </div>
  );

  if (mode === "inline") return <div className="h-dvh p-0">{panel}</div>;

  return (
    <div className={cn("fixed inset-0 flex flex-col justify-end gap-3 p-3 max-[479px]:p-0", side)}>
      {open && panel}
      <button
        ref={bubbleRef}
        type="button"
        aria-expanded={open}
        aria-label={open ? "Fechar conversa" : "Abrir conversa"}
        onClick={() => setOpen((v) => !v)}
        className={cn("inline-flex size-14 shrink-0 items-center justify-center rounded-pill shadow-pop", open && "max-[479px]:hidden")}
        style={{ background: color, color: fg }}
      >
        {open ? <X className="size-6" /> : <MessageSquare className="size-6" />}
      </button>
    </div>
  );
}
