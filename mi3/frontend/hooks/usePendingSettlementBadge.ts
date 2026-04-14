'use client';

import { useState, useEffect } from 'react';
import { apiFetch } from '@/lib/api';

interface Settlement {
  id: number;
  settlement_date: string;
  status: 'pending' | 'paid';
  total_delivery_fees: number;
}

/**
 * Returns true if there is a pending settlement from yesterday with total_delivery_fees > 0.
 * Used to show the badge in the admin sidebar.
 */
export function usePendingSettlementBadge(): boolean {
  const [hasPending, setHasPending] = useState(false);

  useEffect(() => {
    apiFetch<{ success: boolean; settlements: Settlement[] }>('/admin/delivery/settlements')
      .then((res) => {
        if (!res.settlements) return;
        const yesterday = new Date();
        yesterday.setDate(yesterday.getDate() - 1);
        const yesterdayStr = yesterday.toISOString().split('T')[0];

        const pending = res.settlements.some(
          (s) =>
            s.status === 'pending' &&
            s.total_delivery_fees > 0 &&
            s.settlement_date <= yesterdayStr
        );
        setHasPending(pending);
      })
      .catch(() => {});
  }, []);

  return hasPending;
}
