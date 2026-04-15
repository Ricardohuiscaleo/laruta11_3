'use client';

import { useState, useCallback, useRef } from 'react';
import {
  APIProvider,
  Map,
  AdvancedMarker,
  InfoWindow,
  useMap,
  useMapsLibrary,
} from '@vis.gl/react-google-maps';
import type { DeliveryOrder, DeliveryRider } from '@/hooks/useDeliveryTracking';

const ARICA = { lat: -18.4783, lng: -70.3126 };

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

// Directions renderer — only shown when a rider has a position and an assigned order
function DirectionsLayer({ order, rider }: { order: DeliveryOrder; rider: DeliveryRider }) {
  const map = useMap();
  const routesLib = useMapsLibrary('routes');

  const [directionsResult, setDirectionsResult] = useState<google.maps.DirectionsResult | null>(null);
  const [renderer] = useState(() => {
    if (typeof window === 'undefined' || !routesLib) return null;
    return new routesLib.DirectionsRenderer({ suppressMarkers: true });
  });

  // Attach renderer to map
  if (renderer && map && !renderer.getMap()) {
    renderer.setMap(map);
  }

  // Request directions
  if (routesLib && rider.last_lat && rider.last_lng && !directionsResult) {
    const lat = toNum(rider.last_lat);
    const lng = toNum(rider.last_lng);
    if (lat && lng) {
      const service = new routesLib.DirectionsService();
      service.route(
        {
          origin: { lat, lng },
          destination: order.delivery_address,
          travelMode: google.maps.TravelMode.DRIVING,
        },
        (result, status) => {
          if (status === 'OK' && result) {
            setDirectionsResult(result);
            renderer?.setDirections(result);
          }
        }
      );
    }
  }

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
        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          {/* Car body */}
          <path d="M5 17h14v-5l-2-4H7L5 12v5z" fill={busy ? '#F97316' : '#22C55E'} stroke="#fff" strokeWidth="1"/>
          {/* Windshield */}
          <path d="M7.5 8L6 12h12l-1.5-4H7.5z" fill={busy ? '#FB923C' : '#4ADE80'} stroke="#fff" strokeWidth="0.5"/>
          {/* Wheels */}
          <circle cx="7.5" cy="17" r="1.5" fill="#333" stroke="#fff" strokeWidth="0.5"/>
          <circle cx="16.5" cy="17" r="1.5" fill="#333" stroke="#fff" strokeWidth="0.5"/>
          {/* Arrow indicator (direction) */}
          <path d="M12 2L14 6H10L12 2Z" fill={busy ? '#F97316' : '#22C55E'} stroke="#fff" strokeWidth="0.5"/>
        </svg>
      </div>
      <div className={`mt-0.5 text-[8px] font-bold px-1.5 py-0.5 rounded-full shadow whitespace-nowrap text-white ${busy ? 'bg-orange-500' : 'bg-green-500'}`}>
        {name.split(' ')[0]}
      </div>
    </div>
  );
}

function MapContent({
  orders,
  riders,
  onAssignRider,
}: DeliveryMapProps) {
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

  // Pairs of (order, rider) that have a route to draw
  const routePairs = orders
    .filter((o) => o.rider_id && o.rider_last_lat && o.rider_last_lng)
    .map((o) => ({
      order: o,
      rider: riders.find((r) => r.id === o.rider_id),
    }))
    .filter((p): p is { order: DeliveryOrder; rider: DeliveryRider } => !!p.rider);

  return (
    <>
      {/* Food truck marker — La Ruta 11 */}
      <AdvancedMarker position={ARICA} zIndex={1000}>
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

      {/* Order markers */}
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
            >
              📦
            </div>
          </AdvancedMarker>
        );
      })}

      {/* Rider markers — car icon that rotates based on direction */}
      {riders.map((rider) => {
        const lat = toNum(rider.last_lat);
        const lng = toNum(rider.last_lng);
        if (!lat || !lng) return null;
        const isBusy = orders.some((o) => o.rider_id === rider.id);

        // Calculate heading from previous position
        const prev = prevPositions.current[rider.id];
        let heading = 0;
        if (prev && (prev.lat !== lat || prev.lng !== lng)) {
          heading = calcHeading(prev, { lat, lng });
        }
        prevPositions.current[rider.id] = { lat, lng };

        return (
          <AdvancedMarker
            key={`rider-${rider.id}`}
            position={{ lat, lng }}
          >
            <DeliveryCar heading={heading} busy={isBusy} name={rider.nombre} />
          </AdvancedMarker>
        );
      })}

      {/* InfoWindow for selected order */}
      {selectedOrder && (
        <InfoWindow
          position={(() => {
            const lat = toNum(selectedOrder.rider_last_lat);
            const lng = toNum(selectedOrder.rider_last_lng);
            return lat && lng ? { lat, lng } : ARICA;
          })()}
          onCloseClick={() => setSelectedOrder(null)}
        >
          <div className="min-w-[200px] space-y-2 p-1 text-sm">
            <p className="font-semibold text-gray-900">#{selectedOrder.order_number}</p>
            <p className="text-gray-600">{selectedOrder.customer_name}</p>
            <p className="text-gray-500 text-xs">{selectedOrder.delivery_address}</p>
            <p className="text-xs">
              Estado:{' '}
              <span className="font-medium capitalize">
                {selectedOrder.order_status.replace(/_/g, ' ')}
              </span>
            </p>
            {selectedOrder.rider_nombre ? (
              <p className="text-xs text-gray-500">Rider: {selectedOrder.rider_nombre}</p>
            ) : (
              <div className="space-y-1 pt-1">
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

      {/* Directions */}
      {routePairs.map(({ order, rider }) => (
        <DirectionsLayer key={`route-${order.id}`} order={order} rider={rider} />
      ))}
    </>
  );
}

export default function DeliveryMap({ orders, riders, onAssignRider }: DeliveryMapProps) {
  return (
    <APIProvider apiKey={process.env.NEXT_PUBLIC_GOOGLE_MAPS_KEY ?? ''}>
      <Map
        defaultCenter={ARICA}
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
