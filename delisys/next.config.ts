import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // PGlite (banco local de desenvolvimento) e pg rodam só no servidor.
  serverExternalPackages: ["@electric-sql/pglite", "pg"],
  poweredByHeader: false,
};

export default nextConfig;
