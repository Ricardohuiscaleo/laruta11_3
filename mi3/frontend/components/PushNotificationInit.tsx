'use client';

import { useEffect, useCallback } from 'react';
import { usePushNotifications } from '@/hooks/usePushNotifications';
import { useRealtimeNotifications } from '@/hooks/useRealtimeNotifications';
import { useAuth } from '@/hooks/useAuth';

/**
 * Initializes push notifications + WebSocket realtime connection.
 * Mounts in dashboard layout — renders nothing visible.
 */
export default function PushNotificationInit() {
  const { status, activate } = usePushNotifications();
  const { user } = useAuth();

  // Auto-subscribe push if permission already granted
  useEffect(() => {
    if (status === 'inactive') activate();
  }, [status, activate]);

  // Handle realtime notification via WebSocket
  const onNotification = useCallback((data: { titulo: string; cuerpo: string }) => {
    // Refresh notification count in header (SW also does this, but this is instant)
    navigator.serviceWorker?.controller?.postMessage({ type: 'REFRESH_NOTIFICATIONS' });
  }, []);

  useRealtimeNotifications(user?.personal_id ?? null, onNotification);

  return null;
}
