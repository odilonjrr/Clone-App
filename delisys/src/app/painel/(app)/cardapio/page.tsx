import type { Metadata } from "next";
import { MenuEditor } from "@/components/panel/menu-editor";
export const metadata: Metadata = { title: "Cardápio" };
export default function Page() { return <MenuEditor />; }
