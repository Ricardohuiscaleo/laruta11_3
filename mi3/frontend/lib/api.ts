import { getToken } from './auth';

const API_URL = process.env.NEXT_PUBLIC_API_URL || 'https://api-mi3.laruta11.cl';

export async function apiFetch<T>(
  endpoint: string,
  options: RequestInit = {}
): Promise<T> {
  const { headers: customHeaders, ...rest } = options;
  const token = getToken();

  const headers: Record<string, string> = {
    Accept: 'application/json',
    ...((customHeaders as Record<string, string>) || {}),
  };

  // Add Bearer token if available
  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  // Don't set Content-Type for FormData — browser sets it with boundary
  if (!(rest.body instanceof FormData)) {
    headers['Content-Type'] = 'application/json';
  }

  const res = await fetch(`${API_URL}/api/v1${endpoint}`, {
    headers,
    credentials: 'include',
    ...rest,
  });

  if (res.status === 401) {
    // Unauthenticated — clear stale auth data and redirect to login
    if (typeof window !== 'undefined') {
      // Clear localStorage token
      localStorage.removeItem('mi3_token');
      localStorage.removeItem('mi3_user');
      // Call server-side clear-session to expire httpOnly cookies (JS cannot delete them)
      fetch(`${API_URL}/api/v1/auth/clear-session`, { method: 'POST', credentials: 'include' })
        .finally(() => {
          // Clear the non-httpOnly auth flag (JS CAN delete this one)
          document.cookie = 'mi3_auth_flag=; path=/; domain=.laruta11.cl; max-age=0';
          window.location.href = '/login';
        });
    }
    throw new ApiError(401, 'No autenticado');
  }

  if (!res.ok) {
    const body = await res.json().catch(() => ({}));
    throw new ApiError(res.status, body.error || body.message || 'Error de servidor');
  }

  return res.json();
}

export class ApiError extends Error {
  constructor(public status: number, message: string) {
    super(message);
    this.name = 'ApiError';
  }
}
