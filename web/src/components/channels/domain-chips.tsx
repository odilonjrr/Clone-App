"use client";

import { X } from "lucide-react";
import { useId, useState } from "react";
import { cn } from "@/lib/cn";

const DOMAIN = /^(\*\.)?([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/;

export function isValidDomain(d: string) {
  return DOMAIN.test(d);
}

export function DomainChips({ value, onChange }: { value: string[]; onChange: (v: string[]) => void }) {
  const id = useId();
  const [draft, setDraft] = useState("");
  const commit = () => {
    const d = draft.trim().toLowerCase().replace(/^https?:\/\//, "").replace(/\/.*$/, "");
    if (d && !value.includes(d)) onChange([...value, d]);
    setDraft("");
  };
  const invalidCount = value.filter((d) => !isValidDomain(d)).length;
  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={id} className="text-sm font-medium text-fg">Domínios permitidos</label>
      <div className="flex min-h-10 flex-wrap items-center gap-1.5 rounded-md border border-border-input bg-bg px-2 py-1.5 focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-accent">
        {value.map((d) => {
          const ok = isValidDomain(d);
          return (
            <span key={d} className={cn("inline-flex items-center gap-1 rounded-sm px-2 py-0.5 text-xs font-medium", ok ? "bg-surface-hover text-fg" : "bg-danger-soft text-danger")}>
              {d}
              {!ok && <span className="sr-only">(inválido)</span>}
              <button type="button" aria-label={`Remover ${d}`} onClick={() => onChange(value.filter((x) => x !== d))} className="rounded-sm hover:opacity-70">
                <X className="size-3" />
              </button>
            </span>
          );
        })}
        <input
          id={id}
          value={draft}
          onChange={(e) => setDraft(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === "Enter" || e.key === ",") {
              e.preventDefault();
              commit();
            } else if (e.key === "Backspace" && !draft && value.length) {
              onChange(value.slice(0, -1));
            }
          }}
          onBlur={commit}
          placeholder={value.length ? "" : "exemplo.com.br ou *.exemplo.com.br"}
          aria-describedby={`${id}-hint`}
          className="h-7 min-w-40 flex-1 bg-transparent text-sm text-fg outline-none placeholder:text-muted"
        />
      </div>
      <p id={`${id}-hint`} className={cn("text-xs", invalidCount ? "text-danger" : "text-muted")}>
        {invalidCount
          ? `${invalidCount} domínio(s) inválido(s).`
          : "Deixe vazio para o widget funcionar em qualquer site. Enter ou vírgula adiciona."}
      </p>
    </div>
  );
}
