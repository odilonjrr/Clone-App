import { PauseCircle, Pin, Star } from "lucide-react";
import { Avatar } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import type { ChannelKind } from "@/components/ui/channel-badge";
import { cn } from "@/lib/cn";

export type ConversationSummary = {
  id: string;
  name: string;
  channel: ChannelKind;
  preview: string;
  time: string;
  unread: number;
  pinned?: boolean;
  starred?: boolean;
  aiPaused?: boolean;
};

export function ConversationItem({ c, selected, href }: { c: ConversationSummary; selected?: boolean; href: string }) {
  const unread = c.unread > 0;
  return (
    <a
      href={href}
      aria-current={selected ? "true" : undefined}
      className={cn(
        "flex gap-3 rounded-md px-3 py-2.5 transition-colors duration-[var(--motion-fast)]",
        selected ? "bg-accent-soft" : "hover:bg-surface-hover",
      )}
    >
      <Avatar name={c.name} channel={c.channel} />
      <span className="flex min-w-0 flex-1 flex-col gap-0.5">
        <span className="flex items-center gap-1.5">
          <span className={cn("truncate text-sm", unread ? "font-semibold text-fg" : "font-medium text-fg")}>{c.name}</span>
          {c.pinned && <Pin className="size-3 shrink-0 text-muted" aria-label="Fixada" />}
          {c.starred && <Star className="size-3 shrink-0 text-warning" aria-label="Com estrela" />}
          <span className="ml-auto shrink-0 text-xs text-muted">{c.time}</span>
        </span>
        <span className="flex items-center gap-1.5">
          {c.aiPaused && <PauseCircle className="size-3.5 shrink-0 text-warning" aria-label="IA pausada" />}
          <span className={cn("truncate text-sm", unread ? "text-fg" : "text-muted")}>{c.preview}</span>
          {unread && (
            <Badge tone="solid" className="ml-auto shrink-0">
              {c.unread}
              <span className="sr-only"> não lidas</span>
            </Badge>
          )}
        </span>
      </span>
    </a>
  );
}
