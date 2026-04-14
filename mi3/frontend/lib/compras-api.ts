import { ApiError } from './api';

const API_URL = process.env.NEXT_PUBLIC_API_URL || 'https://api-mi3.laruta11.cl';
const BASE = `${API_URL}/api/v1/admin`;

async function handleResponse<T>(res: Response): Promise<T> {
  if (res.status === 401) {
    if (typeof window !== 'undefined') window.location.href = '/login';
    throw new ApiError(401, 'No autenticado');
  }
  if (!res.ok) {
    const body = await res.json().catch(() => ({}));
    throw new ApiError(res.status, body.error || body.message || 'Error de servidor');
  }
  return res.json();
}

export const comprasApi = {
  async get<T>(path: string): Promise<T> {
    const res = await fetch(`${BASE}${path}`, {
      headers: { Accept: 'application/json' },
      credentials: 'include',
    });
    return handleResponse<T>(res);
  },

  async post<T>(path: string, data: unknown): Promise<T> {
    const res = await fetch(`${BASE}${path}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      credentials: 'include',
      body: JSON.stringify(data),
    });
    return handleResponse<T>(res);
  },

  async upload<T>(path: string, formData: FormData): Promise<T> {
    const res = await fetch(`${BASE}${path}`, {
      method: 'POST',
      headers: { Accept: 'application/json' },
      credentials: 'include',
      body: formData,
    });
    return handleResponse<T>(res);
  },

  async patch<T>(path: string, data: unknown): Promise<T> {
    const res = await fetch(`${BASE}${path}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      credentials: 'include',
      body: JSON.stringify(data),
    });
    return handleResponse<T>(res);
  },
};
