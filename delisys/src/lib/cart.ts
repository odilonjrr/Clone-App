"use client";
import { useCallback, useEffect, useState, useSyncExternalStore } from "react";

/** Sacola do cliente, guardada no aparelho (localStorage) por loja. Os preços são só prévia:
 *  o servidor refaz tudo ao criar o pedido. */
export type CartLine = {
  key: string;
  productId: string;
  name: string;
  image?: string | null;
  quantity: number;
  options: Record<string, string[]>;
  optionLabels: string[];
  unitPriceCents: number;
  notes?: string;
};

const listeners = new Set<() => void>();
const emit = () => listeners.forEach((l) => l());
const storageKey = (slug: string) => `delisys:sacola:${slug}`;

function read(slug: string): CartLine[] {
  try {
    const raw = localStorage.getItem(storageKey(slug));
    const v = raw ? JSON.parse(raw) : [];
    return Array.isArray(v) ? v : [];
  } catch {
    return [];
  }
}
function write(slug: string, lines: CartLine[]) {
  try {
    localStorage.setItem(storageKey(slug), JSON.stringify(lines));
  } catch {
    /* modo anônimo ou armazenamento cheio: a sacola vale só nesta aba */
  }
  cache.set(slug, lines);
  emit();
}

const cache = new Map<string, CartLine[]>();
const EMPTY: CartLine[] = [];

export function useCart(slug: string) {
  const subscribe = useCallback((cb: () => void) => {
    listeners.add(cb);
    const onStorage = (e: StorageEvent) => {
      if (e.key === storageKey(slug)) {
        cache.set(slug, read(slug));
        cb();
      }
    };
    window.addEventListener("storage", onStorage);
    return () => {
      listeners.delete(cb);
      window.removeEventListener("storage", onStorage);
    };
  }, [slug]);
  const getSnapshot = useCallback(() => {
    if (!cache.has(slug)) cache.set(slug, read(slug));
    return cache.get(slug)!;
  }, [slug]);
  const lines = useSyncExternalStore(subscribe, getSnapshot, () => EMPTY);

  const [hydrated, setHydrated] = useState(false);
  useEffect(() => setHydrated(true), []);

  return {
    lines,
    hydrated,
    count: lines.reduce((s, l) => s + l.quantity, 0),
    subtotalCents: lines.reduce((s, l) => s + l.unitPriceCents * l.quantity, 0),
    add(line: Omit<CartLine, "key">) {
      write(slug, [...getSnapshot(), { ...line, key: crypto.randomUUID() }]);
    },
    setQuantity(key: string, quantity: number) {
      write(
        slug,
        getSnapshot()
          .map((l) => (l.key === key ? { ...l, quantity: Math.max(0, Math.min(99, quantity)) } : l))
          .filter((l) => l.quantity > 0),
      );
    },
    clear() {
      write(slug, []);
    },
  };
}
