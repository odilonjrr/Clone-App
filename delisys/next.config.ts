import type { NextConfig } from "next";

const securityHeaders = [
  // só HTTPS por 2 anos, inclusive subdomínios
  { key: "Strict-Transport-Security", value: "max-age=63072000; includeSubDomains; preload" },
  { key: "X-Content-Type-Options", value: "nosniff" },
  // não deixa o app ser aberto dentro de outro site (clickjacking)
  { key: "X-Frame-Options", value: "DENY" },
  // links secretos (/r/..., /m/..., redefinir senha) não vazam para sites externos
  { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
  // só a localização é usada (checkout e motoboy); o resto fica desligado
  { key: "Permissions-Policy", value: "geolocation=(self), camera=(), microphone=(), payment=(), usb=(), interest-cohort=()" },
  { key: "Cross-Origin-Opener-Policy", value: "same-origin" },
  { key: "X-DNS-Prefetch-Control", value: "off" },
];

const nextConfig: NextConfig = {
  // PGlite (banco local de desenvolvimento) e pg rodam só no servidor.
  serverExternalPackages: ["@electric-sql/pglite", "pg"],
  poweredByHeader: false,
  async headers() {
    return [
      { source: "/:path*", headers: securityHeaders },
      // páginas e respostas com dados pessoais não ficam em cache compartilhado
      { source: "/(r|m|painel|api)/:path*", headers: [{ key: "Cache-Control", value: "private, no-store" }] },
      { source: "/(r|m)/:path*", headers: [{ key: "X-Robots-Tag", value: "noindex, nofollow" }] },
    ];
  },
};

export default nextConfig;
