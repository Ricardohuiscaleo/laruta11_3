'use client';

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';

/**
 * Checklists were removed from the worker app.
 * They are now only available in comandas (planchero) and caja (cajeras).
 * Redirect any bookmarked/cached URLs back to the dashboard.
 */
export default function ChecklistRedirect() {
  const router = useRouter();
  useEffect(() => {
    router.replace('/dashboard');
  }, [router]);
  return null;
}
