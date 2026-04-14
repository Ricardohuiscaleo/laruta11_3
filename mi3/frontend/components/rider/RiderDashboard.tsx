'use client';

import { useState, useEffect, useCallback } from 'react';
import { APIProvider, Map, useMap, useMapsLibrary } from '@vis.gl/react-google-maps';
import { apiFetch } from '@/lib/api';
import type { GeoPosition } from '@/hooks/useRiderGPS';

const SANTIAGO = { lat: -33.4489, lng: -70.6693 };

interface DeliveryAssignment {
  id: number;
  order_id: number;
  order_number?: string;
  customer_name?: string;
  delivery_address?: string;
  order_total?: number;
  status: 'assigned' | 'picked_up' | 'delivered' | 'cancelled';
  order?: {
    order_number: string;
    customer_name: string;
    delivery_address: string;
    delivery_fee: number;
  };
}

interface RiderDashboardProps {
  position: GeoPosition | null;
  isActive: boolean;
  gpsError: string | null;
  toggleDeliveryMode: () => void;
}

function RouteLayer({ origin, destination }: { origin: GeoPosition; destination: string }) {
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
      }
    );
    return () => { renderer.setMap(null); };
  }, [map, routesLib, origin.lat, origin.lng, destination]);

  return null;
}

export default function RiderDashboard({ position, isActive, gpsError, toggleDeliveryMode }: RiderDashboardProps) {
  const [assignment, setAssignment] = useState<DeliveryAssignment | null>(null);
  const [loadingAssignment, setLoadingAssignment] = useState(true);
  const [updatingStatus, setUpdatingStatus] = useState(false);
  const [statusError, setStatusError] = useState<string | null>(null);

  useEffect(() => {
    apiFetch<{ success: boolean; assignment: DeliveryAssignment | null }>('/rider/current-assignment')
      .then((res) => { if (res.assignment) setAssignment(res.assignment); })
      .catch(() => {})
      .finally(() => setLoadingAssignment(false));
  }, []);

  const updateStatus = useCallback(async (newStatus: 'picked_up' | 'delivered') => {
    setUpdatingStatus(true);
    setStatusError(null);
    try {
      const res = await apiFetch<{ success: boolean; assignment: DeliveryAssignment }>(
        '/rider/current-assignment/status',
        { method: 'PATCH', body: JSON.stringify({ status: newStatus }) }
      );
      if (res.success && res.assignment) setAssignment(res.assignment);
    } catch (err: unknown) {
      setStatusError(err instanceof Error ? err.message : 'Error al actualizar estado');
    } finally {
      setUpdatingStatus(false);
    }
  }, []);

  const formatCLP = (amount: number) =>
    new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(amount);

  const orderNumber = assignment?.order?.order_number ?? assignment?.order_number;
  const customerName = assignment?.order?.customer_name ?? assignment?.customer_name;
  const deliveryAddress = assignment?.order?.delivery_address ?? assignment?.delivery_address;
  const orderTotal = assignment?.order?.delivery_fee ?? assignment?.order_total;

  return (
    <div className="flex flex-col gap-4 min-h-screen bg-gray-50 p-4 max-w-lg mx-auto">
      {/* GPS error banner */}
      {gpsError && (
        <div className="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
          <p className="font-semibold">GPS no disponible</p>
          <p className="mt-0.5">{gpsError}. Ve a Ajustes y habilita la ubicación para esta app.</p>
        </div>
      )}

      {/* Delivery mode toggle */}
      <div className="rounded-2xl bg-white border shadow-sm p-5">
        <div className="flex items-center justify-between">
          <div>
            <p className="text-base font-semibold text-gray-900">Modo Delivery</p>
            <p className="text-sm text-gray-500 mt-0.5">
              {isActive ? 'Activo — compartiendo ubicación' : 'Inactivo'}
            </p>
          </div>
          <button
            onClick={toggleDeliveryMode}
            aria-pressed={isActive}
            className={`relative inline-flex h-14 w-24 items-center justify-center rounded-2xl text-sm font-bold transition-all shadow-sm active:scale-95 ${
              isActive ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600'
            }`}
          >
            <span
              className={`absolute left-1.5 top-1.5 h-11 w-11 rounded-xl bg-white shadow transition-transform ${
                isActive ? 'translate-x-10' : 'translate-x-0'
              }`}
            />
            <span className="relative z-10">{isActive ? 'ON' : 'OFF'}</span>
          </button>
        </div>
        {isActive && position && (
          <div className="mt-3 flex items-center gap-2 text-xs text-green-600">
            <span className="inline-block h-2 w-2 rounded-full bg-green-500 animate-pulse" />
            GPS activo — {position.lat.toFixed(5)}, {position.lng.toFixed(5)}
          </div>
        )}
      </div>

      {/* Assignment section */}
      {loadingAssignment ? (
        <div className="rounded-2xl bg-white border shadow-sm p-5 text-center text-sm text-gray-400">
          Cargando pedido...
        </div>
      ) : assignment && assignment.status !== 'delivered' && assignment.status !== 'cancelled' ? (
        <>
          <div className="rounded-2xl bg-white border shadow-sm p-5 space-y-3">
            <div className="flex items-center justify-between">
              <p className="text-xs font-medium text-gray-500 uppercase tracking-wide">Pedido asignado</p>
              <span className="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-700">
                #{orderNumber}
              </span>
            </div>
            <div className="space-y-1.5">
              <div>
                <p className="text-xs text-gray-400">Cliente</p>
                <p className="text-sm font-semibold text-gray-900">{customerName}</p>
              </div>
              <div>
                <p className="text-xs text-gray-400">Dirección</p>
                <p className="text-sm text-gray-800">{deliveryAddress}</p>
              </div>
              {orderTotal !== undefined && (
                <div>
                  <p className="text-xs text-gray-400">Delivery fee</p>
                  <p className="text-sm font-bold text-gray-900">{formatCLP(orderTotal)}</p>
                </div>
              )}
            </div>
            {statusError && (
              <p className="text-xs text-red-600 bg-red-50 rounded-lg px-3 py-2">{statusError}</p>
            )}
            <div className="pt-1 space-y-2">
              {assignment.status === 'assigned' && (
                <button
                  onClick={() => updateStatus('picked_up')}
                  disabled={updatingStatus}
                  className="w-full rounded-xl bg-amber-500 py-3 text-sm font-bold text-white shadow-sm hover:bg-amber-600 active:scale-[0.98] disabled:opacity-50 transition-all"
                >
                  {updatingStatus ? 'Actualizando...' : '✅ Marcar como Recogido'}
                </button>
              )}
              {assignment.status === 'picked_up' && (
                <button
                  onClick={() => updateStatus('delivered')}
                  disabled={updatingStatus}
                  className="w-full rounded-xl bg-green-500 py-3 text-sm font-bold text-white shadow-sm hover:bg-green-600 active:scale-[0.98] disabled:opacity-50 transition-all"
                >
                  {updatingStatus ? 'Actualizando...' : '🏁 Marcar como Entregado'}
                </button>
              )}
            </div>
          </div>

          {position && deliveryAddress && (
            <div className="h-56 rounded-2xl overflow-hidden shadow-sm border">
              <APIProvider apiKey={process.env.NEXT_PUBLIC_GOOGLE_MAPS_KEY ?? ''}>
                <Map
                  defaultCenter={position}
                  defaultZoom={14}
                  mapId="rider-map"
                  className="h-full w-full"
                  gestureHandling="greedy"
                  disableDefaultUI
                >
                  <RouteLayer origin={position} destination={deliveryAddress} />
                </Map>
              </APIProvider>
            </div>
          )}
        </>
      ) : (
        <div className="rounded-2xl bg-white border shadow-sm p-8 text-center space-y-2">
          <p className="text-3xl">🛵</p>
          <p className="text-sm font-medium text-gray-700">Sin pedido asignado</p>
          <p className="text-xs text-gray-400">Espera la asignación del administrador.</p>
        </div>
      )}
    </div>
  );
}
