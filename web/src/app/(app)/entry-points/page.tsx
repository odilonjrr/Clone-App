import type { Metadata } from "next";
import { Suspense } from "react";
import { PageBody, PageHeader } from "@/components/shell/page-header";
import { Skeleton } from "@/components/ui/misc";
import { ToastProvider } from "@/components/ui/toast";
import { listAgents, listChannels, listEntryPoints } from "@/lib/data";
import { EntryPointsEditor } from "./entry-points-editor";

export const metadata: Metadata = { title: "Roteamento" };

async function Content() {
  const [eps, agents, channels] = await Promise.all([listEntryPoints(), listAgents(), listChannels()]);
  return (
    <EntryPointsEditor
      entryPoints={eps}
      agents={agents.map((a) => ({ id: a.id, name: a.name }))}
      channels={channels.filter((c) => c.status !== "disconnected").map((c) => ({ id: c.id, name: c.displayName, kind: c.kind }))}
    />
  );
}

// S16
export default function EntryPointsPage() {
  return (
    <PageBody>
      <PageHeader title="Roteamento" description="Qual agente responde quando uma conversa nova começa." />
      <ToastProvider>
        <Suspense fallback={<Skeleton className="h-64" />}>
          <Content />
        </Suspense>
      </ToastProvider>
    </PageBody>
  );
}
