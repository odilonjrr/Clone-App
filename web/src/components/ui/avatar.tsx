import { cn } from "@/lib/cn";
import { ChannelBadge, type ChannelKind } from "./channel-badge";

const sizes = { 24: "size-6 text-[10px]", 32: "size-8 text-xs", 40: "size-10 text-sm" } as const;

type AvatarProps = { name: string; src?: string; size?: keyof typeof sizes; channel?: ChannelKind; className?: string };

function initials(name: string) {
  const parts = name.trim().split(/\s+/);
  return ((parts[0]?.[0] ?? "") + (parts.length > 1 ? parts[parts.length - 1][0] : "")).toUpperCase() || "?";
}

export function Avatar({ name, src, size = 40, channel, className }: AvatarProps) {
  return (
    <span className={cn("relative inline-flex shrink-0", className)}>
      {src ? (
        // eslint-disable-next-line @next/next/no-img-element -- fotos de contato vêm de domínios variados
        <img src={src} alt="" className={cn("rounded-pill object-cover", sizes[size])} />
      ) : (
        <span aria-hidden className={cn("inline-flex items-center justify-center rounded-pill bg-surface-hover font-semibold text-muted", sizes[size])}>
          {initials(name)}
        </span>
      )}
      {channel && <ChannelBadge kind={channel} size="sm" className="absolute -right-1 -bottom-1 size-4 [&_svg]:size-2.5" />}
    </span>
  );
}
