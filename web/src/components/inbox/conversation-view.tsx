"use client";

import { ArrowLeft, Info, Pin, Star } from "lucide-react";
import Link from "next/link";
import { useEffect, useOptimistic, useRef, useTransition } from "react";
import { assignContact, markRead, sendMessage, setBotActive, setContactTag, setFollowUpsDisabled, toggleFlag } from "@/app/actions";
import { ChatStatusBar } from "@/components/inbox/chat-status-bar";
import { Composer } from "@/components/inbox/composer";
import { AudioAttachment, FileAttachment, MessageBubble, type MessageStatus as BubbleStatus } from "@/components/inbox/message-bubble";
import { Avatar } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import { ChannelBadge } from "@/components/ui/channel-badge";
import { Checkbox } from "@/components/ui/checkbox";
import { Dialog, DialogContent, DialogTrigger } from "@/components/ui/dialog";
import { Select } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { useToast } from "@/components/ui/toast";
import { Tooltip } from "@/components/ui/tooltip";
import type { getConversationDetail } from "@/lib/data";
import type { Message } from "@/lib/data/types";
import { formatDateTime, formatOpening, formatTime } from "@/lib/format";
import { cn } from "@/lib/cn";

type Detail = Awaited<ReturnType<typeof getConversationDetail>>;

const statusMap: Record<Message["status"], BubbleStatus> = {
  pending: "sending",
  scheduled: "scheduled",
  sent: "sent",
  delivered: "delivered",
  read: "read",
  failed: "failed",
};

function Bubble({ m, members }: { m: Message; members: Detail["members"] }) {
  const author = members.find((x) => x.id === m.authorUserId)?.name.split(" ")[0];
  const time = formatTime(m.createdAt);
  if (m.author === "system") return <MessageBubble author="system" time={time}>{m.body} · {time}</MessageBubble>;
  if (m.kind === "note") return <MessageBubble author="note" authorName={author} time={time}>{m.body}</MessageBubble>;
  const attachment = m.kind === "audio" ? <AudioAttachment duration="áudio" transcript={m.transcript} /> : m.kind === "file" ? <FileAttachment name={m.fileName ?? "arquivo"} size="" /> : undefined;
  return (
    <MessageBubble
      author={m.author}
      authorName={author}
      time={time}
      status={m.author === "contact" ? undefined : statusMap[m.status]}
      scheduledFor={m.scheduledFor ? formatTime(m.scheduledFor) : undefined}
      attachment={attachment}
    >
      {m.kind === "audio" ? null : m.body}
    </MessageBubble>
  );
}

export function ConversationView({ detail, backHref }: { detail: Detail; backHref: string }) {
  const { conversation, contact, channel, agent, messages, members, tags, followUps } = detail;
  const toast = useToast();
  const [, start] = useTransition();
  const [botActive, setBotOptimistic] = useOptimistic(contact.botActive);
  const endRef = useRef<HTMLDivElement>(null);
  const agentOff = !agent || !agent.isActive || !agent.published;

  useEffect(() => {
    if (conversation.unreadCount > 0) void markRead(conversation.id);
  }, [conversation.id, conversation.unreadCount]);

  useEffect(() => {
    endRef.current?.scrollIntoView({ block: "end" });
  }, [messages.length, detail.typing]);

  const toggleBot = (on: boolean) =>
    start(async () => {
      setBotOptimistic(on);
      const r = await setBotActive(contact.id, on);
      if (!r.ok) toast({ tone: "danger", title: r.error });
    });

  const botSwitch = agentOff ? (
    <Tooltip content={!agent ? "Nenhum agente atende este contato." : "O agente está desligado ou não publicado. Ligue-o em Agentes para a IA responder."}>
      <span><Switch label="IA" stateLabels={["IA ativa", "IA pausada"]} locked checked={false} /></span>
    </Tooltip>
  ) : (
    <Switch label="IA" stateLabels={["IA ativa", "IA pausada"]} checked={botActive} onCheckedChange={toggleBot} />
  );

  let status: React.ReactNode = null;
  if (channel.status !== "connected") status = <ChatStatusBar kind="disconnected">O canal {channel.displayName} está desconectado. As mensagens não serão entregues.</ChatStatusBar>;
  else if (!agentOff && !botActive) status = <ChatStatusBar kind="ai-paused">IA pausada neste contato. Você está respondendo.</ChatStatusBar>;
  else if (!agentOff && detail.nextOpening === "never") status = <ChatStatusBar kind="offline-hours">O agente não tem horário de atendimento configurado.</ChatStatusBar>;
  else if (!agentOff && detail.nextOpening) status = <ChatStatusBar kind="offline-hours">Fora do horário · a IA responde {formatOpening(new Date(detail.nextOpening))}</ChatStatusBar>;

  const panel = (
    <ContactPanel
      detail={detail}
      botSwitch={botSwitch}
      onFollowUps={(disabled) => start(async () => void (await setFollowUpsDisabled(contact.id, disabled)))}
      onAssign={(userId) => start(async () => void (await assignContact(contact.id, userId)))}
      onTag={(tagId, on) => start(async () => void (await setContactTag(contact.id, tagId, on)))}
      tags={tags}
      followUps={followUps}
    />
  );

  return (
    <div className="flex h-full min-h-0">
      <section aria-label={`Conversa com ${contact.name}`} className="flex min-w-0 flex-1 flex-col">
        <header className="flex min-h-14 flex-wrap items-center gap-2 border-b border-border px-3 py-2 sm:px-4">
          <Link href={backHref} aria-label="Voltar para a lista" className="-ml-1 rounded-md p-1.5 text-muted hover:bg-surface-hover lg:hidden">
            <ArrowLeft className="size-4" />
          </Link>
          <Avatar name={contact.name} size={32} channel={channel.kind} />
          <div className="min-w-0 flex-1">
            <h1 className="truncate text-sm font-semibold">{contact.name}</h1>
            <p className="truncate text-xs text-muted">{channel.displayName}</p>
          </div>
          {botSwitch}
          <Button variant="ghost" size="icon-sm" aria-label={conversation.pinned ? "Desafixar" : "Fixar"} aria-pressed={conversation.pinned} onClick={() => start(async () => void (await toggleFlag(conversation.id, "pinned")))}>
            <Pin className={cn(conversation.pinned && "fill-current text-accent")} />
          </Button>
          <Button variant="ghost" size="icon-sm" aria-label={conversation.starred ? "Tirar estrela" : "Marcar com estrela"} aria-pressed={conversation.starred} onClick={() => start(async () => void (await toggleFlag(conversation.id, "starred")))}>
            <Star className={cn(conversation.starred && "fill-current text-warning")} />
          </Button>
          <Dialog>
            <DialogTrigger asChild>
              <Button variant="ghost" size="icon-sm" aria-label="Dados do contato" className="xl:hidden"><Info /></Button>
            </DialogTrigger>
            <DialogContent side="drawer" title={contact.name} description={`Contato via ${channel.displayName}`}>{panel}</DialogContent>
          </Dialog>
        </header>
        {status}
        <div className="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto p-4" aria-live="polite" aria-relevant="additions">
          {messages.map((m) => <Bubble key={m.id} m={m} members={members} />)}
          {detail.typing && <p className="self-end text-xs text-muted">A IA está digitando…</p>}
          <div ref={endRef} />
        </div>
        <Composer
          onSend={(text, mode) =>
            start(async () => {
              const r = await sendMessage(conversation.id, text, mode);
              if (!r.ok) toast({ tone: "danger", title: r.error });
              else if (mode === "reply" && botActive && !agentOff) toast({ tone: "info", title: "A IA continua ativa neste contato. Pause se quiser assumir sozinho." });
            })
          }
        />
      </section>
      <aside aria-label="Dados do contato" className="hidden w-[var(--layout-contact-panel)] shrink-0 overflow-y-auto border-l border-border p-4 xl:block">{panel}</aside>
    </div>
  );
}

type PanelProps = {
  detail: Detail;
  botSwitch: React.ReactNode;
  tags: Detail["tags"];
  followUps: Detail["followUps"];
  onFollowUps: (disabled: boolean) => void;
  onAssign: (userId: string | null) => void;
  onTag: (tagId: string, on: boolean) => void;
};

function ContactPanel({ detail, botSwitch, tags, followUps, onFollowUps, onAssign, onTag }: PanelProps) {
  const { contact, channel, agent, members } = detail;
  const next = followUps.map((f) => f.sendAt).sort()[0];
  return (
    <div className="flex flex-col gap-5 text-sm">
      <div className="flex flex-col gap-1">
        <p className="font-semibold break-words">{contact.name}</p>
        <ChannelBadge kind={channel.kind} className="self-start" />
        {contact.email && <p className="text-muted break-all">{contact.email}</p>}
        {contact.phone && <p className="text-muted">{contact.phone}</p>}
        <p className="text-xs text-muted">Primeiro contato em {formatDateTime(contact.createdAt)}</p>
      </div>
      <div className="flex flex-col gap-2">
        <h2 className="text-xs font-semibold text-muted">Atendimento</h2>
        <p>Agente: <span className="font-medium">{agent?.name ?? "Nenhum"}</span></p>
        <label className="flex flex-col gap-1.5">
          <span className="text-xs text-muted">Responsável</span>
          <Select
            value={contact.assignedUserId ?? "none"}
            onValueChange={(v) => onAssign(v === "none" ? null : v)}
            options={[{ value: "none", label: "Ninguém" }, ...members.map((m) => ({ value: m.id, label: m.name }))]}
          />
        </label>
      </div>
      <div className="flex flex-col gap-2">
        <h2 className="text-xs font-semibold text-muted">Follow-up</h2>
        {!agent ? (
          <p className="text-muted">Indisponível: nenhum agente atende este contato.</p>
        ) : contact.followUpsDisabled ? (
          <p className="text-muted">Desligados para este contato.</p>
        ) : next ? (
          <p>Próximo envio: <span className="font-medium">{formatDateTime(next)}</span></p>
        ) : (
          <p className="text-muted">Nenhum agendado.</p>
        )}
      </div>
      <div className="flex flex-col gap-3">
        <h2 className="text-xs font-semibold text-muted">Preferências</h2>
        {botSwitch}
        <Switch label="Follow-ups" stateLabels={["Follow-ups ligados", "Follow-ups desligados"]} checked={!contact.followUpsDisabled} onCheckedChange={(on) => onFollowUps(!on)} />
      </div>
      <fieldset className="flex flex-col gap-2">
        <legend className="mb-2 text-xs font-semibold text-muted">Tags</legend>
        {tags.length === 0 && <p className="text-muted">Nenhuma tag criada.</p>}
        {tags.map((t) => (
          <Checkbox key={t.id} label={t.name} checked={contact.tagIds.includes(t.id)} onCheckedChange={(v) => onTag(t.id, v === true)} />
        ))}
      </fieldset>
    </div>
  );
}
