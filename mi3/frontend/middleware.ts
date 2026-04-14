import { NextResponse } from 'next/server';
import type { NextRequest } from 'next/server';

export function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl;

  // Static/internal routes — always pass through
  if (
    pathname.startsWith('/_next') ||
    pathname.startsWith('/api') ||
    pathname === '/favicon.ico'
  ) {
    return NextResponse.next();
  }

  // Read httpOnly cookies set by the backend
  const token = request.cookies.get('mi3_token')?.value;
  const role = request.cookies.get('mi3_role')?.value;
  // mi3_auth_flag is non-httpOnly — JS can delete it to break the 401 loop
  const authFlag = request.cookies.get('mi3_auth_flag')?.value;

  // If user has auth flag and visits login or root → redirect to their dashboard
  if (authFlag && (pathname === '/login' || pathname === '/')) {
    const dest = role === 'admin' ? '/admin' : '/dashboard';
    return NextResponse.redirect(new URL(dest, request.url));
  }

  // Public routes (login, root) — no token required
  if (pathname === '/login' || pathname === '/') {
    return NextResponse.next();
  }

  // No auth flag → redirect to login (flag is cleared by 401 handler, breaking the loop)
  if (!authFlag) {
    return NextResponse.redirect(new URL('/login', request.url));
  }

  // Admin routes → check role
  if (pathname.startsWith('/admin') && role !== 'admin') {
    return NextResponse.redirect(new URL('/dashboard', request.url));
  }

  return NextResponse.next();
}

export const config = {
  matcher: ['/((?!_next/static|_next/image|favicon.ico).*)'],
};
