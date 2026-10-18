"use client";

import { Moon, Sun } from "lucide-react";
import { Button } from "@/components/ui/button";

export function ThemeToggle() {
  return (
    <Button
      variant="secondary"
      size="sm"
      onClick={() => {
        const root = document.documentElement;
        const dark = root.dataset.theme
          ? root.dataset.theme === "dark"
          : matchMedia("(prefers-color-scheme: dark)").matches;
        const next = dark ? "light" : "dark";
        root.dataset.theme = next;
        try {
          localStorage.setItem("theme", next);
        } catch {}
      }}
    >
      <Sun className="hidden [[data-theme=dark]_&]:block" aria-hidden />
      <Moon className="[[data-theme=dark]_&]:hidden" aria-hidden />
      Alternar tema
    </Button>
  );
}
