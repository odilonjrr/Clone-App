"use client";

import { useState, useTransition } from "react";
import { connectTelegram, disconnectChannel } from "@/app/actions";
import { Button } from "@/components/ui/button";
import { Dialog, DialogClose, DialogContent, DialogTrigger } from "@/components/ui/dialog";
import { Field, Input } from "@/components/ui/field";
import { useToast } from "@/components/ui/toast";

// S14
export function TelegramConnect() {
  const toast = useToast();
  const [open, setOpen] = useState(false);
  const [token, setToken] = useState("");
  const [error, setError] = useState<string>();
  const [pending, start] = useTransition();
  return (
    <Dialog open={open} onOpenChange={(o) => { setOpen(o); if (!o) { setToken(""); setError(undefined); } }}>
      <DialogTrigger asChild><Button size="sm">Conectar Telegram</Button></DialogTrigger>
      <DialogContent
        title="Conectar um bot do Telegram"
        description="O bot responde às mensagens diretas que as pessoas mandarem para ele."
        footer={
          <>
            <DialogClose asChild><Button variant="secondary">Cancelar</Button></DialogClose>
            <Button
              loading={pending}
              disabled={!token.trim()}
              onClick={() =>
                start(async () => {
                  const r = await connectTelegram(token);
                  if (!r.ok) return setError(r.error);
                  setOpen(false);
                  setToken("");
                  toast({ tone: "success", title: "Bot conectado. As mensagens novas já chegam em Conversas." });
                })
              }
            >
              Conectar
            </Button>
          </>
        }
      >
        <ol className="mb-4 list-decimal space-y-1 pl-5 text-sm text-muted">
          <li>No Telegram, abra uma conversa com <strong className="text-fg">@BotFather</strong>.</li>
          <li>Envie <code className="rounded-sm bg-surface px-1 font-mono text-fg">/newbot</code> e escolha nome e usuário.</li>
          <li>Copie o token que ele enviar e cole abaixo.</li>
        </ol>
        <Field label="Token do bot" error={error}>
          {(p) => <Input {...p} value={token} autoComplete="off" spellCheck={false} placeholder="123456789:AA…" className="font-mono" onChange={(e) => { setToken(e.target.value); setError(undefined); }} />}
        </Field>
      </DialogContent>
    </Dialog>
  );
}

export function DisconnectButton({ channelId, name }: { channelId: string; name: string }) {
  const toast = useToast();
  const [pending, start] = useTransition();
  return (
    <Dialog>
      <DialogTrigger asChild><Button variant="secondary" size="sm">Desconectar</Button></DialogTrigger>
      <DialogContent
        title={`Desconectar ${name}?`}
        description="O bot para de responder. As conversas antigas continuam em Conversas."
        footer={
          <>
            <DialogClose asChild><Button variant="secondary">Cancelar</Button></DialogClose>
            <Button variant="danger" loading={pending} onClick={() => start(async () => { await disconnectChannel(channelId); toast({ tone: "info", title: `${name} desconectado.` }); })}>
              Desconectar
            </Button>
          </>
        }
      >
        <p className="text-sm text-muted">Você pode conectar o mesmo bot de novo quando quiser.</p>
      </DialogContent>
    </Dialog>
  );
}
