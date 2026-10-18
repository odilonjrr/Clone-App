import { Workflow } from "lucide-react";
import type { Metadata } from "next";
import { PageBody, PageHeader } from "@/components/shell/page-header";
import { Badge } from "@/components/ui/badge";
import { Card } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/misc";

export const metadata: Metadata = { title: "Automações" };

// S20: fora da fase 1 (ver replica/architecture.md, "Importantes e opcionais").
export default function AutomationsPage() {
  return (
    <PageBody>
      <PageHeader title="Automações" description="Regras do tipo “quando acontecer isto, faça aquilo”." actions={<Badge tone="accent">Em breve</Badge>} />
      <Card>
        <EmptyState icon={Workflow} title="Automações chegam numa próxima versão">
          Por exemplo: quando a IA marcar um contato como “Lead quente”, atribuir a conversa a alguém da equipe.
        </EmptyState>
      </Card>
    </PageBody>
  );
}
