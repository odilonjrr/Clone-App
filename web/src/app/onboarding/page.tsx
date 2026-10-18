import { Bot } from "lucide-react";
import type { Metadata } from "next";
import { OnboardingForm } from "./onboarding-form";

export const metadata: Metadata = { title: "Criar seu agente" };

// S02: o primeiro passo do recorte vertical.
export default function OnboardingPage() {
  return (
    <main className="flex min-h-dvh items-center justify-center bg-surface px-4 py-10">
      <div className="flex w-full max-w-lg flex-col gap-6 rounded-lg border border-border bg-surface-raised p-6 shadow-card sm:p-8">
        <span className="inline-flex size-10 items-center justify-center rounded-md bg-accent text-on-accent"><Bot className="size-5" aria-hidden /></span>
        <div>
          <h1 className="text-xl font-bold">Comece pelo seu site</h1>
          <p className="mt-2 text-sm text-muted">
            Lemos as páginas públicas do seu site e montamos um primeiro agente com o que você vende, preços e perguntas frequentes. Você revisa tudo antes de colocar no ar.
          </p>
        </div>
        <OnboardingForm />
      </div>
    </main>
  );
}
