'use client';

import { useState, useEffect, useRef, useCallback } from 'react';
import { apiFetch } from '@/lib/api';

export interface GeoPosition {
  lat: number;
  lng: number;
}

const SEND_INTERVAL_MS = 15_000;

export function useRiderGPS() {
  const [position, setPosition] = useState<GeoPosition | null>(null);
  const [isActive, setIsActive] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const watchIdRef = useRef<number | null>(null);
  const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const latestPositionRef = useRef<GeoPosition | null>(null);

  const sendLocation = useCallback(async (pos: GeoPosition) => {
    try {
      await apiFetch('/rider/location', {
        method: 'POST',
        body: JSON.stringify({ latitud: pos.lat, longitud: pos.lng }),
      });
    } catch {
      // silently fail — el rider sigue activo aunque falle un envío puntual
    }
  }, []);

  useEffect(() => {
    if (!isActive) {
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
      setError('Tu dispositivo no soporta geolocalización');
      setIsActive(false);
      return;
    }

    watchIdRef.current = navigator.geolocation.watchPosition(
      (geoPos) => {
        const pos: GeoPosition = {
          lat: geoPos.coords.latitude,
          lng: geoPos.coords.longitude,
        };
        setPosition(pos);
        setError(null);
        latestPositionRef.current = pos;
      },
      (err) => {
        if (err.code === 1 /* PERMISSION_DENIED */) {
          setError('Debes habilitar el GPS para continuar');
        } else {
          setError('Error al obtener la ubicación GPS');
        }
      },
      { enableHighAccuracy: true, maximumAge: 10_000, timeout: 15_000 }
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
  }, [isActive, sendLocation]);

  const toggleDeliveryMode = useCallback(() => {
    setIsActive(prev => !prev);
    setError(null);
  }, []);

  return { position, isActive, error, toggleDeliveryMode };
}
