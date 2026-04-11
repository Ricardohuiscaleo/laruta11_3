'use client';

import { useEffect, useState } from 'react';
import { apiFetch } from '@/lib/api';
import type { Checklist, ApiResponse } from '@/types';

/**
 * Returns true when the authenticated worker has at least one
 * checklist with status !== 'completed' for today.
 *
 * Fetches once on mount; non-critical — errors are silently ignored.
 */
export function usePendingChecklistBadge() {
  const [hasPending, setHasPending] = useState(false);

  useEffect(() => {
    apiFetch<ApiResponse<Checklist[]>>('/worker/checklists')
      .then((res) => {
        const pending = (res.data ?? []).some((c) => c.status !== 'completed');
        setHasPending(pending);
      })
      .catch(() => {
        // Silently ignore — badge is non-critical
      });
  }, []);

  return hasPending;
}
