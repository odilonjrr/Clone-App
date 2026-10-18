import { MessagesSquare } from "lucide-react";
import type { Metadata } from "next";
import { Suspense } from "react";
import { AutoRefresh } from "@/components/inbox/auto-refresh";
import { ConversationList, ListSkeleton, parseInboxParams } from "@/components/inbox/conversation-list";
import { EmptyState } from "@/components/ui/misc";

export const metadata: Metadata = { title: "Conversas" };

async function List({ searchParams }: PageProps<"/inbox">) {
  const p = parseInboxParams(await searchParams);
  return <ConversationList {...p} />;
}

// S04
export default function InboxPage(props: PageProps<"/inbox">) {
  return (
    <div className="grid h-[calc(100dvh-var(--layout-header))] grid-cols-1 lg:grid-cols-[var(--layout-inbox-list)_1fr]">
      <AutoRefresh />
      <section aria-label="Lista de conversas" className="min-h-0 border-border lg:border-r">
        <Suspense fallback={<ListSkeleton />}>
          <List {...props} />
        </Suspense>
      </section>
      <div className="hidden items-center justify-center bg-surface lg:flex">
        <EmptyState icon={MessagesSquare} title="Escolha uma conversa">As respostas da IA aparecem em tempo real. Você pode assumir quando quiser.</EmptyState>
      </div>
    </div>
  );
}
