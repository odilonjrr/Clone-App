import { Users } from "lucide-react";
import type { Metadata } from "next";
import { Suspense } from "react";
import { PageBody, PageHeader } from "@/components/shell/page-header";
import { Badge } from "@/components/ui/badge";
import { ChannelBadge } from "@/components/ui/channel-badge";
import { EmptyState, Skeleton, Table } from "@/components/ui/misc";
import { listContacts, listTags } from "@/lib/data";
import { formatDateTime } from "@/lib/format";

export const metadata: Metadata = { title: "Contatos" };

async function ContactsTable() {
  const [rows, tags] = await Promise.all([listContacts(), listTags()]);
  if (rows.length === 0) return <EmptyState icon={Users} title="Nenhum contato ainda">Os contatos são criados quando alguém conversa com você.</EmptyState>;
  const tagName = (id: string) => tags.find((t) => t.id === id)?.name ?? "";
  return (
    <Table>
      <thead>
        <tr><th scope="col">Nome</th><th scope="col">Canais</th><th scope="col">Contato</th><th scope="col">Tags</th><th scope="col">IA</th><th scope="col">Última mensagem</th></tr>
      </thead>
      <tbody>
        {rows.map(({ contact, channels, lastMessageAt }) => (
          <tr key={contact.id}>
            <td className="max-w-56 truncate font-medium">{contact.name}</td>
            <td><span className="flex gap-1">{[...new Set(channels)].map((k) => <ChannelBadge key={k} kind={k} size="sm" />)}</span></td>
            <td className="text-muted">{contact.email ?? contact.phone ?? "—"}</td>
            <td><span className="flex flex-wrap gap-1">{contact.tagIds.map((t) => <Badge key={t}>{tagName(t)}</Badge>)}</span></td>
            <td>{contact.botActive ? <Badge tone="success">Ativa</Badge> : <Badge tone="warning">Pausada</Badge>}</td>
            <td className="whitespace-nowrap text-muted">{lastMessageAt ? formatDateTime(lastMessageAt) : "—"}</td>
          </tr>
        ))}
      </tbody>
    </Table>
  );
}

export default function ContactsPage() {
  return (
    <PageBody>
      <PageHeader title="Contatos" description="Todas as pessoas que conversaram com você, em qualquer canal." />
      <Suspense fallback={<Skeleton className="h-64" />}>
        <ContactsTable />
      </Suspense>
    </PageBody>
  );
}
