'use client';

import { useEffect } from 'react';
import { useSearchParams, usePathname, useRouter } from 'next/navigation';

/**
 * Reads ?token= query param (set by Google OAuth callback) and saves to localStorage.
 * Cleans the URL after saving. Used in admin/dashboard layouts for Google OAuth users.
 */
export default function TokenFromUrl() {
  const searchParams = useSearchParams();
  const pathname = usePathname();
  const router = useRouter();

  useEffect(() => {
    const token = searchParams.get('token');
    if (token) {
      localStorage.setItem('mi3_token', token);
      // Clean the token from URL to avoid it appearing in browser history
      router.replace(pathname);
    }
  }, [searchParams, pathname, router]);

  return null;
}
