"use client";

import { Switch } from "@/components/ui/switch";
import { cn } from "@/lib/cn";

export type DayKey = "mon" | "tue" | "wed" | "thu" | "fri" | "sat" | "sun";
export type Hours = Partial<Record<DayKey, [start: string, end: string]>>;

const days: { key: DayKey; label: string }[] = [
  { key: "mon", label: "Segunda" },
  { key: "tue", label: "Terça" },
  { key: "wed", label: "Quarta" },
  { key: "thu", label: "Quinta" },
  { key: "fri", label: "Sexta" },
  { key: "sat", label: "Sábado" },
  { key: "sun", label: "Domingo" },
];

type HoursEditorProps = {
  alwaysOn: boolean;
  hours: Hours;
  onChange: (next: { alwaysOn: boolean; hours: Hours }) => void;
};

export function HoursEditor({ alwaysOn, hours, onChange }: HoursEditorProps) {
  const setDay = (key: DayKey, value: [string, string] | undefined) => {
    const next = { ...hours };
    if (value) next[key] = value;
    else delete next[key];
    onChange({ alwaysOn, hours: next });
  };
  return (
    <div className="flex flex-col gap-4">
      <Switch label="Ativo 24 horas, todos os dias" checked={alwaysOn} onCheckedChange={(v) => onChange({ alwaysOn: v, hours })} />
      {!alwaysOn && (
        <div className="flex flex-col divide-y divide-border rounded-lg border border-border">
          {days.map(({ key, label }) => {
            const range = hours[key];
            const invalid = !!range && range[1] <= range[0];
            return (
              <fieldset key={key} className="flex flex-wrap items-center gap-3 px-4 py-2.5">
                <legend className="sr-only">{label}</legend>
                <Switch label={label} hideLabel checked={!!range} onCheckedChange={(on) => setDay(key, on ? ["09:00", "18:00"] : undefined)} />
                <span className="w-20 text-sm font-medium text-fg">{label}</span>
                {range ? (
                  <span className="flex items-center gap-2 text-sm">
                    <input
                      type="time"
                      aria-label={`${label}, início`}
                      value={range[0]}
                      onChange={(e) => setDay(key, [e.target.value, range[1]])}
                      className={cn("h-9 rounded-md border bg-bg px-2 text-fg", invalid ? "border-danger" : "border-border-input")}
                    />
                    <span className="text-muted">até</span>
                    <input
                      type="time"
                      aria-label={`${label}, fim`}
                      aria-invalid={invalid || undefined}
                      value={range[1]}
                      onChange={(e) => setDay(key, [range[0], e.target.value])}
                      className={cn("h-9 rounded-md border bg-bg px-2 text-fg", invalid ? "border-danger" : "border-border-input")}
                    />
                    {invalid && <span className="text-xs text-danger">O fim precisa ser depois do início</span>}
                  </span>
                ) : (
                  <span className="text-sm text-muted">Sem atendimento da IA</span>
                )}
              </fieldset>
            );
          })}
        </div>
      )}
    </div>
  );
}
