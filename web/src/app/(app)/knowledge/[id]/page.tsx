import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { Suspense } from "react";
import { AutoRefresh } from "@/components/inbox/auto-refresh";
import { PageBody } from "@/components/shell/page-header";
import { Skeleton } from "@/components/ui/misc";
import { ToastProvider } from "@/components/ui/toast";
import { DataError, getKnowledgeBase } from "@/lib/data";
import { KnowledgeEditor } from "./knowledge-editor";

export const metadata: Metadata = { title: "Base de conhecimento" };

async function Content({ params }: PageProps<"/knowledge/[id]">) {
  const { id } = await params;
  const data = await getKnowledgeBase(id).catch((e) => {
    if (e instanceof DataError) notFound();
    throw e;
  });
  return (
    <>
      {data.sources.some((s) => s.status === "processing") && <AutoRefresh ms={1500} />}
      <div>
        <Link href="/knowledge" className="text-sm text-muted hover:text-fg">← Conhecimento</Link>
        <h1 className="mt-1 text-xl font-bold break-words">{data.kb.name}</h1>
      </div>
      <ToastProvider>
        <KnowledgeEditor {...data} />
      </ToastProvider>
    </>
  );
}

export default function KnowledgeBasePage(props: PageProps<"/knowledge/[id]">) {
  return (
    <PageBody>
      <Suspense fallback={<div className="flex flex-col gap-4"><Skeleton className="h-8 w-64" /><Skeleton className="h-40" /><Skeleton className="h-64" /></div>}>
        <Content {...props} />
      </Suspense>
    </PageBody>
  );
}
