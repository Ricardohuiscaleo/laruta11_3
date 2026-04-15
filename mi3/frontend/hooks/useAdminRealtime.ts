'use client';

import { useState, useEffect, useCallback, useRef } from 'react';
import { getEcho } from '@/lib/echo';

export interface RealtimeEvent {
  type: string;
  section: string;
  data: Record<string, any>;
}

/**
 * Hook that connects to private-admin.{adminId} Reverb channel via Echo.
 * Tracks badge counts per section and exposes event callbacks.
 *
 * Events:
 *   - .loan.requested → increment badges.adelantos
 *   - .admin.notification → increment badges.notificaciones
 */
export function useAdminRealtime(adminId: number | null) {
  const [badges, setBadges] = useState<Record<string, number>>({});
  const callbackRef = useRef<((event: RealtimeEvent) => void) | null>(null);

  const clearBadge = useCallback((section: string) => {
    setBadges(prev => {
      if (!prev[section]) return prev;
      const next = { ...prev };
      delete next[section];
      return next;
    });
  }, []);

  const onEvent = useCallback((callback: (event: RealtimeEvent) => void) => {
    callbackRef.current = callback;
  }, []);

  useEffect(() => {
    if (!adminId) return;

    const echo = getEcho();
    if (!echo) return;

    const channelName = `admin.${adminId}`;
    const channel = echo.private(channelName);

    // LoanRequestedEvent → increment adelantos badge
    channel.listen('.loan.requested', (data: any) => {
      setBadges(prev => ({
        ...prev,
        adelantos: (prev.adelantos || 0) + 1,
      }));
      callbackRef.current?.({
        type: 'loan.requested',
        section: 'adelantos',
        data,
      });
    });

    // AdminNotificationEvent → increment notificaciones badge
    channel.listen('.admin.notification', (data: any) => {
      setBadges(prev => ({
        ...prev,
        notificaciones: (prev.notificaciones || 0) + 1,
      }));
      callbackRef.current?.({
        type: 'admin.notification',
        section: 'notificaciones',
        data,
      });
    });

    // AdminDataUpdatedEvent → increment badge for affected section
    channel.listen('.admin.data.updated', (data: { section: string; action: string }) => {
      setBadges(prev => ({
        ...prev,
        [data.section]: (prev[data.section] || 0) + 1,
      }));
      callbackRef.current?.({
        type: 'admin.data.updated',
        section: data.section,
        data,
      });
    });

    // Auto-reconnect handling via pusher connection events
    const pusher = (echo.connector as any)?.pusher;
    if (pusher) {
      pusher.connection.bind('disconnected', () => {
        // Auto-reconnect handled by pusher
      });
    }

    return () => {
      echo.leave(`private-${channelName}`);
    };
  }, [adminId]);

  return { badges, clearBadge, onEvent };
}
