"use client";

import { useOptimistic, useTransition } from "react";
import { setAgentActive } from "@/app/actions";
import { Switch } from "@/components/ui/switch";

export function AgentActiveSwitch({ agentId, active, name }: { agentId: string; active: boolean; name: string }) {
  const [optimistic, setOptimistic] = useOptimistic(active);
  const [, start] = useTransition();
  return (
    <Switch
      label={`${name}: ${optimistic ? "ligado" : "desligado"}`}
      hideLabel
      checked={optimistic}
      onCheckedChange={(v) =>
        start(async () => {
          setOptimistic(v);
          await setAgentActive(agentId, v);
        })
      }
    />
  );
}
