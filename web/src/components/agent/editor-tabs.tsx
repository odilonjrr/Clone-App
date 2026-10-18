"use client";

import { Plus, Trash2 } from "lucide-react";
import Link from "next/link";
import { EditableList } from "@/components/agent/editable-list";
import { HoursEditor } from "@/components/agent/hours-editor";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Field, Input, Textarea } from "@/components/ui/field";
import { Select } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import type { AgentConfig, AgentMedia, Webhook } from "@/lib/data/types";

export type Draft = AgentConfig & { name: string };
export type TabProps = { draft: Draft; set: (patch: Partial<Draft>) => void };

const id = () => crypto.randomUUID();

function Section({ title, description, children }: { title: string; description?: string; children: React.ReactNode }) {
  return (
    <section className="flex flex-col gap-4 rounded-lg border border-border bg-surface-raised p-5">
      <div>
        <h2 className="text-base font-semibold">{title}</h2>
        {description && <p className="mt-1 text-sm text-muted">{description}</p>}
      </div>
      {children}
    </section>
  );
}

// S08a + S08d
export function InstructionsTab({ draft, set }: TabProps) {
  return (
    <div className="flex flex-col gap-4">
      <Section title="Identidade" description="Como o agente se apresenta e fala.">
        <div className="grid gap-4 md:grid-cols-2">
          <Field label="Nome do agente" hint="Só a sua equipe vê.">
            {(p) => <Input {...p} value={draft.name} maxLength={80} onChange={(e) => set({ name: e.target.value })} />}
          </Field>
          <Field label="Idioma principal">
            {(p) => (
              <Select
                {...p}
                value={draft.primaryLanguage}
                onValueChange={(v) => set({ primaryLanguage: v })}
                options={[
                  { value: "pt-BR", label: "Português (Brasil)" },
                  { value: "pt-PT", label: "Português (Portugal)" },
                  { value: "es", label: "Espanhol" },
                  { value: "en", label: "Inglês" },
                ]}
              />
            )}
          </Field>
          <Field label="Tom de voz">
            {(p) => (
              <Select
                {...p}
                value={draft.tone}
                onValueChange={(v) => set({ tone: v as Draft["tone"] })}
                options={[
                  { value: "friendly", label: "Amigável" },
                  { value: "professional", label: "Profissional" },
                  { value: "casual", label: "Descontraído" },
                  { value: "enthusiastic", label: "Animado" },
                  { value: "custom", label: "Personalizado" },
                ]}
              />
            )}
          </Field>
          {draft.tone === "custom" && (
            <Field label="Descreva o tom">
              {(p) => <Input {...p} value={draft.toneCustom} placeholder="Ex.: direto, sem gírias, trata por “você”" onChange={(e) => set({ toneCustom: e.target.value })} />}
            </Field>
          )}
        </div>
        <Field label="Instruções" hint="O que o agente faz, o que nunca deve fazer e quando chamar alguém da equipe.">
          {(p) => <Textarea {...p} value={draft.instructions} className="min-h-40" onChange={(e) => set({ instructions: e.target.value })} />}
        </Field>
      </Section>
      <Section title="Jeito de responder">
        <div className="grid gap-4 md:grid-cols-2">
          <Field label="Velocidade de digitação" hint="Um pequeno atraso deixa a conversa mais natural.">
            {(p) => (
              <Select
                {...p}
                value={draft.typingSpeed}
                onValueChange={(v) => set({ typingSpeed: v as Draft["typingSpeed"] })}
                options={[
                  { value: "instant", label: "Instantânea" },
                  { value: "fast", label: "Rápida" },
                  { value: "natural", label: "Natural" },
                  { value: "slow", label: "Calma" },
                ]}
              />
            )}
          </Field>
          <Field label="Aviso de IA na primeira resposta" hint="Recomendado: deixa claro que é um assistente virtual.">
            {(p) => <Input {...p} value={draft.aiDisclosure} placeholder="Oi! Sou a assistente virtual da…" onChange={(e) => set({ aiDisclosure: e.target.value })} />}
          </Field>
        </div>
        <Switch label="Dividir respostas longas em várias mensagens" checked={draft.splitMessages} onCheckedChange={(v) => set({ splitMessages: v })} />
      </Section>
    </div>
  );
}

// S08b
export function HoursTab({ draft, set }: TabProps) {
  return (
    <Section title="Horário de atendimento da IA" description="Fora do horário, a IA não responde na hora: a mensagem fica guardada e é respondida quando o horário abrir.">
      <Field label="Fuso horário" className="max-w-xs">
        {(p) => (
          <Select
            {...p}
            value={draft.timezone}
            onValueChange={(v) => set({ timezone: v })}
            options={[
              { value: "America/Sao_Paulo", label: "Brasília (GMT-3)" },
              { value: "America/Manaus", label: "Manaus (GMT-4)" },
              { value: "America/Noronha", label: "Fernando de Noronha (GMT-2)" },
              { value: "Europe/Lisbon", label: "Lisboa" },
            ]}
          />
        )}
      </Field>
      <HoursEditor alwaysOn={draft.alwaysOn} hours={draft.activeHours} onChange={({ alwaysOn, hours }) => set({ alwaysOn, activeHours: hours })} />
    </Section>
  );
}

// S08c
export function PlaybookTab({ draft, set }: TabProps) {
  return (
    <Section title="Roteiro de atendimento" description="As etapas que o agente segue para conduzir a conversa até o objetivo. Ele adapta a ordem à conversa.">
      <EditableList
        items={draft.playbook.map((s) => ({ id: s.id, a: s.title, b: s.goal }))}
        onChange={(items) => set({ playbook: items.map((i) => ({ id: i.id, title: i.a, goal: i.b })) })}
        labels={{ a: "Etapa", b: "O que fazer nesta etapa", add: "Adicionar etapa", empty: "Sem roteiro: o agente só responde às perguntas." }}
      />
    </Section>
  );
}

// S08e
export function TagsTab({ draft, set }: TabProps) {
  return (
    <Section title="Tags automáticas" description="A IA marca o contato quando a conversa se encaixa na regra. Use para filtrar e, mais tarde, para automações.">
      <EditableList
        items={draft.tagRules.map((r) => ({ id: r.id, a: r.tagName, b: r.rule }))}
        onChange={(items) => set({ tagRules: items.map((i) => ({ id: i.id, tagName: i.a, rule: i.b })) })}
        labels={{ a: "Tag", b: "Quando aplicar", add: "Adicionar tag", empty: "Nenhuma tag automática." }}
      />
    </Section>
  );
}

function DelayInputs({ label, values, count, min, onChange, firstMin }: { label: string; values: number[]; count: number; min: number; firstMin?: number; onChange: (v: number[]) => void }) {
  return (
    <div className="flex flex-col gap-2">
      {Array.from({ length: count }, (_, i) => {
        const hours = (values[i] ?? (firstMin ?? min) * (i + 1)) / 60;
        const limit = i === 0 && firstMin ? firstMin : min;
        const invalid = hours * 60 < limit;
        return (
          <div key={i} className="flex flex-wrap items-center gap-2 text-sm">
            <label htmlFor={`${label}-${i}`} className="w-32 text-muted">{i + 1}ª mensagem após</label>
            <input
              id={`${label}-${i}`}
              type="number"
              min={limit / 60}
              step={1}
              value={hours}
              aria-invalid={invalid || undefined}
              onChange={(e) => {
                const next = [...values];
                for (let j = 0; j < count; j++) next[j] ??= (firstMin ?? min) * (j + 1);
                next[i] = Math.round(Number(e.target.value) * 60);
                onChange(next.slice(0, count));
              }}
              className="h-9 w-20 rounded-md border border-border-input bg-bg px-2 text-fg aria-invalid:border-danger"
            />
            <span className="text-muted">horas {i === 0 ? "de silêncio" : "da anterior"}</span>
            {invalid && <span className="text-xs text-danger">Mínimo de {limit / 60} h</span>}
          </div>
        );
      })}
    </div>
  );
}

// S08f
export function FollowUpsTab({ draft, set }: TabProps) {
  const f = draft.followUps;
  const upd = (patch: Partial<Draft["followUps"]>) => set({ followUps: { ...f, ...patch } });
  const countOptions = (from: number) => Array.from({ length: 4 - from }, (_, i) => ({ value: String(i + from), label: String(i + from) }));
  return (
    <Section title="Follow-ups" description="Mensagens automáticas para quem parou de responder. Param assim que o contato responder ou a IA for pausada.">
      <Switch label="Enviar follow-ups" checked={f.enabled} onCheckedChange={(v) => upd({ enabled: v })} />
      {f.enabled && (
        <div className="grid gap-6 md:grid-cols-2">
          <div className="flex flex-col gap-3">
            <Field label="Nas primeiras 24 horas" hint="Os únicos que o Instagram permite.">
              {(p) => <Select {...p} value={String(f.quick.count)} onValueChange={(v) => upd({ quick: { ...f.quick, count: Number(v) } })} options={countOptions(1)} />}
            </Field>
            <DelayInputs label="quick" count={f.quick.count} values={f.quick.delaysMinutes} min={60} onChange={(d) => upd({ quick: { ...f.quick, delaysMinutes: d } })} />
          </div>
          <div className="flex flex-col gap-3">
            <Field label="Depois de 24 horas" hint="No WhatsApp, exige um modelo de mensagem aprovado.">
              {(p) => <Select {...p} value={String(f.long.count)} onValueChange={(v) => upd({ long: { ...f.long, count: Number(v) } })} options={countOptions(0)} />}
            </Field>
            <DelayInputs label="long" count={f.long.count} values={f.long.delaysMinutes} min={60} firstMin={1440} onChange={(d) => upd({ long: { ...f.long, delaysMinutes: d } })} />
          </div>
        </div>
      )}
    </Section>
  );
}

// S08g
export function MediaTab({ draft, set }: TabProps) {
  const update = (i: number, patch: Partial<AgentMedia>) => set({ media: draft.media.map((m, j) => (j === i ? { ...m, ...patch } : m)) });
  return (
    <Section title="Mídia" description="Arquivos que o agente pode enviar, como cardápio, tabela de preços ou catálogo.">
      {draft.media.length === 0 && <p className="rounded-md border border-dashed border-border px-4 py-6 text-center text-sm text-muted">Nenhum arquivo ainda.</p>}
      <ul className="flex flex-col gap-3">
        {draft.media.map((m, i) => (
          <li key={m.id} className="grid gap-3 rounded-lg border border-border p-3 md:grid-cols-[1fr_1fr_2fr_auto]">
            <Field label="Nome">{(p) => <Input {...p} value={m.name} onChange={(e) => update(i, { name: e.target.value })} />}</Field>
            <Field label="Link do arquivo" error={m.url && !/^https:\/\//.test(m.url) ? "Use um link https://" : undefined}>
              {(p) => <Input {...p} value={m.url} type="url" placeholder="https://…" onChange={(e) => update(i, { url: e.target.value })} />}
            </Field>
            <Field label="Quando enviar">{(p) => <Input {...p} value={m.sendWhen} placeholder="Quando pedirem a tabela de preços" onChange={(e) => update(i, { sendWhen: e.target.value })} />}</Field>
            <Button variant="ghost" size="icon" aria-label={`Remover ${m.name || "arquivo"}`} className="self-end" onClick={() => set({ media: draft.media.filter((_, j) => j !== i) })}><Trash2 /></Button>
          </li>
        ))}
      </ul>
      <Button variant="secondary" size="sm" className="self-start" onClick={() => set({ media: [...draft.media, { id: id(), name: "", url: "", sendWhen: "" }] })}>
        <Plus /> Adicionar arquivo
      </Button>
      <p className="text-xs text-muted">O envio de arquivos do computador chega com o backend; por enquanto, use um link.</p>
    </Section>
  );
}

// S08h
export function KnowledgeTab({ draft, set, knowledgeBases }: TabProps & { knowledgeBases: { id: string; name: string; faqCount: number }[] }) {
  return (
    <Section title="Conhecimento" description="As bases que o agente consulta para responder.">
      {knowledgeBases.length === 0 ? (
        <p className="text-sm text-muted">Nenhuma base criada. <Link href="/knowledge" className="font-medium text-accent hover:underline">Criar uma base</Link></p>
      ) : (
        <ul className="flex flex-col gap-3">
          {knowledgeBases.map((kb) => (
            <li key={kb.id} className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border px-3 py-2.5">
              <Checkbox
                label={`${kb.name} (${kb.faqCount} perguntas)`}
                checked={draft.knowledgeBaseIds.includes(kb.id)}
                onCheckedChange={(v) =>
                  set({ knowledgeBaseIds: v === true ? [...draft.knowledgeBaseIds, kb.id] : draft.knowledgeBaseIds.filter((x) => x !== kb.id) })
                }
              />
              <Link href={`/knowledge/${kb.id}`} className="text-sm font-medium text-accent hover:underline">Gerenciar</Link>
            </li>
          ))}
        </ul>
      )}
    </Section>
  );
}

// S08i
export function AbilitiesTab({ draft, set }: TabProps) {
  const a = draft.abilities;
  const upd = (patch: Partial<Draft["abilities"]>) => set({ abilities: { ...a, ...patch } });
  const updHook = (i: number, patch: Partial<Webhook>) => upd({ webhooks: a.webhooks.map((w, j) => (j === i ? { ...w, ...patch } : w)) });
  return (
    <div className="flex flex-col gap-4">
      <Section title="Agendamento" description="O agente oferece horários livres da sua agenda e marca dentro da conversa.">
        <Switch label="Agendar reuniões" checked={a.booking.enabled} onCheckedChange={(v) => upd({ booking: { ...a.booking, enabled: v } })} />
        {a.booking.enabled && (
          <>
            <Field label="Duração" className="max-w-xs">
              {(p) => (
                <Select
                  {...p}
                  value={String(a.booking.durationMinutes)}
                  onValueChange={(v) => upd({ booking: { ...a.booking, durationMinutes: Number(v) } })}
                  options={[15, 30, 45, 50, 60, 90].map((m) => ({ value: String(m), label: `${m} minutos` }))}
                />
              )}
            </Field>
            <p className="rounded-md bg-warning-soft px-3 py-2 text-sm text-warning">A conexão com o Google Agenda chega com o backend. Até lá, o agente só pergunta o melhor dia e período.</p>
          </>
        )}
      </Section>
      <Section title="Pesquisa na web" description="Permite consultar a internet quando a resposta não está no conhecimento. Use com cuidado: a resposta pode vir de fontes fora do seu controle.">
        <Switch label="Pesquisar na web" checked={a.webSearch.enabled} onCheckedChange={(v) => upd({ webSearch: { enabled: v } })} />
      </Section>
      <Section title="Funções personalizadas" description="O agente chama um endereço seu (webhook) para consultar ou registrar algo, como estoque ou um pedido.">
        <ul className="flex flex-col gap-3">
          {a.webhooks.map((w, i) => (
            <li key={w.id} className="grid gap-3 rounded-lg border border-border p-3 md:grid-cols-[1fr_1fr_auto]">
              <Field label="Nome da função">{(p) => <Input {...p} value={w.name} placeholder="consultar_pedido" onChange={(e) => updHook(i, { name: e.target.value.replace(/[^a-z0-9_]/gi, "_").toLowerCase() })} />}</Field>
              <Field label="Endereço" error={w.url && !/^https:\/\//.test(w.url) ? "Use um endereço https://" : undefined}>
                {(p) => <Input {...p} type="url" value={w.url} placeholder="https://api.suaempresa.com/pedido" onChange={(e) => updHook(i, { url: e.target.value })} />}
              </Field>
              <Button variant="ghost" size="icon" aria-label={`Remover ${w.name || "função"}`} className="self-end" onClick={() => upd({ webhooks: a.webhooks.filter((_, j) => j !== i) })}><Trash2 /></Button>
              <Field label="Quando usar" className="md:col-span-3">{(p) => <Input {...p} value={w.description} placeholder="Quando o cliente perguntar pelo status de um pedido" onChange={(e) => updHook(i, { description: e.target.value })} />}</Field>
            </li>
          ))}
        </ul>
        <Button variant="secondary" size="sm" className="self-start" onClick={() => upd({ webhooks: [...a.webhooks, { id: id(), name: "", url: "", description: "" }] })}>
          <Plus /> Adicionar função
        </Button>
      </Section>
    </div>
  );
}
