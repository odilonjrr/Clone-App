"use client";

import { Globe, Pencil, Plus, Trash2 } from "lucide-react";
import { useState, useTransition } from "react";
import { addFaq, addWebsiteSource, deleteFaq, updateFaq } from "@/app/actions";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog, DialogClose, DialogContent, DialogTrigger } from "@/components/ui/dialog";
import { Field, Input, Textarea } from "@/components/ui/field";
import { EmptyState } from "@/components/ui/misc";
import { useToast } from "@/components/ui/toast";
import type { FaqItem, KnowledgeBase, KnowledgeSource } from "@/lib/data/types";
import { formatDateTime } from "@/lib/format";

const sourceStatus = {
  pending: { tone: "neutral", text: "Na fila" },
  processing: { tone: "accent", text: "Lendo…" },
  ready: { tone: "success", text: "Pronto" },
  failed: { tone: "danger", text: "Falhou" },
} as const;

function FaqDialog({ kbId, item, trigger }: { kbId: string; item?: FaqItem; trigger: React.ReactNode }) {
  const toast = useToast();
  const [open, setOpen] = useState(false);
  const [q, setQ] = useState(item?.question ?? "");
  const [a, setA] = useState(item?.answer ?? "");
  const [error, setError] = useState<string>();
  const [pending, start] = useTransition();
  return (
    <Dialog open={open} onOpenChange={(o) => { setOpen(o); if (o) { setQ(item?.question ?? ""); setA(item?.answer ?? ""); setError(undefined); } }}>
      <DialogTrigger asChild>{trigger}</DialogTrigger>
      <DialogContent
        title={item ? "Editar pergunta" : "Nova pergunta"}
        footer={
          <>
            <DialogClose asChild><Button variant="secondary">Cancelar</Button></DialogClose>
            <Button
              loading={pending}
              onClick={() =>
                start(async () => {
                  const r = item ? await updateFaq(item.id, q, a) : await addFaq(kbId, q, a);
                  if (!r.ok) return setError(r.error);
                  setOpen(false);
                  toast({ tone: "success", title: item ? "Pergunta atualizada." : "Pergunta adicionada." });
                })
              }
            >
              Salvar
            </Button>
          </>
        }
      >
        <div className="flex flex-col gap-4">
          <Field label="Pergunta">{(p) => <Input {...p} value={q} onChange={(e) => setQ(e.target.value)} placeholder="Vocês entregam no sábado?" />}</Field>
          <Field label="Resposta" error={error}>{(p) => <Textarea {...p} value={a} onChange={(e) => setA(e.target.value)} placeholder="Sim, das 9h às 13h." />}</Field>
        </div>
      </DialogContent>
    </Dialog>
  );
}

export function KnowledgeEditor({ kb, sources, faq }: { kb: KnowledgeBase; sources: KnowledgeSource[]; faq: FaqItem[] }) {
  const toast = useToast();
  const [url, setUrl] = useState("");
  const [urlError, setUrlError] = useState<string>();
  const [pending, start] = useTransition();
  return (
    <div className="flex flex-col gap-6">
      <section aria-labelledby="fontes" className="flex flex-col gap-3 rounded-lg border border-border bg-surface-raised p-5">
        <h2 id="fontes" className="text-base font-semibold">Fontes</h2>
        {sources.length === 0 && <p className="text-sm text-muted">Nenhum site adicionado.</p>}
        <ul className="flex flex-col divide-y divide-border">
          {sources.map((s) => (
            <li key={s.id} className="flex flex-wrap items-center gap-3 py-2.5 text-sm">
              <Globe className="size-4 text-muted" aria-hidden />
              <span className="min-w-0 flex-1 truncate font-medium">{s.title}</span>
              {s.pages !== undefined && <span className="text-xs text-muted">{s.pages} páginas</span>}
              {s.lastSyncedAt && <span className="text-xs text-muted">Lido em {formatDateTime(s.lastSyncedAt)}</span>}
              <Badge tone={sourceStatus[s.status].tone}>{sourceStatus[s.status].text}</Badge>
            </li>
          ))}
        </ul>
        <form
          className="flex flex-wrap items-start gap-2"
          onSubmit={(e) => {
            e.preventDefault();
            start(async () => {
              const r = await addWebsiteSource(kb.id, url);
              if (!r.ok) return setUrlError(r.error);
              setUrl("");
              toast({ tone: "info", title: "Lendo o site. Isso leva alguns segundos." });
            });
          }}
        >
          <div className="min-w-56 flex-1">
            <Field label="Adicionar site" error={urlError}>{(p) => <Input {...p} value={url} inputMode="url" placeholder="minhaempresa.com.br" onChange={(e) => { setUrl(e.target.value); setUrlError(undefined); }} />}</Field>
          </div>
          <Button type="submit" variant="secondary" className="mt-6" disabled={!url.trim()} loading={pending}>Ler site</Button>
        </form>
        <p className="text-xs text-muted">Nesta versão de demonstração a leitura do site é simulada. A leitura de verdade e o upload de PDF chegam com o backend.</p>
      </section>

      <section aria-labelledby="faq" className="flex flex-col gap-3">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <h2 id="faq" className="text-base font-semibold">Perguntas frequentes ({faq.length})</h2>
          <FaqDialog kbId={kb.id} trigger={<Button size="sm"><Plus /> Nova pergunta</Button>} />
        </div>
        {faq.length === 0 ? (
          <div className="rounded-lg border border-border">
            <EmptyState icon={Plus} title="Nenhuma pergunta ainda">Adicione as perguntas que os clientes mais fazem, com a resposta que você daria.</EmptyState>
          </div>
        ) : (
          <ul className="flex flex-col divide-y divide-border rounded-lg border border-border">
            {faq.map((f) => (
              <li key={f.id} className="flex gap-3 p-4">
                <div className="min-w-0 flex-1">
                  <p className="text-sm font-semibold break-words">{f.question}</p>
                  <p className="mt-1 text-sm whitespace-pre-wrap text-muted">{f.answer}</p>
                </div>
                <FaqDialog kbId={kb.id} item={f} trigger={<Button variant="ghost" size="icon-sm" aria-label={`Editar: ${f.question}`}><Pencil /></Button>} />
                <Button
                  variant="ghost"
                  size="icon-sm"
                  aria-label={`Excluir: ${f.question}`}
                  onClick={() =>
                    start(async () => {
                      await deleteFaq(f.id);
                      toast({ tone: "info", title: "Pergunta excluída.", action: { label: "Desfazer", onClick: () => void addFaq(kb.id, f.question, f.answer) } });
                    })
                  }
                >
                  <Trash2 />
                </Button>
              </li>
            ))}
          </ul>
        )}
      </section>
    </div>
  );
}
