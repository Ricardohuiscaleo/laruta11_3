# Bugfix Requirements Document

## Introduction

Las sesiones de mi3 (mi.laruta11.cl) se pierden cuando el backend Laravel se redeploya en Coolify (Docker), causando un loop infinito de redirección: 401 → /login → redirect → 401. Esto afecta a TODOS los usuarios logueados simultáneamente.

El problema raíz tiene múltiples capas:
1. Los tokens Sanctum persisten en BD (personal_access_tokens), pero las cookies httpOnly del navegador pueden contener valores encriptados por Laravel que el nuevo contenedor no puede desencriptar (APP_KEY diferente o cookie encryption).
2. El middleware de Next.js (`middleware.ts`) solo verifica la existencia de la cookie `mi3_token` para decidir si el usuario está autenticado, sin validar si el token es realmente válido en el backend.
3. El flujo de Google OAuth callback no guarda el token en localStorage, dejando a esos usuarios sin el mecanismo de fallback Bearer token.
4. Cuando el 401 handler limpia cookies y redirige a `/login`, el middleware de Next.js puede no ver las cookies limpiadas inmediatamente, generando el loop.

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN el backend Docker se redeploya Y el usuario tiene una cookie `mi3_token` existente THEN el sistema devuelve 401 en todas las llamadas API porque la cookie encriptada por Laravel no puede ser desencriptada por el nuevo contenedor (si APP_KEY cambió) o la sesión de archivos se perdió

1.2 WHEN el frontend recibe un 401 Y el 401 handler limpia cookies/localStorage Y redirige a `/login` THEN el middleware de Next.js puede aún ver la cookie `mi3_token` (por timing de eliminación cross-domain) y redirige de vuelta a `/admin` o `/dashboard`, creando un loop infinito de redirección

1.3 WHEN un usuario se autentica via Google OAuth callback THEN el backend setea cookies httpOnly pero NO retorna el token en la URL/response para que el frontend lo guarde en localStorage, dejando al usuario sin el mecanismo de fallback Bearer token desde localStorage

1.4 WHEN el middleware `ExtractTokenFromCookie` lee la cookie `mi3_token` Y Laravel tiene `EncryptCookies` middleware activo THEN el middleware inyecta el valor encriptado (no el plainTextToken) como Bearer header, causando que Sanctum no reconozca el token

1.5 WHEN un usuario worker (no admin) tiene sesión activa Y el backend se redeploya THEN el worker pierde su sesión igual que un admin, sin diferencia en el comportamiento de recuperación

### Expected Behavior (Correct)

2.1 WHEN el backend Docker se redeploya Y el usuario tiene un token Sanctum válido en `personal_access_tokens` (BD) THEN el sistema SHALL continuar autenticando al usuario usando el Bearer token desde localStorage, sin depender de cookies de sesión de archivos

2.2 WHEN el frontend recibe un 401 Y limpia las credenciales THEN el sistema SHALL garantizar que las cookies se eliminen efectivamente antes de redirigir a `/login`, Y el middleware de Next.js SHALL verificar la validez real del token (no solo su existencia) para evitar el loop de redirección

2.3 WHEN un usuario se autentica via Google OAuth callback THEN el sistema SHALL pasar el plainTextToken al frontend (via query param o mecanismo seguro) para que se guarde en localStorage, asegurando paridad con el flujo de login por email

2.4 WHEN el middleware `ExtractTokenFromCookie` lee la cookie `mi3_token` THEN el sistema SHALL desencriptar la cookie si Laravel la encriptó, O la cookie SHALL ser excluida de la encriptación de Laravel, para que el valor inyectado como Bearer header sea el plainTextToken válido de Sanctum

2.5 WHEN cualquier usuario (admin o worker) tiene sesión activa Y el backend se redeploya THEN el sistema SHALL mantener la sesión activa para todos los roles, usando tokens persistidos en BD + localStorage como fuente de verdad

### Unchanged Behavior (Regression Prevention)

3.1 WHEN un usuario hace login con email/password Y el backend NO se ha redeployado THEN el sistema SHALL CONTINUE TO autenticar correctamente via cookies httpOnly y/o Bearer token desde localStorage

3.2 WHEN un usuario hace login con Google OAuth Y el backend NO se ha redeployado THEN el sistema SHALL CONTINUE TO autenticar correctamente y redirigir al dashboard correspondiente según su rol

3.3 WHEN un usuario hace logout explícitamente THEN el sistema SHALL CONTINUE TO eliminar el token de la BD, limpiar cookies y localStorage, y redirigir a `/login`

3.4 WHEN un usuario sin token válido intenta acceder a rutas protegidas THEN el sistema SHALL CONTINUE TO redirigir a `/login` sin loop

3.5 WHEN un usuario worker intenta acceder a rutas `/admin` THEN el sistema SHALL CONTINUE TO redirigir a `/dashboard`

3.6 WHEN un usuario hace login en un dispositivo THEN el sistema SHALL CONTINUE TO no invalidar sesiones en otros dispositivos (tokens >30 días se limpian, pero los recientes se mantienen)

3.7 WHEN las cookies `mi3_token`, `mi3_role`, `mi3_user` se setean en login THEN el sistema SHALL CONTINUE TO setearlas en dominio `.laruta11.cl` con httpOnly, secure, y SameSite=Lax
