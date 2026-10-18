import type { ConversationSummary } from "@/components/inbox/conversation-item";
import type { ConversationListItem } from "@/lib/data/types";
import { formatListTime } from "@/lib/format";

export function previewOf(item: ConversationListItem) {
  const m = item.lastMessage;
  if (!m) return "Sem mensagens";
  const prefix = m.author === "ai" ? "IA: " : m.author === "human" ? "Você: " : "";
  const body = m.kind === "audio" ? `Áudio${m.transcript ? `: ${m.transcript}` : ""}` : m.body;
  return prefix + body;
}

export function toSummary(item: ConversationListItem): ConversationSummary {
  return {
    id: item.conversation.id,
    name: item.contact.name,
    channel: item.channel.kind,
    preview: previewOf(item),
    time: formatListTime(item.conversation.lastMessageAt),
    unread: item.conversation.unreadCount,
    pinned: item.conversation.pinned,
    starred: item.conversation.starred,
    aiPaused: !item.contact.botActive,
  };
}
