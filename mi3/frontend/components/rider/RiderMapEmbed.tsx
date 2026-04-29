'use client';

import { useState, useEffect, useRef } from 'react';
import { APIProvider, Map, AdvancedMarker, useMap, useMapsLibrary } from '@vis.gl/react-google-maps';

const API_URL = process.env.NEXT_PUBLIC_API_URL || 'https://api-mi3.laruta11.cl';

interface OrderData {
  delivery_address: string;
  rider_last_lat: number | null;
  rider_last_lng: number | null;
  food_truck: { latitud: number; longitud: number } | null;
}

function RouteLayer({ origin, destination }: { origin: { lat: number; lng: number }; destination: string }) {
  const map = useMap();
  const routesLib = useMapsLibrary('routes');
  const rendererRef = useRef<google.maps.DirectionsRenderer | null>(null);
  useEffect(() => {
    if (!map || !routesLib) return;
    if (!rendererRef.current) {
      rendererRef.current = new routesLib.DirectionsRenderer({ suppressMarkers: true, polylineOptions: { strokeColor: '#4285F4', strokeWeight: 4, strokeOpacity: 0.8 } });
      rendererRef.current.setMap(map);
    }
    new routesLib.DirectionsService().route(
      { origin, destination, travelMode: google.maps.TravelMode.DRIVING },
      (r, s) => { if (s === 'OK' && r && rendererRef.current) rendererRef.current.setDirections(r); },
    );
    return () => { if (rendererRef.current) { rendererRef.current.setMap(null); rendererRef.current = null; } };
  }, [map, routesLib, origin.lat, origin.lng, destination]);
  return null;
}

export default function RiderMapEmbed({ orderId }: { orderId: string }) {
  const [order, setOrder] = useState<OrderData | null>(null);

  useEffect(() => {
    const load = async () => {
      try {
        const res = await fetch(`${API_URL}/api/v1/public/rider-orders/${orderId}`, { headers: { Accept: 'application/json' } });
        if (!res.ok) return;
        const d = await res.json();
        if (d.success && d.order) setOrder(d.order);
      } catch { /* silent */ }
    };
    load();
    const iv = setInterval(load, 15000);
    return () => clearInterval(iv);
  }, [orderId]);

  if (!order) return <div className="h-full w-full bg-gray-100 flex items-center justify-center"><span className="text-xs text-gray-400">Cargando mapa...</span></div>;

  const foodTruck = order.food_truck ? { lat: Number(order.food_truck.latitud), lng: Number(order.food_truck.longitud) } : null;
  const riderPos = order.rider_last_lat && order.rider_last_lng ? { lat: Number(order.rider_last_lat), lng: Number(order.rider_last_lng) } : null;
  const center = riderPos || foodTruck || { lat: -18.4747, lng: -70.2989 };

  return (
    <div className="h-dvh w-full">
      <APIProvider apiKey={process.env.NEXT_PUBLIC_GOOGLE_MAPS_KEY ?? ''}>
        <Map defaultCenter={center} defaultZoom={14} mapId="d51ca892b68e9c5e5e2dd701" className="h-full w-full" gestureHandling="greedy" disableDefaultUI>
          {foodTruck && (
            <AdvancedMarker position={foodTruck} zIndex={100}>
              <div className="flex flex-col items-center">
                <img src="https://laruta11-images.s3.amazonaws.com/menu/logo-optimized.png" alt="R11" className="h-8 w-8 rounded-full border-2 border-red-500 shadow-lg bg-white" />
                <span className="text-[7px] font-bold bg-red-600 text-white px-1 py-0.5 rounded-full mt-0.5">R11</span>
              </div>
            </AdvancedMarker>
          )}
          {order.delivery_address && <RouteLayer origin={riderPos || foodTruck || center} destination={order.delivery_address} />}
          {riderPos && (
            <AdvancedMarker position={riderPos} zIndex={200}>
              <div className="h-10 w-10 drop-shadow-lg">
                <img src="/rider-car.svg" alt="Rider" className="h-full w-full" />
              </div>
            </AdvancedMarker>
          )}
        </Map>
      </APIProvider>
    </div>
  );
}
