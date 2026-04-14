# Fix Sesiones - Bugfix Design

## Overview

Las sesiones de mi3 se pierden completamente cuando el backend Laravel se redeploya en Docker/Coolify. Se identificaron 6 bugs interrelacionados que causan un loop infinito 401 → /login → redirect → 401. El fix aborda cada bug de forma quirúrgica: (1) crear endpoint server-side para limpiar cookies httpOnly, (2) usar cookie no-httpOnly `mi3_auth_flag` como flag de sesión para Next.js middleware, (3) pasar token via query param en Google OAuth callback, (4) eliminar `key:generate` del Dockerfile, (5) excluir `mi3_token` de la encriptación de cookies Laravel, (6) deprecar fallback plaintext de session_token.

## Glossary

- **Bug_Condition (C)**: Conjunto de condiciones que causan pérdida de sesión — redeploy del backend con cookies encriptadas, httpOnly cookies no eliminables desde JS, Google OAuth sin localStorage, APP_KEY regenerada, cookie encriptada leída como plaintext
- **Property (P)**: Las sesiones de todos los usuarios (email + Google OAuth) sobreviven un redeploy del backend sin loop de redirección
- **Preservation**: Login normal (email/password y Google), logout explícito, protección de rutas por rol, y comportamiento multi-dispositivo deben seguir funcionando exactamente igual
- **`ExtractTokenFromCookie`**: Middleware en `mi3/backend/app/Http/Middleware/ExtractTokenFromCookie.php` que extrae `mi3_token` de la cookie y lo inyecta como Bearer header
- **`mi3_token`**: Cookie httpOnly que contiene el plainTextToken de Sanctum (actualmente encriptada por Laravel)
- **`mi3_auth_flag`**: Nueva cookie no-httpOnly que actúa como flag de "sesión activa" para el middleware de Next.js
- **`apiFetch`**: Función en `mi3/frontend/lib/api.ts` que maneja todas las llamadas API incluyendo el 401 handler

## Bug Details

### Bug Condition

El bug se manifiesta cuando se combinan múltiples fallas en el flujo de autenticación. El sistema tiene 6 puntos de falla que, juntos, causan pérdida total de sesión en cada redeploy.

**Formal Specification:**
```
FUNCTION isBugCondition(input)
  INPUT: input of type { event: 'redeploy' | '401_response' | 'google_oauth_callback' | 'cookie_read' | 'login_plaintext', context: SystemState }
  OUTPUT: boolean

  // BUG 1: JS intenta eliminar cookie httpOnly (siempre falla silenciosamente)
  bug1 := input.event == '401_response'
          AND input.context.cookieIsHttpOnly('mi3_token') == true
          AND input.context.deletionMethod == 'document.cookie'

  // BUG 2: Next.js middleware solo verifica existencia, no validez
  bug2 := input.event == '401_response'
          AND input.context.cookie('mi3_token') EXISTS
          AND input.context.tokenIsValidInBackend('mi3_token') == false

  // BUG 3: Google OAuth no guarda token en localStorage
  bug3 := input.event == 'google_oauth_callback'
          AND input.context.localStorage('mi3_token') == null

  // BUG 4: APP_KEY se regenera en cada Docker build
  bug4 := input.event == 'redeploy'
          AND input.context.newAppKey != input.context.previousAppKey

  // BUG 5: ExtractTokenFromCookie lee valor encriptado
  bug5 := input.event == 'cookie_read'
          AND input.context.middlewareOrder('ExtractTokenFromCookie') < input.context.middlewareOrder('EncryptCookies')
          AND input.context.cookieIsEncrypted('mi3_token') == true

  // BUG 6: session_token plaintext fallback
  bug6 := input.event == 'login_plaintext'
          AND input.context.passwordField == null
          AND input.context.sessionTokenMatchesPlaintext == true

  RETURN bug1 OR bug2 OR bug3 OR bug4 OR bug5 OR bug6
END FUNCTION
```

### Examples

- **BUG 1**: Usuario logueado recibe 401 → `api.ts` ejecuta `document.cookie = 'mi3_token=; max-age=0'` → cookie httpOnly NO se elimina → middleware Next.js ve cookie existente → redirige a dashboard → 401 → loop infinito
- **BUG 2**: Backend se redeploya → cookie `mi3_token` sigue en navegador pero token inválido → middleware Next.js ve cookie y asume autenticado → redirige a dashboard → API devuelve 401 → loop
- **BUG 3**: Usuario hace login con Google → backend setea cookies httpOnly y redirige → frontend NO tiene token en localStorage → `getToken()` retorna null → `apiFetch` no envía Bearer header → depende 100% de cookie que puede ser inválida post-redeploy
- **BUG 4**: Coolify rebuilds Docker image → `php artisan key:generate --force` genera nueva APP_KEY → todas las cookies encriptadas con la key anterior son ilegibles → 401 masivo
- **BUG 5**: Request llega → `ExtractTokenFromCookie` (prepended) lee `mi3_token` → valor está encriptado por Laravel → inyecta `Bearer eyJpdiI6...` (encriptado) → Sanctum no reconoce → 401
- **BUG 6**: Usuario con `session_token = "mipassword123"` en BD → login con password "mipassword123" → match por comparación plaintext → funciona pero es inseguro si BD se compromete

## Expected Behavior

### Preservation Requirements

**Unchanged Behaviors:**
- Login con email/password debe seguir funcionando: enviar credenciales, recibir token + cookies, guardar en localStorage, redirigir según rol
- Login con Google OAuth debe seguir funcionando: redirect a Google, callback, setear cookies, redirigir según rol
- Logout explícito debe seguir eliminando token de BD, limpiar cookies y localStorage, redirigir a /login
- Usuarios sin token válido deben ser redirigidos a /login sin loop
- Workers no pueden acceder a rutas /admin (redirigidos a /dashboard)
- Sesiones en otros dispositivos no se invalidan (solo tokens >30 días se limpian)
- Cookies `mi3_token`, `mi3_role`, `mi3_user` se setean en dominio `.laruta11.cl` con secure y SameSite=Lax

**Scope:**
Todos los flujos que NO involucran redeploy del backend, limpieza de cookies httpOnly desde JS, o Google OAuth sin localStorage deben funcionar exactamente igual. Esto incluye:
- Login/logout normal sin redeploy
- Navegación entre páginas con sesión válida
- Llamadas API con Bearer token válido desde localStorage
- Protección de rutas por rol en middleware Next.js

## Hypothesized Root Cause

Based on the code analysis, the root causes are confirmed:

1. **Cookie httpOnly no eliminable desde JS (BUG 1)**: En `api.ts:42` y `compras-api.ts:14`, el 401 handler ejecuta `document.cookie = 'mi3_token=; path=/; domain=.laruta11.cl; max-age=0'`. Pero `mi3_token` se setea con `httpOnly: true` en `AuthController.php:65`. JavaScript NO puede leer ni eliminar cookies httpOnly — la operación falla silenciosamente.

2. **Middleware Next.js no valida token (BUG 2)**: En `middleware.ts:17`, solo hace `const token = request.cookies.get('mi3_token')?.value` y verifica `if (!token)`. No valida si el token es realmente válido en el backend. Post-redeploy, la cookie existe pero el token puede ser inválido.

3. **Google OAuth sin localStorage (BUG 3)**: En `AuthController.php:120-127`, `googleCallback` setea cookies httpOnly y redirige a frontend. El frontend nunca recibe el `plainTextToken` para guardarlo en localStorage. Comparar con el login por email donde `login/page.tsx:42` hace `localStorage.setItem('mi3_token', data.token)`.

4. **APP_KEY regenerada en Dockerfile (BUG 4)**: `Dockerfile:39` ejecuta `RUN php artisan key:generate --force`. Si Coolify no inyecta un APP_KEY persistente como variable de entorno, cada build genera una nueva key, invalidando todas las cookies encriptadas.

5. **ExtractTokenFromCookie lee cookie encriptada (BUG 5)**: En `bootstrap/app.php:22`, el middleware se registra con `$middleware->prepend()`, ejecutándose ANTES de `EncryptCookies`. `$request->cookie('mi3_token')` retorna el valor encriptado, no el plainTextToken.

6. **Fallback plaintext de session_token (BUG 6)**: En `AuthService.php:31-32`, `$user->session_token === $password` compara en plaintext. Si la BD se compromete, los passwords quedan expuestos.

## Correctness Properties

Property 1: Bug Condition - Sesiones sobreviven redeploy del backend

_For any_ estado del sistema donde el backend se redeploya Y un usuario tiene un token Sanctum válido en `personal_access_tokens` (BD), la función de autenticación fija SHALL continuar autenticando al usuario usando el Bearer token desde localStorage (para login email) o la cookie `mi3_token` desencriptada correctamente (con APP_KEY persistente), sin entrar en loop de redirección.

**Validates: Requirements 2.1, 2.2, 2.5**

Property 2: Bug Condition - Google OAuth guarda token en localStorage

_For any_ login via Google OAuth callback, el sistema fijo SHALL pasar el plainTextToken al frontend para que se guarde en localStorage, asegurando paridad con el flujo de login por email y supervivencia post-redeploy.

**Validates: Requirements 2.3**

Property 3: Bug Condition - Cookie mi3_token se lee correctamente

_For any_ request con cookie `mi3_token`, el middleware `ExtractTokenFromCookie` SHALL inyectar el plainTextToken (no el valor encriptado) como Bearer header, permitiendo que Sanctum autentique correctamente.

**Validates: Requirements 2.4**

Property 4: Bug Condition - 401 handler limpia cookies httpOnly via servidor

_For any_ respuesta 401, el handler del frontend SHALL llamar al endpoint `/auth/clear-session` del backend para eliminar cookies httpOnly server-side, Y eliminar la cookie `mi3_auth_flag` (no-httpOnly) desde JS, garantizando que el middleware Next.js no vea una sesión activa.

**Validates: Requirements 2.2**

Property 5: Preservation - Login, logout y protección de rutas sin cambios

_For any_ input que NO involucre redeploy del backend ni los bugs identificados (login normal email/password, login Google sin redeploy, logout explícito, navegación con sesión válida, protección de rutas por rol), el sistema fijo SHALL producir exactamente el mismo comportamiento que el sistema original.

**Validates: Requirements 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7**

## Fix Implementation

### Changes Required

Assuming our root cause analysis is correct:

**File**: `mi3/backend/app/Http/Controllers/Auth/AuthController.php`

**Changes**:
1. **Nuevo endpoint `clearSession`**: Crear `POST /api/v1/auth/clear-session` (público, sin auth) que elimine las cookies httpOnly `mi3_token`, `mi3_role`, `mi3_user` server-side
2. **Google OAuth callback con token en URL**: Modificar `googleCallback()` para incluir `?token={plainTextToken}` en la URL de redirect al frontend
3. **Setear cookie `mi3_auth_flag`**: En `respondWithAuth()` y `googleCallback()`, agregar cookie `mi3_auth_flag=true` (no-httpOnly, secure, SameSite=Lax) como flag de sesión para Next.js middleware

---

**File**: `mi3/backend/bootstrap/app.php`

**Changes**:
4. **Excluir `mi3_token` de encriptación**: Usar `$middleware->encryptCookies(except: ['mi3_token'])` para que `ExtractTokenFromCookie` lea el plainTextToken directamente

---

**File**: `mi3/backend/Dockerfile`

**Changes**:
5. **Eliminar `key:generate`**: Remover la línea `RUN php artisan key:generate --force` — Coolify debe inyectar APP_KEY como variable de entorno persistente

---

**File**: `mi3/backend/routes/api.php`

**Changes**:
6. **Registrar ruta `clear-session`**: Agregar `Route::post('auth/clear-session', [AuthController::class, 'clearSession'])` en el grupo público de auth

---

**File**: `mi3/frontend/lib/api.ts`

**Changes**:
7. **401 handler mejorado**: Reemplazar `document.cookie` deletion con llamada a `POST /api/v1/auth/clear-session` (credentials: include), luego eliminar `mi3_auth_flag` cookie desde JS, luego redirect a /login

---

**File**: `mi3/frontend/lib/compras-api.ts`

**Changes**:
8. **401 handler mejorado**: Misma lógica que api.ts — llamar a clear-session endpoint en vez de intentar eliminar cookies httpOnly desde JS

---

**File**: `mi3/frontend/middleware.ts`

**Changes**:
9. **Verificar `mi3_auth_flag` en vez de `mi3_token`**: Cambiar `request.cookies.get('mi3_token')` por `request.cookies.get('mi3_auth_flag')` como indicador de sesión activa. La cookie `mi3_auth_flag` es no-httpOnly y SÍ puede ser eliminada desde JS, rompiendo el loop

---

**File**: `mi3/frontend/app/login/page.tsx`

**Changes**:
10. **Leer token de query param (Google OAuth)**: En `useEffect`, verificar si hay `?token=` en la URL. Si existe, guardarlo en localStorage y limpiar el query param

---

**File**: `mi3/frontend/lib/auth.ts`

**Changes**:
11. **Actualizar `logout()`**: Llamar a `/auth/clear-session` además de `/auth/logout`, y eliminar cookie `mi3_auth_flag` desde JS

---

**File**: `mi3/backend/app/Services/Auth/AuthService.php`

**Changes**:
12. **Deprecar fallback plaintext**: Eliminar la comparación `$user->session_token === $password` en `loginWithEmail()`. Solo usar `Hash::check()`.

## Testing Strategy

### Validation Approach

La estrategia de testing sigue dos fases: primero, surfear counterexamples que demuestren los bugs en código sin fixear, luego verificar que el fix funciona y preserva el comportamiento existente.

### Exploratory Bug Condition Checking

**Goal**: Surfear counterexamples que demuestren los 6 bugs ANTES de implementar el fix. Confirmar o refutar el análisis de root cause.

**Test Plan**: Escribir tests que simulen cada condición de bug y verifiquen que el sistema actual falla. Ejecutar en código sin fixear para observar las fallas.

**Test Cases**:
1. **Cookie httpOnly deletion test**: Verificar que `document.cookie = 'mi3_token=; max-age=0'` NO elimina una cookie httpOnly (fallará silenciosamente en código actual)
2. **Middleware token validity test**: Simular request con cookie `mi3_token` que contiene token inválido → middleware Next.js redirige a dashboard en vez de login (falla en código actual)
3. **Google OAuth localStorage test**: Simular Google OAuth callback → verificar que localStorage NO tiene `mi3_token` después del redirect (falla en código actual)
4. **APP_KEY rotation test**: Encriptar cookie con APP_KEY_1, intentar desencriptar con APP_KEY_2 → falla (demuestra BUG 4)
5. **Encrypted cookie extraction test**: Verificar que `ExtractTokenFromCookie` inyecta valor encriptado como Bearer header (falla en código actual)
6. **Plaintext password test**: Verificar que login acepta password que coincide con session_token en plaintext (demuestra BUG 6)

**Expected Counterexamples**:
- Cookie httpOnly persiste después de intento de eliminación desde JS
- Middleware redirige a dashboard con token inválido, causando loop
- Google OAuth users no tienen Bearer token fallback

### Fix Checking

**Goal**: Verificar que para todos los inputs donde la bug condition se cumple, la función fija produce el comportamiento esperado.

**Pseudocode:**
```
FOR ALL input WHERE isBugCondition(input) DO
  result := fixedSystem(input)
  ASSERT expectedBehavior(result)
END FOR
```

**Specific checks:**
- POST `/auth/clear-session` elimina cookies httpOnly correctamente
- Middleware Next.js con `mi3_auth_flag` no causa loop cuando token es inválido
- Google OAuth callback pasa token en URL y frontend lo guarda en localStorage
- Sin `key:generate` en Dockerfile, APP_KEY persiste entre deploys
- `mi3_token` excluida de encriptación → `ExtractTokenFromCookie` lee plainTextToken
- Login rechaza session_token plaintext, solo acepta Hash::check

### Preservation Checking

**Goal**: Verificar que para todos los inputs donde la bug condition NO se cumple, el sistema fijo produce el mismo resultado que el original.

**Pseudocode:**
```
FOR ALL input WHERE NOT isBugCondition(input) DO
  ASSERT originalSystem(input) = fixedSystem(input)
END FOR
```

**Testing Approach**: Property-based testing es recomendado para preservation checking porque genera muchos test cases automáticamente y detecta edge cases que tests manuales podrían perder.

**Test Plan**: Observar comportamiento en código sin fixear para login normal, logout, y navegación, luego escribir property-based tests que capturen ese comportamiento.

**Test Cases**:
1. **Login email preservation**: Verificar que login email/password sigue funcionando — envía credenciales, recibe token + cookies + mi3_auth_flag, guarda en localStorage, redirige según rol
2. **Login Google preservation**: Verificar que Google OAuth sigue funcionando — redirect, callback, cookies + token en localStorage, redirect según rol
3. **Logout preservation**: Verificar que logout elimina token de BD, limpia cookies (via clear-session) y localStorage, redirige a /login
4. **Route protection preservation**: Verificar que usuarios sin sesión van a /login, workers no acceden a /admin
5. **Multi-device preservation**: Verificar que login en un dispositivo no invalida sesiones en otros

### Unit Tests

- Test `clearSession` endpoint: verifica que elimina las 3 cookies httpOnly + mi3_auth_flag
- Test `respondWithAuth` setea cookie `mi3_auth_flag` además de las existentes
- Test `googleCallback` incluye `?token=` en redirect URL
- Test `ExtractTokenFromCookie` lee plainTextToken (no encriptado) de cookie
- Test `loginWithEmail` rechaza session_token plaintext match
- Test 401 handler en api.ts llama a clear-session antes de redirect
- Test middleware.ts usa `mi3_auth_flag` para decisiones de routing

### Property-Based Tests

- Generar estados de sistema aleatorios (con/sin redeploy, con/sin cookies válidas) y verificar que el flujo de autenticación no entra en loop
- Generar configuraciones de cookies aleatorias y verificar que `ExtractTokenFromCookie` siempre inyecta un token válido o no inyecta nada
- Generar inputs de login aleatorios y verificar que solo Hash::check autentica, nunca plaintext comparison

### Integration Tests

- Test flujo completo: login email → usar app → simular redeploy (invalidar cookies) → verificar que Bearer token desde localStorage mantiene sesión
- Test flujo completo: login Google → verificar token en localStorage → simular redeploy → verificar sesión sobrevive
- Test flujo 401: recibir 401 → clear-session → mi3_auth_flag eliminada → middleware redirige a /login sin loop
- Test SSH en producción: verificar que APP_KEY persiste entre deploys de Coolify
- Test multi-dispositivo: login en 2 dispositivos → redeploy → ambos mantienen sesión
