import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { Suspense } from "react";
import { AutoRefresh } from "@/components/inbox/auto-refresh";
import { ConversationList, ListSkeleton, parseInboxParams } from "@/components/inbox/conversation-list";
import { ConversationView } from "@/components/inbox/conversation-view";
import { Skeleton } from "@/components/ui/misc";
import { ToastProvider } from "@/components/ui/toast";
import { DataError, getConversationDetail } from "@/lib/data";

export const metadata: Metadata = { title: "Conversa" };

async function List({ params, searchParams }: PageProps<"/inbox/[id]">) {
  const [{ id }, sp] = await Promise.all([params, searchParams]);
  return <ConversationList selectedId={id} {...parseInboxParams(sp)} />;
}

async function Detail({ params, searchParams }: PageProps<"/inbox/[id]">) {
  const [{ id }, sp] = await Promise.all([params, searchParams]);
  const detail = await getConversationDetail(id).catch((e) => {
    if (e instanceof DataError) notFound();
    throw e;
  });
  const back = new URLSearchParams(Object.entries(sp).filter((e): e is [string, string] => typeof e[1] === "string")).toString();
  return <ConversationView detail={detail} backHref={`/inbox${back ? `?${back}` : ""}`} />;
}

function DetailSkeleton() {
  return (
    <div className="flex h-full flex-col" aria-busy="true" aria-label="Carregando conversa">
      <div className="flex h-14 items-center gap-3 border-b border-border px-4"><Skeleton className="size-8 rounded-pill" /><Skeleton className="h-4 w-40" /></div>
      <div className="flex flex-1 flex-col gap-3 p-4"><Skeleton className="h-10 w-2/3" /><Skeleton className="h-10 w-1/2 self-end" /><Skeleton className="h-10 w-3/5" /></div>
    </div>
  );
}

// S05 + S06
export default function ConversationPage(props: PageProps<"/inbox/[id]">) {
  return (
    <div className="grid h-[calc(100dvh-var(--layout-header))] grid-cols-1 lg:grid-cols-[var(--layout-inbox-list)_1fr]">
      <AutoRefresh />
      <section aria-label="Lista de conversas" className="hidden min-h-0 border-r border-border lg:block">
        <Suspense fallback={<ListSkeleton />}>
          <List {...props} />
        </Suspense>
      </section>
      <div className="min-h-0 min-w-0">
        <ToastProvider>
          <Suspense fallback={<DetailSkeleton />}>
            <Detail {...props} />
          </Suspense>
        </ToastProvider>
      </div>
    </div>
  );
}
