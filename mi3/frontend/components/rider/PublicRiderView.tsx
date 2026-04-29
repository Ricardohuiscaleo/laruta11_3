'use client';

import { useState, useEffect, useCallback, useRef } from 'react';
import { APIProvider, Map, AdvancedMarker, useMap, useMapsLibrary } from '@vis.gl/react-google-maps';
import { usePublicRiderGPS } from '@/hooks/usePublicRiderGPS';
import type { GeoPosition } from '@/hooks/usePublicRiderGPS';

const API_URL = process.env.NEXT_PUBLIC_API_URL || 'https://api-mi3.laruta11.cl';

interface OrderItem { product_name: string; quantity: number; product_price: number; }
interface PublicOrderData {
  id: number; order_number: string; order_status: string;
  customer_name: string; customer_phone: string; delivery_address: string;
  delivery_fee: number; card_surcharge: number; subtotal: number; product_price: number;
  payment_method: string; delivery_distance_km: number | null; delivery_duration_min: number | null;
  rider_last_lat: number | null; rider_last_lng: number | null;
  items: OrderItem[]; food_truck: { latitud: number; longitud: number } | null;
}

const fmt = (n: number) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(n);
const payLabel: Record<string, string> = { cash: 'Efectivo', card: 'Tarjeta', transfer: 'Transferencia' };

/* ── Dynamic Route overlay — re-renders when origin moves ── */
function RouteLayer({ origin, destination, routeKey }: { origin: { lat: number; lng: number }; destination: string; routeKey: string }) {
  const map = useMap();
  const routesLib = useMapsLibrary('routes');
  const rendererRef = useRef<google.maps.DirectionsRenderer | null>(null);

  useEffect(() => {
    if (!map || !routesLib) return;
    if (!rendererRef.current) {
      rendererRef.current = new routesLib.DirectionsRenderer({ suppressMarkers: true, polylineOptions: { strokeColor: '#4285F4', strokeWeight: 5, strokeOpacity: 0.8 } });
      rendererRef.current.setMap(map);
    }
    new routesLib.DirectionsService().route(
      { origin, destination, travelMode: google.maps.TravelMode.DRIVING },
      (r, s) => { if (s === 'OK' && r && rendererRef.current) rendererRef.current.setDirections(r); },
    );
    return () => { if (rendererRef.current) { rendererRef.current.setMap(null); rendererRef.current = null; } };
  }, [map, routesLib, routeKey, destination]);
  return null;
}

/* ── Pan map + rotate heading ── */
function PanToPosition({ position, trigger }: { position: GeoPosition; trigger: number }) {
  const map = useMap();
  useEffect(() => {
    if (map && trigger > 0) { map.panTo(position); map.setZoom(16); }
  }, [map, position, trigger]);
  return null;
}

/* ── Rotate map based on heading (car always points up) ── */
function MapHeading({ heading }: { heading: number | null }) {
  const map = useMap();
  useEffect(() => {
    if (map && heading != null) {
      // moveCamera is instant — no slow animation
      (map as any).moveCamera({ heading });
    }
  }, [map, heading]);
  return null;
}

/*
 * Rider flow — 3 phases:
 * 1. "Ir al local"     → GPS on, route to food truck, no status change
 * 2. "Recibir pedido"  → at food truck, picks up → status = out_for_delivery
 * 3. "Entregar"        → at destination → status = delivered
 */

export default function PublicRiderView({ orderId }: { orderId: string }) {
  const [order, setOrder] = useState<PublicOrderData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [updating, setUpdating] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  const [detailOpen, setDetailOpen] = useState(false);
  const [gpsActive, setGpsActive] = useState(false);
  const [panTrigger, setPanTrigger] = useState(0);

  const gpsEnabled = gpsActive || order?.order_status === 'out_for_delivery';
  const { position, gpsError } = usePublicRiderGPS({ orderId, enabled: gpsEnabled });

  useEffect(() => {
    (async () => {
      try {
        const res = await fetch(`${API_URL}/api/v1/public/rider-orders/${orderId}`, { headers: { Accept: 'application/json' } });
        if (!res.ok) { setError(res.status === 404 ? 'Pedido no encontrado' : 'Error al cargar'); return; }
        const d = await res.json();
        if (d.success && d.order) setOrder(d.order); else setError('Pedido no encontrado');
      } catch { setError('Sin conexión'); } finally { setLoading(false); }
    })();
  }, [orderId]);

  const updateStatus = useCallback(async (s: 'out_for_delivery' | 'delivered') => {
    setUpdating(true); setActionError(null);
    try {
      const res = await fetch(`${API_URL}/api/v1/public/rider-orders/${orderId}/status`, {
        method: 'PATCH', headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ status: s }),
      });
      if (!res.ok) { const b = await res.json().catch(() => ({})); setActionError(b.error || 'Error'); return; }
      setOrder(p => p ? { ...p, order_status: s } : p);
    } catch { setActionError('Sin conexión'); } finally { setUpdating(false); }
  }, [orderId]);

  const rejectDelivery = useCallback(async () => {
    if (!confirm('¿Cancelar este delivery? Se dejará de compartir tu ubicación.')) return;
    setUpdating(true); setActionError(null);
    try {
      const res = await fetch(`${API_URL}/api/v1/public/rider-orders/${orderId}/status`, {
        method: 'PATCH', headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ status: 'reject' }),
      });
      if (!res.ok) { const b = await res.json().catch(() => ({})); setActionError(b.error || 'Error'); return; }
      setGpsActive(false);
      setOrder(p => p ? { ...p, order_status: 'ready', rider_last_lat: null, rider_last_lng: null } : p);
    } catch { setActionError('Sin conexión'); } finally { setUpdating(false); }
  }, [orderId]);

  if (loading) return (
    <div className="flex items-center justify-center h-dvh bg-gray-900">
      <div className="h-10 w-10 border-4 border-gray-600 border-t-white rounded-full animate-spin" />
    </div>
  );
  if (error || !order) return (
    <div className="flex items-center justify-center h-dvh bg-gray-50 p-6 text-center">
      <div><p className="text-4xl mb-3">🔍</p><p className="font-semibold text-gray-800">Pedido no encontrado</p><p className="text-sm text-gray-500 mt-1">{error}</p></div>
    </div>
  );
  if (order.order_status === 'delivered') return (
    <div className="flex items-center justify-center h-dvh bg-gray-50 p-6 text-center">
      <div>
        <div className="h-20 w-20 mx-auto rounded-full bg-green-100 flex items-center justify-center mb-4"><span className="text-4xl">✅</span></div>
        <p className="text-xl font-bold text-gray-900">¡Entregado!</p>
        <p className="text-sm text-gray-500 mt-1">#{order.order_number} — {order.customer_name}</p>
      </div>
    </div>
  );

  const foodTruck = order.food_truck ? { lat: Number(order.food_truck.latitud), lng: Number(order.food_truck.longitud) } : null;
  const isOnRoute = order.order_status === 'out_for_delivery';
  const isPrePickup = ['sent_to_kitchen', 'preparing', 'ready'].includes(order.order_status);

  // Route: phase 1 → rider to food truck, phase 2/3 → rider to delivery address
  const routeOrigin = position && gpsEnabled ? position : foodTruck;
  const routeDest = isOnRoute ? order.delivery_address : (foodTruck ? `${foodTruck.lat},${foodTruck.lng}` : order.delivery_address);

  return (
    <div className="relative h-dvh w-full overflow-hidden">
      {/* ── HEADER ── */}
      <div className="absolute top-0 left-0 right-0 z-10">
        {gpsError && <div className="mx-3 mt-2 rounded-lg bg-amber-500/90 backdrop-blur px-3 py-2 text-xs text-white" role="alert">⚠️ {gpsError}</div>}
        {actionError && <div className="mx-3 mt-2 rounded-lg bg-red-500/90 backdrop-blur px-3 py-2 text-xs text-white" role="alert">❌ {actionError}</div>}
        <div className="mx-3 mt-2 rounded-2xl bg-white/95 backdrop-blur-md shadow-lg px-4 py-3">
          <div className="flex items-start gap-3">
            <div className="shrink-0 mt-0.5">
              <div className="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center text-sm">{isOnRoute ? '📍' : '🏪'}</div>
            </div>
            <div className="min-w-0 flex-1">
              <p className="text-xs text-gray-400 font-medium">{isOnRoute ? 'Entregar en' : gpsActive ? 'Ir a retirar en' : 'Pedido para'}</p>
              <p className="text-sm font-semibold text-gray-900 leading-tight line-clamp-2">{isOnRoute ? order.delivery_address : 'La Ruta 11 — Yumbel 2629'}</p>
              <div className="flex items-center gap-3 mt-1">
                {order.delivery_distance_km != null && <span className="text-xs text-gray-500">📏 {order.delivery_distance_km} km</span>}
                {order.delivery_duration_min != null && <span className="text-xs text-gray-500">⏱️ {order.delivery_duration_min} min</span>}
                <span className="text-xs font-bold text-green-600">{fmt(order.product_price)}</span>
              </div>
            </div>
            {order.customer_phone && (
              <a href={`tel:${order.customer_phone}`} className="shrink-0 h-10 w-10 rounded-full bg-green-500 flex items-center justify-center shadow-md active:scale-95 transition-transform" aria-label={`Llamar a ${order.customer_name}`}>
                <span className="text-lg">📞</span>
              </a>
            )}
          </div>
          {/* Progress bar — 3 steps */}
          <div className="flex items-center gap-1 mt-2 pt-2 border-t border-gray-100">
            <div className={`flex-1 h-1.5 rounded-full ${gpsActive || isOnRoute ? 'bg-blue-500' : 'bg-gray-200'}`} />
            <div className={`flex-1 h-1.5 rounded-full ${isOnRoute ? 'bg-amber-500' : 'bg-gray-200'}`} />
            <div className="flex-1 h-1.5 rounded-full bg-gray-200" />
            <span className="text-[10px] text-gray-400 ml-1 whitespace-nowrap">
              {isOnRoute ? '3/3 Entregando' : gpsActive ? '1/3 Al local' : 'Esperando'}
            </span>
          </div>
          {gpsEnabled && position && (
            <div className="flex items-center gap-1.5 mt-1">
              <span className="h-2 w-2 rounded-full bg-green-500 animate-pulse" />
              <span className="text-[10px] text-green-600 font-medium">GPS activo</span>
            </div>
          )}
        </div>
      </div>

      {/* ── MAP ── */}
      <div className="absolute inset-0">
        {foodTruck ? (
          <APIProvider apiKey={process.env.NEXT_PUBLIC_GOOGLE_MAPS_KEY ?? ''}>
            <Map defaultCenter={foodTruck} defaultZoom={14} mapId="d51ca892b68e9c5e5e2dd701" className="h-full w-full" gestureHandling="greedy" disableDefaultUI>
              {routeOrigin && <RouteLayer origin={routeOrigin} destination={routeDest} routeKey={`${Math.round((position?.lat ?? 0) * 1000)}-${Math.round((position?.lng ?? 0) * 1000)}-${isOnRoute ? 'd' : 'f'}`} />}
              {/* Food truck marker — R11 logo */}
              <AdvancedMarker position={foodTruck} zIndex={100}>
                <div className="flex flex-col items-center">
                  <img src="https://laruta11-images.s3.amazonaws.com/menu/logo-optimized.png" alt="La Ruta 11" className="h-10 w-10 rounded-full border-2 border-red-500 shadow-lg bg-white" />
                  <span className="text-[8px] font-bold bg-red-600 text-white px-1.5 py-0.5 rounded-full mt-0.5 shadow">La Ruta 11</span>
                </div>
              </AdvancedMarker>
              {/* Rider car — always points up, map rotates instead */}
              {position && gpsEnabled && (
                <AdvancedMarker position={position} zIndex={200}>
                  <div className="h-14 w-14 drop-shadow-lg">
                    <img src="/rider-car.svg" alt="Rider" className="h-full w-full" />
                  </div>
                </AdvancedMarker>
              )}
              {position && gpsEnabled && <PanToPosition position={position} trigger={panTrigger} />}
              {position && gpsEnabled && <MapHeading heading={position.heading} />}
            </Map>
          </APIProvider>
        ) : (
          <div className="h-full w-full bg-gray-200 flex items-center justify-center"><p className="text-sm text-gray-500">Mapa no disponible</p></div>
        )}
      </div>

      {/* ── BOTTOM ── */}
      <div className="absolute bottom-0 left-0 right-0 z-10">
        {detailOpen && (
          <div className="mx-3 mb-2 rounded-2xl bg-white/95 backdrop-blur-md shadow-lg max-h-[50vh] overflow-y-auto">
            <div className="p-4 space-y-3">
              <div className="flex items-center justify-between">
                <div><p className="text-xs text-gray-400">Cliente</p><p className="text-sm font-semibold text-gray-900">{order.customer_name}</p></div>
                <span className="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-bold text-blue-700">#{order.order_number}</span>
              </div>
              <div>
                <p className="text-xs text-gray-400 mb-1">Productos</p>
                {order.items.map((item, i) => (
                  <div key={i} className="flex justify-between text-sm py-0.5">
                    <span className="text-gray-800">{item.product_name} <span className="text-gray-400">×{item.quantity}</span></span>
                    <span className="text-gray-600 font-medium">{fmt(item.product_price)}</span>
                  </div>
                ))}
              </div>
              <div className="border-t border-gray-100 pt-2 space-y-1">
                <div className="flex justify-between text-xs text-gray-500"><span>Subtotal</span><span>{fmt(order.subtotal)}</span></div>
                <div className="flex justify-between text-xs text-gray-500"><span>Delivery</span><span>{fmt(order.delivery_fee)}</span></div>
                {order.card_surcharge > 0 && <div className="flex justify-between text-xs text-gray-500"><span>Recargo</span><span>{fmt(order.card_surcharge)}</span></div>}
                <div className="flex justify-between text-sm font-bold text-gray-900 pt-1 border-t border-gray-100"><span>Total</span><span>{fmt(order.product_price)}</span></div>
                <div className="text-xs text-gray-400 text-right">{payLabel[order.payment_method] || order.payment_method}</div>
              </div>
              <div className="pt-1">
                <p className="text-xs text-gray-400">Dirección entrega</p>
                <a href={`https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(order.delivery_address)}`} target="_blank" rel="noopener noreferrer" className="text-sm text-blue-600 font-medium underline">{order.delivery_address}</a>
              </div>
            </div>
          </div>
        )}
        <div className="mx-3 mb-3 flex gap-2">
          <button onClick={() => { if (!gpsActive) setGpsActive(true); setPanTrigger(t => t + 1); }} className={`h-14 w-14 shrink-0 rounded-2xl shadow-lg flex items-center justify-center active:scale-95 transition-all ${gpsEnabled ? 'bg-blue-500 ring-2 ring-blue-300' : 'bg-white/95 backdrop-blur-md'}`} aria-label="Mi ubicación">
            <svg className={`h-6 w-6 ${gpsEnabled ? 'text-white' : 'text-gray-600'}`} viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L4.5 20.29l.71.71L12 18l6.79 3 .71-.71z" /></svg>
          </button>
          {isPrePickup && !gpsActive && (
            <button onClick={() => setGpsActive(true)} className="flex-1 h-14 rounded-2xl bg-blue-500 text-white font-bold text-base shadow-lg active:scale-[0.98] transition-all flex items-center justify-center gap-2" aria-label="Ir al local">
              🏪 Ir al local
            </button>
          )}
          {isPrePickup && gpsActive && (
            <button onClick={() => updateStatus('out_for_delivery')} disabled={updating} className="flex-1 h-14 rounded-2xl bg-amber-500 text-white font-bold text-base shadow-lg active:scale-[0.98] disabled:opacity-50 transition-all flex items-center justify-center gap-2" aria-label="Recibir pedido">
              {updating ? <span className="h-5 w-5 border-2 border-white/30 border-t-white rounded-full animate-spin" /> : '📦'}
              {updating ? 'Enviando...' : 'Recibir pedido'}
            </button>
          )}
          {isOnRoute && (
            <button onClick={() => updateStatus('delivered')} disabled={updating} className="flex-1 h-14 rounded-2xl bg-green-500 text-white font-bold text-base shadow-lg active:scale-[0.98] disabled:opacity-50 transition-all flex items-center justify-center gap-2" aria-label="Entregar">
              {updating ? <span className="h-5 w-5 border-2 border-white/30 border-t-white rounded-full animate-spin" /> : '✅'}
              {updating ? 'Enviando...' : 'Entregar'}
            </button>
          )}
          <button onClick={() => setDetailOpen(v => !v)} className={`h-14 px-4 shrink-0 rounded-2xl shadow-lg flex items-center justify-center gap-1.5 active:scale-95 transition-all ${detailOpen ? 'bg-blue-500 text-white' : 'bg-white/95 backdrop-blur-md text-gray-700'}`} aria-label="Ver pedido">
            <span className="text-base">{detailOpen ? '✕' : '🧾'}</span>
            <span className="text-xs font-semibold">{detailOpen ? 'Cerrar' : 'Pedido'}</span>
          </button>
          {/* Cancel button — visible when GPS active */}
          {gpsActive && (
            <button onClick={rejectDelivery} disabled={updating} className="h-14 w-14 shrink-0 rounded-2xl bg-red-500/90 shadow-lg flex items-center justify-center active:scale-95 transition-all disabled:opacity-50" aria-label="Cancelar delivery">
              <span className="text-white text-lg font-bold">✕</span>
            </button>
          )}
        </div>
      </div>
    </div>
  );
}
