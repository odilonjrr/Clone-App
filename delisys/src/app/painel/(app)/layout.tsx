import { redirect } from "next/navigation";
import { currentUser } from "@/lib/auth";
import { getStoreById } from "@/lib/store";
import { PanelNav } from "@/components/panel/nav";

export const dynamic = "force-dynamic";

export default async function PanelLayout({ children }: { children: React.ReactNode }) {
  const user = await currentUser();
  if (!user) redirect("/painel/entrar");
  const store = await getStoreById(user.store_id);
  return (
    <div data-theme="light" className="min-h-dvh bg-bg text-fg lg:grid lg:grid-cols-[240px_1fr]">
      <PanelNav storeName={store?.name ?? ""} slug={store?.slug ?? ""} userName={user.name} />
      <main className="min-w-0 px-4 pb-16 pt-4 lg:px-6">{children}</main>
    </div>
  );
}
