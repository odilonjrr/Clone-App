"use client";
import { useEffect, useRef } from "react";
import "leaflet/dist/leaflet.css";

type Pt = { lat: number; lng: number };

/**
 * Mapa com o motoboy (laranja) e o destino (verde). Leaflet + OpenStreetMap: sem chave de API.
 * Para uso em produção com muitos acessos, troque o servidor de mapas por um provedor pago
 * (a política do OpenStreetMap pede uso leve).
 */
export function LiveMap({ courier, destination, height = 220 }: { courier: Pt | null; destination: Pt | null; height?: number }) {
  const el = useRef<HTMLDivElement>(null);
  const state = useRef<{ map?: import("leaflet").Map; courier?: import("leaflet").Marker; dest?: import("leaflet").Marker; fitted?: boolean }>({});

  useEffect(() => {
    let cancelled = false;
    (async () => {
      const L = (await import("leaflet")).default;
      if (cancelled || !el.current) return;
      const s = state.current;
      if (!s.map) {
        s.map = L.map(el.current, { zoomControl: true, attributionControl: true }).setView([courier?.lat ?? destination?.lat ?? -23.55, courier?.lng ?? destination?.lng ?? -46.63], 15);
        L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
          maxZoom: 19,
          attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        }).addTo(s.map);
      }
      const icon = (color: string, label: string) =>
        L.divIcon({
          className: "",
          html: `<div aria-label="${label}" style="width:22px;height:22px;border-radius:50%;background:${color};border:3px solid #fff;box-shadow:0 0 0 4px ${color}55,0 2px 8px rgba(0,0,0,.5)"></div>`,
          iconSize: [22, 22], iconAnchor: [11, 11],
        });
      if (courier) {
        if (!s.courier) s.courier = L.marker([courier.lat, courier.lng], { icon: icon("#F06A1C", "Motoboy"), title: "Motoboy" }).addTo(s.map);
        else s.courier.setLatLng([courier.lat, courier.lng]);
      }
      if (destination && !s.dest) s.dest = L.marker([destination.lat, destination.lng], { icon: icon("#2BD46B", "Destino"), title: "Destino" }).addTo(s.map);
      const pts = [courier, destination].filter(Boolean) as Pt[];
      if (pts.length === 2 && !s.fitted) { s.map.fitBounds(L.latLngBounds(pts.map((p) => [p.lat, p.lng])), { padding: [40, 40], maxZoom: 16 }); s.fitted = true; }
      else if (courier && pts.length === 1) s.map.panTo([courier.lat, courier.lng]);
    })();
    return () => { cancelled = true; };
  }, [courier, destination]);

  useEffect(() => () => { state.current.map?.remove(); state.current = {}; }, []);

  return <div ref={el} style={{ height }} className="w-full overflow-hidden rounded-md" role="img" aria-label="Mapa com a posição do motoboy" />;
}
