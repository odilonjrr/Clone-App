"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Field, Input } from "@/components/ui/field";

export function LoginForm() {
  const router = useRouter();
  const [error, setError] = useState<string>();
  return (
    <form
      className="flex flex-col gap-4"
      noValidate
      onSubmit={(e) => {
        e.preventDefault();
        const email = String(new FormData(e.currentTarget).get("email") ?? "");
        if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) return setError("Digite um e-mail válido.");
        // Sem backend ainda: entra direto na conta de demonstração.
        router.push("/");
      }}
    >
      <Field label="E-mail" error={error}>
        {(p) => <Input {...p} name="email" type="email" autoComplete="email" placeholder="voce@empresa.com.br" onChange={() => setError(undefined)} />}
      </Field>
      <Button type="submit">Receber link de acesso</Button>
      <Button variant="secondary" type="button" onClick={() => router.push("/")}>Entrar com Google</Button>
    </form>
  );
}
