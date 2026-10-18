import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { Suspense } from "react";
import { AgentEditor } from "@/components/agent/agent-editor";
import { PageBody } from "@/components/shell/page-header";
import { Skeleton } from "@/components/ui/misc";
import { ToastProvider } from "@/components/ui/toast";
import { DataError, getAgent, listKnowledgeBases } from "@/lib/data";

export const metadata: Metadata = { title: "Editar agente" };

async function Editor({ params, searchParams }: PageProps<"/agents/[id]">) {
  const [{ id }, sp] = await Promise.all([params, searchParams]);
  const agent = await getAgent(id).catch((e) => {
    if (e instanceof DataError) notFound();
    throw e;
  });
  const kbs = await listKnowledgeBases();
  return (
    <>
      <div>
        <Link href="/agents" className="text-sm text-muted hover:text-fg">← Agentes</Link>
        <h1 className="mt-1 text-xl font-bold break-words">{agent.name}</h1>
      </div>
      <ToastProvider>
        <AgentEditor key={agent.draft.id} agent={agent} knowledgeBases={kbs} isNew={sp.novo === "1"} />
      </ToastProvider>
    </>
  );
}

export default function AgentPage(props: PageProps<"/agents/[id]">) {
  return (
    <PageBody>
      <Suspense fallback={<div className="flex flex-col gap-4"><Skeleton className="h-8 w-64" /><Skeleton className="h-14" /><Skeleton className="h-96" /></div>}>
        <Editor {...props} />
      </Suspense>
    </PageBody>
  );
}
