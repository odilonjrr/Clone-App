import { ShoppingBag } from "lucide-react";

export default function AuthLayout({ children }: { children: React.ReactNode }) {
  return (
    <div data-theme="light" className="min-h-dvh bg-bg text-fg">
      <main className="mx-auto flex min-h-dvh w-full max-w-md flex-col justify-center gap-6 px-4 py-12">
        <div className="flex items-center gap-2">
          <span className="grid size-10 place-items-center rounded-xl" style={{ background: "var(--gradient-accent)" }}>
            <ShoppingBag className="size-5 text-white" aria-hidden />
          </span>
          <span className="font-display text-2xl font-extrabold">Deli<span className="text-accent">sys</span></span>
        </div>
        {children}
      </main>
    </div>
  );
}
