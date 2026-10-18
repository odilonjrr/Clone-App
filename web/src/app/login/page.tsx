import { Bot } from "lucide-react";
import type { Metadata } from "next";
import Link from "next/link";
import { buttonVariants } from "@/components/ui/button";
import { LoginForm } from "./login-form";

export const metadata: Metadata = { title: "Entrar" };

// S01. O login de verdade (Supabase Auth) entra no /replica-backend.
export default function LoginPage() {
  return (
    <main className="flex min-h-dvh items-center justify-center bg-surface px-4 py-10">
      <div className="flex w-full max-w-sm flex-col gap-6 rounded-lg border border-border bg-surface-raised p-6 shadow-card">
        <div className="flex flex-col items-center gap-2 text-center">
          <span className="inline-flex size-10 items-center justify-center rounded-md bg-accent text-on-accent"><Bot className="size-5" aria-hidden /></span>
          <h1 className="text-lg font-semibold">Entrar no Atendente IA</h1>
          <p className="text-sm text-muted">Seus agentes atendem no site, no Telegram e, em breve, no WhatsApp e no Instagram.</p>
        </div>
        <LoginForm />
        <p className="text-center text-sm text-muted">
          Primeira vez? <Link href="/onboarding" className="font-medium text-accent hover:underline">Criar conta</Link>
        </p>
        <Link href="/" className={buttonVariants({ variant: "ghost", size: "sm", className: "self-center" })}>Ver a demonstração sem entrar</Link>
      </div>
    </main>
  );
}
