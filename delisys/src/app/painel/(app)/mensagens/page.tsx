import type { Metadata } from "next";
import { requireUser } from "@/lib/auth";
import { q } from "@/lib/db";
import { formatBrPhone } from "@/lib/phone";
import { whatsappMode } from "@/lib/whatsapp";

export const dynamic = "force-dynamic";
export const metadata: Metadata = { title: "Avisos WhatsApp" };

const STAGE: Record<string, string> = { realizado: "Pedido realizado", em_preparo: "Em preparação", pronto: "Pronto", em_entrega: "Saiu para entrega" };
const STATUS: Record<string, string> = { sent: "Enviado", pending: "Aguardando envio manual", failed: "Falhou" };

export default async function Page() {
  const u = await requireUser();
  const rows = await q<{ id: string; stage: string; status: string; mode: string; to_phone: string; body: string; error: string | null; created_at: string; number: number }>(
    `select n.id, n.stage, n.status, n.mode, n.to_phone, n.body, n.error, n.created_at, o.number
     from notifications n join orders o on o.id = n.order_id where n.store_id = $1 order by n.created_at desc limit 100`, [u.store_id]);
  const mode = whatsappMode();
  return (
    <div className="grid max-w-3xl gap-5">
      <header>
        <h1 className="text-2xl font-bold">Avisos no WhatsApp</h1>
        <p className="text-sm text-muted">O cliente recebe uma mensagem a cada etapa: pedido realizado, em preparação, pronto e saiu para entrega (com o link para acompanhar o motoboy).</p>
      </header>
      <section className={`rounded-md border p-4 text-sm ${mode === "api" ? "border-success/40 bg-success-soft" : "border-warning/40 bg-warning-soft"}`}>
        {mode === "api" ? (
          <p><b>Envio automático ligado</b> pela API oficial do WhatsApp (Meta). As mensagens saem sozinhas a cada etapa.</p>
        ) : (
          <>
            <p><b>Modo manual.</b> A API oficial ainda não está configurada. No quadro de pedidos, cada etapa mostra o botão <b>Avisar no WhatsApp</b>, que abre o WhatsApp com a mensagem pronta.</p>
            <p className="mt-2">Para ligar o envio automático, quem administra o Delisys precisa conectar a WhatsApp Cloud API e aprovar os 4 modelos de mensagem na Meta.</p>
          </>
        )}
      </section>
      {rows.length === 0 ? <p className="card p-6 text-center text-muted">Nenhum aviso ainda. Eles aparecem aqui quando chegarem pedidos.</p> : (
        <ul className="grid gap-2">
          {rows.map((r) => (
            <li key={r.id} className="card p-3 text-sm">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="font-semibold">#{r.number} · {STAGE[r.stage] ?? r.stage}</p>
                <span className={`chip ${r.status === "sent" ? "bg-success-soft text-success" : r.status === "failed" ? "bg-danger-soft text-danger" : "bg-warning-soft text-warning"}`}>{STATUS[r.status]}</span>
              </div>
              <p className="text-xs text-muted">Para {formatBrPhone(r.to_phone)} · {new Date(r.created_at).toLocaleString("pt-BR", { timeZone: "America/Sao_Paulo" })} · {r.mode === "api" ? "automático" : "manual"}</p>
              <p className="mt-2 whitespace-pre-wrap break-words">{r.body}</p>
              {r.error && <p className="mt-1 text-xs text-danger">Erro da Meta: {r.error}</p>}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
