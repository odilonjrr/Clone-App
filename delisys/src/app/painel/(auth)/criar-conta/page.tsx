import type { Metadata } from "next";
import { SignupForm } from "./form";
export const dynamic = "force-dynamic";
export const metadata: Metadata = { title: "Criar loja" };
export default function Page() {
  return <SignupForm needsCode={!!process.env.SETUP_CODE} />;
}
