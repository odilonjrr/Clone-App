import { redirect } from "next/navigation";

// Enquanto as telas não existem, a raiz leva à vitrine do sistema de design.
export default function Home() {
  redirect("/design");
}
