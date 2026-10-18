import type { LucideIcon } from "lucide-react";
import type { ComponentProps, ReactNode } from "react";
import { cn } from "@/lib/cn";

export function Skeleton({ className, ...props }: ComponentProps<"div">) {
  return <div aria-hidden className={cn("animate-pulse rounded-md bg-surface-hover", className)} {...props} />;
}

type EmptyStateProps = { icon: LucideIcon; title: string; children?: ReactNode; action?: ReactNode; className?: string };

export function EmptyState({ icon: Icon, title, children, action, className }: EmptyStateProps) {
  return (
    <div className={cn("flex flex-col items-center gap-2 px-6 py-10 text-center", className)}>
      <Icon className="size-8 text-muted" aria-hidden />
      <p className="text-sm font-semibold text-fg">{title}</p>
      {children && <p className="max-w-sm text-sm text-muted">{children}</p>}
      {action && <div className="mt-2">{action}</div>}
    </div>
  );
}

export function Table({ className, ...props }: ComponentProps<"table">) {
  return (
    <div className="overflow-x-auto rounded-lg border border-border">
      <table
        className={cn(
          "w-full border-collapse text-left text-sm",
          "[&_th]:bg-surface [&_th]:px-4 [&_th]:py-2.5 [&_th]:text-xs [&_th]:font-semibold [&_th]:text-muted",
          "[&_td]:border-t [&_td]:border-border [&_td]:px-4 [&_td]:py-3 [&_tbody_tr:hover]:bg-surface",
          className,
        )}
        {...props}
      />
    </div>
  );
}
