"use client";

import { useId } from "react";

const HEX = /^#[0-9a-f]{6}$/i;

export function ColorField({ label, value, onChange }: { label: string; value: string; onChange: (v: string) => void }) {
  const id = useId();
  const invalid = !HEX.test(value);
  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={id} className="text-sm font-medium text-fg">{label}</label>
      <div className="flex items-center gap-2">
        <input
          type="color"
          aria-label={`${label}, seletor`}
          value={invalid ? "#000000" : value}
          onChange={(e) => onChange(e.target.value)}
          className="size-10 cursor-pointer rounded-md border border-border-input bg-bg p-1"
        />
        <input
          id={id}
          value={value}
          onChange={(e) => onChange(e.target.value)}
          aria-invalid={invalid || undefined}
          className="h-10 w-28 rounded-md border border-border-input bg-bg px-3 font-mono text-sm text-fg aria-invalid:border-danger"
        />
      </div>
      {invalid && <p className="text-xs text-danger">Use o formato #RRGGBB.</p>}
    </div>
  );
}
