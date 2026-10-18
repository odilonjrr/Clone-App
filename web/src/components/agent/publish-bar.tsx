"use client";

import { CheckCircle2 } from "lucide-react";
import { Button } from "@/components/ui/button";

type PublishBarProps = {
  dirty: boolean;
  publishing?: boolean;
  publishedAt?: string;
  onPublish?: () => void;
  onDiscard?: () => void;
  onTry?: () => void;
};

export function PublishBar({ dirty, publishing, publishedAt, onPublish, onDiscard, onTry }: PublishBarProps) {
  return (
    <div className="flex flex-wrap items-center gap-3 rounded-lg border border-border bg-surface-raised px-4 py-2.5 shadow-card">
      <p role="status" className="flex items-center gap-2 text-sm">
        {dirty ? (
          <>
            <span aria-hidden className="size-2 rounded-pill bg-accent" />
            <span className="font-medium text-fg">Rascunho com mudanças</span>
          </>
        ) : (
          <>
            <CheckCircle2 className="size-4 text-success" aria-hidden />
            <span className="text-muted">Publicado{publishedAt ? ` em ${publishedAt}` : ""}</span>
          </>
        )}
      </p>
      <div className="ml-auto flex gap-2">
        <Button variant="ghost" size="sm" onClick={onTry}>Testar</Button>
        {dirty && <Button variant="secondary" size="sm" onClick={onDiscard} disabled={publishing}>Descartar</Button>}
        <Button size="sm" onClick={onPublish} disabled={!dirty} loading={publishing}>Publicar</Button>
      </div>
    </div>
  );
}
