const API_URL = process.env.NEXT_PUBLIC_API_URL || 'https://api-mi3.laruta11.cl';

export async function logout(): Promise<void> {
  try {
    await fetch(`${API_URL}/api/v1/auth/logout`, {
      method: 'POST',
      headers: { 'Accept': 'application/json' },
      credentials: 'include',
    });
  } catch {
    // Even if the API call fails, clear local state
  }
  // Clear any localStorage remnants
  if (typeof window !== 'undefined') {
    localStorage.removeItem('mi3_token');
    localStorage.removeItem('mi3_user');
  }
  // Hard redirect to login (cookies cleared by backend)
  window.location.href = '/login';
}
