import type { Metadata } from "next";
import { Suspense } from "react";
import { PageBody, PageHeader } from "@/components/shell/page-header";
import { Badge } from "@/components/ui/badge";
import { Card } from "@/components/ui/card";
import { Skeleton, Table } from "@/components/ui/misc";
import { getWorkspace, listMembers } from "@/lib/data";

export const metadata: Metadata = { title: "Configurações" };

const roleLabel = { owner: "Dono", admin: "Admin", agent: "Atendente" } as const;

async function Members() {
  const [ws, members] = await Promise.all([getWorkspace(), listMembers()]);
  return (
    <>
      <Card className="flex flex-col gap-1">
        <h2 className="text-sm font-semibold">Empresa</h2>
        <p className="text-sm">{ws.name}</p>
        <p className="text-xs text-muted">Fuso horário: {ws.timezone} · Plano: {ws.plan === "trial" ? "Teste grátis" : ws.plan}</p>
      </Card>
      <section aria-labelledby="equipe" className="flex flex-col gap-3">
        <h2 id="equipe" className="text-sm font-semibold">Equipe</h2>
        <Table>
          <thead><tr><th scope="col">Nome</th><th scope="col">E-mail</th><th scope="col">Papel</th></tr></thead>
          <tbody>
            {members.map((m) => (
              <tr key={m.id}><td className="font-medium">{m.name}</td><td className="text-muted">{m.email}</td><td><Badge>{roleLabel[m.role]}</Badge></td></tr>
            ))}
          </tbody>
        </Table>
        <p className="text-xs text-muted">Convites, Google Agenda e cobrança entram com o backend.</p>
      </section>
    </>
  );
}

export default function SettingsPage() {
  return (
    <PageBody>
      <PageHeader title="Configurações" />
      <Suspense fallback={<Skeleton className="h-48" />}>
        <Members />
      </Suspense>
    </PageBody>
  );
}
