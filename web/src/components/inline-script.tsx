// Script que roda antes da primeira pintura (ver o guia "Preventing Flash"
// da documentação do Next.js). O tipo muda no cliente para o React não
// avisar sobre <script> renderizado.
export function InlineScript({ html }: { html: string }) {
  return (
    <script
      type={typeof window === "undefined" ? "text/javascript" : "text/plain"}
      suppressHydrationWarning
      dangerouslySetInnerHTML={{ __html: html }}
    />
  );
}
