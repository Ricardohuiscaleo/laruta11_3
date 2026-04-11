import { NextResponse } from 'next/server';
import type { NextRequest } from 'next/server';

export function middleware(request: NextRequest) {
  // Let all requests through — auth is handled client-side
  // The middleware only blocks /admin for non-admin users via cookie
  const { pathname } = request.nextUrl;

  // Public routes — always allow
  if (
    pathname === '/login' ||
    pathname === '/' ||
    pathname.startsWith('/_next') ||
    pathname.startsWith('/api') ||
    pathname === '/favicon.ico'
  ) {
    return NextResponse.next();
  }

  // Check for auth cookie (set by client after login)
  const token = request.cookies.get('mi3_token')?.value;

  // No token — let client-side handle redirect (avoids loop with OAuth callback)
  if (!token) {
    // For RSC/prefetch requests, redirect to login
    if (request.headers.get('RSC') === '1') {
      return NextResponse.redirect(new URL('/login', request.url));
    }
    // For full page loads, let client handle it
    return NextResponse.next();
  }

  // Admin routes — check role cookie
  if (pathname.startsWith('/admin')) {
    const role = request.cookies.get('mi3_role')?.value;
    if (role !== 'admin') {
      return NextResponse.redirect(new URL('/dashboard', request.url));
    }
  }

  return NextResponse.next();
}

export const config = {
  matcher: ['/((?!_next/static|_next/image|favicon.ico).*)'],
};
