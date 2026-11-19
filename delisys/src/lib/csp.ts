/** Política de conteúdo (CSP). Scripts só do próprio app, com nonce por requisição. */
export function buildCsp(nonce: string, isDev: boolean, isHttps = true) {
  return [
    `default-src 'self'`,
    `script-src 'self' 'nonce-${nonce}' 'strict-dynamic'${isDev ? " 'unsafe-eval'" : ""}`,
    // estilos inline: React (style={...}) e o mapa usam atributos de estilo
    `style-src 'self' 'unsafe-inline'`,
    // fotos de produto vêm de links https cadastrados pela loja; mapa do OpenStreetMap; QR do Pix em data:
    `img-src 'self' data: blob: https:`,
    `font-src 'self'`,
    `connect-src 'self'${isDev ? " ws: wss:" : ""}`,
    `object-src 'none'`,
    `base-uri 'self'`,
    `form-action 'self'`,
    `frame-ancestors 'none'`,
    `frame-src 'none'`,
    `worker-src 'self' blob:`,
    `manifest-src 'self'`,
    ...(isDev || !isHttps ? [] : ["upgrade-insecure-requests"]),
  ].join("; ");
}
