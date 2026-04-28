'use client';

import { useState, useEffect, useRef, useCallback } from 'react';

export interface GeoPosition {
  lat: number;
  lng: number;
  heading: number | null;
}

const API_URL = process.env.NEXT_PUBLIC_API_URL || 'https://api-mi3.laruta11.cl';
const SEND_INTERVAL_MS = 10_000;

interface UsePublicRiderGPSOptions {
  orderId: string;
  enabled: boolean;
}

export function usePublicRiderGPS({ orderId, enabled }: UsePublicRiderGPSOptions) {
  const [position, setPosition] = useState<GeoPosition | null>(null);
  const [gpsError, setGpsError] = useState<string | null>(null);

  const watchIdRef = useRef<number | null>(null);
  const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const latestPositionRef = useRef<GeoPosition | null>(null);

  const sendLocation = useCallback(
    async (pos: GeoPosition) => {
      try {
        await fetch(`${API_URL}/api/v1/public/rider-orders/${orderId}/location`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          body: JSON.stringify({ latitud: pos.lat, longitud: pos.lng }),
        });
      } catch {
        // silently fail — retry on next interval
      }
    },
    [orderId],
  );

  useEffect(() => {
    if (!enabled) {
      if (watchIdRef.current !== null) {
        navigator.geolocation.clearWatch(watchIdRef.current);
        watchIdRef.current = null;
      }
      if (intervalRef.current !== null) {
        clearInterval(intervalRef.current);
        intervalRef.current = null;
      }
      return;
    }

    if (!navigator.geolocation) {
      setGpsError('Tu dispositivo no soporta geolocalización');
      return;
    }

    watchIdRef.current = navigator.geolocation.watchPosition(
      (geoPos) => {
        const pos: GeoPosition = {
          lat: geoPos.coords.latitude,
          lng: geoPos.coords.longitude,
          heading: geoPos.coords.heading,
        };
        setPosition(pos);
        setGpsError(null);
        latestPositionRef.current = pos;
      },
      (err) => {
        if (err.code === 1 /* PERMISSION_DENIED */) {
          setGpsError('Debes habilitar el GPS para compartir tu ubicación');
        } else {
          setGpsError('Error al obtener la ubicación GPS');
        }
      },
      { enableHighAccuracy: true, maximumAge: 10_000, timeout: 15_000 },
    );

    intervalRef.current = setInterval(() => {
      if (latestPositionRef.current) {
        sendLocation(latestPositionRef.current);
      }
    }, SEND_INTERVAL_MS);

    return () => {
      if (watchIdRef.current !== null) {
        navigator.geolocation.clearWatch(watchIdRef.current);
        watchIdRef.current = null;
      }
      if (intervalRef.current !== null) {
        clearInterval(intervalRef.current);
        intervalRef.current = null;
      }
    };
  }, [enabled, sendLocation]);

  return { position, gpsError };
}
