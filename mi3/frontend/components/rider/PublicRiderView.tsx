'use client';

import { useState, useEffect, useCallback } from 'react';
import { APIProvider, Map, useMap, useMapsLibrary } from '@vis.gl/react-google-maps';
import { usePublicRiderGPS } from '@/hooks/usePublicRiderGPS';
import type { GeoPosition } from '@/hooks/usePublicRiderGPS';

const API_URL = process.env.NEXT_PUBLIC_API_URL || 'https://api-mi3.laruta11.cl';

interface OrderItem {
  product_name: string;
  quantity: number;
  product_price: number;
}

interface PublicOrderData {
  id: number;
  order_number: string;
  order_status: string;
  customer_name: string;
  customer_phone: string;
  delivery_address: string;
  delivery_fee: number;
  card_surcharge: number;
  subtotal: number;
  product_price: number;
  payment_method: string;
  delivery_distance_km: number | null;
  delivery_duration_min: number | null;
  rider_last_lat: number | null;
  rider_last_lng: number | null;
  items: OrderItem[];
  food_truck: { latitud: number; longitud: number } | null;
}

const formatCLP = (amount: number) =>
  new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(amount);

const paymentLabel: Record<string, string> = {
  cash: 'Efectivo',
  card: 'Tarjeta',
  transfer: 'Transferencia',
};

function RouteLayer({ origin, destination }: { origin: { lat: number; lng: number }; destination: string }) {
  const map = useMap();
  const routesLib = useMapsLibrary('routes');

  useEffect(() => {
    if (!map || !routesLib) return;
    const renderer = new routesLib.DirectionsRenderer({ suppressMarkers: false });
    renderer.setMap(map);
    const service = new routesLib.DirectionsService();
    service.route(
      {
        origin: { lat: origin.lat, lng: origin.lng },
        destination,
        travelMode: google.maps.TravelMode.DRIVING,
      },
      (result, status) => {
        if (status === 'OK' && result) renderer.setDirections(result);
      },
    );
    return () => {
      renderer.setMap(null);
    };
  }, [map, routesLib, origin.lat, origin.lng, destination]);

  return null;
}

export default function PublicRiderView({ orderId }: { orderId: string }) {
  const [order, setOrder] = useState<PublicOrderData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [updating, setUpdating] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);

  const gpsEnabled = order?.order_status === 'out_for_delivery';
  const { position, gpsError } = usePublicRiderGPS({ orderId, enabled: gpsEnabled });

  // Fetch order data on mount
  useEffect(() => {
    async function loadOrder() {
      try {
        const res = await fetch(`${API_URL}/api/v1/public/rider-orders/${orderId}`, {
          headers: { Accept: 'application/json' },
        });
        if (!res.ok) {
          setError(res.status === 404 ? 'Pedido no encontrado' : 'Error al cargar el pedido');
          return;
        }
        const data = await res.json();
        if (data.success && data.order) setOrder(data.order);
        else setError('Pedido no encontrado');
      } catch {
        setError('Error de conexión');
      } finally {
        setLoading(false);
      }
    }
    loadOrder();
  }, [orderId]);

  const updateStatus = useCallback(
    async (newStatus: 'out_for_delivery' | 'delivered') => {
      setUpdating(true);
      setActionError(null);
      try {
        const res = await fetch(`${API_URL}/api/v1/public/rider-orders/${orderId}/status`, {
          method: 'PATCH',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          body: JSON.stringify({ status: newStatus }),
        });
        if (!res.ok) {
          const body = await res.json().catch(() => ({}));
          setActionError(body.error || 'Error al actualizar estado');
          return;
        }
        setOrder((prev) => (prev ? { ...prev, order_status: newStatus } : prev));
      } catch {
        setActionError('Error de conexión');
      } finally {
        setUpdating(false);
      }
    },
    [orderId],
  );

  // Loading state
  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-screen bg-gray-50">
        <div className="text-center space-y-3">
          <div className="h-10 w-10 mx-auto border-4 border-gray-300 border-t-blue-500 rounded-full animate-spin" role="status" aria-label="Cargando" />
          <p className="text-sm text-gray-500">Cargando pedido...</p>
        </div>
      </div>
    );
  }

  // Error state
  if (error || !order) {
    return (
      <div className="flex items-center justify-center min-h-screen bg-gray-50 p-4">
        <div className="text-center space-y-3 max-w-sm">
          <p className="text-4xl">🔍</p>
          <p className="text-lg font-semibold text-gray-800">Pedido no encontrado</p>
          <p className="text-sm text-gray-500">{error || 'El pedido no existe o no es un delivery.'}</p>
        </div>
      </div>
    );
  }

  // Delivered confirmation
  if (order.order_status === 'delivered') {
    return (
      <div className="flex items-center justify-center min-h-screen bg-gray-50 p-4">
        <div className="text-center space-y-4 max-w-sm">
          <div className="h-20 w-20 mx-auto rounded-full bg-green-100 flex items-center justify-center">
            <span className="text-4xl">✅</span>
          </div>
          <p className="text-xl font-bold text-gray-900">¡Pedido entregado!</p>
          <p className="text-sm text-gray-500">
            Pedido #{order.order_number} entregado a {order.customer_name}.
          </p>
        </div>
      </div>
    );
  }

  const mapsOrigin = order.food_truck
    ? { lat: Number(order.food_truck.latitud), lng: Number(order.food_truck.longitud) }
    : null;

  return (
    <div className="flex flex-col gap-4 min-h-screen bg-gray-50 p-4 max-w-lg mx-auto pb-8">
      {/* Order number badge */}
      <div className="flex items-center justify-between">
        <span className="rounded-full bg-blue-100 px-3 py-1 text-sm font-bold text-blue-700">
          Pedido #{order.order_number}
        </span>
        <span className="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600 capitalize">
          {paymentLabel[order.payment_method] || order.payment_method}
        </span>
      </div>

      {/* GPS error banner */}
      {gpsError && (
        <div className="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-700" role="alert">
          <p className="font-semibold">GPS no disponible</p>
          <p className="mt-0.5 text-xs">{gpsError}</p>
        </div>
      )}

      {/* Customer info */}
      <div className="rounded-2xl bg-white border shadow-sm p-4 space-y-2">
        <p className="text-xs font-medium text-gray-400 uppercase tracking-wide">Cliente</p>
        <p className="text-base font-semibold text-gray-900">{order.customer_name}</p>
        {order.customer_phone && (
          <a
            href={`tel:${order.customer_phone}`}
            className="inline-flex items-center gap-1.5 text-sm text-blue-600 font-medium"
            aria-label={`Llamar a ${order.customer_name}`}
          >
            📞 {order.customer_phone}
          </a>
        )}
      </div>

      {/* Products */}
      <div className="rounded-2xl bg-white border shadow-sm p-4 space-y-3">
        <p className="text-xs font-medium text-gray-400 uppercase tracking-wide">Productos</p>
        <ul className="divide-y divide-gray-100">
          {order.items.map((item, i) => (
            <li key={i} className="flex items-center justify-between py-2 text-sm">
              <div>
                <span className="font-medium text-gray-900">{item.product_name}</span>
                <span className="ml-1.5 text-gray-400">×{item.quantity}</span>
              </div>
              <span className="text-gray-700 font-medium">{formatCLP(item.product_price)}</span>
            </li>
          ))}
        </ul>
      </div>

      {/* Amounts */}
      <div className="rounded-2xl bg-white border shadow-sm p-4 space-y-2">
        <p className="text-xs font-medium text-gray-400 uppercase tracking-wide">Montos</p>
        <div className="flex justify-between text-sm text-gray-600">
          <span>Subtotal</span>
          <span>{formatCLP(order.subtotal)}</span>
        </div>
        <div className="flex justify-between text-sm text-gray-600">
          <span>Delivery</span>
          <span>{formatCLP(order.delivery_fee)}</span>
        </div>
        {order.card_surcharge > 0 && (
          <div className="flex justify-between text-sm text-gray-600">
            <span>Recargo tarjeta</span>
            <span>{formatCLP(order.card_surcharge)}</span>
          </div>
        )}
        <div className="flex justify-between text-sm font-bold text-gray-900 pt-1 border-t border-gray-100">
          <span>Total</span>
          <span>{formatCLP(order.product_price)}</span>
        </div>
      </div>

      {/* Delivery address */}
      <div className="rounded-2xl bg-white border shadow-sm p-4 space-y-2">
        <p className="text-xs font-medium text-gray-400 uppercase tracking-wide">Dirección de entrega</p>
        <a
          href={`https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(order.delivery_address)}`}
          target="_blank"
          rel="noopener noreferrer"
          className="text-sm text-blue-600 font-medium underline underline-offset-2"
          aria-label="Abrir dirección en Google Maps"
        >
          📍 {order.delivery_address}
        </a>
        {(order.delivery_distance_km != null || order.delivery_duration_min != null) && (
          <div className="flex gap-4 text-xs text-gray-500 pt-1">
            {order.delivery_distance_km != null && <span>📏 {order.delivery_distance_km} km</span>}
            {order.delivery_duration_min != null && <span>⏱️ {order.delivery_duration_min} min</span>}
          </div>
        )}
      </div>

      {/* Map */}
      {mapsOrigin && order.delivery_address && (
        <div className="h-56 rounded-2xl overflow-hidden shadow-sm border">
          <APIProvider apiKey={process.env.NEXT_PUBLIC_GOOGLE_MAPS_KEY ?? ''}>
            <Map
              defaultCenter={mapsOrigin}
              defaultZoom={13}
              mapId="d51ca892b68e9c5e5e2dd701"
              className="h-full w-full"
              gestureHandling="greedy"
              disableDefaultUI
            >
              <RouteLayer origin={mapsOrigin} destination={order.delivery_address} />
            </Map>
          </APIProvider>
        </div>
      )}

      {/* GPS active indicator */}
      {gpsEnabled && position && (
        <div className="flex items-center gap-2 text-xs text-green-600 px-1">
          <span className="inline-block h-2 w-2 rounded-full bg-green-500 animate-pulse" />
          GPS activo — compartiendo ubicación
        </div>
      )}

      {/* Action error */}
      {actionError && (
        <p className="text-xs text-red-600 bg-red-50 rounded-lg px-3 py-2" role="alert">{actionError}</p>
      )}

      {/* Action buttons */}
      {(order.order_status === 'ready' || order.order_status === 'preparing') && (
        <button
          onClick={() => updateStatus('out_for_delivery')}
          disabled={updating}
          className="w-full rounded-xl bg-amber-500 py-4 text-base font-bold text-white shadow-sm hover:bg-amber-600 active:scale-[0.98] disabled:opacity-50 transition-all"
          aria-label="Marcar pedido en camino"
        >
          {updating ? 'Actualizando...' : '🛵 En camino'}
        </button>
      )}

      {order.order_status === 'out_for_delivery' && (
        <button
          onClick={() => updateStatus('delivered')}
          disabled={updating}
          className="w-full rounded-xl bg-green-500 py-4 text-base font-bold text-white shadow-sm hover:bg-green-600 active:scale-[0.98] disabled:opacity-50 transition-all"
          aria-label="Marcar pedido como entregado"
        >
          {updating ? 'Actualizando...' : '✅ Entregado'}
        </button>
      )}
    </div>
  );
}
