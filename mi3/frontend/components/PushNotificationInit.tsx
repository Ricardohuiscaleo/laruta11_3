'use client';

import { usePushNotifications } from '@/hooks/usePushNotifications';

/**
 * Client component that initializes push notifications.
 * Renders nothing — just runs the hook on mount.
 */
export default function PushNotificationInit() {
  usePushNotifications();
  return null;
}
