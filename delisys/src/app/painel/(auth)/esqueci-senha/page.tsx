import type { Metadata } from "next";
import { ForgotForm } from "./form";
export const metadata: Metadata = { title: "Esqueci minha senha" };
export default function Page() { return <ForgotForm />; }
