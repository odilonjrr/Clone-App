"use client";

import { Bot, BookOpen, Inbox, LayoutDashboard, Plug, Route, Settings, Users, Workflow } from "lucide-react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { cn } from "@/lib/cn";

export const navItems = [
  { href: "/", label: "Início", icon: LayoutDashboard },
  { href: "/inbox", label: "Conversas", icon: Inbox },
  { href: "/agents", label: "Agentes", icon: Bot },
  { href: "/knowledge", label: "Conhecimento", icon: BookOpen },
  { href: "/channels", label: "Canais", icon: Plug },
  { href: "/entry-points", label: "Roteamento", icon: Route },
  { href: "/contacts", label: "Contatos", icon: Users },
  { href: "/automations", label: "Automações", icon: Workflow },
  { href: "/settings", label: "Configurações", icon: Settings },
];

function isCurrent(href: string, path: string) {
  return href === "/" ? path === "/" : path === href || path.startsWith(`${href}/`);
}

type SideNavProps = {
  /** Fixa o item ativo (vitrine /design); sem isso, usa a URL. */
  active?: string;
  collapsed?: boolean;
  unread?: number;
  onNavigate?: () => void;
  className?: string;
};

export function SideNav(props: SideNavProps) {
  const pathname = usePathname();
  return <NavLinks {...props} path={props.active ?? pathname} />;
}

/** Sem usePathname: serve de fallback estático enquanto a URL não chega. */
export function NavLinks({ path, collapsed, unread, onNavigate, className }: SideNavProps & { path: string }) {
  return (
    <nav aria-label="Principal" className={cn("flex flex-col gap-0.5 p-2", collapsed ? "w-16" : "w-[var(--layout-sidebar)]", className)}>
      {navItems.map(({ href, label, icon: Icon }) => {
        const current = isCurrent(href, path);
        const count = href === "/inbox" ? unread : undefined;
        return (
          <Link
            key={href}
            href={href}
            onClick={onNavigate}
            aria-current={current ? "page" : undefined}
            title={collapsed ? label : undefined}
            className={cn(
              "flex h-9 items-center gap-3 rounded-md px-3 text-sm font-medium transition-colors",
              current ? "bg-accent-soft text-accent" : "text-fg hover:bg-surface-hover",
              collapsed && "justify-center px-0",
            )}
          >
            <Icon className="size-4 shrink-0" aria-hidden />
            {collapsed ? <span className="sr-only">{label}</span> : <span className="flex-1">{label}</span>}
            {!collapsed && count ? (
              <span className="text-xs text-muted">
                {count}
                <span className="sr-only"> não lidas</span>
              </span>
            ) : null}
          </Link>
        );
      })}
    </nav>
  );
}
