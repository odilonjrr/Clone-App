import type { Metadata } from "next";
import Link from "next/link";
import { Suspense } from "react";
import { ChannelCard } from "@/components/channels/channel-card";
import { PageBody, PageHeader } from "@/components/shell/page-header";
import { buttonVariants } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/misc";
import { ToastProvider } from "@/components/ui/toast";
import { listChannels } from "@/lib/data";
import { formatDateTime } from "@/lib/format";
import { DisconnectButton, TelegramConnect } from "./telegram-connect";

export const metadata: Metadata = { title: "Canais" };

async function ChannelGrid() {
  const channels = await listChannels();
  const web = channels.filter((c) => c.kind === "web");
  const telegram = channels.filter((c) => c.kind === "telegram" && c.status !== "disconnected");
  return (
    <div className="flex flex-col gap-8">
      <section aria-labelledby="ativos" className="flex flex-col gap-3">
        <h2 id="ativos" className="text-sm font-semibold">Seus canais</h2>
        <ul className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          {web.map((c) => (
            <li key={c.id} className="flex">
              <ChannelCard
                kind="web"
                status={c.status === "connected" ? "connected" : "error"}
                title={c.displayName}
                description="Chat no seu site, respondido pela IA."
                detail={c.widget?.allowedDomains.length ? `Liberado em: ${c.widget.allowedDomains.join(", ")}` : "Liberado em qualquer site"}
                action={<Link href={`/channels/web/${c.id}`} className={buttonVariants({ variant: "secondary", size: "sm" })}>Configurar widget</Link>}
              />
            </li>
          ))}
          {telegram.map((c) => (
            <li key={c.id} className="flex">
              <ChannelCard
                kind="telegram"
                status={c.status === "connected" ? "connected" : "error"}
                title={c.displayName}
                description="Bot do Telegram conectado."
                detail={c.status === "error" ? c.statusDetail : `Conectado em ${formatDateTime(c.connectedAt)}`}
                action={<DisconnectButton channelId={c.id} name={c.displayName} />}
              />
            </li>
          ))}
        </ul>
      </section>
      <section aria-labelledby="novos" className="flex flex-col gap-3">
        <h2 id="novos" className="text-sm font-semibold">Adicionar canal</h2>
        <ul className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          <li className="flex">
            <ChannelCard kind="telegram" status="disconnected" description="Conecte um bot criado no BotFather. Leva 2 minutos." action={<TelegramConnect />} />
          </li>
          <li className="flex">
            <ChannelCard kind="whatsapp" status="soon" description="Número oficial pela API do WhatsApp, com modelos de mensagem aprovados." detail="Depende da aprovação do app na Meta." />
          </li>
          <li className="flex">
            <ChannelCard kind="instagram" status="soon" description="DMs, respostas a stories e comentários que viram conversa." detail="Depende da aprovação do app na Meta." />
          </li>
        </ul>
      </section>
    </div>
  );
}

export default function ChannelsPage() {
  return (
    <PageBody>
      <PageHeader title="Canais" description="Onde os seus agentes atendem." />
      <ToastProvider>
        <Suspense fallback={<div className="grid gap-4 md:grid-cols-3"><Skeleton className="h-48" /><Skeleton className="h-48" /><Skeleton className="h-48" /></div>}>
          <ChannelGrid />
        </Suspense>
      </ToastProvider>
    </PageBody>
  );
}
