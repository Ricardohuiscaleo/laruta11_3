'use client';

import { useEffect, useCallback } from 'react';
import { usePushNotifications } from '@/hooks/usePushNotifications';
import { useRealtimeNotifications } from '@/hooks/useRealtimeNotifications';
import { useAuth } from '@/hooks/useAuth';
import { getEcho } from '@/lib/echo';

/**
 * Initializes push notifications sync + WebSocket realtime connection.
 * Mounts in both dashboard and admin layouts — renders nothing visible.
 *
 * IMPORTANT: Never call Notification.requestPermission() here.
 * Safari requires it from a user gesture (click). The modal handles that.
 */
export default function PushNotificationInit() {
  const { status } = usePushNotifications(); // checkAndSync runs on mount (no requestPermission)
  const { user } = useAuth();

  // Initialize Echo WebSocket connection immediately (no user needed for connection)
  useEffect(() => {
    getEcho(); // establishes wss:// connection on first call
  }, []);

  // Handle realtime notification via WebSocket
  const onNotification = useCallback(() => {
    navigator.serviceWorker?.controller?.postMessage({ type: 'REFRESH_NOTIFICATIONS' });
  }, []);

  useRealtimeNotifications(user?.personal_id ?? null, onNotification);

  return null;
}
