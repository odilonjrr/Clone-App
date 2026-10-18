"use client";

import { useRouter } from "next/navigation";
import { useEffect } from "react";

/** Substituto do Supabase Realtime no data layer falso: recarrega os dados. */
export function AutoRefresh({ ms = 2500 }: { ms?: number }) {
  const router = useRouter();
  useEffect(() => {
    const t = setInterval(() => {
      if (document.visibilityState === "visible") router.refresh();
    }, ms);
    return () => clearInterval(t);
  }, [router, ms]);
  return null;
}
