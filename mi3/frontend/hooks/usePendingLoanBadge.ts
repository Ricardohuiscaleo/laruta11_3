'use client';

import { useEffect, useState } from 'react';
import { apiFetch } from '@/lib/api';
import type { Prestamo, ApiResponse } from '@/types';

/**
 * Returns true when the authenticated worker has at least one loan
 * with estado === 'pendiente' (waiting for admin approval).
 *
 * Fetches once on mount; call `refresh()` to re-check.
 */
export function usePendingLoanBadge() {
  const [hasPending, setHasPending] = useState(false);

  useEffect(() => {
    apiFetch<ApiResponse<Prestamo[]>>('/worker/loans')
      .then((res) => {
        const pending = (res.data ?? []).some((p) => p.estado === 'pendiente');
        setHasPending(pending);
      })
      .catch(() => {
        // Silently ignore — badge is non-critical
      });
  }, []);

  return hasPending;
}
