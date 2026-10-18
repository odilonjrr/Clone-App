// Data layer FALSO (em memória). O /replica-backend troca este arquivo por
// chamadas ao Supabase e ao Inngest mantendo as mesmas assinaturas, então as
// telas não mudam. Tudo some quando o servidor reinicia.
//
// As leituras chamam connection() para se comportarem como I/O real: o Next
// não pré-renderiza o resultado no build (cacheComponents).
import { connection } from "next/server";
import { nextOpening } from "@/lib/hours";
import { fakeReply, typingDelayMs } from "./fake-ai";
import { createSeed, defaultConfig, type Store } from "./seed";
import type {
  Agent, AgentConfig, Channel, ConversationFilter, ConversationListItem, EntryPoint,
  FaqItem, KnowledgeSource, Message, WidgetConfig,
} from "./types";

const g = globalThis as unknown as { __replicaStore?: Store; __replicaGen?: Map<string, number> };
const db = () => (g.__replicaStore ??= createSeed());
const gens = () => (g.__replicaGen ??= new Map());

const now = () => new Date().toISOString();
const uid = () => crypto.randomUUID();
const sleep = (ms: number) => new Promise((r) => setTimeout(r, ms));
const clone = <T>(v: T): T => structuredClone(v);

export class DataError extends Error {
  constructor(public code: "not_found" | "invalid" | "forbidden_domain", message: string) {
    super(message);
  }
}

function must<T>(v: T | undefined, what: string): T {
  if (!v) throw new DataError("not_found", `${what} não encontrado`);
  return v;
}

/** Só para testes: volta ao estado inicial. */
export async function resetStore() {
  g.__replicaStore = createSeed();
  gens().clear();
}

// ---------------------------------------------------------------------------
// Workspace
// ---------------------------------------------------------------------------

export async function getWorkspace() {
  await connection();
  return clone(db().workspace);
}

export async function listMembers() {
  await connection();
  return clone(db().members);
}

export async function getCurrentUser() {
  await connection();
  const s = db();
  return clone(must(s.members.find((m) => m.id === s.currentUserId), "Usuário"));
}

export async function getDashboardStats() {
  await connection();
  const s = db();
  const since = Date.now() - 7 * 86_400_000;
  const recent = s.messages.filter((m) => Date.parse(m.createdAt) >= since);
  return {
    conversations: s.conversations.length,
    unread: s.conversations.reduce((n, c) => n + c.unreadCount, 0),
    aiReplies7d: recent.filter((m) => m.author === "ai").length,
    humanReplies7d: recent.filter((m) => m.author === "human" && m.kind !== "note").length,
    pausedContacts: s.contacts.filter((c) => !c.botActive).length,
    connectedChannels: s.channels.filter((c) => c.status === "connected").length,
    activeAgents: s.agents.filter((a) => a.isActive && a.published).length,
  };
}

// ---------------------------------------------------------------------------
// Agentes
// ---------------------------------------------------------------------------

export async function listAgents() {
  await connection();
  return clone(db().agents);
}

export async function getAgent(id: string) {
  await connection();
  return clone(must(db().agents.find((a) => a.id === id), "Agente"));
}

function newAgent(name: string, config: Partial<AgentConfig> = {}): Agent {
  const id = uid();
  return { id, name, isActive: true, draft: { ...defaultConfig(), ...config, id: uid(), status: "draft" }, createdAt: now() };
}

export async function createAgent(name: string) {
  const a = newAgent(name.trim() || "Novo agente");
  db().agents.push(a);
  return a.id;
}

function hostOf(url: string) {
  try {
    return new URL(/^https?:\/\//.test(url) ? url : `https://${url}`).hostname;
  } catch {
    throw new DataError("invalid", "Esse endereço não parece um site. Confira e tente de novo.");
  }
}

/**
 * Cria agente + base de conhecimento a partir de um site. No falso, a leitura
 * do site é simulada (2 s) e o FAQ vem vazio; no backend é o job knowledge/ingest.
 */
export async function createAgentFromWebsite(url: string) {
  const host = hostOf(url);
  if (!host.includes(".")) throw new DataError("invalid", "Esse endereço não parece um site. Confira e tente de novo.");
  const s = db();
  const kbId = uid();
  s.knowledgeBases.push({ id: kbId, name: host, createdAt: now() });
  const source: KnowledgeSource = { id: uid(), knowledgeBaseId: kbId, kind: "website", sourceUrl: `https://${host}`, title: host, status: "processing" };
  s.sources.push(source);
  const agent = newAgent(`Assistente ${host.replace(/^www\./, "").split(".")[0]}`, {
    instructions: `Você atende os clientes de ${host}.\nResponda em frases curtas, com base no conhecimento do site.\nQuando não souber, diga que vai confirmar com a equipe.`,
    knowledgeBaseIds: [kbId],
    aiDisclosure: "Oi! Sou a assistente virtual.",
  });
  s.agents.push(agent);
  setTimeout(() => {
    source.status = "ready";
    source.pages = 1 + (host.length % 9);
    source.lastSyncedAt = now();
  }, 2000);
  return { agentId: agent.id, knowledgeBaseId: kbId };
}

export async function getSource(id: string) {
  await connection();
  return clone(must(db().sources.find((x) => x.id === id), "Fonte"));
}

export async function saveDraft(id: string, patch: Partial<AgentConfig> & { name?: string }) {
  const a = must(db().agents.find((x) => x.id === id), "Agente");
  const { name, ...config } = patch;
  if (name !== undefined) a.name = name.trim() || a.name;
  Object.assign(a.draft, config);
  if (config.tagRules) ensureTags(config.tagRules.map((r) => r.tagName));
}

/** Cria no workspace as tags citadas nas regras que ainda não existem. */
function ensureTags(names: string[]) {
  const s = db();
  for (const name of names.map((n) => n.trim()).filter(Boolean)) {
    if (!s.tags.some((t) => t.name.toLowerCase() === name.toLowerCase())) s.tags.push({ id: uid(), name });
  }
}

export async function publishAgent(id: string) {
  const a = must(db().agents.find((x) => x.id === id), "Agente");
  a.published = { ...clone(a.draft), id: uid(), status: "published", publishedAt: now() };
}

export async function discardDraft(id: string) {
  const a = must(db().agents.find((x) => x.id === id), "Agente");
  if (a.published) a.draft = { ...clone(a.published), id: a.draft.id, status: "draft", publishedAt: undefined };
}

export async function setAgentActive(id: string, active: boolean) {
  must(db().agents.find((x) => x.id === id), "Agente").isActive = active;
}

/** Resposta do playground: usa o RASCUNHO e não grava nada. */
export async function playgroundReply(agentId: string, history: { author: "contact" | "ai"; body: string }[]) {
  const a = must(db().agents.find((x) => x.id === agentId), "Agente");
  const faq = db().faq.filter((f) => a.draft.knowledgeBaseIds.includes(f.knowledgeBaseId));
  return fakeReply(a.draft, faq, history).parts;
}

// ---------------------------------------------------------------------------
// Conhecimento
// ---------------------------------------------------------------------------

export async function listKnowledgeBases() {
  await connection();
  const s = db();
  return s.knowledgeBases.map((kb) => ({
    ...clone(kb),
    faqCount: s.faq.filter((f) => f.knowledgeBaseId === kb.id).length,
    sources: clone(s.sources.filter((x) => x.knowledgeBaseId === kb.id)),
    agentNames: s.agents.filter((a) => a.draft.knowledgeBaseIds.includes(kb.id)).map((a) => a.name),
  }));
}

export async function getKnowledgeBase(id: string) {
  await connection();
  const s = db();
  return {
    kb: clone(must(s.knowledgeBases.find((k) => k.id === id), "Base")),
    sources: clone(s.sources.filter((x) => x.knowledgeBaseId === id)),
    faq: clone(s.faq.filter((f) => f.knowledgeBaseId === id)),
  };
}

export async function createKnowledgeBase(name: string) {
  const id = uid();
  db().knowledgeBases.push({ id, name: name.trim() || "Nova base", createdAt: now() });
  return id;
}

export async function addFaq(knowledgeBaseId: string, question: string, answer: string) {
  if (!question.trim() || !answer.trim()) throw new DataError("invalid", "Preencha a pergunta e a resposta.");
  const item: FaqItem = { id: uid(), knowledgeBaseId, question: question.trim(), answer: answer.trim() };
  db().faq.push(item);
  return item.id;
}

export async function updateFaq(id: string, question: string, answer: string) {
  if (!question.trim() || !answer.trim()) throw new DataError("invalid", "Preencha a pergunta e a resposta.");
  Object.assign(must(db().faq.find((f) => f.id === id), "Pergunta"), { question: question.trim(), answer: answer.trim() });
}

export async function deleteFaq(id: string) {
  const s = db();
  s.faq = s.faq.filter((f) => f.id !== id);
}

export async function addWebsiteSource(knowledgeBaseId: string, url: string) {
  const host = hostOf(url);
  const source: KnowledgeSource = { id: uid(), knowledgeBaseId, kind: "website", sourceUrl: `https://${host}`, title: host, status: "processing" };
  db().sources.push(source);
  setTimeout(() => {
    source.status = "ready";
    source.pages = 1 + (host.length % 9);
    source.lastSyncedAt = now();
  }, 2000);
}

// ---------------------------------------------------------------------------
// Canais
// ---------------------------------------------------------------------------

export async function listChannels() {
  await connection();
  return clone(db().channels);
}

export async function getChannel(id: string) {
  await connection();
  return clone(must(db().channels.find((c) => c.id === id), "Canal"));
}

const TELEGRAM_TOKEN = /^\d{6,12}:[A-Za-z0-9_-]{30,}$/;

/** No falso só valida o formato; no backend chama getMe e setWebhook. */
export async function connectTelegram(token: string) {
  const t = token.trim();
  if (!TELEGRAM_TOKEN.test(t)) throw new DataError("invalid", "Esse token não tem o formato do BotFather (números, dois-pontos e uma sequência longa).");
  const s = db();
  const botId = t.split(":")[0];
  if (s.channels.some((c) => c.kind === "telegram" && c.externalId === botId && c.status === "connected"))
    throw new DataError("invalid", "Esse bot já está conectado.");
  const ch: Channel = { id: uid(), kind: "telegram", displayName: `@bot_${botId.slice(-4)}`, status: "connected", externalId: botId, connectedAt: now() };
  s.channels.push(ch);
  const agent = s.agents.find((a) => a.isActive);
  if (agent) s.entryPoints.push({ id: uid(), kind: "channel_default", channelId: ch.id, agentId: agent.id, priority: 100, isActive: true });
  return ch.id;
}

export async function disconnectChannel(id: string) {
  const ch = must(db().channels.find((c) => c.id === id), "Canal");
  ch.status = "disconnected";
  ch.statusDetail = undefined;
}

export async function updateWidgetConfig(channelId: string, patch: Partial<WidgetConfig>) {
  const ch = must(db().channels.find((c) => c.id === channelId && c.widget), "Widget");
  Object.assign(ch.widget!, patch);
}

export async function getWidgetByKey(publicKey: string) {
  const ch = db().channels.find((c) => c.widget?.publicKey === publicKey && c.status === "connected");
  return ch ? clone(ch) : undefined;
}

// ---------------------------------------------------------------------------
// Roteamento
// ---------------------------------------------------------------------------

export async function listEntryPoints() {
  await connection();
  return clone(db().entryPoints);
}

export async function upsertEntryPoint(ep: Omit<EntryPoint, "id"> & { id?: string }) {
  const s = db();
  if (ep.kind === "keyword" && !ep.keyword?.trim()) throw new DataError("invalid", "Informe a palavra-chave.");
  if (ep.kind === "channel_default" && s.entryPoints.some((x) => x.kind === "channel_default" && x.channelId === ep.channelId && x.id !== ep.id))
    throw new DataError("invalid", "Esse canal já tem um agente padrão. Edite a regra existente.");
  if (ep.id) Object.assign(must(s.entryPoints.find((x) => x.id === ep.id), "Regra"), ep);
  else s.entryPoints.push({ ...ep, id: uid() });
}

export async function deleteEntryPoint(id: string) {
  const s = db();
  s.entryPoints = s.entryPoints.filter((x) => x.id !== id);
}

/** Palavra-chave só vale na primeira mensagem; senão, o padrão do canal. */
function resolveAgent(channelId: string, firstMessage: string) {
  const eps = db().entryPoints.filter((e) => e.channelId === channelId && e.isActive).sort((a, b) => a.priority - b.priority);
  const text = firstMessage.toLowerCase();
  return (eps.find((e) => e.kind === "keyword" && e.keyword && text.includes(e.keyword.toLowerCase())) ?? eps.find((e) => e.kind === "channel_default"))?.agentId;
}

// ---------------------------------------------------------------------------
// Caixa de entrada
// ---------------------------------------------------------------------------

export async function listConversations({ filter = "all", q = "" }: { filter?: ConversationFilter; q?: string } = {}) {
  await connection();
  const s = db();
  const query = q.trim().toLowerCase();
  const items: ConversationListItem[] = s.conversations.map((conversation) => {
    const msgs = s.messages.filter((m) => m.conversationId === conversation.id && m.kind !== "note" && m.author !== "system");
    return {
      conversation,
      contact: must(s.contacts.find((c) => c.id === conversation.contactId), "Contato"),
      channel: must(s.channels.find((c) => c.id === conversation.channelId), "Canal"),
      lastMessage: msgs[msgs.length - 1],
    };
  });
  return clone(
    items
      .filter(({ conversation, contact }) => {
        if (filter === "unread" && conversation.unreadCount === 0) return false;
        if (filter === "mine" && contact.assignedUserId !== s.currentUserId) return false;
        if (filter === "paused" && contact.botActive) return false;
        if (query && !contact.name.toLowerCase().includes(query) && !s.messages.some((m) => m.conversationId === conversation.id && m.body.toLowerCase().includes(query))) return false;
        return true;
      })
      .sort((a, b) => Number(b.conversation.pinned) - Number(a.conversation.pinned) || b.conversation.lastMessageAt.localeCompare(a.conversation.lastMessageAt)),
  );
}

export async function getConversationDetail(id: string) {
  await connection();
  const s = db();
  const conversation = must(s.conversations.find((c) => c.id === id), "Conversa");
  const contact = must(s.contacts.find((c) => c.id === conversation.contactId), "Contato");
  const channel = must(s.channels.find((c) => c.id === conversation.channelId), "Canal");
  const agent = s.agents.find((a) => a.id === contact.agentId);
  const v = agent?.published;
  const opening = v ? nextOpening(new Date(), v.alwaysOn, v.activeHours, v.timezone) : null;
  return clone({
    conversation,
    contact,
    channel,
    agent,
    messages: s.messages.filter((m) => m.conversationId === id),
    followUps: s.followUps.filter((f) => f.conversationId === id && f.status === "scheduled"),
    tags: s.tags,
    members: s.members,
    nextOpening: opening instanceof Date ? opening.toISOString() : opening,
    typing: !!conversation.typingUntil && Date.parse(conversation.typingUntil) > Date.now(),
  });
}

export async function markRead(conversationId: string) {
  const c = db().conversations.find((x) => x.id === conversationId);
  if (c) c.unreadCount = 0;
}

function cancelFollowUps(conversationId: string) {
  for (const f of db().followUps) if (f.conversationId === conversationId && f.status === "scheduled") f.status = "cancelled";
}

function bump(conversationId: string) {
  const n = (gens().get(conversationId) ?? 0) + 1;
  gens().set(conversationId, n);
  return n;
}

function pushMessage(m: Omit<Message, "id" | "createdAt">) {
  const s = db();
  const msg: Message = { ...m, id: uid(), createdAt: now() };
  s.messages.push(msg);
  const c = s.conversations.find((x) => x.id === m.conversationId);
  if (c && m.kind !== "note" && m.author !== "system") {
    c.lastMessageAt = msg.createdAt;
    if (m.author === "contact") {
      c.lastInboundAt = msg.createdAt;
      c.unreadCount += 1;
    }
  }
  return msg;
}

export async function setBotActive(contactId: string, active: boolean) {
  const s = db();
  const contact = must(s.contacts.find((c) => c.id === contactId), "Contato");
  if (contact.botActive === active) return;
  contact.botActive = active;
  const me = s.members.find((m) => m.id === s.currentUserId);
  for (const conv of s.conversations.filter((c) => c.contactId === contactId)) {
    // Pausar interrompe uma resposta em andamento e cancela follow-ups.
    bump(conv.id);
    conv.typingUntil = undefined;
    if (!active) cancelFollowUps(conv.id);
    pushMessage({ conversationId: conv.id, direction: "internal", author: "system", kind: "text", status: "sent", body: `${me?.name.split(" ")[0] ?? "Alguém"} ${active ? "retomou" : "pausou"} a IA neste contato` });
  }
}

export async function setFollowUpsDisabled(contactId: string, disabled: boolean) {
  const s = db();
  must(s.contacts.find((c) => c.id === contactId), "Contato").followUpsDisabled = disabled;
  if (disabled) for (const conv of s.conversations.filter((c) => c.contactId === contactId)) cancelFollowUps(conv.id);
}

export async function sendHumanMessage(conversationId: string, body: string) {
  if (!body.trim()) throw new DataError("invalid", "A mensagem está vazia.");
  const s = db();
  const conv = must(s.conversations.find((c) => c.id === conversationId), "Conversa");
  const msg = pushMessage({ conversationId, direction: "out", author: "human", authorUserId: s.currentUserId, kind: "text", body: body.trim(), status: "sent" });
  conv.unreadCount = 0;
  // Simula a confirmação de entrega do canal.
  setTimeout(() => (msg.status = "delivered"), 800);
}

export async function addNote(conversationId: string, body: string) {
  if (!body.trim()) throw new DataError("invalid", "A nota está vazia.");
  must(db().conversations.find((c) => c.id === conversationId), "Conversa");
  pushMessage({ conversationId, direction: "internal", author: "human", authorUserId: db().currentUserId, kind: "note", body: body.trim(), status: "sent" });
}

export async function assignContact(contactId: string, userId: string | null) {
  must(db().contacts.find((c) => c.id === contactId), "Contato").assignedUserId = userId ?? undefined;
}

export async function toggleFlag(conversationId: string, flag: "pinned" | "starred") {
  const c = must(db().conversations.find((x) => x.id === conversationId), "Conversa");
  c[flag] = !c[flag];
}

export async function setContactTag(contactId: string, tagId: string, on: boolean) {
  const c = must(db().contacts.find((x) => x.id === contactId), "Contato");
  c.tagIds = on ? [...new Set([...c.tagIds, tagId])] : c.tagIds.filter((t) => t !== tagId);
}

export async function listTags() {
  await connection();
  return clone(db().tags);
}

export async function listContacts() {
  await connection();
  const s = db();
  return clone(
    s.contacts.map((contact) => ({
      contact,
      channels: s.conversations.filter((c) => c.contactId === contact.id).map((c) => s.channels.find((ch) => ch.id === c.channelId)!.kind),
      lastMessageAt: s.conversations.filter((c) => c.contactId === contact.id).map((c) => c.lastMessageAt).sort().at(-1),
    })),
  );
}

// ---------------------------------------------------------------------------
// Resposta da IA (simula o job conversation/reply do Inngest)
// ---------------------------------------------------------------------------

const DEBOUNCE_MS = 1500;

function scheduleReply(conversationId: string) {
  const gen = bump(conversationId);
  setTimeout(() => void runReply(conversationId, gen), DEBOUNCE_MS);
}

async function runReply(conversationId: string, gen: number) {
  const s = db();
  const alive = () => gens().get(conversationId) === gen;
  if (!alive()) return;
  const conv = s.conversations.find((c) => c.id === conversationId);
  const contact = conv && s.contacts.find((c) => c.id === conv.contactId);
  const agent = contact && s.agents.find((a) => a.id === contact.agentId);
  const v = agent?.published;
  if (!conv || !contact || !agent || !v || !agent.isActive || !contact.botActive) return;

  const opening = nextOpening(new Date(), v.alwaysOn, v.activeHours, v.timezone);
  if (opening === "never") return;
  if (opening) {
    // Fora do horário: guarda e responde na abertura (setTimeout só no falso).
    const wait = opening.getTime() - Date.now();
    if (wait < 2 ** 31 - 1) setTimeout(() => alive() && void runReply(conversationId, gen), wait);
    return;
  }

  const faq = s.faq.filter((f) => v.knowledgeBaseIds.includes(f.knowledgeBaseId));
  const history = s.messages.filter((m) => m.conversationId === conversationId && m.kind !== "note" && m.author !== "system");
  const { parts } = fakeReply(v, faq, history);
  for (const part of parts) {
    const delay = typingDelayMs(part, v.typingSpeed);
    conv.typingUntil = new Date(Date.now() + delay + 500).toISOString();
    await sleep(delay);
    if (!alive() || !contact.botActive) {
      conv.typingUntil = undefined;
      return;
    }
    pushMessage({ conversationId, direction: "out", author: "ai", kind: "text", body: part, status: "delivered" });
  }
  conv.typingUntil = undefined;

  if (v.followUps.enabled && !contact.followUpsDisabled) {
    cancelFollowUps(conversationId);
    let at = Date.now();
    v.followUps.quick.delaysMinutes.slice(0, v.followUps.quick.count).forEach((d, i) => {
      at += d * 60_000;
      s.followUps.push({ id: uid(), conversationId, step: i + 1, cycle: "quick", sendAt: new Date(at).toISOString(), status: "scheduled" });
    });
  }
}

// ---------------------------------------------------------------------------
// Widget (lado do visitante)
// ---------------------------------------------------------------------------

function domainAllowed(allowed: string[], host?: string) {
  if (allowed.length === 0) return true;
  if (!host) return false;
  return allowed.some((d) => (d.startsWith("*.") ? host === d.slice(2) || host.endsWith(d.slice(1)) : host === d));
}

/** Para o proxy: domínios que podem embutir o iframe (CSP frame-ancestors). */
export function getFrameAncestors(publicKey: string) {
  return db().channels.find((c) => c.widget?.publicKey === publicKey)?.widget?.allowedDomains ?? [];
}

function widgetChannel(publicKey: string, parentHost?: string) {
  const ch = db().channels.find((c) => c.widget?.publicKey === publicKey && c.status === "connected");
  if (!ch?.widget) throw new DataError("not_found", "Widget não encontrado");
  if (!domainAllowed(ch.widget.allowedDomains, parentHost)) throw new DataError("forbidden_domain", "Este site não está autorizado a usar o widget.");
  return ch;
}

function findVisitorConversation(channelId: string, visitorId: string) {
  const s = db();
  const contact = s.contacts.find((c) => c.id === visitorId);
  return contact && s.conversations.find((c) => c.contactId === contact.id && c.channelId === channelId);
}

const visibleToVisitor = (m: Message) => m.kind !== "note" && m.author !== "system";

export async function startWidgetSession(publicKey: string, visitorId: string | undefined, parentHost?: string) {
  const ch = widgetChannel(publicKey, parentHost);
  const conv = visitorId ? findVisitorConversation(ch.id, visitorId) : undefined;
  return clone({
    visitorId: conv ? visitorId! : uid(),
    config: ch.widget!,
    messages: conv ? db().messages.filter((m) => m.conversationId === conv.id && visibleToVisitor(m)) : [],
  });
}

export async function postWidgetMessage(publicKey: string, visitorId: string, body: string, parentHost?: string, fields?: { name?: string; email?: string; phone?: string }) {
  const text = body.trim().slice(0, 2000);
  if (!text) throw new DataError("invalid", "Mensagem vazia");
  const ch = widgetChannel(publicKey, parentHost);
  const s = db();
  let conv = findVisitorConversation(ch.id, visitorId);
  if (!conv) {
    const agentId = resolveAgent(ch.id, text);
    s.contacts.push({ id: visitorId, name: fields?.name?.trim() || `Visitante ${visitorId.slice(0, 4)}`, email: fields?.email, phone: fields?.phone, botActive: true, followUpsDisabled: false, agentId, tagIds: [], createdAt: now() });
    conv = { id: uid(), contactId: visitorId, channelId: ch.id, lastMessageAt: now(), unreadCount: 0, pinned: false, starred: false };
    s.conversations.push(conv);
  } else if (fields) {
    const contact = s.contacts.find((c) => c.id === visitorId)!;
    if (fields.name?.trim()) contact.name = fields.name.trim();
    if (fields.email) contact.email = fields.email;
    if (fields.phone) contact.phone = fields.phone;
  }
  cancelFollowUps(conv.id);
  pushMessage({ conversationId: conv.id, direction: "in", author: "contact", kind: "text", body: text, status: "delivered" });
  scheduleReply(conv.id);
}

export async function getWidgetMessages(publicKey: string, visitorId: string, parentHost?: string) {
  const ch = widgetChannel(publicKey, parentHost);
  const conv = findVisitorConversation(ch.id, visitorId);
  if (!conv) return { messages: [] as Message[], typing: false };
  return clone({
    messages: db().messages.filter((m) => m.conversationId === conv.id && visibleToVisitor(m)),
    typing: !!conv.typingUntil && Date.parse(conv.typingUntil) > Date.now(),
  });
}

/** Mensagem que chega por um canal de mensageria (Telegram no backend). */
export async function receiveChannelMessage(channelId: string, externalUserId: string, name: string, body: string) {
  const s = db();
  let identityContact = s.contacts.find((c) => c.id === `${channelId}:${externalUserId}`);
  if (!identityContact) {
    identityContact = { id: `${channelId}:${externalUserId}`, name, botActive: true, followUpsDisabled: false, agentId: resolveAgent(channelId, body), tagIds: [], createdAt: now() };
    s.contacts.push(identityContact);
  }
  let conv = s.conversations.find((c) => c.contactId === identityContact.id && c.channelId === channelId);
  if (!conv) {
    conv = { id: uid(), contactId: identityContact.id, channelId, lastMessageAt: now(), unreadCount: 0, pinned: false, starred: false };
    s.conversations.push(conv);
  }
  cancelFollowUps(conv.id);
  pushMessage({ conversationId: conv.id, direction: "in", author: "contact", kind: "text", body, status: "delivered" });
  scheduleReply(conv.id);
  return conv.id;
}
