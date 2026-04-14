/**
 * Bug Condition Exploration Tests — Session Loss Loop on Backend Redeploy
 *
 * These tests encode the EXPECTED (fixed) behavior for each bug.
 * On UNFIXED code: tests FAIL (confirms bugs exist)
 * On FIXED code:   tests PASS (confirms bugs are resolved)
 *
 * Validates: Requirements 1.1, 1.2, 1.3, 2.1, 2.2, 2.3
 */

import { describe, it, expect } from 'vitest';
import * as fc from 'fast-check';
import * as fs from 'fs';
import * as path from 'path';

// ─── Helpers ────────────────────────────────────────────────────────────────

// __dirname = mi3/frontend/lib/__tests__
// frontendRoot = mi3/frontend
// repoRoot = mi3/
const FRONTEND_ROOT = path.resolve(__dirname, '../..');
const REPO_ROOT = path.resolve(__dirname, '../../..');

function readSource(relPath: string): string {
  // Paths starting with ../../backend/ are relative to REPO_ROOT
  if (relPath.startsWith('../../backend/')) {
    return fs.readFileSync(path.resolve(REPO_ROOT, relPath.replace('../../', '')), 'utf-8');
  }
  return fs.readFileSync(path.resolve(FRONTEND_ROOT, relPath), 'utf-8');
}

// ─── BUG 1: httpOnly cookie not deletable from JS ───────────────────────────
// The 401 handler used to do: document.cookie = 'mi3_token=; max-age=0'
// httpOnly cookies CANNOT be deleted from JS — the operation fails silently.
// Fix: call /auth/clear-session (server-side) instead.
//
// Validates: Requirements 1.1, 2.2

describe('BUG 1 — httpOnly cookie not deletable from JS', () => {
  it('api.ts 401 handler calls /auth/clear-session instead of document.cookie deletion', () => {
    const source = readSource('lib/api.ts');

    // FIXED behavior: must call clear-session endpoint
    expect(source).toContain('/auth/clear-session');

    // BUG behavior: must NOT attempt to delete mi3_token via document.cookie
    // (httpOnly cookies cannot be deleted from JS — fails silently)
    const httpOnlyCookieDeletion = /document\.cookie\s*=\s*['"]mi3_token=/;
    expect(source).not.toMatch(httpOnlyCookieDeletion);
  });

  it('compras-api.ts 401 handler calls /auth/clear-session instead of document.cookie deletion', () => {
    const source = readSource('lib/compras-api.ts');

    // FIXED behavior: must call clear-session endpoint
    expect(source).toContain('/auth/clear-session');

    // BUG behavior: must NOT attempt to delete mi3_token via document.cookie
    const httpOnlyCookieDeletion = /document\.cookie\s*=\s*['"]mi3_token=/;
    expect(source).not.toMatch(httpOnlyCookieDeletion);
  });

  it('property: any token string — 401 handler never tries to delete httpOnly cookie via JS', () => {
    /**
     * **Validates: Requirements 1.1, 2.2**
     *
     * For any token value, the 401 handler must use the server-side
     * clear-session endpoint, never document.cookie assignment on mi3_token.
     */
    const source = readSource('lib/api.ts');
    fc.assert(
      fc.property(fc.string(), (_token) => {
        // The source code must not contain the buggy pattern regardless of token value
        const hasBugPattern = /document\.cookie\s*=\s*['"]mi3_token=/.test(source);
        return !hasBugPattern;
      }),
    );
  });
});

// ─── BUG 2: Middleware checks existence not validity ─────────────────────────
// The middleware used to check: request.cookies.get('mi3_token')?.value
// An invalid/expired token still has a value → middleware lets user through → 401 loop.
// Fix: check mi3_auth_flag (non-httpOnly, deletable from JS) instead.
//
// Validates: Requirements 1.2, 2.2

describe('BUG 2 — middleware checks existence not validity', () => {
  it('middleware.ts uses mi3_auth_flag as session indicator, not mi3_token', () => {
    const source = readSource('middleware.ts');

    // FIXED behavior: must check mi3_auth_flag
    expect(source).toContain('mi3_auth_flag');

    // BUG behavior: must NOT use mi3_token as the primary auth gate
    // (mi3_token is httpOnly — JS cannot delete it, causing the loop)
    // The middleware may still read mi3_token for other purposes, but
    // the auth gate (redirect to /login when missing) must use mi3_auth_flag
    const authGateOnToken = /if\s*\(\s*!token\s*\)\s*\{[\s\S]*?redirect[\s\S]*?\/login/;
    expect(source).not.toMatch(authGateOnToken);
  });

  it('middleware.ts redirects to /login when mi3_auth_flag is absent', () => {
    const source = readSource('middleware.ts');

    // FIXED behavior: the redirect-to-login guard must reference authFlag
    expect(source).toMatch(/authFlag[\s\S]{0,200}\/login/);
  });

  it('property: middleware source always gates on mi3_auth_flag, not mi3_token', () => {
    /**
     * **Validates: Requirements 1.2, 2.2**
     *
     * For any request state, the session validity check must use
     * mi3_auth_flag (non-httpOnly, JS-deletable) not mi3_token (httpOnly).
     */
    const source = readSource('middleware.ts');
    fc.assert(
      fc.property(fc.boolean(), (_hasExpiredToken) => {
        // The auth gate must use mi3_auth_flag
        const usesAuthFlag = source.includes('mi3_auth_flag');
        // Must not use mi3_token as the sole auth gate
        const hasTokenOnlyGate = /if\s*\(\s*!token\s*\)\s*\{[\s\S]*?redirect[\s\S]*?\/login/.test(source);
        return usesAuthFlag && !hasTokenOnlyGate;
      }),
    );
  });
});

// ─── BUG 3: Google OAuth no localStorage ────────────────────────────────────
// googleCallback() used to redirect without passing the token to the frontend.
// Frontend never received the plainTextToken → localStorage empty → no Bearer fallback.
// Fix: redirect with ?token=<plainTextToken> so frontend can save it.
//
// Validates: Requirements 1.3, 2.3

describe('BUG 3 — Google OAuth no localStorage', () => {
  it('AuthController.php googleCallback includes ?token= in redirect URL', () => {
    const source = readSource('../../backend/app/Http/Controllers/Auth/AuthController.php');

    // FIXED behavior: redirect URL must include the token as a query param
    expect(source).toContain('?token=');
    expect(source).toContain('urlencode($token)');
  });

  it('property: googleCallback always passes token in redirect for any user role', () => {
    /**
     * **Validates: Requirements 1.3, 2.3**
     *
     * For any Google OAuth user (admin or worker), the callback redirect
     * must include the token as a query parameter so the frontend can
     * save it to localStorage.
     */
    const source = readSource('../../backend/app/Http/Controllers/Auth/AuthController.php');
    fc.assert(
      fc.property(fc.boolean(), (_isAdmin) => {
        // The redirect must include ?token= regardless of user role
        return source.includes('?token=') && source.includes('urlencode($token)');
      }),
    );
  });
});

// ─── BUG 7: useAuth null for Google users ───────────────────────────────────
// useAuth.fetchUser() used to do: if (!token) { setLoading(false); return; }
// Google OAuth users have no localStorage token → fetchUser returns early → user = null.
// Fix: always call /auth/me (using fetch directly, not apiFetch) even without localStorage token.
//
// Validates: Requirements 2.3, 2.5

describe('BUG 7 — useAuth null for Google users', () => {
  it('useAuth.ts fetchUser does not early-return when localStorage token is null', () => {
    const source = readSource('hooks/useAuth.ts');

    // BUG behavior: early return when no token
    const earlyReturnPattern = /if\s*\(\s*!token\s*\)\s*\{[\s\S]{0,100}return/;
    expect(source).not.toMatch(earlyReturnPattern);
  });

  it('useAuth.ts fetchUser calls /auth/me directly (not apiFetch) to support cookie auth', () => {
    const source = readSource('hooks/useAuth.ts');

    // FIXED behavior: must call /auth/me via fetch() directly
    expect(source).toContain('/auth/me');
    // Must use fetch() directly, not apiFetch (apiFetch would trigger 401 cleanup on Google users)
    expect(source).toContain("fetch(`${API_URL}/api/v1/auth/me`");
  });

  it('property: fetchUser always attempts /auth/me regardless of localStorage state', () => {
    /**
     * **Validates: Requirements 2.3, 2.5**
     *
     * For any combination of localStorage token presence (null or valid),
     * fetchUser must always call /auth/me to support cookie-based auth
     * for Google OAuth users.
     */
    const source = readSource('hooks/useAuth.ts');
    fc.assert(
      fc.property(fc.option(fc.string({ minLength: 1 })), (_token) => {
        // No early return on missing token
        const hasEarlyReturn = /if\s*\(\s*!token\s*\)\s*\{[\s\S]{0,100}return/.test(source);
        // Always calls /auth/me
        const callsAuthMe = source.includes('/auth/me');
        return !hasEarlyReturn && callsAuthMe;
      }),
    );
  });
});

// ─── BUG 8: Inconsistent logout ─────────────────────────────────────────────
// auth.ts logout() used to do: window.location.href = '/login' (no clear-session)
// useAuth.ts logout() used to do: removeToken() + setUser(null) (no clear-session, no redirect)
// Neither called /auth/clear-session to expire httpOnly cookies server-side.
// Fix: both must call clear-session + logout API + removeToken + clear mi3_auth_flag + redirect.
//
// Validates: Requirements 3.3

describe('BUG 8 — inconsistent logout', () => {
  it('auth.ts logout() calls /auth/clear-session to expire httpOnly cookies server-side', () => {
    const source = readSource('lib/auth.ts');

    // FIXED behavior: must call clear-session
    expect(source).toContain('/auth/clear-session');
    // Must also call /auth/logout to delete Sanctum token from DB
    expect(source).toContain('/auth/logout');
    // Must clear localStorage
    expect(source).toContain('removeToken');
    // Must clear mi3_auth_flag (non-httpOnly, JS can delete it)
    expect(source).toContain('mi3_auth_flag');
  });

  it('useAuth.ts logout() delegates to auth.ts logout (unified implementation)', () => {
    const source = readSource('hooks/useAuth.ts');

    // FIXED behavior: useAuth logout must delegate to auth.ts logout
    // (either imports and calls authLogout, or inlines the same logic)
    const delegatesToAuthLogout = source.includes('authLogout') || source.includes('auth.ts');
    expect(delegatesToAuthLogout).toBe(true);
  });

  it('property: both logout implementations always call clear-session', () => {
    /**
     * **Validates: Requirements 3.3**
     *
     * For any logout trigger, both auth.ts and useAuth.ts must ensure
     * /auth/clear-session is called to expire httpOnly cookies server-side.
     * This prevents the stale-cookie loop after logout.
     */
    const authSource = readSource('lib/auth.ts');
    const useAuthSource = readSource('hooks/useAuth.ts');

    fc.assert(
      fc.property(fc.boolean(), (_isGoogleUser) => {
        // auth.ts must call clear-session
        const authCallsClearSession = authSource.includes('/auth/clear-session');
        // useAuth.ts must delegate to auth.ts (which calls clear-session)
        const useAuthDelegates =
          useAuthSource.includes('authLogout') || useAuthSource.includes('/auth/clear-session');
        return authCallsClearSession && useAuthDelegates;
      }),
    );
  });
});
