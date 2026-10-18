// Tipos do domínio. Espelham replica/schema.sql (nomes em camelCase).
import type { ChannelKind } from "@/components/ui/channel-badge";

export type { ChannelKind };

export type Tone = "friendly" | "professional" | "casual" | "enthusiastic" | "custom";
export type DayKey = "mon" | "tue" | "wed" | "thu" | "fri" | "sat" | "sun";
export type ActiveHours = Partial<Record<DayKey, [start: string, end: string]>>;

export type PlaybookStep = { id: string; title: string; goal: string };
export type TagRule = { id: string; tagName: string; rule: string };
export type AgentMedia = { id: string; name: string; url: string; sendWhen: string };
export type Webhook = { id: string; name: string; url: string; description: string };

export type FollowUps = {
  enabled: boolean;
  /** Mensagens dentro das primeiras 24 h (únicas permitidas no Instagram). */
  quick: { count: number; delaysMinutes: number[] };
  /** Ciclo longo, depois de 24 h (no WhatsApp exige modelo aprovado). */
  long: { count: number; delaysMinutes: number[] };
};

export type Abilities = {
  booking: { enabled: boolean; durationMinutes: number };
  webSearch: { enabled: boolean };
  webhooks: Webhook[];
};

export type AgentConfig = {
  instructions: string;
  tone: Tone;
  toneCustom: string;
  primaryLanguage: string;
  playbook: PlaybookStep[];
  alwaysOn: boolean;
  activeHours: ActiveHours;
  timezone: string;
  followUps: FollowUps;
  abilities: Abilities;
  tagRules: TagRule[];
  media: AgentMedia[];
  knowledgeBaseIds: string[];
  typingSpeed: "instant" | "fast" | "natural" | "slow";
  splitMessages: boolean;
  aiDisclosure: string;
};

export type AgentVersion = AgentConfig & {
  id: string;
  status: "draft" | "published";
  publishedAt?: string;
};

export type Agent = {
  id: string;
  name: string;
  isActive: boolean;
  draft: AgentVersion;
  published?: AgentVersion;
  createdAt: string;
};

export type KnowledgeSource = {
  id: string;
  knowledgeBaseId: string;
  kind: "website" | "file" | "text";
  sourceUrl?: string;
  title: string;
  status: "pending" | "processing" | "ready" | "failed";
  error?: string;
  pages?: number;
  lastSyncedAt?: string;
};

export type FaqItem = { id: string; knowledgeBaseId: string; question: string; answer: string };

export type KnowledgeBase = { id: string; name: string; createdAt: string };

export type WidgetConfig = {
  publicKey: string;
  primaryColor: string;
  theme: "light" | "dark" | "auto";
  position: "bottom-right" | "bottom-left";
  layout: "bubble" | "inline";
  title: string;
  welcomeMessage: string;
  collectFields: ("name" | "email" | "phone")[];
  allowedDomains: string[];
};

export type Channel = {
  id: string;
  kind: ChannelKind;
  displayName: string;
  status: "connected" | "error" | "disconnected";
  statusDetail?: string;
  externalId?: string;
  connectedAt: string;
  widget?: WidgetConfig;
};

export type EntryPoint = {
  id: string;
  kind: "channel_default" | "keyword";
  channelId: string;
  agentId: string;
  keyword?: string;
  priority: number;
  isActive: boolean;
};

export type Tag = { id: string; name: string };

export type Member = { id: string; name: string; email: string; role: "owner" | "admin" | "agent" };

export type Contact = {
  id: string;
  name: string;
  email?: string;
  phone?: string;
  timezone?: string;
  botActive: boolean;
  followUpsDisabled: boolean;
  agentId?: string;
  assignedUserId?: string;
  tagIds: string[];
  createdAt: string;
};

export type MessageAuthor = "contact" | "ai" | "human" | "system";
export type MessageKind = "text" | "audio" | "image" | "file" | "note";
export type MessageStatus = "pending" | "scheduled" | "sent" | "delivered" | "read" | "failed";

export type Message = {
  id: string;
  conversationId: string;
  direction: "in" | "out" | "internal";
  author: MessageAuthor;
  authorUserId?: string;
  kind: MessageKind;
  body: string;
  transcript?: string;
  fileName?: string;
  status: MessageStatus;
  scheduledFor?: string;
  createdAt: string;
};

export type Conversation = {
  id: string;
  contactId: string;
  channelId: string;
  lastMessageAt: string;
  lastInboundAt?: string;
  unreadCount: number;
  pinned: boolean;
  starred: boolean;
  /** A IA está "digitando" até este instante (só no data layer falso). */
  typingUntil?: string;
};

export type FollowUpJob = {
  id: string;
  conversationId: string;
  step: number;
  cycle: "quick" | "long";
  sendAt: string;
  status: "scheduled" | "sent" | "cancelled";
};

export type Workspace = { id: string; name: string; timezone: string; plan: string };

export type ConversationListItem = {
  conversation: Conversation;
  contact: Contact;
  channel: Channel;
  lastMessage?: Message;
};

export type ConversationFilter = "all" | "unread" | "mine" | "paused";
