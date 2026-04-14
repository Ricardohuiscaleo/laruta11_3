import { ApiError } from './api';
import { getToken } from './auth';

const API_URL = process.env.NEXT_PUBLIC_API_URL || 'https://api-mi3.laruta11.cl';
const BASE = `${API_URL}/api/v1/admin`;

function authHeaders(): Record<string, string> {
  const token = getToken();
  const h: Record<string, string> = { Accept: 'application/json' };
  if (token) h['Authorization'] = `Bearer ${token}`;
  return h;
}

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
      headers: authHeaders(),
      credentials: 'include',
    });
    return handleResponse<T>(res);
  },

  async post<T>(path: string, data: unknown): Promise<T> {
    const res = await fetch(`${BASE}${path}`, {
      method: 'POST',
      headers: { ...authHeaders(), 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(data),
    });
    return handleResponse<T>(res);
  },

  async upload<T>(path: string, formData: FormData): Promise<T> {
    const res = await fetch(`${BASE}${path}`, {
      method: 'POST',
      headers: { ...authHeaders() },
      credentials: 'include',
      body: formData,
    });
    return handleResponse<T>(res);
  },

  async patch<T>(path: string, data: unknown): Promise<T> {
    const res = await fetch(`${BASE}${path}`, {
      method: 'PATCH',
      headers: { ...authHeaders(), 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(data),
    });
    return handleResponse<T>(res);
  },
};
