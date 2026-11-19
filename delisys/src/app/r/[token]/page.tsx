import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { TrackingView } from "@/components/tracking-view";
import { getTracking } from "@/lib/tracking";

export const dynamic = "force-dynamic";
export const metadata: Metadata = { title: "Seu pedido", robots: { index: false } };

export default async function TrackingPage({ params }: { params: Promise<{ token: string }> }) {
  const { token } = await params;
  const t = await getTracking(token);
  if (!t) notFound();
  return <TrackingView token={token} initial={t} />;
}
