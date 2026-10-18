import type { Metadata } from "next";
import { headers } from "next/headers";
import Link from "next/link";
import { notFound } from "next/navigation";
import { Suspense } from "react";
import { PageBody } from "@/components/shell/page-header";
import { Skeleton } from "@/components/ui/misc";
import { ToastProvider } from "@/components/ui/toast";
import { DataError, getChannel } from "@/lib/data";
import { WidgetSettings } from "./widget-settings";

export const metadata: Metadata = { title: "Widget do site" };

async function Settings({ params }: PageProps<"/channels/web/[id]">) {
  const { id } = await params;
  const channel = await getChannel(id).catch((e) => {
    if (e instanceof DataError) notFound();
    throw e;
  });
  if (!channel.widget) notFound();
  const h = await headers();
  const origin = `${h.get("x-forwarded-proto") ?? "http"}://${h.get("host")}`;
  return (
    <>
      <div>
        <Link href="/channels" className="text-sm text-muted hover:text-fg">← Canais</Link>
        <h1 className="mt-1 text-xl font-bold">Widget do site</h1>
        <p className="mt-1 text-sm text-muted">Aparência, instalação e onde o widget pode aparecer.</p>
      </div>
      <ToastProvider>
        <WidgetSettings channelId={channel.id} initial={channel.widget} origin={origin} />
      </ToastProvider>
    </>
  );
}

export default function WidgetPage(props: PageProps<"/channels/web/[id]">) {
  return (
    <PageBody>
      <Suspense fallback={<div className="flex flex-col gap-4"><Skeleton className="h-10 w-64" /><Skeleton className="h-[560px]" /></div>}>
        <Settings {...props} />
      </Suspense>
    </PageBody>
  );
}
