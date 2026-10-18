import { BookOpen } from "lucide-react";
import type { Metadata } from "next";
import Link from "next/link";
import { Suspense } from "react";
import { PageBody, PageHeader } from "@/components/shell/page-header";
import { Badge } from "@/components/ui/badge";
import { Card } from "@/components/ui/card";
import { EmptyState, Skeleton } from "@/components/ui/misc";
import { listKnowledgeBases } from "@/lib/data";
import { NewKnowledgeBase } from "./new-knowledge-base";

export const metadata: Metadata = { title: "Conhecimento" };

async function Bases() {
  const kbs = await listKnowledgeBases();
  if (kbs.length === 0)
    return <Card><EmptyState icon={BookOpen} title="Nenhuma base ainda">Uma base reúne o conteúdo do seu site e as perguntas frequentes que o agente usa para responder.</EmptyState></Card>;
  return (
    <ul className="grid gap-4 md:grid-cols-2">
      {kbs.map((kb) => (
        <li key={kb.id}>
          <Link href={`/knowledge/${kb.id}`} className="flex h-full flex-col gap-2 rounded-lg border border-border bg-surface-raised p-5 shadow-card hover:bg-surface-hover">
            <span className="text-base font-semibold break-words">{kb.name}</span>
            <span className="flex flex-wrap gap-2">
              <Badge>{kb.faqCount} perguntas</Badge>
              <Badge>{kb.sources.length} {kb.sources.length === 1 ? "fonte" : "fontes"}</Badge>
              {kb.sources.some((s) => s.status === "processing") && <Badge tone="accent">Lendo site…</Badge>}
            </span>
            <span className="text-sm text-muted">{kb.agentNames.length ? `Usada por: ${kb.agentNames.join(", ")}` : "Nenhum agente usa esta base."}</span>
          </Link>
        </li>
      ))}
    </ul>
  );
}

// S10
export default function KnowledgePage() {
  return (
    <PageBody>
      <PageHeader title="Conhecimento" description="O que os agentes sabem sobre a sua empresa." actions={<NewKnowledgeBase />} />
      <Suspense fallback={<div className="grid gap-4 md:grid-cols-2"><Skeleton className="h-32" /><Skeleton className="h-32" /></div>}>
        <Bases />
      </Suspense>
    </PageBody>
  );
}
