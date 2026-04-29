'use client';

import { useState, useCallback, useRef, useEffect } from 'react';
import {
  APIProvider,
  Map,
  AdvancedMarker,
  InfoWindow,
  useMap,
  useMapsLibrary,
} from '@vis.gl/react-google-maps';
import type { DeliveryOrder, DeliveryRider } from '@/hooks/useDeliveryTracking';

const RUTA11 = { lat: -18.47141320, lng: -70.28881320 }; // Yumbel 2629, Arica

const STATUS_PIN_COLORS: Record<string, string> = {
  preparing: '#EAB308',      // yellow
  sent_to_kitchen: '#EAB308',
  ready: '#22C55E',          // green
  out_for_delivery: '#3B82F6', // blue
};

interface DeliveryMapProps {
  orders: DeliveryOrder[];
  riders: DeliveryRider[];
  onAssignRider: (orderId: number, riderId: number) => void;
}

/** Safely parse a lat/lng value that may be string or number */
function toNum(v: unknown): number | null {
  if (v == null) return null;
  const n = Number(v);
  return Number.isFinite(n) ? n : null;
}

/** Calculate bearing between two points in degrees (0=north, 90=east) */
function calcHeading(from: { lat: number; lng: number }, to: { lat: number; lng: number }): number {
  const dLng = (to.lng - from.lng) * Math.PI / 180;
  const lat1 = from.lat * Math.PI / 180;
  const lat2 = to.lat * Math.PI / 180;
  const y = Math.sin(dLng) * Math.cos(lat2);
  const x = Math.cos(lat1) * Math.sin(lat2) - Math.sin(lat1) * Math.cos(lat2) * Math.cos(dLng);
  return ((Math.atan2(y, x) * 180 / Math.PI) + 360) % 360;
}

/** Geocode an address and cache the result */
const geocodeCacheStore: Record<string, { lat: number; lng: number }> = {};

function useGeocode(address: string | null) {
  const [pos, setPos] = useState<{ lat: number; lng: number } | null>(null);
  const map = useMap();

  useEffect(() => {
    if (!map || !address) return;
    if (geocodeCacheStore[address]) {
      setPos(geocodeCacheStore[address]);
      return;
    }
    const geocoder = new google.maps.Geocoder();
    geocoder.geocode({ address }, (results, status) => {
      if (status === 'OK' && results?.[0]) {
        const loc = results[0].geometry.location;
        const p = { lat: loc.lat(), lng: loc.lng() };
        geocodeCacheStore[address] = p;
        setPos(p);
      }
    });
  }, [map, address]);

  return pos;
}

/** Destination pin marker for an order */
function DestinationPin({ address, orderNumber, status }: {
  address: string; orderNumber: string; status: string;
}) {
  const pos = useGeocode(address);
  if (!pos) return null;

  const isOnRoute = status === 'out_for_delivery';
  return (
    <AdvancedMarker position={pos} zIndex={50}>
      <div className="flex flex-col items-center">
        <div className={`h-7 w-7 rounded-full border-2 border-white shadow-lg flex items-center justify-center text-white text-xs font-bold ${isOnRoute ? 'bg-blue-500 animate-pulse' : 'bg-red-500'}`}>
          📍
        </div>
        <span className={`text-[7px] font-bold text-white px-1 py-0.5 rounded-full mt-0.5 shadow max-w-[80px] truncate ${isOnRoute ? 'bg-blue-600' : 'bg-red-600'}`}>
          #{orderNumber.replace(/^R11-/, '').slice(-4)}
        </span>
      </div>
    </AdvancedMarker>
  );
}

/** Route line from rider to destination — updates in real time */
function RiderRoute({ riderLat, riderLng, destination, routeKey }: {
  riderLat: number; riderLng: number; destination: string; routeKey: string;
}) {
  const map = useMap();
  const routesLib = useMapsLibrary('routes');
  const rendererRef = useRef<google.maps.DirectionsRenderer | null>(null);
  const lastKeyRef = useRef('');

  useEffect(() => {
    if (!map || !routesLib) return;

    // Throttle: only re-route when key changes significantly
    const roundedKey = `${Math.round(riderLat * 500)}-${Math.round(riderLng * 500)}-${destination}`;
    if (lastKeyRef.current === roundedKey) return;
    lastKeyRef.current = roundedKey;

    if (!rendererRef.current) {
      rendererRef.current = new routesLib.DirectionsRenderer({
        suppressMarkers: true,
        polylineOptions: { strokeColor: '#4285F4', strokeWeight: 4, strokeOpacity: 0.7 },
      });
      rendererRef.current.setMap(map);
    }

    new routesLib.DirectionsService().route(
      {
        origin: { lat: riderLat, lng: riderLng },
        destination,
        travelMode: google.maps.TravelMode.DRIVING,
      },
      (result, status) => {
        if (status === 'OK' && result && rendererRef.current) {
          rendererRef.current.setDirections(result);
        }
      },
    );

    return () => {
      if (rendererRef.current) {
        rendererRef.current.setMap(null);
        rendererRef.current = null;
      }
    };
  }, [map, routesLib, riderLat, riderLng, destination, routeKey]);

  return null;
}

/** SVG delivery car that rotates based on heading */
function DeliveryCar({ heading, busy, name }: { heading: number; busy: boolean; name: string }) {
  return (
    <div className="flex flex-col items-center" title={`${name} — ${busy ? 'En ruta' : 'Disponible'}`}>
      <div
        className="transition-transform duration-700 ease-linear"
        style={{ transform: `rotate(${heading}deg)` }}
      >
        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label={`Rider ${name}`}>
          <path d="M5 17h14v-5l-2-4H7L5 12v5z" fill={busy ? '#F97316' : '#22C55E'} stroke="#fff" strokeWidth="1"/>
          <path d="M7.5 8L6 12h12l-1.5-4H7.5z" fill={busy ? '#FB923C' : '#4ADE80'} stroke="#fff" strokeWidth="0.5"/>
          <circle cx="7.5" cy="17" r="1.5" fill="#333" stroke="#fff" strokeWidth="0.5"/>
          <circle cx="16.5" cy="17" r="1.5" fill="#333" stroke="#fff" strokeWidth="0.5"/>
          <path d="M12 2L14 6H10L12 2Z" fill={busy ? '#F97316' : '#22C55E'} stroke="#fff" strokeWidth="0.5"/>
        </svg>
      </div>
      <div className={`mt-0.5 text-[8px] font-bold px-1.5 py-0.5 rounded-full shadow whitespace-nowrap text-white ${busy ? 'bg-orange-500' : 'bg-green-500'}`}>
        {name.split(' ')[0]}
      </div>
    </div>
  );
}

const fmt = (n: number) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(n);

function MapContent({ orders, riders, onAssignRider }: DeliveryMapProps) {
  const [selectedOrder, setSelectedOrder] = useState<DeliveryOrder | null>(null);
  const [assigningRider, setAssigningRider] = useState<number | null>(null);
  const prevPositions = useRef<Record<number, { lat: number; lng: number }>>({});

  const availableRiders = riders.filter((r) => r.last_lat !== null && !orders.some((o) => o.rider_id === r.id));

  const handleAssign = useCallback(
    async (orderId: number, riderId: number) => {
      setAssigningRider(riderId);
      try {
        await onAssignRider(orderId, riderId);
        setSelectedOrder(null);
      } finally {
        setAssigningRider(null);
      }
    },
    [onAssignRider]
  );

  return (
    <>
      {/* Food truck marker — La Ruta 11 */}
      <AdvancedMarker position={RUTA11} zIndex={1000}>
        <div className="flex flex-col items-center">
          <img
            src="https://laruta11-images.s3.amazonaws.com/menu/logo-optimized.png"
            alt="La Ruta 11"
            className="h-10 w-10 rounded-full border-2 border-red-500 shadow-lg bg-white"
          />
          <div className="mt-0.5 bg-red-600 text-white text-[8px] font-bold px-1.5 py-0.5 rounded-full shadow whitespace-nowrap">
            La Ruta 11
          </div>
        </div>
      </AdvancedMarker>

      {/* Destination pins for ALL delivery orders */}
      {orders.map((order) => (
        <DestinationPin
          key={`dest-${order.id}`}
          address={order.delivery_address}
          orderNumber={order.order_number}
          status={order.order_status}
        />
      ))}

      {/* Route lines: rider → destination (only for out_for_delivery with GPS) */}
      {orders
        .filter((o) => o.order_status === 'out_for_delivery' && o.rider_last_lat && o.rider_last_lng)
        .map((o) => {
          const lat = toNum(o.rider_last_lat);
          const lng = toNum(o.rider_last_lng);
          if (!lat || !lng) return null;
          return (
            <RiderRoute
              key={`route-${o.id}`}
              riderLat={lat}
              riderLng={lng}
              destination={o.delivery_address}
              routeKey={`${o.id}-${Math.round(lat * 500)}-${Math.round(lng * 500)}`}
            />
          );
        })}

      {/* Rider markers — car icon that rotates based on direction */}
      {riders.map((rider) => {
        const lat = toNum(rider.last_lat);
        const lng = toNum(rider.last_lng);
        if (!lat || !lng) return null;
        const isBusy = orders.some((o) => o.rider_id === rider.id);

        const prev = prevPositions.current[rider.id];
        let heading = 0;
        if (prev && (prev.lat !== lat || prev.lng !== lng)) {
          heading = calcHeading(prev, { lat, lng });
        }
        prevPositions.current[rider.id] = { lat, lng };

        return (
          <AdvancedMarker key={`rider-${rider.id}`} position={{ lat, lng }}>
            <DeliveryCar heading={heading} busy={isBusy} name={rider.nombre} />
          </AdvancedMarker>
        );
      })}

      {/* Order markers — only for orders with rider GPS (rider position) */}
      {orders.map((order) => {
        const lat = toNum(order.rider_last_lat);
        const lng = toNum(order.rider_last_lng);
        if (!lat || !lng) return null;
        const pinColor = STATUS_PIN_COLORS[order.order_status] ?? '#6B7280';

        return (
          <AdvancedMarker
            key={`order-${order.id}`}
            position={{ lat, lng }}
            onClick={() => setSelectedOrder(order)}
          >
            <div
              className="flex h-8 w-8 items-center justify-center rounded-full border-2 border-white shadow-md text-white text-xs font-bold"
              style={{ backgroundColor: pinColor }}
              title={`#${order.order_number}`}
              role="button"
              aria-label={`Pedido ${order.order_number} — ${order.customer_name}`}
            >
              📦
            </div>
          </AdvancedMarker>
        );
      })}

      {/* InfoWindow for selected order */}
      {selectedOrder && (
        <InfoWindow
          position={(() => {
            const lat = toNum(selectedOrder.rider_last_lat);
            const lng = toNum(selectedOrder.rider_last_lng);
            return lat && lng ? { lat, lng } : RUTA11;
          })()}
          onCloseClick={() => setSelectedOrder(null)}
        >
          <div className="min-w-[220px] space-y-2 p-1 text-sm">
            <div className="flex items-center justify-between gap-2">
              <p className="font-semibold text-gray-900">#{selectedOrder.order_number}</p>
              <span className="text-xs rounded-full px-2 py-0.5 font-medium"
                style={{ backgroundColor: `${STATUS_PIN_COLORS[selectedOrder.order_status] ?? '#6B7280'}20`, color: STATUS_PIN_COLORS[selectedOrder.order_status] ?? '#6B7280' }}>
                {selectedOrder.order_status.replace(/_/g, ' ')}
              </span>
            </div>
            <p className="text-gray-600">{selectedOrder.customer_name}</p>
            <p className="text-gray-500 text-xs">📍 {selectedOrder.delivery_address}</p>
            {selectedOrder.delivery_distance_km != null && (
              <p className="text-xs text-gray-500">
                📏 {selectedOrder.delivery_distance_km} km
                {selectedOrder.delivery_duration_min != null && ` · ⏱️ ${selectedOrder.delivery_duration_min} min`}
              </p>
            )}
            {selectedOrder.product_price != null && (
              <p className="text-xs font-semibold text-green-700">Total: {fmt(selectedOrder.product_price)}</p>
            )}
            {selectedOrder.rider_nombre ? (
              <p className="text-xs text-blue-600 font-medium">🛵 {selectedOrder.rider_nombre}</p>
            ) : (
              <div className="space-y-1 pt-1 border-t border-gray-100">
                <p className="text-xs font-medium text-gray-700">Asignar rider:</p>
                {availableRiders.length === 0 ? (
                  <p className="text-xs text-gray-400">Sin riders disponibles</p>
                ) : (
                  availableRiders.map((rider) => (
                    <button
                      key={rider.id}
                      onClick={() => handleAssign(selectedOrder.id, rider.id)}
                      disabled={assigningRider !== null}
                      className="block w-full rounded border border-blue-300 bg-blue-50 px-2 py-1 text-left text-xs text-blue-700 hover:bg-blue-100 disabled:opacity-50"
                    >
                      {assigningRider === rider.id ? 'Asignando...' : rider.nombre}
                    </button>
                  ))
                )}
              </div>
            )}
          </div>
        </InfoWindow>
      )}
    </>
  );
}

export default function DeliveryMap({ orders, riders, onAssignRider }: DeliveryMapProps) {
  return (
    <APIProvider apiKey={process.env.NEXT_PUBLIC_GOOGLE_MAPS_KEY ?? ''}>
      <Map
        defaultCenter={RUTA11}
        defaultZoom={13}
        mapId="d51ca892b68e9c5e5e2dd701"
        className="h-full w-full rounded-xl"
        gestureHandling="greedy"
        disableDefaultUI={false}
      >
        <MapContent orders={orders} riders={riders} onAssignRider={onAssignRider} />
      </Map>
    </APIProvider>
  );
}
