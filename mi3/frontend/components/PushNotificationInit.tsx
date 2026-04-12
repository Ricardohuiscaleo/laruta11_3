'use client';

import { useEffect } from 'react';
import { usePushNotifications } from '@/hooks/usePushNotifications';

/**
 * Auto-activates push if permission was already granted but not subscribed.
 * The hook's checkAndSync() already handles re-syncing existing subscriptions.
 */
export default function PushNotificationInit() {
  const { status, activate } = usePushNotifications();

  useEffect(() => {
    if (status === 'inactive') {
      activate();
    }
  }, [status, activate]);

  return null;
}
