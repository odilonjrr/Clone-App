import type { Metadata, Viewport } from "next";
import { Inter, Poppins, Satisfy } from "next/font/google";
import "./globals.css";

const inter = Inter({ subsets: ["latin"], variable: "--font-inter", display: "swap" });
const poppins = Poppins({ subsets: ["latin"], weight: ["600", "700", "800"], variable: "--font-poppins", display: "swap" });
const satisfy = Satisfy({ subsets: ["latin"], weight: "400", variable: "--font-satisfy", display: "swap" });

export const metadata: Metadata = {
  title: { default: "Delisys", template: "%s · Delisys" },
  description: "Cardápio digital e gestor de pedidos sem comissão.",
};
export const viewport: Viewport = { themeColor: "#0F0C0A", width: "device-width", initialScale: 1, viewportFit: "cover" };

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="pt-BR" className={`${inter.variable} ${poppins.variable} ${satisfy.variable}`}>
      <body>{children}</body>
    </html>
  );
}
