import { Camera, Globe, MessageCircle, Send, type LucideIcon } from "lucide-react";
import { cn } from "@/lib/cn";

export type ChannelKind = "whatsapp" | "instagram" | "telegram" | "web";

// Ícones genéricos do Lucide; nenhum logo das plataformas.
export const channels: Record<ChannelKind, { label: string; icon: LucideIcon; bg: string }> = {
  whatsapp: { label: "WhatsApp", icon: MessageCircle, bg: "bg-channel-whatsapp" },
  instagram: { label: "Instagram", icon: Camera, bg: "bg-channel-instagram" },
  telegram: { label: "Telegram", icon: Send, bg: "bg-channel-telegram" },
  web: { label: "Site", icon: Globe, bg: "bg-channel-web" },
};

type ChannelBadgeProps = { kind: ChannelKind; size?: "dot" | "sm" | "md"; className?: string };

export function ChannelBadge({ kind, size = "md", className }: ChannelBadgeProps) {
  const c = channels[kind];
  if (size === "dot") {
    return <span role="img" aria-label={c.label} className={cn("inline-block size-2 rounded-pill", c.bg, className)} />;
  }
  const Icon = c.icon;
  if (size === "sm") {
    return (
      <span role="img" aria-label={c.label} className={cn("inline-flex size-5 items-center justify-center rounded-pill text-on-channel ring-2 ring-bg", c.bg, className)}>
        <Icon className="size-3" aria-hidden />
      </span>
    );
  }
  return (
    <span className={cn("inline-flex items-center gap-1.5 rounded-pill px-2 py-0.5 text-xs font-medium text-on-channel", c.bg, className)}>
      <Icon className="size-3.5" aria-hidden />
      {c.label}
    </span>
  );
}
