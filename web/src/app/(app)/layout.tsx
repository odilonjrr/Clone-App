import { Bot } from "lucide-react";
import Link from "next/link";
import { Suspense } from "react";
import { MobileNav } from "@/components/shell/mobile-nav";
import { NavLinks, SideNav } from "@/components/shell/side-nav";
import { Skeleton } from "@/components/ui/misc";
import { getCurrentUser, getDashboardStats, getWorkspace } from "@/lib/data";

async function WorkspaceName() {
  const ws = await getWorkspace();
  return <span className="truncate text-sm font-semibold">{ws.name}</span>;
}

async function UserChip() {
  const me = await getCurrentUser();
  return <span className="hidden truncate text-sm text-muted sm:inline">{me.name}</span>;
}

async function Nav({ mobile }: { mobile?: boolean }) {
  const { unread } = await getDashboardStats();
  return mobile ? <MobileNav unread={unread} /> : <SideNav unread={unread} />;
}

export default function AppLayout({ children }: LayoutProps<"/">) {
  return (
    <div className="flex min-h-dvh flex-col">
      <a href="#conteudo" className="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded-md focus:bg-accent focus:px-3 focus:py-2 focus:text-on-accent">
        Pular para o conteúdo
      </a>
      <header className="sticky top-0 z-30 flex h-[var(--layout-header)] items-center gap-3 border-b border-border bg-bg px-3 sm:px-4">
        <Suspense fallback={<span className="size-10 lg:hidden" />}>
          <Nav mobile />
        </Suspense>
        <Link href="/" className="flex items-center gap-2 rounded-md">
          <span className="inline-flex size-7 items-center justify-center rounded-md bg-accent text-on-accent">
            <Bot className="size-4" aria-hidden />
          </span>
          <span className="hidden text-sm font-bold sm:inline">Atendente IA</span>
        </Link>
        <span aria-hidden className="hidden h-5 w-px bg-border sm:block" />
        <Suspense fallback={<Skeleton className="h-4 w-28" />}>
          <WorkspaceName />
        </Suspense>
        <div className="ml-auto">
          <Suspense fallback={null}>
            <UserChip />
          </Suspense>
        </div>
      </header>
      <div className="flex flex-1">
        <aside className="sticky top-[var(--layout-header)] hidden h-[calc(100dvh-var(--layout-header))] shrink-0 overflow-y-auto border-r border-border bg-surface lg:block">
          <Suspense fallback={<NavLinks path="" />}>
            <Nav />
          </Suspense>
        </aside>
        <main id="conteudo" className="min-w-0 flex-1">
          {children}
        </main>
      </div>
    </div>
  );
}
