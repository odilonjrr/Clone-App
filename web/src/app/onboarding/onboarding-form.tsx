"use client";

import Link from "next/link";
import { useActionState } from "react";
import { startFromWebsite } from "@/app/actions";
import { Button } from "@/components/ui/button";
import { Field, Input } from "@/components/ui/field";

export function OnboardingForm() {
  const [state, action, pending] = useActionState(startFromWebsite, null);
  return (
    <form action={action} className="flex flex-col gap-4">
      <Field label="Endereço do site" hint="Ex.: minhaempresa.com.br" error={state && !state.ok ? state.error : undefined}>
        {(p) => <Input {...p} name="url" inputMode="url" autoComplete="url" required placeholder="minhaempresa.com.br" autoFocus />}
      </Field>
      <Button type="submit" size="lg" loading={pending}>{pending ? "Lendo o site…" : "Criar meu agente"}</Button>
      <p className="text-center text-sm text-muted">
        Sem site? <Link href="/agents" className="font-medium text-accent hover:underline">Comece em branco</Link>
      </p>
    </form>
  );
}
