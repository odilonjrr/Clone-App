"use client";

import { Info } from "lucide-react";
import { useCallback, useEffect, useRef, useState, useTransition } from "react";
import { discardDraft, publishAgent, saveDraft } from "@/app/actions";
import { PublishBar } from "@/components/agent/publish-bar";
import { Playground } from "@/components/agent/playground";
import { Dialog, DialogContent } from "@/components/ui/dialog";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { useToast } from "@/components/ui/toast";
import type { Agent, AgentConfig, AgentVersion } from "@/lib/data/types";
import { formatDateTime } from "@/lib/format";
import {
  AbilitiesTab, FollowUpsTab, HoursTab, InstructionsTab, KnowledgeTab, MediaTab, PlaybookTab, TagsTab, type Draft,
} from "./editor-tabs";

function configOf(v: AgentVersion): AgentConfig {
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  const { id, status, publishedAt, ...config } = v;
  return config;
}

const tabFields: Record<string, (keyof Draft)[]> = {
  instructions: ["name", "instructions", "tone", "toneCustom", "primaryLanguage", "typingSpeed", "splitMessages", "aiDisclosure"],
  hours: ["alwaysOn", "activeHours", "timezone"],
  playbook: ["playbook"],
  tags: ["tagRules"],
  followups: ["followUps"],
  media: ["media"],
  knowledge: ["knowledgeBaseIds"],
  abilities: ["abilities"],
};

type Props = {
  agent: Agent;
  knowledgeBases: { id: string; name: string; faqCount: number }[];
  isNew?: boolean;
};

export function AgentEditor({ agent, knowledgeBases, isNew }: Props) {
  const toast = useToast();
  const [draft, setDraft] = useState<Draft>({ ...configOf(agent.draft), name: agent.name });
  const [published, setPublished] = useState(agent.published ? configOf(agent.published) : undefined);
  const [publishedAt, setPublishedAt] = useState(agent.published?.publishedAt);
  const [saveState, setSaveState] = useState<"saved" | "saving">("saved");
  const [playgroundOpen, setPlaygroundOpen] = useState(false);
  const [publishing, startPublish] = useTransition();
  const timer = useRef<ReturnType<typeof setTimeout>>(undefined);
  const latest = useRef(draft);

  const flush = useCallback(async () => {
    clearTimeout(timer.current);
    await saveDraft(agent.id, latest.current);
    setSaveState("saved");
  }, [agent.id]);

  useEffect(() => () => clearTimeout(timer.current), []);

  const set = (patch: Partial<Draft>) => {
    setDraft((d) => {
      const next = { ...d, ...patch };
      latest.current = next;
      return next;
    });
    setSaveState("saving");
    clearTimeout(timer.current);
    timer.current = setTimeout(() => void flush(), 600);
  };

  const { name: _name, ...draftConfig } = draft;
  void _name;
  const dirtyTabs = Object.fromEntries(
    Object.entries(tabFields).map(([tab, fields]) => [
      tab,
      !published || fields.some((f) => (f === "name" ? draft.name !== agent.name && !published : JSON.stringify(draft[f]) !== JSON.stringify(published[f as keyof AgentConfig]))),
    ]),
  );
  const dirty = !published || JSON.stringify(draftConfig) !== JSON.stringify(published);

  return (
    <div className="flex flex-col gap-4">
      {isNew && (
        <p className="flex items-start gap-2 rounded-md bg-accent-soft px-3 py-2.5 text-sm text-fg">
          <Info className="mt-0.5 size-4 shrink-0 text-accent" aria-hidden />
          Criamos um rascunho a partir do seu site. Revise as instruções, teste e publique quando estiver bom.
        </p>
      )}
      <div className="sticky top-[calc(var(--layout-header)+0.5rem)] z-20">
        <PublishBar
          dirty={dirty}
          publishing={publishing}
          publishedAt={publishedAt ? formatDateTime(publishedAt) : undefined}
          onTry={() => setPlaygroundOpen(true)}
          onPublish={() =>
            startPublish(async () => {
              await flush();
              const r = await publishAgent(agent.id);
              if (r.ok) {
                setPublished(structuredClone(draftConfig));
                setPublishedAt(new Date().toISOString());
                toast({ tone: "success", title: "Agente publicado. As próximas mensagens já usam esta versão." });
              } else toast({ tone: "danger", title: r.error });
            })
          }
          onDiscard={() =>
            startPublish(async () => {
              clearTimeout(timer.current);
              const r = await discardDraft(agent.id);
              if (r.ok && published) {
                const restored = { ...published, name: draft.name };
                latest.current = restored;
                setDraft(restored);
                setSaveState("saved");
                toast({ tone: "info", title: "Mudanças descartadas." });
              }
            })
          }
        />
        <p className="mt-1 px-1 text-right text-xs text-muted" role="status">{saveState === "saving" ? "Salvando rascunho…" : "Rascunho salvo"}</p>
      </div>
      <Tabs defaultValue="instructions">
        <TabsList aria-label="Configurações do agente">
          <TabsTrigger value="instructions" dirty={!!published && dirtyTabs.instructions}>Instruções</TabsTrigger>
          <TabsTrigger value="hours" dirty={!!published && dirtyTabs.hours}>Horário</TabsTrigger>
          <TabsTrigger value="playbook" dirty={!!published && dirtyTabs.playbook}>Roteiro</TabsTrigger>
          <TabsTrigger value="tags" dirty={!!published && dirtyTabs.tags}>Tags</TabsTrigger>
          <TabsTrigger value="followups" dirty={!!published && dirtyTabs.followups}>Follow-ups</TabsTrigger>
          <TabsTrigger value="media" dirty={!!published && dirtyTabs.media}>Mídia</TabsTrigger>
          <TabsTrigger value="knowledge" dirty={!!published && dirtyTabs.knowledge}>Conhecimento</TabsTrigger>
          <TabsTrigger value="abilities" dirty={!!published && dirtyTabs.abilities}>Habilidades</TabsTrigger>
        </TabsList>
        <div className="pt-4">
          <TabsContent value="instructions"><InstructionsTab draft={draft} set={set} /></TabsContent>
          <TabsContent value="hours"><HoursTab draft={draft} set={set} /></TabsContent>
          <TabsContent value="playbook"><PlaybookTab draft={draft} set={set} /></TabsContent>
          <TabsContent value="tags"><TagsTab draft={draft} set={set} /></TabsContent>
          <TabsContent value="followups"><FollowUpsTab draft={draft} set={set} /></TabsContent>
          <TabsContent value="media"><MediaTab draft={draft} set={set} /></TabsContent>
          <TabsContent value="knowledge"><KnowledgeTab draft={draft} set={set} knowledgeBases={knowledgeBases} /></TabsContent>
          <TabsContent value="abilities"><AbilitiesTab draft={draft} set={set} /></TabsContent>
        </div>
      </Tabs>
      <Dialog open={playgroundOpen} onOpenChange={setPlaygroundOpen}>
        <DialogContent side="drawer" title="Testar agente" description={draft.name} className="max-w-md">
          <Playground agentId={agent.id} beforeSend={flush} />
        </DialogContent>
      </Dialog>
    </div>
  );
}
