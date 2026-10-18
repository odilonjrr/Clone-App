import type { Metadata } from "next";
import { InlineScript } from "@/components/inline-script";
import "./globals.css";

export const metadata: Metadata = {
  title: "Atendente IA",
  description: "Agentes de IA para WhatsApp, Instagram, Telegram e o seu site.",
};

// Aplica o tema salvo (claro/escuro) antes da pintura. Sem tema salvo, o CSS
// segue prefers-color-scheme.
const themeScript = `try{var t=localStorage.getItem("theme");if(t==="light"||t==="dark")document.documentElement.dataset.theme=t}catch(e){}`;

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="pt-BR" suppressHydrationWarning className="h-full">
      <head>
        <InlineScript html={themeScript} />
      </head>
      <body className="min-h-full bg-bg text-fg">{children}</body>
    </html>
  );
}
