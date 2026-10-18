"use client";

import { Plus, Route, Trash2 } from "lucide-react";
import { useState, useTransition } from "react";
import { deleteEntryPoint, upsertEntryPoint } from "@/app/actions";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { ChannelBadge, type ChannelKind } from "@/components/ui/channel-badge";
import { Dialog, DialogClose, DialogContent, DialogTrigger } from "@/components/ui/dialog";
import { Field, Input } from "@/components/ui/field";
import { EmptyState } from "@/components/ui/misc";
import { Select } from "@/components/ui/select";
import { useToast } from "@/components/ui/toast";
import type { EntryPoint } from "@/lib/data/types";

type Opt = { id: string; name: string };

function RuleDialog({ agents, channels }: { agents: Opt[]; channels: (Opt & { kind: ChannelKind })[] }) {
  const toast = useToast();
  const [open, setOpen] = useState(false);
  const [kind, setKind] = useState<EntryPoint["kind"]>("keyword");
  const [channelId, setChannelId] = useState(channels[0]?.id ?? "");
  const [agentId, setAgentId] = useState(agents[0]?.id ?? "");
  const [keyword, setKeyword] = useState("");
  const [error, setError] = useState<string>();
  const [pending, start] = useTransition();
  return (
    <Dialog open={open} onOpenChange={(o) => { setOpen(o); setError(undefined); }}>
      <DialogTrigger asChild><Button disabled={!agents.length || !channels.length}><Plus /> Nova regra</Button></DialogTrigger>
      <DialogContent
        title="Nova regra de roteamento"
        footer={
          <>
            <DialogClose asChild><Button variant="secondary">Cancelar</Button></DialogClose>
            <Button
              loading={pending}
              onClick={() =>
                start(async () => {
                  const r = await upsertEntryPoint({ kind, channelId, agentId, keyword: kind === "keyword" ? keyword : undefined, priority: kind === "keyword" ? 10 : 100, isActive: true });
                  if (!r.ok) return setError(r.error);
                  setOpen(false);
                  setKeyword("");
                  toast({ tone: "success", title: "Regra criada." });
                })
              }
            >
              Criar regra
            </Button>
          </>
        }
      >
        <div className="flex flex-col gap-4">
          <Field label="Tipo">
            {(p) => <Select {...p} value={kind} onValueChange={(v) => setKind(v as EntryPoint["kind"])} options={[{ value: "keyword", label: "Palavra-chave na primeira mensagem" }, { value: "channel_default", label: "Padrão do canal" }]} />}
          </Field>
          <Field label="Canal">{(p) => <Select {...p} value={channelId} onValueChange={setChannelId} options={channels.map((c) => ({ value: c.id, label: c.name }))} />}</Field>
          {kind === "keyword" && (
            <Field label="Palavra-chave" hint="Só vale para a primeira mensagem de uma conversa nova." error={error}>
              {(p) => <Input {...p} value={keyword} placeholder="Ex.: orçamento" onChange={(e) => { setKeyword(e.target.value); setError(undefined); }} />}
            </Field>
          )}
          <Field label="Agente que responde" error={kind !== "keyword" ? error : undefined}>{(p) => <Select {...p} value={agentId} onValueChange={setAgentId} options={agents.map((a) => ({ value: a.id, label: a.name }))} />}</Field>
        </div>
      </DialogContent>
    </Dialog>
  );
}

export function EntryPointsEditor({ entryPoints, agents, channels }: { entryPoints: EntryPoint[]; agents: Opt[]; channels: (Opt & { kind: ChannelKind })[] }) {
  const toast = useToast();
  const [, start] = useTransition();
  const rows = entryPoints
    .map((e) => ({ e, channel: channels.find((c) => c.id === e.channelId), agent: agents.find((a) => a.id === e.agentId) }))
    .filter((r) => r.channel)
    .sort((a, b) => a.channel!.name.localeCompare(b.channel!.name) || a.e.priority - b.e.priority);
  return (
    <div className="flex flex-col gap-4">
      <div className="flex justify-end"><RuleDialog agents={agents} channels={channels} /></div>
      {rows.length === 0 ? (
        <div className="rounded-lg border border-border"><EmptyState icon={Route} title="Nenhuma regra">Sem regra, conversas novas ficam sem agente e só a equipe responde.</EmptyState></div>
      ) : (
        <ul className="flex flex-col divide-y divide-border rounded-lg border border-border">
          {rows.map(({ e, channel, agent }) => (
            <li key={e.id} className="flex flex-wrap items-center gap-3 p-4 text-sm">
              <ChannelBadge kind={channel!.kind} size="sm" />
              <span className="font-medium">{channel!.name}</span>
              {e.kind === "keyword" ? <Badge tone="accent">Contém “{e.keyword}”</Badge> : <Badge>Padrão</Badge>}
              <span className="text-muted">→</span>
              <span className="min-w-0 flex-1 truncate">{agent?.name ?? "Agente removido"}</span>
              <Button
                variant="ghost"
                size="icon-sm"
                aria-label="Excluir regra"
                onClick={() => start(async () => { await deleteEntryPoint(e.id); toast({ tone: "info", title: "Regra excluída." }); })}
              >
                <Trash2 />
              </Button>
            </li>
          ))}
        </ul>
      )}
      <p className="text-xs text-muted">Comentários e novos seguidores do Instagram entram como regras quando o canal chegar.</p>
    </div>
  );
}
