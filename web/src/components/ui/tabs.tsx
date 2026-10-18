"use client";

import { Tabs as T } from "radix-ui";
import type { ComponentProps } from "react";
import { cn } from "@/lib/cn";

export const Tabs = T.Root;
export const TabsContent = T.Content;

export function TabsList({ className, variant = "line", ...props }: ComponentProps<typeof T.List> & { variant?: "line" | "segmented" }) {
  return (
    <T.List
      className={cn(
        variant === "line"
          ? "flex gap-1 overflow-x-auto border-b border-border"
          : "inline-flex gap-1 rounded-md bg-surface p-1",
        className,
      )}
      data-variant={variant}
      {...props}
    />
  );
}

export function TabsTrigger({ className, dirty, children, ...props }: ComponentProps<typeof T.Trigger> & { dirty?: boolean }) {
  return (
    <T.Trigger
      className={cn(
        "relative inline-flex h-10 items-center gap-1.5 px-3 text-sm font-medium whitespace-nowrap text-muted transition-colors hover:text-fg",
        "data-[state=active]:text-fg",
        "[[data-variant=line]_&]:data-[state=active]:after:absolute [[data-variant=line]_&]:data-[state=active]:after:inset-x-2 [[data-variant=line]_&]:data-[state=active]:after:-bottom-px [[data-variant=line]_&]:data-[state=active]:after:h-0.5 [[data-variant=line]_&]:data-[state=active]:after:rounded-pill [[data-variant=line]_&]:data-[state=active]:after:bg-accent",
        "[[data-variant=segmented]_&]:h-8 [[data-variant=segmented]_&]:rounded-sm [[data-variant=segmented]_&]:data-[state=active]:bg-bg [[data-variant=segmented]_&]:data-[state=active]:shadow-card",
        className,
      )}
      {...props}
    >
      {children}
      {dirty && (
        <>
          <span aria-hidden className="size-1.5 rounded-pill bg-accent" />
          <span className="sr-only">(mudanças não publicadas)</span>
        </>
      )}
    </T.Trigger>
  );
}
