"use client";
import { useEffect, useRef } from "react";
import "leaflet/dist/leaflet.css";
import type { Pt } from "@/lib/eta";

export type MapBubble = { at: "store" | "destination" | "courier"; label: string; href?: string };
export type MapApi = { recenter: () => void };

/** Pins desenhados aqui (SVG próprio). Estilos inline: permitidos pela CSP (style-src 'unsafe-inline'). */
const PIN = (bg: string, glyph: string) => `
  <svg width="44" height="54" viewBox="0 0 44 54" aria-hidden="true">
    <path d="M22 53C22 53 4 33 4 21a18 18 0 0 1 36 0c0 12-18 32-18 32Z" fill="${bg}" stroke="#fff" stroke-width="3"/>
    <circle cx="22" cy="21" r="11" fill="#fff"/>
    <g transform="translate(14 13)" fill="none" stroke="${bg}" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">${glyph}</g>
  </svg>`;
const FORK_KNIFE = `<path d="M3 1v6a2 2 0 0 0 2 2h0a2 2 0 0 0 2-2V1M5 9v7M13 10V1c-2 1-3 3-3 6v3h3Zm0 0v6"/>`;
const HOME = `<path d="M1 8 8 2l7 6v7a1 1 0 0 1-1 1h-3v-5H5v5H2a1 1 0 0 1-1-1Z"/>`;
const COURIER = `
  <div style="width:46px;height:46px;border-radius:50%;background:#E2560C;border:3px solid #fff;display:grid;place-items:center;box-shadow:0 0 0 6px rgba(226,86,12,.25),0 4px 12px rgba(0,0,0,.35)">
    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <circle cx="5.5" cy="17.5" r="3.5"/><circle cx="18.5" cy="17.5" r="3.5"/><path d="M15 6h3l3 7.5M5.5 17.5 9 9h5l4.5 8.5M9 9 7 6H4"/>
    </svg>
  </div>`;

function esc(s: string) {
  return s.replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]!);
}

export function TrackingMap({
  store, destination, courier, bubble, onReady, bottomInset = 340,
}: { store: Pt | null; destination: Pt | null; courier: Pt | null; bubble: MapBubble | null; onReady?: (api: MapApi) => void; bottomInset?: number }) {
  const el = useRef<HTMLDivElement>(null);
  const s = useRef<{
    L?: typeof import("leaflet");
    map?: import("leaflet").Map;
    store?: import("leaflet").Marker; dest?: import("leaflet").Marker; courier?: import("leaflet").Marker;
    tip?: import("leaflet").Tooltip; tipKey?: string; fitted?: boolean;
  }>({});
  const latest = useRef({ store, destination, courier, bottomInset });
  latest.current = { store, destination, courier, bottomInset };

  const fit = () => {
    const { L, map } = s.current;
    if (!L || !map) return;
    const pts = [latest.current.store, latest.current.destination, latest.current.courier].filter(Boolean) as Pt[];
    if (!pts.length) return;
    const pad = { paddingTopLeft: [40, 120] as [number, number], paddingBottomRight: [40, latest.current.bottomInset] as [number, number] };
    if (pts.length === 1) {
      map.setView([pts[0].lat, pts[0].lng], 16);
      map.panBy([0, latest.current.bottomInset / 2 - 60], { animate: false });
    } else map.fitBounds(L.latLngBounds(pts.map((p) => [p.lat, p.lng])), { ...pad, maxZoom: 16 });
  };

  useEffect(() => {
    let cancelled = false;
    (async () => {
      const L = (await import("leaflet")).default;
      if (cancelled || !el.current) return;
      const st = s.current;
      st.L = L;
      if (!st.map) {
        st.map = L.map(el.current, { zoomControl: false, attributionControl: true }).setView([-23.55, -46.63], 13);
        L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
          maxZoom: 19,
          attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        }).addTo(st.map);
        onReady?.({ recenter: fit });
      }
      const icon = (html: string, size: [number, number], anchor: [number, number]) =>
        L.divIcon({ className: "dl-pin", html, iconSize: size, iconAnchor: anchor });
      const place = (key: "store" | "dest" | "courier", p: Pt | null, html: string, size: [number, number], anchor: [number, number], title: string) => {
        if (!p) { st[key]?.remove(); st[key] = undefined; return; }
        if (!st[key]) st[key] = L.marker([p.lat, p.lng], { icon: icon(html, size, anchor), title, keyboard: false, zIndexOffset: key === "courier" ? 1000 : 0 }).addTo(st.map!);
        else st[key]!.setLatLng([p.lat, p.lng]);
      };
      place("store", store, PIN("#1A1512", FORK_KNIFE), [44, 54], [22, 53], "Restaurante");
      place("dest", destination, PIN("#1E7A3A", HOME), [44, 54], [22, 53], "Endereço de entrega");
      place("courier", courier, COURIER, [46, 46], [23, 23], "Motoboy");

      // balão (ex.: "Como chegar") preso ao pin escolhido
      const target = bubble ? { store: st.store, destination: st.dest, courier: st.courier }[bubble.at] : undefined;
      const key = bubble && target ? `${bubble.at}|${bubble.label}|${bubble.href ?? ""}` : "";
      if (key !== st.tipKey) {
        st.tip?.remove(); st.tip = undefined; st.tipKey = key;
        if (bubble && target) {
          const html = bubble.href
            ? `<a href="${esc(bubble.href)}" target="_blank" rel="noreferrer">${esc(bubble.label)}</a>`
            : `<span>${esc(bubble.label)}</span>`;
          st.tip = L.tooltip({ permanent: true, direction: "top", offset: [0, bubble.at === "courier" ? -26 : -56], className: "dl-bubble", interactive: !!bubble.href, opacity: 1 })
            .setLatLng(target.getLatLng()).setContent(html).addTo(st.map!);
        }
      } else if (st.tip && target) st.tip.setLatLng(target.getLatLng());

      if (!st.fitted && (store || destination || courier)) { fit(); st.fitted = true; }
    })();
    return () => { cancelled = true; };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [store?.lat, store?.lng, destination?.lat, destination?.lng, courier?.lat, courier?.lng, bubble?.at, bubble?.label, bubble?.href]);

  useEffect(() => () => { s.current.map?.remove(); s.current = {}; }, []);

  return <div ref={el} className="h-full w-full" role="img" aria-label="Mapa do pedido: restaurante, endereço de entrega e motoboy" />;
}
