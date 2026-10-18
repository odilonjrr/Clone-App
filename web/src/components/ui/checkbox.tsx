"use client";

import { Check, Minus } from "lucide-react";
import { Checkbox as C } from "radix-ui";
import { useId } from "react";

type CheckboxProps = {
  label: string;
  checked?: boolean | "indeterminate";
  defaultChecked?: boolean;
  onCheckedChange?: (checked: boolean | "indeterminate") => void;
  disabled?: boolean;
};

export function Checkbox({ label, ...props }: CheckboxProps) {
  const id = useId();
  return (
    <div className="inline-flex items-center gap-2">
      <C.Root
        id={id}
        {...props}
        className="flex size-4 items-center justify-center rounded-sm border border-border-input bg-bg text-on-accent data-[state=checked]:border-accent data-[state=checked]:bg-accent data-[state=indeterminate]:border-accent data-[state=indeterminate]:bg-accent disabled:opacity-50"
      >
        <C.Indicator>
          {props.checked === "indeterminate" ? <Minus className="size-3" /> : <Check className="size-3" />}
        </C.Indicator>
      </C.Root>
      <label htmlFor={id} className="text-sm text-fg select-none">{label}</label>
    </div>
  );
}
