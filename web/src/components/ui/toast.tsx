"use client";

import { CheckCircle2, AlertCircle, Info, X } from "lucide-react";
import { Toast as T } from "radix-ui";
import { createContext, useCallback, useContext, useState, type ReactNode } from "react";
import { cn } from "@/lib/cn";

type Tone = "success" | "danger" | "info";
type Item = { id: number; tone: Tone; title: string; action?: { label: string; onClick: () => void } };

const ToastContext = createContext<(t: Omit<Item, "id">) => void>(() => {});
export const useToast = () => useContext(ToastContext);

const icons = { success: CheckCircle2, danger: AlertCircle, info: Info };
const tones = { success: "text-success", danger: "text-danger", info: "text-accent" };

export function ToastProvider({ children }: { children: ReactNode }) {
  const [items, setItems] = useState<Item[]>([]);
  const push = useCallback((t: Omit<Item, "id">) => setItems((xs) => [...xs, { ...t, id: Date.now() + Math.random() }]), []);
  return (
    <ToastContext.Provider value={push}>
      <T.Provider duration={5000} label="Notificações">
        {children}
        {items.map((t) => {
          const Icon = icons[t.tone];
          return (
            <T.Root
              key={t.id}
              type={t.tone === "danger" ? "foreground" : "background"}
              onOpenChange={(open) => !open && setItems((xs) => xs.filter((x) => x.id !== t.id))}
              className="flex items-center gap-3 rounded-md border border-border bg-surface-raised p-3 shadow-pop"
            >
              <Icon className={cn("size-4 shrink-0", tones[t.tone])} aria-hidden />
              <T.Title className="flex-1 text-sm text-fg">{t.title}</T.Title>
              {t.action && (
                <T.Action altText={t.action.label} asChild>
                  <button onClick={t.action.onClick} className="text-sm font-semibold text-accent hover:underline">
                    {t.action.label}
                  </button>
                </T.Action>
              )}
              <T.Close aria-label="Fechar" className="rounded-sm p-0.5 text-muted hover:text-fg">
                <X className="size-4" />
              </T.Close>
            </T.Root>
          );
        })}
        <T.Viewport className="fixed right-4 bottom-4 z-50 flex w-[calc(100vw-2rem)] max-w-sm flex-col gap-2" />
      </T.Provider>
    </ToastContext.Provider>
  );
}
