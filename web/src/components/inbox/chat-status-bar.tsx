import { AlertTriangle, Clock, PauseCircle, Unplug } from "lucide-react";
import { cn } from "@/lib/cn";

type Kind = "offline-hours" | "ai-paused" | "disconnected" | "window-closed";

const config: Record<Kind, { icon: typeof Clock; cls: string }> = {
  "offline-hours": { icon: Clock, cls: "bg-surface text-muted" },
  "ai-paused": { icon: PauseCircle, cls: "bg-warning-soft text-warning" },
  disconnected: { icon: Unplug, cls: "bg-danger-soft text-danger" },
  "window-closed": { icon: AlertTriangle, cls: "bg-warning-soft text-warning" },
};

export function ChatStatusBar({ kind, children }: { kind: Kind; children: React.ReactNode }) {
  const { icon: Icon, cls } = config[kind];
  return (
    <div role="status" className={cn("flex items-center gap-2 px-4 py-2 text-xs font-medium", cls)}>
      <Icon className="size-3.5 shrink-0" aria-hidden />
      <span>{children}</span>
    </div>
  );
}
