"use client";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { Bike, ClipboardList, ExternalLink, LogOut, MessageCircle, ShoppingBag, Store, UtensilsCrossed } from "lucide-react";
import { api } from "@/lib/client-api";

const ITEMS = [
  { href: "/painel", label: "Pedidos", Icon: ClipboardList },
  { href: "/painel/motoboys", label: "Motoboys", Icon: Bike },
  { href: "/painel/cardapio", label: "Cardápio", Icon: UtensilsCrossed },
  { href: "/painel/loja", label: "Loja e entrega", Icon: Store },
  { href: "/painel/mensagens", label: "Avisos WhatsApp", Icon: MessageCircle },
];

export function PanelNav({ storeName, slug, userName }: { storeName: string; slug: string; userName: string }) {
  const path = usePathname();
  const router = useRouter();
  async function logout() {
    await api("/api/painel/sessao", { method: "DELETE" }).catch(() => {});
    router.replace("/painel/entrar");
    router.refresh();
  }
  return (
    <aside className="border-b border-border bg-surface lg:sticky lg:top-0 lg:h-dvh lg:border-b-0 lg:border-r">
      <div className="flex items-center gap-2 px-4 py-3 lg:py-5">
        <span className="grid size-9 place-items-center rounded-lg" style={{ background: "var(--gradient-accent)" }}><ShoppingBag className="size-5 text-white" aria-hidden /></span>
        <div className="min-w-0">
          <p className="truncate font-display text-base font-bold leading-tight">{storeName}</p>
          <p className="truncate text-xs text-muted">{userName}</p>
        </div>
      </div>
      <nav aria-label="Painel" className="flex gap-1 overflow-x-auto px-2 pb-2 lg:flex-col lg:px-3 [scrollbar-width:none]">
        {ITEMS.map(({ href, label, Icon }) => {
          const active = href === "/painel" ? path === "/painel" : path.startsWith(href);
          return (
            <Link key={href} href={href} aria-current={active ? "page" : undefined}
              className="flex min-h-10 shrink-0 items-center gap-2.5 rounded-md px-3 text-sm font-medium text-muted hover:bg-surface-sunken aria-[current=page]:bg-accent-soft aria-[current=page]:font-semibold aria-[current=page]:text-accent-strong">
              <Icon className="size-[18px]" aria-hidden />{label}
            </Link>
          );
        })}
      </nav>
      <div className="hidden gap-1 px-3 pt-4 lg:grid">
        <a href={`/${slug}`} target="_blank" className="flex min-h-10 items-center gap-2.5 rounded-md px-3 text-sm text-muted hover:bg-surface-sunken"><ExternalLink className="size-[18px]" aria-hidden />Ver meu cardápio</a>
        <button onClick={logout} className="flex min-h-10 items-center gap-2.5 rounded-md px-3 text-left text-sm text-muted hover:bg-surface-sunken"><LogOut className="size-[18px]" aria-hidden />Sair</button>
      </div>
    </aside>
  );
}
