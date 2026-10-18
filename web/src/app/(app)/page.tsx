import { Bot, Inbox, MessageSquare, PauseCircle, Plug, UserRound } from "lucide-react";
import type { Metadata } from "next";
import Link from "next/link";
import { Suspense } from "react";
import { ConversationItem } from "@/components/inbox/conversation-item";
import { PageBody, PageHeader } from "@/components/shell/page-header";
import { buttonVariants } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { EmptyState, Skeleton } from "@/components/ui/misc";
import { getDashboardStats, listConversations } from "@/lib/data";
import { toSummary } from "@/lib/inbox";

export const metadata: Metadata = { title: "Início" };

async function Stats() {
  const s = await getDashboardStats();
  const tiles = [
    { label: "Não lidas", value: s.unread, icon: Inbox, href: "/inbox?filtro=nao-lidas" },
    { label: "Respostas da IA (7 dias)", value: s.aiReplies7d, icon: Bot },
    { label: "Respostas da equipe (7 dias)", value: s.humanReplies7d, icon: UserRound },
    { label: "Contatos com IA pausada", value: s.pausedContacts, icon: PauseCircle, href: "/inbox?filtro=pausadas" },
    { label: "Canais conectados", value: s.connectedChannels, icon: Plug, href: "/channels" },
    { label: "Agentes no ar", value: s.activeAgents, icon: MessageSquare, href: "/agents" },
  ];
  return (
    <ul className="grid grid-cols-2 gap-3 md:grid-cols-3">
      {tiles.map(({ label, value, icon: Icon, href }) => {
        const body = (
          <>
            <Icon className="size-4 text-muted" aria-hidden />
            <span className="text-xl font-bold">{value}</span>
            <span className="text-xs text-muted">{label}</span>
          </>
        );
        return (
          <li key={label}>
            {href ? (
              <Link href={href} className="flex h-full flex-col gap-1 rounded-lg border border-border bg-surface-raised p-4 shadow-card hover:bg-surface-hover">{body}</Link>
            ) : (
              <div className="flex h-full flex-col gap-1 rounded-lg border border-border bg-surface-raised p-4 shadow-card">{body}</div>
            )}
          </li>
        );
      })}
    </ul>
  );
}

async function Recent() {
  const items = (await listConversations()).slice(0, 5);
  if (items.length === 0)
    return (
      <EmptyState icon={Inbox} title="Nenhuma conversa ainda" action={<Link href="/channels" className={buttonVariants({ size: "sm" })}>Conectar um canal</Link>}>
        Quando alguém mandar mensagem num canal conectado, a conversa aparece aqui.
      </EmptyState>
    );
  return (
    <ul className="flex flex-col gap-0.5">
      {items.map((i) => (
        <li key={i.conversation.id}>
          <ConversationItem c={toSummary(i)} href={`/inbox/${i.conversation.id}`} />
        </li>
      ))}
    </ul>
  );
}

export default function DashboardPage() {
  return (
    <PageBody>
      <PageHeader title="Início" description="Como estão os seus atendimentos." />
      <Suspense fallback={<div className="grid grid-cols-2 gap-3 md:grid-cols-3">{Array.from({ length: 6 }, (_, i) => <Skeleton key={i} className="h-24" />)}</div>}>
        <Stats />
      </Suspense>
      <Card className="p-3">
        <div className="flex items-center justify-between px-2 pb-2">
          <h2 className="text-sm font-semibold">Conversas recentes</h2>
          <Link href="/inbox" className="text-sm font-medium text-accent hover:underline">Ver todas</Link>
        </div>
        <Suspense fallback={<div className="flex flex-col gap-2 p-2">{Array.from({ length: 4 }, (_, i) => <Skeleton key={i} className="h-12" />)}</div>}>
          <Recent />
        </Suspense>
      </Card>
    </PageBody>
  );
}
