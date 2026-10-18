"use client";

import { Check, ChevronDown } from "lucide-react";
import { Select as S } from "radix-ui";
import { cn } from "@/lib/cn";

type Option = { value: string; label: string };

type SelectProps = {
  id?: string;
  value?: string;
  defaultValue?: string;
  onValueChange?: (value: string) => void;
  options: Option[];
  placeholder?: string;
  disabled?: boolean;
  "aria-describedby"?: string;
  "aria-invalid"?: true;
};

export function Select({ options, placeholder, id, ...props }: SelectProps) {
  return (
    <S.Root value={props.value} defaultValue={props.defaultValue} onValueChange={props.onValueChange} disabled={props.disabled}>
      <S.Trigger
        id={id}
        aria-describedby={props["aria-describedby"]}
        aria-invalid={props["aria-invalid"]}
        className="flex h-10 w-full items-center justify-between gap-2 rounded-md border border-border-input bg-bg px-3 text-sm text-fg data-[placeholder]:text-muted disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-danger"
      >
        <S.Value placeholder={placeholder} />
        <S.Icon>
          <ChevronDown className="size-4 text-muted" aria-hidden />
        </S.Icon>
      </S.Trigger>
      <S.Portal>
        <S.Content
          position="popper"
          sideOffset={4}
          className="z-50 max-h-72 min-w-[var(--radix-select-trigger-width)] overflow-hidden rounded-md border border-border bg-surface-raised p-1 shadow-pop"
        >
          <S.Viewport>
            {options.map((o) => (
              <S.Item
                key={o.value}
                value={o.value}
                className={cn(
                  "relative flex h-9 cursor-pointer items-center rounded-sm pr-8 pl-2 text-sm text-fg outline-none select-none",
                  "data-[highlighted]:bg-accent-soft data-[state=checked]:font-semibold",
                )}
              >
                <S.ItemText>{o.label}</S.ItemText>
                <S.ItemIndicator className="absolute right-2">
                  <Check className="size-4 text-accent" aria-hidden />
                </S.ItemIndicator>
              </S.Item>
            ))}
          </S.Viewport>
        </S.Content>
      </S.Portal>
    </S.Root>
  );
}
