import { clsx, type ClassValue } from "clsx";
import { extendTailwindMerge } from "tailwind-merge";

// Ensina o tailwind-merge sobre a escala de texto dos tokens, para que
// "text-sm" e "text-muted" não sejam tratados como conflito.
const twMerge = extendTailwindMerge({
  extend: {
    theme: { text: ["xs", "sm", "base", "lg", "xl", "display"] },
  },
});

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}
