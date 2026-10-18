"use client";

import { Tooltip as T } from "radix-ui";
import type { ReactNode } from "react";

export function Tooltip({ content, children }: { content: ReactNode; children: ReactNode }) {
  return (
    <T.Provider delayDuration={400}>
      <T.Root>
        <T.Trigger asChild>{children}</T.Trigger>
        <T.Portal>
          <T.Content sideOffset={6} className="z-50 max-w-xs rounded-sm bg-fg px-2 py-1 text-xs text-bg shadow-pop">
            {content}
            <T.Arrow className="fill-fg" />
          </T.Content>
        </T.Portal>
      </T.Root>
    </T.Provider>
  );
}
