"use client";

import { Switch as S } from "radix-ui";
import { useId } from "react";
import { cn } from "@/lib/cn";

type SwitchProps = {
  checked?: boolean;
  defaultChecked?: boolean;
  onCheckedChange?: (checked: boolean) => void;
  label: string;
  /** Rótulos que mudam com o valor, ex.: ["IA ativa", "IA pausada"]. */
  stateLabels?: [on: string, off: string];
  disabled?: boolean;
  /** Bloqueado mas focável, para um Tooltip explicar o motivo. */
  locked?: boolean;
  hideLabel?: boolean;
  className?: string;
};

export function Switch({ label, stateLabels, locked, hideLabel, className, ...props }: SwitchProps) {
  const id = useId();
  const on = props.checked ?? props.defaultChecked ?? false;
  return (
    <div className={cn("inline-flex items-center gap-2", className)}>
      <S.Root
        id={id}
        checked={props.checked}
        defaultChecked={props.defaultChecked}
        onCheckedChange={locked ? undefined : props.onCheckedChange}
        disabled={props.disabled}
        aria-disabled={locked || undefined}
        aria-label={hideLabel ? label : undefined}
        className={cn(
          "relative inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-pill bg-border-input transition-colors duration-[var(--motion-fast)]",
          "data-[state=checked]:bg-accent disabled:cursor-not-allowed disabled:opacity-50",
          locked && "cursor-not-allowed opacity-50",
        )}
        onClick={locked ? (e) => e.preventDefault() : undefined}
      >
        <S.Thumb className="block size-4 translate-x-0.5 rounded-pill bg-white shadow-card transition-transform duration-[var(--motion-fast)] data-[state=checked]:translate-x-[18px]" />
      </S.Root>
      {!hideLabel && (
        <label htmlFor={id} className="text-sm text-fg select-none">
          {stateLabels ? (on ? stateLabels[0] : stateLabels[1]) : label}
        </label>
      )}
    </div>
  );
}
