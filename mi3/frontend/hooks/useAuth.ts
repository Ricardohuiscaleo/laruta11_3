'use client';

import { useState, useEffect, useCallback } from 'react';
import { apiFetch } from '@/lib/api';
import { getToken, setToken, removeToken, logout as authLogout } from '@/lib/auth';
import type { User, ApiResponse } from '@/types';

const API_URL = process.env.NEXT_PUBLIC_API_URL || 'https://api-mi3.laruta11.cl';

export function useAuth() {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  const fetchUser = useCallback(async () => {
    const token = getToken();
    // Use fetch() directly (NOT apiFetch) to avoid triggering the 401 cleanup handler
    // This allows cookie-based auth for Google OAuth users who have no localStorage token
    try {
      const headers: Record<string, string> = { Accept: 'application/json' };
      if (token) headers['Authorization'] = `Bearer ${token}`;
      const res = await fetch(`${API_URL}/api/v1/auth/me`, {
        headers,
        credentials: 'include',
      });
      if (res.ok) {
        const data: ApiResponse<User> = await res.json();
        if (data.success && data.data) setUser(data.data);
      }
      // If 401: user is not authenticated — silently set loading false, no redirect
    } catch {
      // Network error — silently fail
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchUser();
  }, [fetchUser]);

  const login = async (email: string, password: string) => {
    const res = await apiFetch<{ success: boolean; token: string; user: User }>(
      '/auth/login',
      { method: 'POST', body: JSON.stringify({ email, password }) }
    );
    if (res.success) {
      setToken(res.token);
      setUser(res.user);
    }
    return res;
  };

  const logout = async () => {
    setUser(null);
    await authLogout(); // Delegates to auth.ts: clear-session + logout API + removeToken + mi3_auth_flag + redirect
  };

  return { user, loading, login, logout, isAuthenticated: !!user };
}
