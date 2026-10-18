import { Bot, Plus } from "lucide-react";
import type { Metadata } from "next";
import Link from "next/link";
import { Suspense } from "react";
import { createBlankAgent } from "@/app/actions";
import { PageBody, PageHeader } from "@/components/shell/page-header";
import { Badge } from "@/components/ui/badge";
import { Button, buttonVariants } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { EmptyState, Skeleton } from "@/components/ui/misc";
import { isDraftDirty } from "@/lib/agent-utils";
import { listAgents, listChannels, listEntryPoints } from "@/lib/data";
import { formatDateTime } from "@/lib/format";
import { AgentActiveSwitch } from "./agent-active-switch";

export const metadata: Metadata = { title: "Agentes" };

async function AgentList() {
  const [agents, eps, channels] = await Promise.all([listAgents(), listEntryPoints(), listChannels()]);
  if (agents.length === 0)
    return (
      <Card>
        <EmptyState icon={Bot} title="Você ainda não tem agentes" action={<Link href="/onboarding" className={buttonVariants({ size: "sm" })}>Criar a partir do site</Link>}>
          Um agente responde às mensagens com as suas instruções e o seu conhecimento.
        </EmptyState>
      </Card>
    );
  return (
    <ul className="grid gap-4 md:grid-cols-2">
      {agents.map((a) => {
        const dirty = isDraftDirty(a);
        const channelNames = eps
          .filter((e) => e.agentId === a.id)
          .map((e) => channels.find((c) => c.id === e.channelId)?.displayName)
          .filter(Boolean);
        return (
          <li key={a.id}>
            <Card className="flex h-full flex-col gap-3">
              <div className="flex items-start justify-between gap-3">
                <Link href={`/agents/${a.id}`} className="min-w-0 rounded-sm text-base font-semibold break-words hover:underline">{a.name}</Link>
                <AgentActiveSwitch agentId={a.id} active={a.isActive} name={a.name} />
              </div>
              <div className="flex flex-wrap gap-2">
                {!a.published ? <Badge tone="warning">Nunca publicado</Badge> : dirty ? <Badge tone="accent">Rascunho com mudanças</Badge> : <Badge tone="success">Publicado</Badge>}
                {!a.isActive && <Badge>Desligado</Badge>}
              </div>
              <p className="text-sm text-muted">
                {channelNames.length ? `Atende: ${channelNames.join(", ")}` : "Ainda não atende nenhum canal."}
              </p>
              {a.published?.publishedAt && <p className="text-xs text-muted">Publicado em {formatDateTime(a.published.publishedAt)}</p>}
              <Link href={`/agents/${a.id}`} className={buttonVariants({ variant: "secondary", size: "sm", className: "mt-auto self-start" })}>Editar</Link>
            </Card>
          </li>
        );
      })}
    </ul>
  );
}

export default function AgentsPage() {
  return (
    <PageBody>
      <PageHeader
        title="Agentes"
        description="Cada agente tem instruções, horário e conhecimento próprios."
        actions={
          <>
            <Link href="/onboarding" className={buttonVariants({ variant: "secondary" })}>Criar a partir do site</Link>
            <form action={createBlankAgent}>
              <Button type="submit"><Plus /> Novo agente</Button>
            </form>
          </>
        }
      />
      <Suspense fallback={<div className="grid gap-4 md:grid-cols-2"><Skeleton className="h-44" /><Skeleton className="h-44" /></div>}>
        <AgentList />
      </Suspense>
    </PageBody>
  );
}
