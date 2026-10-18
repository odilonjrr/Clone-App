"use client";

import { ArrowDown, ArrowUp, Plus, Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";

export type Pair = { id: string; a: string; b: string };

type EditableListProps = {
  items: Pair[];
  onChange: (items: Pair[]) => void;
  labels: { a: string; b: string; add: string; empty: string };
};

// Lista de pares (tag + regra, pergunta + resposta, etapa + objetivo).
// Reordenar por botões, não só arrastando, para funcionar no teclado.
export function EditableList({ items, onChange, labels }: EditableListProps) {
  const update = (id: string, patch: Partial<Pair>) => onChange(items.map((i) => (i.id === id ? { ...i, ...patch } : i)));
  const move = (index: number, by: number) => {
    const next = [...items];
    const [it] = next.splice(index, 1);
    next.splice(index + by, 0, it);
    onChange(next);
  };
  return (
    <div className="flex flex-col gap-3">
      {items.length === 0 && <p className="rounded-md border border-dashed border-border px-4 py-6 text-center text-sm text-muted">{labels.empty}</p>}
      <ol className="flex flex-col gap-3">
        {items.map((item, i) => (
          <li key={item.id} className="flex gap-2 rounded-lg border border-border p-3">
            <div className="grid flex-1 gap-2 sm:grid-cols-[1fr_2fr]">
              <input
                aria-label={`${labels.a} ${i + 1}`}
                value={item.a}
                onChange={(e) => update(item.id, { a: e.target.value })}
                className="h-9 rounded-md border border-border-input bg-bg px-3 text-sm text-fg"
              />
              <textarea
                aria-label={`${labels.b} ${i + 1}`}
                value={item.b}
                rows={1}
                onChange={(e) => update(item.id, { b: e.target.value })}
                className="min-h-9 rounded-md border border-border-input bg-bg px-3 py-1.5 text-sm text-fg [field-sizing:content]"
              />
            </div>
            <div className="flex flex-col gap-1">
              <Button variant="ghost" size="icon-sm" aria-label="Subir" disabled={i === 0} onClick={() => move(i, -1)}><ArrowUp /></Button>
              <Button variant="ghost" size="icon-sm" aria-label="Descer" disabled={i === items.length - 1} onClick={() => move(i, 1)}><ArrowDown /></Button>
              <Button variant="ghost" size="icon-sm" aria-label="Remover" onClick={() => onChange(items.filter((x) => x.id !== item.id))}><Trash2 /></Button>
            </div>
          </li>
        ))}
      </ol>
      <Button variant="secondary" size="sm" className="self-start" onClick={() => onChange([...items, { id: crypto.randomUUID(), a: "", b: "" }])}>
        <Plus /> {labels.add}
      </Button>
    </div>
  );
}
