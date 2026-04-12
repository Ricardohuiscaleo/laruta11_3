'use client';

import { useEffect } from 'react';
import { usePushNotifications } from '@/hooks/usePushNotifications';

/**
 * Auto-activates push if permission was already granted (returning user).
 * Renders nothing visible.
 */
export default function PushNotificationInit() {
  const { status, activate } = usePushNotifications();

  useEffect(() => {
    // If permission already granted but not subscribed, auto-subscribe silently
    if (status === 'inactive') {
      activate();
    }
  }, [status, activate]);

  return null;
}
