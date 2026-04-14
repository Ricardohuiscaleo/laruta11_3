const API_URL = process.env.NEXT_PUBLIC_API_URL || 'https://api-mi3.laruta11.cl';
const TOKEN_KEY = 'mi3_token';

export function getToken(): string | null {
  if (typeof window === 'undefined') return null;
  return localStorage.getItem(TOKEN_KEY);
}

export function setToken(token: string): void {
  if (typeof window === 'undefined') return;
  localStorage.setItem(TOKEN_KEY, token);
}

export function removeToken(): void {
  if (typeof window === 'undefined') return;
  localStorage.removeItem(TOKEN_KEY);
  localStorage.removeItem('mi3_user');
}

export async function logout(): Promise<void> {
  try {
    // Call clear-session first to expire httpOnly cookies server-side
    await fetch(`${API_URL}/api/v1/auth/clear-session`, {
      method: 'POST',
      headers: { 'Accept': 'application/json' },
      credentials: 'include',
    });
    // Then delete the Sanctum token from DB
    await fetch(`${API_URL}/api/v1/auth/logout`, {
      method: 'POST',
      headers: { 'Accept': 'application/json' },
      credentials: 'include',
    });
  } catch {
    // Even if API fails, clear local state
  } finally {
    removeToken();
    // Clear the non-httpOnly auth flag (JS can delete this)
    document.cookie = 'mi3_auth_flag=; path=/; domain=.laruta11.cl; max-age=0';
    window.location.href = '/login';
  }
}
