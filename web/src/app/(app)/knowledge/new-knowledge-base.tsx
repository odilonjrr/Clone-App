"use client";

import { Plus } from "lucide-react";
import { useState, useTransition } from "react";
import { createKnowledgeBase } from "@/app/actions";
import { Button } from "@/components/ui/button";
import { Dialog, DialogClose, DialogContent, DialogTrigger } from "@/components/ui/dialog";
import { Field, Input } from "@/components/ui/field";

export function NewKnowledgeBase() {
  const [name, setName] = useState("");
  const [pending, start] = useTransition();
  return (
    <Dialog>
      <DialogTrigger asChild><Button><Plus /> Nova base</Button></DialogTrigger>
      <DialogContent
        title="Nova base de conhecimento"
        footer={<><DialogClose asChild><Button variant="secondary">Cancelar</Button></DialogClose><Button loading={pending} disabled={!name.trim()} onClick={() => start(() => createKnowledgeBase(name))}>Criar</Button></>}
      >
        <Field label="Nome" hint="Ex.: Produtos e preços">{(p) => <Input {...p} value={name} maxLength={80} onChange={(e) => setName(e.target.value)} />}</Field>
      </DialogContent>
    </Dialog>
  );
}
