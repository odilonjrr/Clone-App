import { Bot, BookOpen, Inbox, LayoutDashboard, Plug, Route, Settings, Users, Workflow } from "lucide-react";
import { cn } from "@/lib/cn";

export const navItems = [
  { href: "/", label: "Início", icon: LayoutDashboard },
  { href: "/inbox", label: "Conversas", icon: Inbox, count: 3 },
  { href: "/agents", label: "Agentes", icon: Bot },
  { href: "/knowledge", label: "Conhecimento", icon: BookOpen },
  { href: "/channels", label: "Canais", icon: Plug },
  { href: "/entry-points", label: "Roteamento", icon: Route },
  { href: "/contacts", label: "Contatos", icon: Users },
  { href: "/automations", label: "Automações", icon: Workflow },
  { href: "/settings", label: "Configurações", icon: Settings },
];

export function SideNav({ active, collapsed }: { active: string; collapsed?: boolean }) {
  return (
    <nav aria-label="Principal" className={cn("flex flex-col gap-0.5 border-r border-border bg-surface p-2", collapsed ? "w-16" : "w-[var(--layout-sidebar)]")}>
      {navItems.map(({ href, label, icon: Icon, count }) => {
        const current = href === active;
        return (
          <a
            key={href}
            href={href}
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
            {!collapsed && count ? <span className="text-xs text-muted">{count}</span> : null}
          </a>
        );
      })}
    </nav>
  );
}
