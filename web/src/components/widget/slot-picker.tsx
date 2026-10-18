"use client";

import { useState } from "react";
import { cn } from "@/lib/cn";

type Day = { key: string; label: string; slots: string[] };

export function SlotPicker({ days, timezone, onPick }: { days: Day[]; timezone: string; onPick?: (day: string, slot: string) => void }) {
  const [day, setDay] = useState(days[0]?.key);
  const [picked, setPicked] = useState<string>();
  const current = days.find((d) => d.key === day);
  return (
    <div className="flex flex-col gap-2">
      <div role="tablist" aria-label="Dia" className="flex gap-1 overflow-x-auto">
        {days.map((d) => (
          <button
            key={d.key}
            role="tab"
            type="button"
            aria-selected={d.key === day}
            onClick={() => setDay(d.key)}
            className={cn("rounded-md px-2.5 py-1 text-xs font-medium whitespace-nowrap", d.key === day ? "bg-accent text-on-accent" : "bg-surface text-fg hover:bg-surface-hover")}
          >
            {d.label}
          </button>
        ))}
      </div>
      {current && current.slots.length === 0 ? (
        <p className="text-xs text-muted">Sem horários neste dia.</p>
      ) : (
        <div className="grid grid-cols-3 gap-1.5">
          {current?.slots.map((s) => (
            <button
              key={s}
              type="button"
              aria-pressed={picked === s}
              onClick={() => {
                setPicked(s);
                onPick?.(current.key, s);
              }}
              className={cn("h-8 rounded-md border text-xs font-medium", picked === s ? "border-accent bg-accent-soft text-accent" : "border-border-input bg-bg text-fg hover:bg-surface-hover")}
            >
              {s}
            </button>
          ))}
        </div>
      )}
      <p className="text-xs text-muted">Horários em {timezone}</p>
    </div>
  );
}
