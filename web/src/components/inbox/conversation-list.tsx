import { Inbox, Search } from "lucide-react";
import Link from "next/link";
import { ConversationItem } from "@/components/inbox/conversation-item";
import { EmptyState, Skeleton } from "@/components/ui/misc";
import { listConversations } from "@/lib/data";
import type { ConversationFilter } from "@/lib/data/types";
import { toSummary } from "@/lib/inbox";
import { cn } from "@/lib/cn";

export const filters: { slug: string; value: ConversationFilter; label: string }[] = [
  { slug: "todas", value: "all", label: "Todas" },
  { slug: "nao-lidas", value: "unread", label: "Não lidas" },
  { slug: "minhas", value: "mine", label: "Minhas" },
  { slug: "pausadas", value: "paused", label: "IA pausada" },
];

export function parseInboxParams(sp: Record<string, string | string[] | undefined>) {
  const slug = typeof sp.filtro === "string" ? sp.filtro : "todas";
  const q = typeof sp.q === "string" ? sp.q : "";
  return { slug, filter: filters.find((f) => f.slug === slug)?.value ?? "all", q };
}

function qs(slug: string, q: string) {
  const p = new URLSearchParams();
  if (slug !== "todas") p.set("filtro", slug);
  if (q) p.set("q", q);
  const s = p.toString();
  return s ? `?${s}` : "";
}

export async function ConversationList({ selectedId, slug, filter, q }: { selectedId?: string; slug: string; filter: ConversationFilter; q: string }) {
  const items = await listConversations({ filter, q });
  return (
    <div className="flex h-full flex-col">
      <div className="flex flex-col gap-2 border-b border-border p-3">
        <form role="search" action="/inbox" className="relative">
          {slug !== "todas" && <input type="hidden" name="filtro" value={slug} />}
          <label htmlFor="inbox-search" className="sr-only">Buscar conversas</label>
          <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted" aria-hidden />
          <input id="inbox-search" name="q" defaultValue={q} placeholder="Buscar por nome ou mensagem" className="h-9 w-full rounded-md border border-border-input bg-bg pr-3 pl-9 text-sm text-fg placeholder:text-muted" />
        </form>
        <nav aria-label="Filtros" className="flex gap-1 overflow-x-auto">
          {filters.map((f) => (
            <Link
              key={f.slug}
              href={`/inbox${qs(f.slug, q)}`}
              aria-current={f.slug === slug ? "page" : undefined}
              className={cn("rounded-sm px-2.5 py-1 text-xs font-medium whitespace-nowrap", f.slug === slug ? "bg-accent-soft text-accent" : "text-muted hover:bg-surface-hover hover:text-fg")}
            >
              {f.label}
            </Link>
          ))}
        </nav>
      </div>
      {items.length === 0 ? (
        <EmptyState icon={Inbox} title={q ? "Nada encontrado" : filter === "all" ? "Nenhuma conversa ainda" : "Nenhuma conversa neste filtro"}>
          {q ? `Nenhuma conversa com “${q}”.` : filter === "all" ? "Quando alguém mandar mensagem num canal conectado, ela aparece aqui." : "Tudo em dia por aqui."}
        </EmptyState>
      ) : (
        <ul className="flex flex-1 flex-col gap-0.5 overflow-y-auto p-2">
          {items.map((i) => (
            <li key={i.conversation.id}>
              <ConversationItem c={toSummary(i)} selected={i.conversation.id === selectedId} href={`/inbox/${i.conversation.id}${qs(slug, q)}`} />
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

export function ListSkeleton() {
  return (
    <div className="flex flex-col gap-3 p-3" aria-busy="true" aria-label="Carregando conversas">
      <Skeleton className="h-9" />
      {Array.from({ length: 6 }, (_, i) => (
        <div key={i} className="flex gap-3"><Skeleton className="size-10 rounded-pill" /><div className="flex flex-1 flex-col gap-2"><Skeleton className="h-3 w-1/2" /><Skeleton className="h-3 w-3/4" /></div></div>
      ))}
    </div>
  );
}

