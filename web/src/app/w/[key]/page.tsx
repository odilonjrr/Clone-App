import type { Metadata } from "next";
import { Suspense } from "react";
import { WidgetApp } from "@/components/widget/widget-app";

export const metadata: Metadata = { title: "Chat", robots: { index: false } };

async function Widget({ params, searchParams }: PageProps<"/w/[key]">) {
  const [{ key }, sp] = await Promise.all([params, searchParams]);
  const host = typeof sp.host === "string" ? sp.host : undefined;
  const mode = sp.mode === "inline" ? "inline" : "bubble";
  return <WidgetApp publicKey={decodeURIComponent(key)} host={host} mode={mode} />;
}

// S18: página carregada dentro do iframe do widget.js.
export default function WidgetPage(props: PageProps<"/w/[key]">) {
  return (
    <>
      <style>{`html,body{background:transparent!important;min-height:0!important}`}</style>
      <Suspense fallback={null}>
        <Widget {...props} />
      </Suspense>
    </>
  );
}
