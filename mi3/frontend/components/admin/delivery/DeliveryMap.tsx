'use client';

import { useState, useCallback } from 'react';
import {
  APIProvider,
  Map,
  AdvancedMarker,
  InfoWindow,
  useMap,
  useMapsLibrary,
} from '@vis.gl/react-google-maps';
import type { DeliveryOrder, DeliveryRider } from '@/hooks/useDeliveryTracking';

const SANTIAGO = { lat: -33.4489, lng: -70.6693 };

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
    const service = new routesLib.DirectionsService();
    service.route(
      {
        origin: { lat: rider.last_lat, lng: rider.last_lng },
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

  return null;
}

function MapContent({
  orders,
  riders,
  onAssignRider,
}: DeliveryMapProps) {
  const [selectedOrder, setSelectedOrder] = useState<DeliveryOrder | null>(null);
  const [assigningRider, setAssigningRider] = useState<number | null>(null);

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
      {/* Order markers */}
      {orders.map((order) => {
        if (!order.rider_last_lat && !order.rider_last_lng) return null;
        const pinColor = STATUS_PIN_COLORS[order.order_status] ?? '#6B7280';

        return (
          <AdvancedMarker
            key={`order-${order.id}`}
            position={{ lat: order.rider_last_lat!, lng: order.rider_last_lng! }}
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

      {/* Rider markers */}
      {riders.map((rider) => {
        if (!rider.last_lat || !rider.last_lng) return null;
        const isBusy = orders.some((o) => o.rider_id === rider.id);

        return (
          <AdvancedMarker
            key={`rider-${rider.id}`}
            position={{ lat: rider.last_lat, lng: rider.last_lng }}
          >
            <div
              className="flex h-9 w-9 items-center justify-center rounded-full border-2 border-white shadow-md text-base"
              style={{ backgroundColor: isBusy ? '#F97316' : '#22C55E' }}
              title={`${rider.nombre} — ${isBusy ? 'Ocupado' : 'Disponible'}`}
            >
              🛵
            </div>
          </AdvancedMarker>
        );
      })}

      {/* InfoWindow for selected order */}
      {selectedOrder && (
        <InfoWindow
          position={
            selectedOrder.rider_last_lat && selectedOrder.rider_last_lng
              ? { lat: selectedOrder.rider_last_lat, lng: selectedOrder.rider_last_lng }
              : SANTIAGO
          }
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
        defaultCenter={SANTIAGO}
        defaultZoom={13}
        mapId="d51ca892b68e9c5e5e2dd701"
        className="h-[400px] md:h-full w-full rounded-xl"
        gestureHandling="greedy"
        disableDefaultUI={false}
      >
        <MapContent orders={orders} riders={riders} onAssignRider={onAssignRider} />
      </Map>
    </APIProvider>
  );
}
