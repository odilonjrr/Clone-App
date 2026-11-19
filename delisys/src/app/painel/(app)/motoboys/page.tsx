import type { Metadata } from "next";
import { CouriersPage } from "@/components/panel/couriers";
export const metadata: Metadata = { title: "Motoboys" };
export default function Page() { return <CouriersPage />; }
