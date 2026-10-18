// Dados de exemplo inventados (empresa fictícia). Só para o data layer falso.
import type {
  Agent, AgentConfig, Channel, Contact, Conversation, EntryPoint, FaqItem, FollowUpJob,
  KnowledgeBase, KnowledgeSource, Member, Message, Tag, Workspace,
} from "./types";

export type Store = {
  workspace: Workspace;
  members: Member[];
  currentUserId: string;
  agents: Agent[];
  knowledgeBases: KnowledgeBase[];
  sources: KnowledgeSource[];
  faq: FaqItem[];
  channels: Channel[];
  entryPoints: EntryPoint[];
  tags: Tag[];
  contacts: Contact[];
  conversations: Conversation[];
  messages: Message[];
  followUps: FollowUpJob[];
};

export const defaultConfig = (): AgentConfig => ({
  instructions: "",
  tone: "friendly",
  toneCustom: "",
  primaryLanguage: "pt-BR",
  playbook: [],
  alwaysOn: true,
  activeHours: {},
  timezone: "America/Sao_Paulo",
  followUps: { enabled: false, quick: { count: 1, delaysMinutes: [60] }, long: { count: 0, delaysMinutes: [] } },
  abilities: { booking: { enabled: false, durationMinutes: 30 }, webSearch: { enabled: false }, webhooks: [] },
  tagRules: [],
  media: [],
  knowledgeBaseIds: [],
  typingSpeed: "natural",
  splitMessages: true,
  aiDisclosure: "",
});

export function createSeed(now = new Date()): Store {
  const ago = (min: number) => new Date(now.getTime() - min * 60_000).toISOString();
  const weekdays = { mon: ["08:00", "19:00"], tue: ["08:00", "19:00"], wed: ["08:00", "19:00"], thu: ["08:00", "19:00"], fri: ["08:00", "18:00"], sat: ["09:00", "13:00"] } as AgentConfig["activeHours"];

  const salesConfig: AgentConfig = {
    ...defaultConfig(),
    instructions:
      "Você é a assistente virtual do Studio Aurora, um estúdio de pilates e fisioterapia em Curitiba.\nResponda em frases curtas e simpáticas. Quando a pessoa demonstrar interesse, ofereça uma aula experimental gratuita e peça nome e melhor horário.\nNunca invente preços ou horários que não estejam no conhecimento.",
    playbook: [
      { id: "p1", title: "Entender o objetivo", goal: "Descobrir se a pessoa busca condicionamento, dor ou reabilitação." },
      { id: "p2", title: "Qualificar", goal: "Perguntar a região do bairro e a disponibilidade na semana." },
      { id: "p3", title: "Convidar", goal: "Oferecer a aula experimental e agendar." },
    ],
    alwaysOn: false,
    activeHours: weekdays,
    followUps: { enabled: true, quick: { count: 1, delaysMinutes: [120] }, long: { count: 1, delaysMinutes: [1440] } },
    abilities: { booking: { enabled: true, durationMinutes: 50 }, webSearch: { enabled: false }, webhooks: [] },
    tagRules: [
      { id: "tr1", tagName: "Lead quente", rule: "A pessoa pediu preço, plano ou quer marcar a aula experimental." },
      { id: "tr2", tagName: "Aluno", rule: "A pessoa já é aluna e fala de remarcação, pagamento ou problema." },
    ],
    knowledgeBaseIds: ["kb1"],
    aiDisclosure: "Oi! Sou a assistente virtual do Studio Aurora.",
  };

  const agents: Agent[] = [
    {
      id: "a1",
      name: "Recepção Studio Aurora",
      isActive: true,
      draft: { ...salesConfig, id: "a1-draft", status: "draft" },
      published: { ...salesConfig, id: "a1-v3", status: "published", publishedAt: ago(60 * 26) },
      createdAt: ago(60 * 24 * 20),
    },
  ];

  const contacts: Contact[] = [
    { id: "c1", name: "Marina Costa", phone: "+55 41 99876-1122", botActive: true, followUpsDisabled: false, agentId: "a1", tagIds: ["t1"], createdAt: ago(300) },
    { id: "c2", name: "Rafael Lima", botActive: false, followUpsDisabled: false, agentId: "a1", assignedUserId: "u1", tagIds: ["t2"], createdAt: ago(800) },
    { id: "c3", name: "Visitante 4821", email: "bia.souza@exemplo.com", botActive: true, followUpsDisabled: false, agentId: "a1", tagIds: [], createdAt: ago(120) },
    { id: "c4", name: "João Pereira", botActive: true, followUpsDisabled: true, agentId: "a1", tagIds: [], createdAt: ago(60 * 30) },
  ];

  const conversations: Conversation[] = [
    { id: "v1", contactId: "c1", channelId: "ch-tg", lastMessageAt: ago(4), lastInboundAt: ago(6), unreadCount: 2, pinned: true, starred: false },
    { id: "v2", contactId: "c2", channelId: "ch-tg", lastMessageAt: ago(35), lastInboundAt: ago(40), unreadCount: 0, pinned: false, starred: true },
    { id: "v3", contactId: "c3", channelId: "ch-web", lastMessageAt: ago(90), lastInboundAt: ago(90), unreadCount: 1, pinned: false, starred: false },
    { id: "v4", contactId: "c4", channelId: "ch-tg", lastMessageAt: ago(60 * 26), lastInboundAt: ago(60 * 26), unreadCount: 0, pinned: false, starred: false },
  ];

  let n = 0;
  const msg = (conversationId: string, min: number, m: Partial<Message> & Pick<Message, "author" | "body">): Message => ({
    id: `m${++n}`,
    conversationId,
    direction: m.author === "contact" ? "in" : m.kind === "note" ? "internal" : "out",
    kind: "text",
    status: m.author === "contact" ? "delivered" : "read",
    createdAt: ago(min),
    ...m,
  });

  const messages: Message[] = [
    msg("v1", 12, { author: "contact", body: "Oi! Vocês têm aula de pilates para iniciantes?" }),
    msg("v1", 11, { author: "ai", body: "Oi! Sou a assistente virtual do Studio Aurora." }),
    msg("v1", 11, { author: "ai", body: "Temos sim! As turmas de iniciantes têm no máximo 4 alunos por horário. Quer fazer uma aula experimental gratuita?" }),
    msg("v1", 6, { author: "contact", body: "Quero! Quinta à tarde dá?", kind: "audio", transcript: "Quero! Quinta à tarde dá?" }),
    msg("v1", 4, { author: "contact", body: "Moro no Batel" }),
    msg("v2", 50, { author: "contact", body: "Preciso remarcar minha aula de amanhã" }),
    msg("v2", 49, { author: "ai", body: "Claro, Rafael. Vou passar para a equipe da recepção confirmar o novo horário." }),
    msg("v2", 45, { author: "system", body: "Ana pausou a IA neste contato" }),
    msg("v2", 44, { author: "human", authorUserId: "u1", body: "Oi Rafael, aqui é a Ana. Pode ser sexta às 7h?" }),
    msg("v2", 42, { author: "human", authorUserId: "u1", kind: "note", body: "Aluno do plano trimestral, já remarcou 2x este mês." }),
    msg("v2", 40, { author: "contact", body: "Sexta às 7h perfeito, obrigado" }),
    msg("v2", 35, { author: "human", authorUserId: "u1", body: "Combinado! Até sexta." }),
    msg("v3", 92, { author: "contact", body: "Vocês aceitam convênio?" }),
    msg("v3", 91, { author: "ai", body: "Para fisioterapia emitimos recibo para reembolso do seu plano de saúde. Não atendemos pelo convênio direto." }),
    msg("v3", 90, { author: "contact", body: "E estacionamento?" }),
    msg("v4", 60 * 26 + 5, { author: "contact", body: "Qual o valor do plano mensal?" }),
    msg("v4", 60 * 26, { author: "ai", body: "O plano mensal com 2 aulas por semana custa R$ 390. Quer agendar uma aula experimental?" }),
  ];

  const faq: FaqItem[] = [
    { id: "f1", knowledgeBaseId: "kb1", question: "Quanto custa o plano mensal?", answer: "O plano mensal com 2 aulas por semana custa R$ 390. O de 3 aulas custa R$ 520." },
    { id: "f2", knowledgeBaseId: "kb1", question: "Vocês aceitam convênio ou plano de saúde?", answer: "Para fisioterapia emitimos recibo para reembolso do seu plano de saúde. Não atendemos pelo convênio direto." },
    { id: "f3", knowledgeBaseId: "kb1", question: "Tem estacionamento?", answer: "Temos 4 vagas na frente do estúdio e convênio com o estacionamento da esquina." },
    { id: "f4", knowledgeBaseId: "kb1", question: "Qual o endereço e o horário?", answer: "Rua das Araucárias, 120, Batel. De segunda a sexta das 7h às 20h e sábado das 8h às 12h." },
    { id: "f5", knowledgeBaseId: "kb1", question: "A aula experimental é gratuita?", answer: "Sim, a primeira aula é gratuita e dura 50 minutos." },
  ];

  return {
    workspace: { id: "w1", name: "Studio Aurora", timezone: "America/Sao_Paulo", plan: "trial" },
    members: [
      { id: "u1", name: "Ana Ribeiro", email: "ana@studioaurora.exemplo", role: "owner" },
      { id: "u2", name: "Bruno Alves", email: "bruno@studioaurora.exemplo", role: "agent" },
    ],
    currentUserId: "u1",
    agents,
    knowledgeBases: [{ id: "kb1", name: "Site e perguntas frequentes", createdAt: ago(60 * 24 * 20) }],
    sources: [
      { id: "s1", knowledgeBaseId: "kb1", kind: "website", sourceUrl: "https://studioaurora.exemplo", title: "studioaurora.exemplo", status: "ready", pages: 12, lastSyncedAt: ago(60 * 24 * 3) },
    ],
    faq,
    channels: [
      {
        id: "ch-web",
        kind: "web",
        displayName: "Chat do site",
        status: "connected",
        connectedAt: ago(60 * 24 * 19),
        widget: {
          publicKey: "pk_demo_aurora",
          primaryColor: "#0e7c66",
          theme: "light",
          position: "bottom-right",
          layout: "bubble",
          title: "Studio Aurora",
          welcomeMessage: "Olá! Quer conhecer nossas aulas? Pergunte o que quiser.",
          collectFields: ["name", "email"],
          allowedDomains: [],
        },
      },
      { id: "ch-tg", kind: "telegram", displayName: "@aurora_recepcao_bot", status: "connected", externalId: "7012345678", connectedAt: ago(60 * 24 * 10) },
    ],
    entryPoints: [
      { id: "e1", kind: "channel_default", channelId: "ch-web", agentId: "a1", priority: 100, isActive: true },
      { id: "e2", kind: "channel_default", channelId: "ch-tg", agentId: "a1", priority: 100, isActive: true },
    ],
    tags: [
      { id: "t1", name: "Lead quente" },
      { id: "t2", name: "Aluno" },
    ],
    contacts,
    conversations,
    messages,
    followUps: [{ id: "fu1", conversationId: "v3", step: 1, cycle: "quick", sendAt: new Date(now.getTime() + 30 * 60_000).toISOString(), status: "scheduled" }],
  };
}
