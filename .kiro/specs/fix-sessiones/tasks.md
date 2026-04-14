# Implementation Plan

- [x] 1. Write bug condition exploration test
  - **Property 1: Bug Condition** - Session Loss Loop on Backend Redeploy
  - **CRITICAL**: This test MUST FAIL on unfixed code - failure confirms the bugs exist
  - **DO NOT attempt to fix the test or the code when it fails**
  - **NOTE**: This test encodes the expected behavior - it will validate the fix when it passes after implementation
  - **GOAL**: Surface counterexamples that demonstrate the session loss bugs exist
  - **Scoped PBT Approach**: Scope to the concrete failing cases across BUG 1, 2, 3, 7, 8
  - Test cases to write (all should FAIL on unfixed code):
    - **BUG 1 (httpOnly cookie not deletable from JS)**: Simulate 401 handler executing `document.cookie = 'mi3_token=; max-age=0'` on an httpOnly cookie → assert cookie is NOT deleted (confirms JS cannot clear httpOnly cookies, proving the loop cause)
    - **BUG 2 (middleware checks existence not validity)**: Simulate Next.js middleware with `mi3_token` cookie containing an invalid/expired token → assert middleware redirects to `/login` (FAILS: middleware sees cookie exists and redirects to dashboard instead)
    - **BUG 3 (Google OAuth no localStorage)**: Trace `googleCallback()` redirect flow → assert `localStorage.getItem('mi3_token')` has a value after Google OAuth login (FAILS: callback only sets httpOnly cookies, no token passed to frontend)
    - **BUG 7 (useAuth null for Google users)**: Simulate `useAuth.fetchUser()` when `localStorage('mi3_token')` is null but cookie auth is valid → assert `user` is not null (FAILS: `fetchUser()` returns early when `getToken()` is null, never calls `/auth/me`)
    - **BUG 8 (inconsistent logout)**: Compare `auth.ts logout()` vs `useAuth.ts logout()` → assert both call the same cleanup sequence (FAILS: `auth.ts` does `window.location.href = '/login'` while `useAuth.ts` only calls `removeToken()` + `setUser(null)`, neither calls clear-session)
  - Run test on UNFIXED code
  - **EXPECTED OUTCOME**: Tests FAIL (this is correct - proves the bugs exist)
  - Document counterexamples found to understand root cause
  - Mark task complete when test is written, run, and failure is documented
  - _Requirements: 1.1, 1.2, 1.3, 2.1, 2.2, 2.3_

- [x] 2. Write preservation property tests (BEFORE implementing fix)
  - **Property 2: Preservation** - Normal Auth Flows Unchanged
  - **IMPORTANT**: Follow observation-first methodology
  - Observe on UNFIXED code and write property-based tests for:
    - **Login email preservation**: POST `/auth/login` with valid email/password → returns `{ success: true, token, user }` + sets cookies `mi3_token`, `mi3_role`, `mi3_user` on `.laruta11.cl` with httpOnly/secure/SameSite=Lax → `login/page.tsx` saves token to localStorage → redirects to `/admin` or `/dashboard` based on `is_admin`
    - **Logout preservation**: POST `/auth/logout` with valid token → deletes token from `personal_access_tokens` BD → expires cookies → returns `{ success: true }`
    - **Route protection preservation**: Middleware redirects unauthenticated users to `/login` without loop; workers accessing `/admin` get redirected to `/dashboard`; admins can access `/admin`
    - **Multi-device preservation**: `authenticateUser()` only deletes tokens >30 days old via `$user->tokens()->where('created_at', '<', now()->subDays(30))->delete()` — recent tokens on other devices survive
    - **API auth preservation**: `apiFetch` sends Bearer token from localStorage in Authorization header; `ExtractTokenFromCookie` injects cookie token only when no Authorization header present
  - Verify all tests PASS on UNFIXED code
  - **EXPECTED OUTCOME**: Tests PASS (confirms baseline behavior to preserve)
  - Mark task complete when tests are written, run, and passing on unfixed code
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7_

- [x] 3. BUG 1+2: Create clear-session endpoint + mi3_auth_flag cookie (CRITICAL — fixes the loop)

  - [x] 3.1 Backend: Create POST /api/v1/auth/clear-session endpoint
    - Create public endpoint (no auth required) in `AuthController.php`
    - Endpoint expires all auth cookies server-side: `mi3_token`, `mi3_role`, `mi3_user`, `mi3_auth_flag`
    - Each cookie expired with `cookie('name', '', -1, '/', '.laruta11.cl', true, ...)`
    - Register route in `routes/api.php` under the public `auth` prefix group
    - _Bug_Condition: input.event == '401_response' AND input.context.cookieIsHttpOnly('mi3_token') == true AND input.context.deletionMethod == 'document.cookie' (always fails silently)_
    - _Expected_Behavior: Server-side endpoint successfully expires httpOnly cookies_
    - _Preservation: Logout flow continues to work; cookies still set on `.laruta11.cl` with same attributes_
    - _Requirements: 1.1, 1.2, 2.2_

  - [x] 3.2 Backend: Add mi3_auth_flag cookie in respondWithAuth() and googleCallback()
    - In `respondWithAuth()`: add `->cookie('mi3_auth_flag', '1', $maxAge, '/', '.laruta11.cl', true, false, false, 'Lax')` (NOT httpOnly so JS can read/delete it)
    - In `googleCallback()`: add same `mi3_auth_flag` cookie to the redirect response
    - In `logout()`: expire `mi3_auth_flag` alongside other cookies
    - In `clearSession()`: expire `mi3_auth_flag` alongside other cookies
    - _Bug_Condition: input.event == '401_response' AND input.context.cookie('mi3_token') EXISTS AND input.context.tokenIsValidInBackend == false → middleware sees cookie, assumes authenticated_
    - _Expected_Behavior: mi3_auth_flag (non-httpOnly) is the session indicator; JS can delete it to break the loop_
    - _Requirements: 1.2, 2.2_

  - [x] 3.3 Frontend: Update middleware.ts to check mi3_auth_flag instead of mi3_token
    - Replace `request.cookies.get('mi3_token')` with `request.cookies.get('mi3_auth_flag')`
    - Replace `request.cookies.get('mi3_role')` logic remains the same (mi3_role is not httpOnly)
    - When `mi3_auth_flag` is absent → user is not authenticated → redirect to `/login`
    - When `mi3_auth_flag` is present → user may be authenticated → allow through
    - _Bug_Condition: middleware checks mi3_token (httpOnly, not deletable from JS) → loop when token invalid_
    - _Expected_Behavior: middleware checks mi3_auth_flag (non-httpOnly, deletable from JS) → no loop_
    - _Requirements: 1.2, 2.2_

  - [x] 3.4 Frontend: Update 401 handler in api.ts to call /auth/clear-session
    - Replace `document.cookie = 'mi3_token=; ...; max-age=0'` lines with:
      - `await fetch(API_URL + '/api/v1/auth/clear-session', { method: 'POST', credentials: 'include' })`
      - `document.cookie = 'mi3_auth_flag=; path=/; domain=.laruta11.cl; max-age=0'` (this one works because mi3_auth_flag is NOT httpOnly)
      - `localStorage.removeItem('mi3_token')` and `localStorage.removeItem('mi3_user')`
      - `window.location.href = '/login'`
    - _Bug_Condition: JS tries document.cookie deletion on httpOnly cookies → fails silently_
    - _Expected_Behavior: Server clears httpOnly cookies; JS clears mi3_auth_flag + localStorage_
    - _Requirements: 1.1, 2.2_

  - [x] 3.5 Frontend: Update 401 handler in compras-api.ts with same logic
    - Same changes as api.ts: call `/auth/clear-session`, delete `mi3_auth_flag` via JS, clear localStorage, redirect
    - _Requirements: 1.1, 2.2_

  - [x] 3.6 Verify bug condition exploration test now passes (BUG 1+2 portion)
    - **Property 1: Expected Behavior** - Session Loop Broken
    - **IMPORTANT**: Re-run the SAME test from task 1 - do NOT write a new test
    - The BUG 1 and BUG 2 test cases from task 1 should now PASS
    - 401 handler calls clear-session (httpOnly cookies cleared server-side)
    - Middleware checks mi3_auth_flag (deletable from JS, breaks the loop)
    - **EXPECTED OUTCOME**: BUG 1+2 tests PASS (confirms loop is fixed)
    - _Requirements: 2.1, 2.2_

  - [x] 3.7 Verify preservation tests still pass
    - **Property 2: Preservation** - Auth Flows After Loop Fix
    - **IMPORTANT**: Re-run the SAME tests from task 2 - do NOT write new tests
    - Login email still works (now also sets mi3_auth_flag)
    - Logout still works (now also expires mi3_auth_flag + calls clear-session)
    - Route protection still works (middleware uses mi3_auth_flag)
    - **EXPECTED OUTCOME**: Tests PASS (confirms no regressions)
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

- [x] 4. BUG 3+7: Google OAuth token to localStorage + fix useAuth hook (HIGH)

  - [x] 4.1 Backend: Pass token as query param in Google OAuth redirect
    - In `googleCallback()`, change redirect URL from `$frontendUrl . $redirectTo` to `$frontendUrl . $redirectTo . '?token=' . urlencode($token)`
    - This redirects directly to /admin or /dashboard with the token — avoids double redirect through /login
    - The destination page reads the param, saves to localStorage, and cleans the URL
    - Token is passed via HTTPS redirect (encrypted in transit), consumed once, then cleared from URL
    - _Bug_Condition: Google OAuth callback sets httpOnly cookies but never passes token to frontend for localStorage_
    - _Expected_Behavior: Frontend receives token via query param, saves to localStorage, has Bearer token fallback_
    - _Requirements: 1.3, 2.3_

  - [x] 4.2 Frontend: Read ?token= param in app layout or dashboard/admin pages and save to localStorage
    - In a shared layout or useEffect in dashboard/admin pages, check for `searchParams.get('token')`
    - If token exists: `localStorage.setItem('mi3_token', token)`, then `router.replace(pathname)` to clean URL
    - This gives Google OAuth users the same localStorage token as email login users
    - _Bug_Condition: Google OAuth users have no localStorage token → no Bearer fallback post-redeploy_
    - _Expected_Behavior: Google OAuth users get token in localStorage → Bearer fallback works_
    - _Requirements: 1.3, 2.3_

  - [x] 4.3 Frontend: Fix useAuth.ts fetchUser() to work without localStorage token
    - Current bug: `fetchUser()` does `if (!token) { setLoading(false); return; }` — Google OAuth users with no localStorage token get `user = null`
    - Fix: Remove the early return. Always call `/auth/me` using `fetch` directly (NOT `apiFetch`) to avoid triggering the 401 cleanup handler
    - Use `fetch(API_URL + '/api/v1/auth/me', { headers: { ...Bearer if token exists }, credentials: 'include' })` — this way cookie auth works for Google users AND Bearer auth works for email users
    - If response is 200, parse user and set state. If 401 or error, set loading false silently (user is truly unauthenticated, no redirect)
    - IMPORTANT: Do NOT use `apiFetch` here because a 401 would trigger clear-session + redirect, which is wrong during initial page load
    - _Bug_Condition: useAuth.fetchUser() returns early when getToken() is null → Google OAuth users always see user=null_
    - _Expected_Behavior: fetchUser() tries cookie-based auth via /auth/me even without localStorage token_
    - _Requirements: 2.3, 2.5_

  - [x] 4.4 Verify bug condition exploration test now passes (BUG 3+7 portion)
    - **Property 1: Expected Behavior** - Google OAuth Token + useAuth Fix
    - **IMPORTANT**: Re-run the SAME test from task 1 - do NOT write a new test
    - BUG 3 test: Google OAuth callback now passes token in URL → localStorage has token
    - BUG 7 test: useAuth.fetchUser() now calls /auth/me even without localStorage token → user is not null
    - **EXPECTED OUTCOME**: BUG 3+7 tests PASS
    - _Requirements: 2.3, 2.5_

  - [x] 4.5 Verify preservation tests still pass
    - **Property 2: Preservation** - Auth Flows After Google OAuth Fix
    - **IMPORTANT**: Re-run the SAME tests from task 2 - do NOT write new tests
    - Email login still works (unaffected by Google OAuth changes)
    - Google OAuth login still works (now also saves token to localStorage)
    - **EXPECTED OUTCOME**: Tests PASS (confirms no regressions)
    - _Requirements: 3.1, 3.2, 3.3_

- [x] 5. BUG 4: Remove key:generate from Dockerfile

  - [x] 5.1 Remove `RUN php artisan key:generate --force --no-interaction` from Dockerfile
    - Delete line 39 from `mi3/backend/Dockerfile`
    - APP_KEY is already confirmed persistent as a Coolify environment variable
    - This prevents APP_KEY from being regenerated on each Docker build, which would invalidate all encrypted cookies
    - _Bug_Condition: Dockerfile runs key:generate → new APP_KEY on each build → cookies encrypted with old key become unreadable_
    - _Expected_Behavior: APP_KEY persists via Coolify env var → cookies remain valid across deploys_
    - _Requirements: 2.1, 2.5_

  - [x] 5.2 Verify Dockerfile no longer contains key:generate
    - Confirm the line is removed
    - Verify the rest of the Dockerfile is intact (composer, migrations, permissions, etc.)
    - _Requirements: 2.1_

- [x] 6. BUG 8: Unify logout implementations (MEDIUM)

  - [x] 6.1 Refactor auth.ts logout() to use clear-session + logout
    - Update `auth.ts logout()` to:
      1. Call `POST /api/v1/auth/clear-session` (credentials: include) to expire httpOnly cookies server-side
      2. Call `POST /api/v1/auth/logout` (credentials: include) to delete Sanctum token from BD
      3. `removeToken()` to clear localStorage
      4. `document.cookie = 'mi3_auth_flag=; path=/; domain=.laruta11.cl; max-age=0'` to clear the flag
      5. `window.location.href = '/login'`
    - _Bug_Condition: auth.ts and useAuth.ts have different logout implementations → inconsistent cleanup_
    - _Expected_Behavior: Single unified logout that clears everything: BD token, httpOnly cookies (server-side), localStorage, mi3_auth_flag_
    - _Requirements: 3.3_

  - [x] 6.2 Update useAuth.ts logout() to call auth.ts logout()
    - Import `logout` from `@/lib/auth` and delegate to it
    - Or inline the same logic: clear-session → logout API → removeToken → clear mi3_auth_flag → redirect
    - Ensure `setUser(null)` is called before redirect for React state consistency
    - _Requirements: 3.3_

  - [x] 6.3 Verify preservation tests still pass
    - **Property 2: Preservation** - Logout After Unification
    - **IMPORTANT**: Re-run the SAME tests from task 2 - do NOT write new tests
    - Logout still deletes token from BD, clears all cookies and localStorage, redirects to /login
    - **EXPECTED OUTCOME**: Tests PASS
    - _Requirements: 3.3_

- [x] 7. BUG 6: Deprecate session_token plaintext fallback (MEDIUM)

  - [x] 7.1 Remove plaintext session_token comparison from AuthService.php
    - In `loginWithEmail()`, remove the block:
      ```php
      if (!$passwordValid && !empty($user->session_token)) {
          $passwordValid = $user->session_token === $password;
      }
      ```
    - Only `Hash::check($password, $user->password)` should authenticate
    - Users who only have session_token (no hashed password) will need a password reset
    - _Bug_Condition: session_token === $password is plaintext comparison → if BD is compromised, passwords are exposed_
    - _Expected_Behavior: Only Hash::check() authenticates → passwords are never stored/compared in plaintext_
    - _Requirements: 2.1_

  - [x] 7.2 Create migration to hash existing session_tokens (REQUIRED before 7.1)
    - Create a Laravel migration that hashes any non-null `session_token` values in the `usuarios` table into the `password` field
    - For each user with session_token but no password: `$user->password = Hash::make($user->session_token)`
    - Then null out session_token fields to remove plaintext passwords from the database
    - MUST run BEFORE removing the fallback code in 7.1, otherwise users with only session_token get locked out
    - _Requirements: 2.1_

  - [x] 7.3 Verify preservation tests still pass
    - **Property 2: Preservation** - Login After Plaintext Removal
    - **IMPORTANT**: Re-run the SAME tests from task 2 - do NOT write new tests
    - Email login with hashed password still works
    - Google OAuth login unaffected
    - **EXPECTED OUTCOME**: Tests PASS
    - _Requirements: 3.1, 3.2_

- [x] 8. BUG 9: maxAge consistency (LOW — cosmetic, optional)

  - [x] 8.1 Standardize maxAge calculation in AuthController.php
    - In `respondWithAuth()`: `$maxAge = 30 * 24 * 60` (30 days in minutes — correct for Laravel's `cookie()` helper)
    - In `googleCallback()`: `$maxAge = 30 * 24 * 60 * 60` then uses `$maxAge / 60` — same result but confusing
    - Standardize both to use `$maxAge = 30 * 24 * 60` (minutes) directly, no division
    - _Requirements: 3.7_

- [x] 9. Checkpoint - Ensure all tests pass
  - Run all exploration tests (Property 1) — all should PASS after fixes
  - Run all preservation tests (Property 2) — all should still PASS
  - Manual verification checklist:
    - [ ] Login with email/password → token in localStorage + cookies + mi3_auth_flag set
    - [ ] Login with Google OAuth → token in localStorage (via ?token= param) + cookies + mi3_auth_flag set
    - [ ] useAuth hook returns user for both email and Google OAuth users
    - [ ] 401 response → calls /auth/clear-session → clears mi3_auth_flag → redirects to /login (no loop)
    - [ ] Logout → clears BD token + httpOnly cookies (server-side) + localStorage + mi3_auth_flag → /login
    - [ ] Simulate redeploy: restart backend container → verify sessions survive (Bearer token from localStorage)
    - [ ] Workers cannot access /admin routes
    - [ ] Dockerfile no longer has key:generate
  - Ensure all tests pass, ask the user if questions arise.
