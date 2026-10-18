import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { ChannelBadge, channels, type ChannelKind } from "@/components/ui/channel-badge";

type Status = "disconnected" | "connected" | "error" | "soon";

const statusBadge: Record<Status, { tone: "neutral" | "success" | "danger" | "accent"; text: string }> = {
  disconnected: { tone: "neutral", text: "Não conectado" },
  connected: { tone: "success", text: "Conectado" },
  error: { tone: "danger", text: "Com erro" },
  soon: { tone: "accent", text: "Em breve" },
};

type ChannelCardProps = { kind: ChannelKind; status: Status; description: string; detail?: string };

export function ChannelCard({ kind, status, description, detail }: ChannelCardProps) {
  const b = statusBadge[status];
  return (
    <Card className="flex flex-col gap-3">
      <div className="flex items-center justify-between gap-2">
        <ChannelBadge kind={kind} />
        <Badge tone={b.tone}>{b.text}</Badge>
      </div>
      <p className="text-sm text-muted">{description}</p>
      {detail && <p className={status === "error" ? "text-xs text-danger" : "text-xs text-muted"}>{detail}</p>}
      <div className="mt-auto">
        {status === "connected" ? (
          <Button variant="secondary" size="sm">Configurar</Button>
        ) : status === "error" ? (
          <Button size="sm">Reconectar</Button>
        ) : (
          <Button size="sm" disabled={status === "soon"}>Conectar {channels[kind].label}</Button>
        )}
      </div>
    </Card>
  );
}
