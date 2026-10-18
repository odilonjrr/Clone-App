"use client";

import { Bot, Inbox, Search } from "lucide-react";
import { useState } from "react";
import { EditableList, type Pair } from "@/components/agent/editable-list";
import { HoursEditor, type Hours } from "@/components/agent/hours-editor";
import { PublishBar } from "@/components/agent/publish-bar";
import { ChannelCard } from "@/components/channels/channel-card";
import { ColorField } from "@/components/channels/color-field";
import { DomainChips } from "@/components/channels/domain-chips";
import { ChatStatusBar } from "@/components/inbox/chat-status-bar";
import { Composer } from "@/components/inbox/composer";
import { ConversationItem, type ConversationSummary } from "@/components/inbox/conversation-item";
import { AudioAttachment, FileAttachment, MessageBubble } from "@/components/inbox/message-bubble";
import { SideNav } from "@/components/shell/side-nav";
import { Avatar } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { ChannelBadge } from "@/components/ui/channel-badge";
import { Checkbox } from "@/components/ui/checkbox";
import { CodeBlock } from "@/components/ui/code-block";
import { Dialog, DialogClose, DialogContent, DialogTrigger } from "@/components/ui/dialog";
import { Field, Input, Textarea } from "@/components/ui/field";
import { EmptyState, Skeleton, Table } from "@/components/ui/misc";
import { Select } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { ToastProvider, useToast } from "@/components/ui/toast";
import { Tooltip } from "@/components/ui/tooltip";
import { ChatWidgetPreview } from "@/components/widget/chat-widget-preview";
import { SlotPicker } from "@/components/widget/slot-picker";

// Dados de exemplo inventados para a vitrine.
const conversations: ConversationSummary[] = [
  { id: "1", name: "Marina Costa", channel: "whatsapp", preview: "Perfeito, pode marcar às 15h", time: "14:02", unread: 2, pinned: true },
  { id: "2", name: "Rafael Lima", channel: "instagram", preview: "Qual o valor do plano anual?", time: "13:47", unread: 0, starred: true },
  { id: "3", name: "Visitante 4821", channel: "web", preview: "Vocês emitem nota fiscal?", time: "12:10", unread: 1, aiPaused: true },
  { id: "4", name: "João Pereira", channel: "telegram", preview: "Obrigado!", time: "Ontem", unread: 0 },
];

function Section({ id, title, children }: { id: string; title: string; children: React.ReactNode }) {
  return (
    <section aria-labelledby={id} className="flex flex-col gap-4 border-t border-border pt-8">
      <h2 id={id} className="text-lg font-semibold">{title}</h2>
      {children}
    </section>
  );
}

function ToastDemo() {
  const toast = useToast();
  return (
    <div className="flex flex-wrap gap-2">
      <Button variant="secondary" onClick={() => toast({ tone: "success", title: "Agente publicado" })}>Toast de sucesso</Button>
      <Button variant="secondary" onClick={() => toast({ tone: "danger", title: "Não foi possível enviar a mensagem" })}>Toast de erro</Button>
      <Button variant="secondary" onClick={() => toast({ tone: "info", title: "Conversa arquivada", action: { label: "Desfazer", onClick: () => {} } })}>Toast com ação</Button>
    </div>
  );
}

export function Showcase() {
  const [ai, setAi] = useState(true);
  const [hours, setHours] = useState<{ alwaysOn: boolean; hours: Hours }>({
    alwaysOn: false,
    hours: { mon: ["09:00", "18:00"], tue: ["09:00", "18:00"], wed: ["09:00", "18:00"], thu: ["09:00", "18:00"], fri: ["09:00", "17:00"], sat: ["10:00", "09:00"] },
  });
  const [tags, setTags] = useState<Pair[]>([
    { id: "a", a: "Lead quente", b: "A pessoa pediu preço ou quer agendar uma reunião nesta conversa." },
    { id: "b", a: "Suporte", b: "A pessoa já é cliente e relata um problema." },
  ]);
  const [domains, setDomains] = useState(["minhaloja.com.br", "*.minhaloja.com.br", "nao é domínio"]);
  const [color, setColor] = useState("#0e7c66");
  const [dirty, setDirty] = useState(true);

  return (
    <ToastProvider>
      <div className="flex flex-col gap-10">
        <Section id="buttons" title="Botões">
          <div className="flex flex-wrap items-center gap-2">
            <Button>Primário</Button>
            <Button variant="secondary">Secundário</Button>
            <Button variant="ghost">Fantasma</Button>
            <Button variant="danger">Excluir</Button>
            <Button loading>Salvando</Button>
            <Button disabled>Desabilitado</Button>
            <Button size="sm">Pequeno</Button>
            <Button size="lg">Grande</Button>
            <Tooltip content="Buscar conversas">
              <Button variant="secondary" size="icon" aria-label="Buscar"><Search /></Button>
            </Tooltip>
          </div>
        </Section>

        <Section id="forms" title="Formulário">
          <div className="grid gap-4 md:grid-cols-2">
            <Field label="Nome do agente" hint="Aparece só para a sua equipe.">
              {(p) => <Input {...p} defaultValue="Assistente de vendas" />}
            </Field>
            <Field label="Site da empresa" error="Esse endereço não abriu. Confira e tente de novo.">
              {(p) => <Input {...p} type="url" defaultValue="https://minhaloja" />}
            </Field>
            <Field label="Tom de voz">
              {(p) => (
                <Select
                  {...p}
                  defaultValue="friendly"
                  options={[
                    { value: "friendly", label: "Amigável" },
                    { value: "professional", label: "Profissional" },
                    { value: "casual", label: "Descontraído" },
                    { value: "enthusiastic", label: "Animado" },
                  ]}
                />
              )}
            </Field>
            <Field label="Idioma principal">
              {(p) => <Select {...p} placeholder="Escolha um idioma" options={[{ value: "pt-BR", label: "Português (Brasil)" }, { value: "en", label: "Inglês" }, { value: "es", label: "Espanhol" }]} />}
            </Field>
            <Field label="Instruções" className="md:col-span-2">
              {(p) => <Textarea {...p} defaultValue={"Você atende a loja Minha Loja.\nResponda curto, em no máximo três frases."} />}
            </Field>
          </div>
          <div className="flex flex-wrap gap-6">
            <Switch label="IA" stateLabels={["IA ativa", "IA pausada"]} checked={ai} onCheckedChange={setAi} />
            <Tooltip content="O agente está desligado. Ligue o agente para retomar a IA neste contato.">
              <span><Switch label="Bot responde" locked checked={false} /></span>
            </Tooltip>
            <Switch label="Desabilitado" disabled />
            <Checkbox label="Pedir e-mail" defaultChecked />
            <Checkbox label="Pedir telefone" />
          </div>
        </Section>

        <Section id="badges" title="Selos, canais e avatares">
          <div className="flex flex-wrap items-center gap-2">
            <Badge>Rascunho</Badge>
            <Badge tone="accent">Novo</Badge>
            <Badge tone="success">Conectado</Badge>
            <Badge tone="warning">Fora do horário</Badge>
            <Badge tone="danger">Com erro</Badge>
            <ChannelBadge kind="whatsapp" />
            <ChannelBadge kind="instagram" />
            <ChannelBadge kind="telegram" />
            <ChannelBadge kind="web" />
            <ChannelBadge kind="telegram" size="sm" />
            <ChannelBadge kind="whatsapp" size="dot" />
            <Avatar name="Marina Costa" size={24} />
            <Avatar name="Rafael Lima" size={32} />
            <Avatar name="João Pereira" channel="telegram" />
          </div>
        </Section>

        <Section id="inbox" title="Caixa de entrada">
          <div className="grid grid-cols-1 overflow-hidden rounded-lg border border-border lg:grid-cols-[var(--layout-inbox-list)_1fr] [&>*]:min-w-0">
            <ul className="flex flex-col gap-0.5 border-b border-border p-2 lg:border-r lg:border-b-0">
              {conversations.map((c, i) => (
                <li key={c.id}><ConversationItem c={c} href="#inbox" selected={i === 0} /></li>
              ))}
            </ul>
            <div className="flex min-h-[520px] flex-col">
              <div className="flex h-14 items-center gap-3 border-b border-border px-4">
                <Avatar name="Marina Costa" size={32} channel="whatsapp" />
                <span className="text-sm font-semibold">Marina Costa</span>
                <Switch className="ml-auto" label="IA" stateLabels={["IA ativa", "IA pausada"]} checked={ai} onCheckedChange={setAi} />
              </div>
              <ChatStatusBar kind="offline-hours">Fora do horário · a IA volta às 9h de segunda</ChatStatusBar>
              <div className="flex flex-1 flex-col gap-3 overflow-y-auto p-4">
                <MessageBubble author="system" time="13:58">Conversa iniciada pelo WhatsApp</MessageBubble>
                <MessageBubble author="contact" time="13:58">Oi! Queria marcar uma avaliação essa semana.</MessageBubble>
                <MessageBubble author="ai" time="13:59" status="read">Claro! Tenho quinta às 10h ou às 15h. Qual fica melhor?</MessageBubble>
                <MessageBubble author="contact" time="14:01" attachment={<AudioAttachment duration="0:12" transcript="Às três da tarde fica ótimo pra mim." />}> </MessageBubble>
                <MessageBubble author="note" authorName="Ana" time="14:01">Cliente antiga, ofereça o desconto de retorno.</MessageBubble>
                <MessageBubble author="human" authorName="Ana" time="14:02" status="delivered" attachment={<FileAttachment name="preparo.pdf" size="220 KB" />}>
                  Marcado para quinta às 15h. Segue o preparo.
                </MessageBubble>
                <MessageBubble author="ai" time="14:03" status="scheduled" scheduledFor="09:00">Bom dia! Passando para confirmar sua avaliação de quinta.</MessageBubble>
                <MessageBubble author="human" authorName="Ana" time="14:04" status="failed" onRetry={() => {}}>Até lá!</MessageBubble>
              </div>
              <Composer />
            </div>
          </div>
          <div className="grid gap-2 md:grid-cols-2">
            <ChatStatusBar kind="ai-paused">IA pausada neste contato. Você está respondendo.</ChatStatusBar>
            <ChatStatusBar kind="disconnected">O canal Telegram está desconectado.</ChatStatusBar>
            <ChatStatusBar kind="window-closed">Passaram 24h desde a última mensagem do contato. Só modelos aprovados podem ser enviados.</ChatStatusBar>
          </div>
          <div className="max-w-xl rounded-lg border border-border">
            <Composer blockedReason="Passaram 24h desde a última mensagem do contato. No WhatsApp, só modelos aprovados podem ser enviados agora." />
          </div>
        </Section>

        <Section id="agent" title="Editor do agente">
          <PublishBar dirty={dirty} publishedAt="12/10 às 18:20" onPublish={() => setDirty(false)} onDiscard={() => setDirty(false)} />
          <Tabs defaultValue="hours">
            <TabsList aria-label="Configurações do agente">
              <TabsTrigger value="instructions">Instruções</TabsTrigger>
              <TabsTrigger value="hours" dirty>Horário</TabsTrigger>
              <TabsTrigger value="playbook">Roteiro</TabsTrigger>
              <TabsTrigger value="tags" dirty>Tags</TabsTrigger>
              <TabsTrigger value="followups">Follow-ups</TabsTrigger>
              <TabsTrigger value="media">Mídia</TabsTrigger>
              <TabsTrigger value="knowledge">Conhecimento</TabsTrigger>
              <TabsTrigger value="abilities">Habilidades</TabsTrigger>
            </TabsList>
            <TabsContent value="hours" className="pt-4"><HoursEditor {...hours} onChange={setHours} /></TabsContent>
            <TabsContent value="tags" className="pt-4">
              <EditableList items={tags} onChange={setTags} labels={{ a: "Tag", b: "Quando aplicar", add: "Adicionar tag", empty: "Nenhuma tag ainda." }} />
            </TabsContent>
            {["instructions", "playbook", "followups", "media", "knowledge", "abilities"].map((v) => (
              <TabsContent key={v} value={v} className="pt-4">
                <EmptyState icon={Bot} title="Conteúdo da aba">Cada aba é montada na fase de construção.</EmptyState>
              </TabsContent>
            ))}
          </Tabs>
          <Tabs defaultValue="all">
            <TabsList variant="segmented" aria-label="Filtro">
              <TabsTrigger value="all">Todas</TabsTrigger>
              <TabsTrigger value="mine">Minhas</TabsTrigger>
              <TabsTrigger value="paused">IA pausada</TabsTrigger>
            </TabsList>
          </Tabs>
        </Section>

        <Section id="channels" title="Canais e widget">
          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <ChannelCard kind="web" status="connected" description="Um chat no seu site, respondido pela IA." detail="Instalado em minhaloja.com.br" />
            <ChannelCard kind="telegram" status="error" description="Conecte um bot criado no BotFather." detail="O token foi revogado. Gere outro e reconecte." />
            <ChannelCard kind="whatsapp" status="soon" description="Número oficial pela API do WhatsApp." />
            <ChannelCard kind="instagram" status="disconnected" description="DMs, respostas a stories e comentários." />
          </div>
          <div className="grid grid-cols-1 gap-6 lg:grid-cols-2 [&>*]:min-w-0">
            <div className="flex flex-col gap-4">
              <ColorField label="Cor do widget" value={color} onChange={setColor} />
              <DomainChips value={domains} onChange={setDomains} />
              <CodeBlock label="Código de instalação" code={`<script src="https://app.exemplo.com/widget.js"\n  data-key="pk_3f9a12c0" async></script>`} />
            </div>
            <ChatWidgetPreview color={/^#[0-9a-f]{6}$/i.test(color) ? color : "#0e7c66"} position="bottom-right" title="Minha Loja" welcome="Olá! Como posso ajudar?" />
          </div>
          <div className="max-w-xs rounded-lg border border-border p-3">
            <SlotPicker
              timezone="Horário de Brasília"
              days={[
                { key: "qui", label: "Qui 23", slots: ["09:00", "10:00", "11:30", "14:00", "15:00", "16:30"] },
                { key: "sex", label: "Sex 24", slots: ["09:00", "13:00"] },
                { key: "sab", label: "Sáb 25", slots: [] },
              ]}
            />
          </div>
        </Section>

        <Section id="data" title="Tabelas, vazio e carregando">
          <Table>
            <thead>
              <tr><th scope="col">Pergunta</th><th scope="col">Resposta</th></tr>
            </thead>
            <tbody>
              <tr><td>Vocês entregam no sábado?</td><td>Sim, das 9h às 13h.</td></tr>
              <tr><td>Aceitam Pix?</td><td>Sim, com 5% de desconto.</td></tr>
            </tbody>
          </Table>
          <div className="grid gap-4 md:grid-cols-2">
            <div className="rounded-lg border border-border">
              <EmptyState icon={Inbox} title="Nenhuma conversa ainda" action={<Button size="sm">Conectar um canal</Button>}>
                Quando alguém mandar mensagem num canal conectado, ela aparece aqui.
              </EmptyState>
            </div>
            <div className="flex flex-col gap-3 rounded-lg border border-border p-4" aria-busy="true" aria-label="Carregando">
              {[0, 1, 2].map((i) => (
                <div key={i} className="flex gap-3">
                  <Skeleton className="size-10 rounded-pill" />
                  <div className="flex flex-1 flex-col gap-2"><Skeleton className="h-3 w-1/3" /><Skeleton className="h-3 w-2/3" /></div>
                </div>
              ))}
            </div>
          </div>
        </Section>

        <Section id="overlays" title="Modal, gaveta e avisos">
          <div className="flex flex-wrap gap-2">
            <Dialog>
              <DialogTrigger asChild><Button variant="secondary">Abrir modal</Button></DialogTrigger>
              <DialogContent
                title="Conectar Telegram"
                description="Cole o token que o BotFather enviou ao criar o bot."
                footer={<><DialogClose asChild><Button variant="secondary">Cancelar</Button></DialogClose><Button>Conectar</Button></>}
              >
                <Field label="Token do bot">{(p) => <Input {...p} placeholder="123456:ABC-DEF…" />}</Field>
              </DialogContent>
            </Dialog>
            <Dialog>
              <DialogTrigger asChild><Button variant="secondary">Abrir gaveta</Button></DialogTrigger>
              <DialogContent side="drawer" title="Marina Costa" description="Contato via WhatsApp">
                <div className="flex flex-col gap-4">
                  <Switch label="IA" stateLabels={["IA ativa", "IA pausada"]} checked={ai} onCheckedChange={setAi} />
                  <Switch label="Follow-ups" defaultChecked stateLabels={["Follow-ups ligados", "Follow-ups desligados"]} />
                  <p className="text-sm text-muted">Próximo follow-up: amanhã às 10h</p>
                </div>
              </DialogContent>
            </Dialog>
          </div>
          <ToastDemo />
        </Section>

        <Section id="nav" title="Navegação">
          <div className="flex gap-4">
            <SideNav active="/inbox" />
            <SideNav active="/inbox" collapsed />
          </div>
        </Section>
      </div>
    </ToastProvider>
  );
}
