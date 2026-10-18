"use client";

import { ExternalLink } from "lucide-react";
import { useState, useTransition } from "react";
import { updateWidgetConfig } from "@/app/actions";
import { ColorField } from "@/components/channels/color-field";
import { DomainChips, isValidDomain } from "@/components/channels/domain-chips";
import { Button, buttonVariants } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { CodeBlock } from "@/components/ui/code-block";
import { Field, Input, Textarea } from "@/components/ui/field";
import { Select } from "@/components/ui/select";
import { useToast } from "@/components/ui/toast";
import { ChatWidgetPreview } from "@/components/widget/chat-widget-preview";
import type { WidgetConfig } from "@/lib/data/types";

const HEX = /^#[0-9a-f]{6}$/i;

// S15
export function WidgetSettings({ channelId, initial, origin }: { channelId: string; initial: WidgetConfig; origin: string }) {
  const toast = useToast();
  const [saved, setSaved] = useState(initial);
  const [w, setW] = useState(initial);
  const [pending, start] = useTransition();
  const set = (patch: Partial<WidgetConfig>) => setW((x) => ({ ...x, ...patch }));
  const dirty = JSON.stringify(w) !== JSON.stringify(saved);
  const invalid = !HEX.test(w.primaryColor) || w.allowedDomains.some((d) => !isValidDomain(d)) || !w.title.trim();
  const snippet =
    w.layout === "inline"
      ? `<div id="atendente-ia" style="height:600px"></div>\n<script src="${origin}/widget.js" data-key="${w.publicKey}"\n  data-mode="inline" data-target="atendente-ia" async></script>`
      : `<script src="${origin}/widget.js" data-key="${w.publicKey}" async></script>`;
  const toggleField = (f: WidgetConfig["collectFields"][number], on: boolean) =>
    set({ collectFields: on ? [...new Set([...w.collectFields, f])] : w.collectFields.filter((x) => x !== f) });

  return (
    <div className="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_minmax(0,400px)] [&>*]:min-w-0">
      <form
        className="flex flex-col gap-6"
        onSubmit={(e) => {
          e.preventDefault();
          if (invalid) return;
          start(async () => {
            const r = await updateWidgetConfig(channelId, w);
            if (r.ok) {
              setSaved(w);
              toast({ tone: "success", title: "Widget atualizado. O site já mostra a nova versão." });
            } else toast({ tone: "danger", title: r.error });
          });
        }}
      >
        <section className="flex flex-col gap-4 rounded-lg border border-border bg-surface-raised p-5">
          <h2 className="text-base font-semibold">Aparência</h2>
          <div className="grid gap-4 md:grid-cols-2">
            <Field label="Título" error={!w.title.trim() ? "Dê um título ao widget." : undefined}>
              {(p) => <Input {...p} value={w.title} maxLength={40} onChange={(e) => set({ title: e.target.value })} />}
            </Field>
            <ColorField label="Cor principal" value={w.primaryColor} onChange={(v) => set({ primaryColor: v })} />
            <Field label="Posição">
              {(p) => <Select {...p} value={w.position} onValueChange={(v) => set({ position: v as WidgetConfig["position"] })} options={[{ value: "bottom-right", label: "Canto inferior direito" }, { value: "bottom-left", label: "Canto inferior esquerdo" }]} />}
            </Field>
            <Field label="Formato">
              {(p) => <Select {...p} value={w.layout} onValueChange={(v) => set({ layout: v as WidgetConfig["layout"] })} options={[{ value: "bubble", label: "Bolha flutuante" }, { value: "inline", label: "Dentro da página" }]} />}
            </Field>
          </div>
          <Field label="Mensagem de boas-vindas">
            {(p) => <Textarea {...p} value={w.welcomeMessage} maxLength={300} className="min-h-20" onChange={(e) => set({ welcomeMessage: e.target.value })} />}
          </Field>
        </section>

        <section className="flex flex-col gap-4 rounded-lg border border-border bg-surface-raised p-5">
          <h2 className="text-base font-semibold">Dados do visitante</h2>
          <p className="text-sm text-muted">Pedidos antes da primeira mensagem. Com e-mail ou telefone, dá para retomar a conversa depois.</p>
          <div className="flex flex-wrap gap-6">
            <Checkbox label="Nome" checked={w.collectFields.includes("name")} onCheckedChange={(v) => toggleField("name", v === true)} />
            <Checkbox label="E-mail" checked={w.collectFields.includes("email")} onCheckedChange={(v) => toggleField("email", v === true)} />
            <Checkbox label="Telefone" checked={w.collectFields.includes("phone")} onCheckedChange={(v) => toggleField("phone", v === true)} />
          </div>
        </section>

        <section className="flex flex-col gap-4 rounded-lg border border-border bg-surface-raised p-5">
          <h2 className="text-base font-semibold">Instalação</h2>
          <DomainChips value={w.allowedDomains} onChange={(v) => set({ allowedDomains: v })} />
          <div className="flex flex-col gap-1.5">
            <span className="text-sm font-medium">Código de instalação</span>
            <CodeBlock label="Código de instalação" code={snippet} />
            <p className="text-xs text-muted">Cole antes de &lt;/body&gt; em todas as páginas onde o chat deve aparecer.</p>
          </div>
          <a href={`/demo-site?key=${w.publicKey}`} target="_blank" rel="noreferrer" className={buttonVariants({ variant: "secondary", size: "sm", className: "self-start" })}>
            <ExternalLink /> Abrir página de demonstração
          </a>
        </section>

        <div className="sticky bottom-0 -mx-1 flex items-center justify-end gap-2 bg-bg/90 px-1 py-3 backdrop-blur">
          {dirty && <span className="mr-auto text-sm text-muted">Mudanças não salvas</span>}
          <Button variant="secondary" disabled={!dirty || pending} onClick={() => setW(saved)}>Descartar</Button>
          <Button type="submit" disabled={!dirty || invalid} loading={pending}>Salvar</Button>
        </div>
      </form>
      <div className="lg:sticky lg:top-[calc(var(--layout-header)+1rem)] lg:self-start">
        <p className="mb-2 text-sm font-medium">Prévia</p>
        <ChatWidgetPreview color={HEX.test(w.primaryColor) ? w.primaryColor : saved.primaryColor} position={w.position} title={w.title || "Chat"} welcome={w.welcomeMessage} />
      </div>
    </div>
  );
}
