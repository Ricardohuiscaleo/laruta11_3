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

/* ── Route overlay ── */
function RouteLayer({ origin, destination }: { origin: { lat: number; lng: number }; destination: string }) {
  const map = useMap();
  const routesLib = useMapsLibrary('routes');
  useEffect(() => {
    if (!map || !routesLib) return;
    const renderer = new routesLib.DirectionsRenderer({ suppressMarkers: false });
    renderer.setMap(map);
    new routesLib.DirectionsService().route(
      { origin, destination, travelMode: google.maps.TravelMode.DRIVING },
      (r, s) => { if (s === 'OK' && r) renderer.setDirections(r); },
    );
    return () => { renderer.setMap(null); };
  }, [map, routesLib, origin.lat, origin.lng, destination]);
  return null;
}

/* ── Main ── */
export default function PublicRiderView({ orderId }: { orderId: string }) {
  const [order, setOrder] = useState<PublicOrderData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [updating, setUpdating] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  const [detailOpen, setDetailOpen] = useState(false);

  const gpsEnabled = order?.order_status === 'out_for_delivery';
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

  /* Loading */
  if (loading) return (
    <div className="flex items-center justify-center h-dvh bg-gray-900">
      <div className="h-10 w-10 border-4 border-gray-600 border-t-white rounded-full animate-spin" />
    </div>
  );
  /* Error */
  if (error || !order) return (
    <div className="flex items-center justify-center h-dvh bg-gray-50 p-6 text-center">
      <div><p className="text-4xl mb-3">🔍</p><p className="font-semibold text-gray-800">Pedido no encontrado</p><p className="text-sm text-gray-500 mt-1">{error}</p></div>
    </div>
  );
  /* Delivered */
  if (order.order_status === 'delivered') return (
    <div className="flex items-center justify-center h-dvh bg-gray-50 p-6 text-center">
      <div>
        <div className="h-20 w-20 mx-auto rounded-full bg-green-100 flex items-center justify-center mb-4"><span className="text-4xl">✅</span></div>
        <p className="text-xl font-bold text-gray-900">¡Entregado!</p>
        <p className="text-sm text-gray-500 mt-1">#{order.order_number} — {order.customer_name}</p>
      </div>
    </div>
  );

  const origin = order.food_truck ? { lat: Number(order.food_truck.latitud), lng: Number(order.food_truck.longitud) } : null;
  const canStart = ['ready', 'preparing', 'sent_to_kitchen'].includes(order.order_status);
  const isOnRoute = order.order_status === 'out_for_delivery';

  return (
    <div className="relative h-dvh w-full overflow-hidden">

      {/* ── HEADER: dirección + info rápida ── */}
      <div className="absolute top-0 left-0 right-0 z-10 safe-top">
        {/* GPS error */}
        {gpsError && (
          <div className="mx-3 mt-2 rounded-lg bg-amber-500/90 backdrop-blur px-3 py-2 text-xs text-white" role="alert">
            ⚠️ {gpsError}
          </div>
        )}
        {actionError && (
          <div className="mx-3 mt-2 rounded-lg bg-red-500/90 backdrop-blur px-3 py-2 text-xs text-white" role="alert">
            ❌ {actionError}
          </div>
        )}

        {/* Address bar */}
        <div className="mx-3 mt-2 rounded-2xl bg-white/95 backdrop-blur-md shadow-lg px-4 py-3">
          <div className="flex items-start gap-3">
            <div className="shrink-0 mt-0.5">
              <div className="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center text-sm">📍</div>
            </div>
            <div className="min-w-0 flex-1">
              <p className="text-xs text-gray-400 font-medium">Entregar en</p>
              <a
                href={`https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(order.delivery_address)}`}
                target="_blank"
                rel="noopener noreferrer"
                className="text-sm font-semibold text-gray-900 leading-tight line-clamp-2"
              >
                {order.delivery_address}
              </a>
              <div className="flex items-center gap-3 mt-1">
                {order.delivery_distance_km != null && (
                  <span className="text-xs text-gray-500">📏 {order.delivery_distance_km} km</span>
                )}
                {order.delivery_duration_min != null && (
                  <span className="text-xs text-gray-500">⏱️ {order.delivery_duration_min} min</span>
                )}
                <span className="text-xs font-bold text-green-600">{fmt(order.product_price)}</span>
              </div>
            </div>
            {/* Call button */}
            {order.customer_phone && (
              <a
                href={`tel:${order.customer_phone}`}
                className="shrink-0 h-10 w-10 rounded-full bg-green-500 flex items-center justify-center shadow-md active:scale-95 transition-transform"
                aria-label={`Llamar a ${order.customer_name}`}
              >
                <span className="text-lg">📞</span>
              </a>
            )}
          </div>
          {/* GPS indicator */}
          {isOnRoute && position && (
            <div className="flex items-center gap-1.5 mt-2 pt-2 border-t border-gray-100">
              <span className="h-2 w-2 rounded-full bg-green-500 animate-pulse" />
              <span className="text-[10px] text-green-600 font-medium">GPS activo — compartiendo ubicación</span>
            </div>
          )}
        </div>
      </div>

      {/* ── MAP: fullscreen ── */}
      <div className="absolute inset-0">
        {origin && order.delivery_address ? (
          <APIProvider apiKey={process.env.NEXT_PUBLIC_GOOGLE_MAPS_KEY ?? ''}>
            <Map
              defaultCenter={origin}
              defaultZoom={14}
              mapId="d51ca892b68e9c5e5e2dd701"
              className="h-full w-full"
              gestureHandling="greedy"
              disableDefaultUI
            >
              <RouteLayer origin={position && isOnRoute ? position : origin} destination={order.delivery_address} />
              {/* Rider position marker */}
              {position && isOnRoute && (
                <AdvancedMarker position={position}>
                  <div className="h-8 w-8 rounded-full bg-blue-600 border-3 border-white shadow-lg flex items-center justify-center">
                    <span className="text-sm">🛵</span>
                  </div>
                </AdvancedMarker>
              )}
            </Map>
          </APIProvider>
        ) : (
          <div className="h-full w-full bg-gray-200 flex items-center justify-center">
            <p className="text-sm text-gray-500">Mapa no disponible</p>
          </div>
        )}
      </div>

      {/* ── BOTTOM: action button + detail toggle ── */}
      <div className="absolute bottom-0 left-0 right-0 z-10 safe-bottom">
        {/* Detail sheet */}
        {detailOpen && (
          <div className="mx-3 mb-2 rounded-2xl bg-white/95 backdrop-blur-md shadow-lg max-h-[50vh] overflow-y-auto">
            <div className="p-4 space-y-3">
              {/* Customer */}
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-xs text-gray-400">Cliente</p>
                  <p className="text-sm font-semibold text-gray-900">{order.customer_name}</p>
                </div>
                <span className="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-bold text-blue-700">
                  #{order.order_number}
                </span>
              </div>
              {/* Products */}
              <div>
                <p className="text-xs text-gray-400 mb-1">Productos</p>
                {order.items.map((item, i) => (
                  <div key={i} className="flex justify-between text-sm py-0.5">
                    <span className="text-gray-800">{item.product_name} <span className="text-gray-400">×{item.quantity}</span></span>
                    <span className="text-gray-600 font-medium">{fmt(item.product_price)}</span>
                  </div>
                ))}
              </div>
              {/* Totals */}
              <div className="border-t border-gray-100 pt-2 space-y-1">
                <div className="flex justify-between text-xs text-gray-500">
                  <span>Subtotal</span><span>{fmt(order.subtotal)}</span>
                </div>
                <div className="flex justify-between text-xs text-gray-500">
                  <span>Delivery</span><span>{fmt(order.delivery_fee)}</span>
                </div>
                {order.card_surcharge > 0 && (
                  <div className="flex justify-between text-xs text-gray-500">
                    <span>Recargo tarjeta</span><span>{fmt(order.card_surcharge)}</span>
                  </div>
                )}
                <div className="flex justify-between text-sm font-bold text-gray-900 pt-1 border-t border-gray-100">
                  <span>Total</span><span>{fmt(order.product_price)}</span>
                </div>
                <div className="text-xs text-gray-400 text-right">
                  {payLabel[order.payment_method] || order.payment_method}
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Action bar */}
        <div className="mx-3 mb-3 flex gap-2">
          {/* Detail toggle */}
          <button
            onClick={() => setDetailOpen(v => !v)}
            className="h-14 w-14 shrink-0 rounded-2xl bg-white/95 backdrop-blur-md shadow-lg flex items-center justify-center active:scale-95 transition-transform"
            aria-label={detailOpen ? 'Cerrar detalle' : 'Ver detalle del pedido'}
          >
            <span className="text-xl">{detailOpen ? '✕' : '📋'}</span>
          </button>

          {/* Main action button */}
          {canStart && (
            <button
              onClick={() => updateStatus('out_for_delivery')}
              disabled={updating}
              className="flex-1 h-14 rounded-2xl bg-amber-500 text-white font-bold text-base shadow-lg hover:bg-amber-600 active:scale-[0.98] disabled:opacity-50 transition-all flex items-center justify-center gap-2"
              aria-label="Marcar en camino"
            >
              {updating ? <span className="h-5 w-5 border-2 border-white/30 border-t-white rounded-full animate-spin" /> : '🛵'}
              {updating ? 'Enviando...' : 'En camino'}
            </button>
          )}

          {isOnRoute && (
            <button
              onClick={() => updateStatus('delivered')}
              disabled={updating}
              className="flex-1 h-14 rounded-2xl bg-green-500 text-white font-bold text-base shadow-lg hover:bg-green-600 active:scale-[0.98] disabled:opacity-50 transition-all flex items-center justify-center gap-2"
              aria-label="Marcar como entregado"
            >
              {updating ? <span className="h-5 w-5 border-2 border-white/30 border-t-white rounded-full animate-spin" /> : '✅'}
              {updating ? 'Enviando...' : 'Entregado'}
            </button>
          )}

          {/* Navigate button */}
          <a
            href={`https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(order.delivery_address)}`}
            target="_blank"
            rel="noopener noreferrer"
            className="h-14 w-14 shrink-0 rounded-2xl bg-blue-500 shadow-lg flex items-center justify-center active:scale-95 transition-transform"
            aria-label="Navegar con Google Maps"
          >
            <span className="text-xl">🧭</span>
          </a>
        </div>
      </div>
    </div>
  );
}
