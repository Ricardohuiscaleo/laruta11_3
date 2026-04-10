'use client';

import { useState, useCallback } from 'react';
import { apiFetch, ApiError } from '@/lib/api';

interface UseApiState<T> {
  data: T | null;
  loading: boolean;
  error: string | null;
}

export function useApi<T>() {
  const [state, setState] = useState<UseApiState<T>>({
    data: null,
    loading: false,
    error: null,
  });

  const request = useCallback(
    async (endpoint: string, options?: RequestInit) => {
      setState((s) => ({ ...s, loading: true, error: null }));
      try {
        const data = await apiFetch<T>(endpoint, options);
        setState({ data, loading: false, error: null });
        return data;
      } catch (err) {
        const message =
          err instanceof ApiError ? err.message : 'Error inesperado';
        setState((s) => ({ ...s, loading: false, error: message }));
        throw err;
      }
    },
    []
  );

  return { ...state, request };
}
