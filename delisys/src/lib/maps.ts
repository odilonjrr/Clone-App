export type DeliveryAddress = {
  street: string; number: string; district: string; city: string;
  complement?: string; reference?: string; lat?: number | null; lng?: number | null;
};

export function addressOneLine(a: DeliveryAddress) {
  return [`${a.street}, ${a.number}`, a.complement, a.district, a.city].filter(Boolean).join(" - ");
}

/**
 * Link do Google Maps com a rota até o cliente (abre o app no celular do motoboy).
 * Usa as coordenadas quando o cliente compartilhou a localização; senão, o endereço escrito.
 */
export function googleMapsDirections(a: DeliveryAddress) {
  const dest = a.lat != null && a.lng != null ? `${a.lat},${a.lng}` : addressOneLine(a);
  return `https://www.google.com/maps/dir/?api=1&travelmode=driving&destination=${encodeURIComponent(dest)}`;
}
