import { AlertCircle, Bot, Check, CheckCheck, Clock, FileText, Mic, StickyNote } from "lucide-react";
import type { ReactNode } from "react";
import { cn } from "@/lib/cn";

export type MessageAuthor = "contact" | "ai" | "human" | "note" | "system";
export type MessageStatus = "sending" | "scheduled" | "sent" | "delivered" | "read" | "failed";

const statusText: Record<MessageStatus, string> = {
  sending: "Enviando",
  scheduled: "Agendada",
  sent: "Enviada",
  delivered: "Entregue",
  read: "Lida",
  failed: "Falhou",
};

function StatusIcon({ status }: { status: MessageStatus }) {
  const cls = "size-3.5";
  switch (status) {
    case "sending":
    case "scheduled":
      return <Clock className={cls} aria-hidden />;
    case "sent":
      return <Check className={cls} aria-hidden />;
    case "delivered":
      return <CheckCheck className={cls} aria-hidden />;
    case "read":
      return <CheckCheck className={cn(cls, "text-accent")} aria-hidden />;
    case "failed":
      return <AlertCircle className={cn(cls, "text-danger")} aria-hidden />;
  }
}

type BubbleProps = {
  author: MessageAuthor;
  authorName?: string;
  time: string;
  status?: MessageStatus;
  scheduledFor?: string;
  children: ReactNode;
  attachment?: ReactNode;
  onRetry?: () => void;
};

export function MessageBubble({ author, authorName, time, status, scheduledFor, children, attachment, onRetry }: BubbleProps) {
  if (author === "system") {
    return <p className="mx-auto max-w-md text-center text-xs text-muted">{children}</p>;
  }
  if (author === "note") {
    return (
      <article aria-label={`Nota interna de ${authorName ?? "equipe"}, ${time}`} className="rounded-md bg-note-bg px-3 py-2 text-sm text-note-text">
        <p className="mb-1 flex items-center gap-1.5 text-xs font-semibold">
          <StickyNote className="size-3.5" aria-hidden /> Nota interna · {authorName} · {time}
        </p>
        {children}
      </article>
    );
  }
  const outgoing = author !== "contact";
  return (
    <article
      aria-label={`${author === "contact" ? "Contato" : author === "ai" ? "IA" : authorName}, ${time}`}
      className={cn("flex max-w-[80%] flex-col gap-1", outgoing ? "items-end self-end" : "items-start self-start")}
    >
      {author !== "contact" && (
        <span className="flex items-center gap-1 text-xs text-muted">
          {author === "ai" ? <><Bot className="size-3.5" aria-hidden /> IA</> : authorName}
        </span>
      )}
      <div
        className={cn(
          "rounded-lg px-3 py-2 text-sm whitespace-pre-wrap",
          author === "contact" && "rounded-bl-sm bg-surface text-fg",
          author === "ai" && "rounded-br-sm bg-accent-soft text-fg",
          author === "human" && "rounded-br-sm bg-accent text-on-accent",
        )}
      >
        {attachment && <div className="mb-2">{attachment}</div>}
        {children}
      </div>
      <span className="flex items-center gap-1 text-xs text-muted">
        {status === "scheduled" && scheduledFor ? `Sai às ${scheduledFor}` : time}
        {status && (
          <>
            <StatusIcon status={status} />
            <span className="sr-only">{statusText[status]}</span>
          </>
        )}
        {status === "failed" && onRetry && (
          <button type="button" onClick={onRetry} className="font-semibold text-danger hover:underline">
            Tentar de novo
          </button>
        )}
      </span>
    </article>
  );
}

export function AudioAttachment({ duration, transcript }: { duration: string; transcript?: string }) {
  return (
    <div className="flex flex-col gap-1">
      <span className="inline-flex items-center gap-2 text-sm">
        <Mic className="size-4" aria-hidden /> Áudio · {duration}
      </span>
      {transcript && (
        <details className="text-xs opacity-90">
          <summary className="cursor-pointer">Transcrição</summary>
          <p className="mt-1">{transcript}</p>
        </details>
      )}
    </div>
  );
}

export function FileAttachment({ name, size }: { name: string; size: string }) {
  return (
    <span className="inline-flex items-center gap-2 rounded-md border border-current/20 px-2 py-1.5 text-sm">
      <FileText className="size-4" aria-hidden /> {name} <span className="opacity-70">· {size}</span>
    </span>
  );
}
