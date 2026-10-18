import type { Agent, AgentVersion } from "@/lib/data/types";

function strip(v: AgentVersion) {
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  const { id, status, publishedAt, ...config } = v;
  return config;
}

/** O rascunho difere da versão publicada (ou nunca foi publicado). */
export function isDraftDirty(agent: Agent) {
  if (!agent.published) return true;
  return JSON.stringify(strip(agent.draft)) !== JSON.stringify(strip(agent.published));
}
