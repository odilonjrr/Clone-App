import type { Metadata } from "next";
import { CourierApp } from "@/components/courier-app";

export const metadata: Metadata = { title: "Minhas entregas", robots: { index: false } };

export default async function Page({ params }: { params: Promise<{ token: string }> }) {
  return <CourierApp token={(await params).token} />;
}
