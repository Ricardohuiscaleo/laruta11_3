'use client';

import { useEffect } from 'react';
import { getEcho } from '@/lib/echo';

interface NotificacionData {
  titulo: string;
  cuerpo: string;
  tipo: string;
  url: string;
  timestamp: string;
}

/**
 * Listen for real-time notifications on the worker's channel.
 * Calls onNotification when a new notification arrives via WebSocket.
 */
export function useRealtimeNotifications(
  personalId: number | null,
  onNotification: (data: NotificacionData) => void
) {
  useEffect(() => {
    if (!personalId) return;

    const echo = getEcho();
    if (!echo) return;

    const channel = echo.channel(`worker.${personalId}`);

    channel.listen('.notificacion.nueva', (data: NotificacionData) => {
      onNotification(data);
    });

    return () => {
      echo.leave(`worker.${personalId}`);
    };
  }, [personalId, onNotification]);
}
