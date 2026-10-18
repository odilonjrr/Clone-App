"use server";

// Server actions do painel. Cada uma chama o data layer e atualiza a tela.
import { refresh } from "next/cache";
import { redirect } from "next/navigation";
import * as data from "@/lib/data";
import type { AgentConfig, EntryPoint, WidgetConfig } from "@/lib/data/types";

export type ActionResult = { ok: true } | { ok: false; error: string };

async function run(fn: () => Promise<unknown>): Promise<ActionResult> {
  try {
    await fn();
    refresh();
    return { ok: true };
  } catch (e) {
    if (e instanceof data.DataError) return { ok: false, error: e.message };
    throw e;
  }
}

// Onboarding e agentes
export async function startFromWebsite(_: ActionResult | null, form: FormData): Promise<ActionResult> {
  let agentId: string;
  try {
    ({ agentId } = await data.createAgentFromWebsite(String(form.get("url") ?? "")));
  } catch (e) {
    if (e instanceof data.DataError) return { ok: false, error: e.message };
    throw e;
  }
  redirect(`/agents/${agentId}?novo=1`);
}

export async function createBlankAgent() {
  const id = await data.createAgent("Novo agente");
  redirect(`/agents/${id}`);
}

export async function saveDraft(agentId: string, patch: Partial<AgentConfig> & { name?: string }) {
  return run(() => data.saveDraft(agentId, patch));
}
export async function publishAgent(agentId: string) {
  return run(() => data.publishAgent(agentId));
}
export async function discardDraft(agentId: string) {
  return run(() => data.discardDraft(agentId));
}
export async function setAgentActive(agentId: string, active: boolean) {
  return run(() => data.setAgentActive(agentId, active));
}
export async function playgroundReply(agentId: string, history: { author: "contact" | "ai"; body: string }[]) {
  return data.playgroundReply(agentId, history);
}

// Conhecimento
export async function createKnowledgeBase(name: string) {
  const id = await data.createKnowledgeBase(name);
  redirect(`/knowledge/${id}`);
}
export async function addFaq(kbId: string, q: string, a: string) {
  return run(() => data.addFaq(kbId, q, a));
}
export async function updateFaq(id: string, q: string, a: string) {
  return run(() => data.updateFaq(id, q, a));
}
export async function deleteFaq(id: string) {
  return run(() => data.deleteFaq(id));
}
export async function addWebsiteSource(kbId: string, url: string) {
  return run(() => data.addWebsiteSource(kbId, url));
}

// Canais e roteamento
export async function connectTelegram(token: string) {
  return run(() => data.connectTelegram(token));
}
export async function disconnectChannel(id: string) {
  return run(() => data.disconnectChannel(id));
}
export async function updateWidgetConfig(channelId: string, patch: Partial<WidgetConfig>) {
  return run(() => data.updateWidgetConfig(channelId, patch));
}
export async function upsertEntryPoint(ep: Omit<EntryPoint, "id"> & { id?: string }) {
  return run(() => data.upsertEntryPoint(ep));
}
export async function deleteEntryPoint(id: string) {
  return run(() => data.deleteEntryPoint(id));
}

// Caixa de entrada
export async function markRead(conversationId: string) {
  return run(() => data.markRead(conversationId));
}
export async function setBotActive(contactId: string, active: boolean) {
  return run(() => data.setBotActive(contactId, active));
}
export async function setFollowUpsDisabled(contactId: string, disabled: boolean) {
  return run(() => data.setFollowUpsDisabled(contactId, disabled));
}
export async function sendMessage(conversationId: string, body: string, mode: "reply" | "note") {
  return run(() => (mode === "note" ? data.addNote(conversationId, body) : data.sendHumanMessage(conversationId, body)));
}
export async function assignContact(contactId: string, userId: string | null) {
  return run(() => data.assignContact(contactId, userId));
}
export async function toggleFlag(conversationId: string, flag: "pinned" | "starred") {
  return run(() => data.toggleFlag(conversationId, flag));
}
export async function setContactTag(contactId: string, tagId: string, on: boolean) {
  return run(() => data.setContactTag(contactId, tagId, on));
}
/** Recarrega a tela (substituto do Realtime no data layer falso). */
export async function poll() {
  refresh();
}
