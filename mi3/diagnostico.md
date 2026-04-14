# Diagnostico de Sesiones - mi3 (mi.laruta11.cl)

**Fecha:** 2026-04-14
**Proyecto:** mi3 — Backend Laravel 11 + Sanctum / Frontend Next.js 14
**Problema reportado:** Loop infinito de redireccion 401 -> /login -> redirect -> 401 despues de cada redeploy en Coolify (Docker)

---

## Resumen Ejecutivo

Se identificaron **6 bugs activos** que en conjunto causan la perdida total de sesiones al redeployar el backend. El problema no es un solo bug sino una cadena de fallas que se amplifican entre si. Los 3 mas criticos son:

1. La cookie `mi3_token` es **httpOnly** — JavaScript NO puede borrarla al recibir un 401
2. El middleware de Next.js solo verifica que la cookie **exista**, no que sea valida
3. Google OAuth no guarda el token en localStorage (sin fallback Bearer)

Estos 3 combinados producen el loop infinito de redireccion.

---

## BUG 1 (CRITICO): JavaScript no puede borrar la cookie httpOnly `mi3_token`

### Donde esta el problema

**Backend** — `AuthController.php:65`:
```php
->cookie('mi3_token', $result['token'], $maxAge, '/', '.laruta11.cl', true, true, false, 'Lax')
//                                                       secure=true ^^  httpOnly=true ^^
```

**Frontend** — `api.ts:40`:
```javascript
document.cookie = 'mi3_token=; path=/; domain=.laruta11.cl; max-age=0';
```

### Que pasa

`mi3_token` se setea con `httpOnly=true`, lo que significa que `document.cookie` **no puede leerla ni borrarla**. Cuando el 401 handler intenta limpiar cookies, `mi3_token` sobrevive silenciosamente.

### Consecuencia directa

Despues de un 401:
1. Frontend borra localStorage (funciona)
2. Frontend intenta borrar mi3_token via document.cookie (**FALLA silenciosamente**)
3. Frontend redirige a `/login`
4. Next.js middleware lee `mi3_token` del request (sigue ahi)
5. Middleware ve token existente -> redirige a `/admin` o `/dashboard`
6. Dashboard hace API call -> recibe 401
7. **LOOP INFINITO**

### Archivos afectados
- `frontend/lib/api.ts:33-44`
- `frontend/middleware.ts:17,21-24,32-34`
- `backend/app/Http/Controllers/Auth/AuthController.php:65`

---

## BUG 2 (CRITICO): Next.js middleware no valida el token, solo su existencia

### Donde esta el problema

**Frontend** — `middleware.ts:17,32`:
```typescript
const token = request.cookies.get('mi3_token')?.value;
// ...
if (!token) {
  return NextResponse.redirect(new URL('/login', request.url));
}
```

### Que pasa

El middleware solo hace `if (!token)` — verifica que la cookie exista, no que el token sea valido. Una cookie corrupta, expirada, o encriptada con un APP_KEY viejo pasa el check igual.

### Consecuencia directa

Un usuario con un token invalido en la cookie nunca llega a `/login` porque el middleware lo redirige de vuelta al dashboard. Esto es la otra mitad del loop infinito.

### Archivos afectados
- `frontend/middleware.ts:17,21-24,32-34`

---

## BUG 3 (CRITICO): Google OAuth no guarda token en localStorage

### Donde esta el problema

**Backend** — `AuthController.php:131-135`:
```php
return redirect()->away($frontendUrl . $redirectTo)
    ->cookie('mi3_token', $token, ...)
    ->cookie('mi3_role', $role, ...)
    ->cookie('mi3_user', json_encode($user), ...);
```

### Que pasa

El callback de Google OAuth hace un redirect HTTP con cookies. El frontend **nunca recibe el token en el body** de la respuesta, asi que no puede guardarlo en localStorage.

Comparacion:
| Flujo | Cookie mi3_token | localStorage mi3_token |
|-------|:---:|:---:|
| Email login | Si | Si (via `data.token`) |
| Google OAuth | Si | **NO** |

### Consecuencia directa

Los usuarios de Google OAuth dependen 100% de las cookies. Si las cookies se invalidan (redeploy, APP_KEY, etc.), no tienen fallback Bearer. Los usuarios de email login al menos tienen el token en localStorage como respaldo.

### Archivos afectados
- `backend/app/Http/Controllers/Auth/AuthController.php:110-136`
- `frontend/app/login/page.tsx:54-56`

---

## BUG 4 (ALTO): APP_KEY se regenera en cada Docker build

### Donde esta el problema

**Backend** — `Dockerfile:39`:
```dockerfile
RUN php artisan key:generate --force --no-interaction
```

### Que pasa

Cada `docker build` genera un nuevo APP_KEY y lo escribe en `.env`. Si Coolify inyecta un APP_KEY persistente como variable de entorno en runtime, el de runtime tiene precedencia y esto **no es un problema**. Pero si Coolify NO tiene APP_KEY configurado, cada redeploy cambia la key.

### Consecuencia directa

Laravel usa APP_KEY para:
- Encriptar cookies (via EncryptCookies middleware)
- Encriptar sesiones
- Firmar URLs

Si el APP_KEY cambia, **todas las cookies encriptadas de todos los usuarios se vuelven ilegibles** instantaneamente.

**NOTA:** En Laravel 11, las rutas API por defecto NO incluyen `EncryptCookies` en su middleware stack. Las cookies se setean via `->cookie()` en las responses, y Laravel las encripta al enviarlas. Al leerlas, `$request->cookie()` las desencripta. Si ExtractTokenFromCookie corre antes de la desencripcion, lee basura.

### Verificacion necesaria
- Confirmar si Coolify tiene APP_KEY como variable de entorno persistente
- Si no lo tiene, este es el trigger principal de la perdida de sesiones

### Archivos afectados
- `backend/Dockerfile:39`
- `backend/app/Http/Middleware/ExtractTokenFromCookie.php:23`

---

## BUG 5 (MEDIO): ExtractTokenFromCookie puede leer cookie encriptada

### Donde esta el problema

**Backend** — `bootstrap/app.php:20`:
```php
$middleware->prepend(\App\Http\Middleware\ExtractTokenFromCookie::class);
```

**Backend** — `ExtractTokenFromCookie.php:23-25`:
```php
$token = $request->cookie('mi3_token');
if ($token) {
    $request->headers->set('Authorization', 'Bearer ' . $token);
}
```

### Que pasa

`ExtractTokenFromCookie` esta **prepended** al middleware stack, lo que significa que corre **antes** que `EncryptCookies`. Si Laravel encripta las cookies de respuesta (comportamiento por defecto), el valor leido por `$request->cookie()` en este punto podria ser el valor encriptado, no el plainTextToken.

### Consecuencia directa

El Bearer token inyectado seria algo como `eyJpdiI6Im...` (base64 de la encripcion de Laravel) en lugar del formato Sanctum `1|abc123...`. Sanctum no lo reconoce -> 401.

**NOTA:** Esto depende de si `EncryptCookies` esta activo en la pipeline de API. En Laravel 11, el grupo API por defecto no lo incluye, pero la configuracion de Sanctum lo referencia. Requiere verificacion en produccion.

### Archivos afectados
- `backend/bootstrap/app.php:20`
- `backend/app/Http/Middleware/ExtractTokenFromCookie.php:22-26`

---

## BUG 6 (BAJO): session_token como fallback de password en plaintext

### Donde esta el problema

**Backend** — `AuthService.php:31-32`:
```php
if (!$passwordValid && !empty($user->session_token)) {
    $passwordValid = $user->session_token === $password;
}
```

### Que pasa

Si el password hasheado no coincide, el sistema compara el password enviado con `session_token` en la BD usando `===` (comparacion en plaintext). Esto implica que `session_token` se almacena sin hashear y se compara directamente.

### Riesgo de seguridad
- Si la BD se compromete, los session_tokens estan en texto plano
- Es un mecanismo legacy que deberia depreciarse

### Archivos afectados
- `backend/app/Services/Auth/AuthService.php:31-32`

---

## Cadena de Fallas Completa (El Loop)

```
REDEPLOY DOCKER
      |
      v
APP_KEY nuevo (si no es persistente en Coolify)
      |
      v
Cookies encriptadas con key viejo se vuelven ilegibles
      |
      v
Usuario hace request -> Backend no puede leer cookie -> 401
      |
      v
Frontend recibe 401 en api.ts:33
      |
      v
Intenta borrar mi3_token via document.cookie (FALLA - httpOnly)
      |
      v
Borra localStorage.mi3_token (funciona)
      |
      v
Redirige a /login via window.location.href
      |
      v
Next.js middleware.ts lee mi3_token de cookies (SIGUE AHI)
      |
      v
Middleware ve token existente -> redirect a /admin
      |
      v
/admin carga -> hace apiFetch -> no hay localStorage token
  + cookies invalidas -> 401
      |
      v
VUELVE AL PASO "Frontend recibe 401"
      |
      v
LOOP INFINITO
```

---

## Matriz de Impacto por Tipo de Usuario

| Escenario | Email Login | Google OAuth |
|-----------|:-----------:|:------------:|
| Sin redeploy | OK | OK |
| Redeploy (APP_KEY persiste) | OK (Bearer desde localStorage) | **ROTO** (sin localStorage, cookie puede fallar) |
| Redeploy (APP_KEY cambia) | **LOOP** (cookie httpOnly no se borra) | **LOOP** (sin localStorage + cookie corrupta) |
| Logout explicito | OK | OK |

---

## Recomendaciones de Fix (Orden de Prioridad)

### Fix 1: Permitir borrar mi3_token desde el frontend

Cambiar `mi3_token` a `httpOnly=false` (como mi3_role y mi3_user) **O** crear un endpoint `/auth/clear-session` que borre las cookies httpOnly desde el servidor.

**Opcion recomendada:** Endpoint server-side, porque mantener httpOnly es mejor para seguridad.

### Fix 2: Middleware Next.js debe validar token, no solo existencia

Agregar un flag secundario (ej: `mi3_authenticated=true` como cookie no-httpOnly) que el 401 handler SI pueda borrar. El middleware checkea ese flag en lugar de mi3_token.

### Fix 3: Google OAuth debe pasar token al frontend

En `googleCallback()`, redirigir con el token como query param:
```
redirect($frontendUrl . $redirectTo . '?token=' . $token)
```
Y en el frontend, leer el query param y guardarlo en localStorage.

### Fix 4: APP_KEY persistente en Coolify

Remover linea 39 del Dockerfile. Asegurar que Coolify inyecte `APP_KEY` como variable de entorno persistente.

### Fix 5: Resolver ordering de ExtractTokenFromCookie

Mover ExtractTokenFromCookie despues de EncryptCookies, o excluir `mi3_token` de la encripcion.

### Fix 6: Deprecar session_token plaintext

Migrar todos los session_token a passwords hasheados y eliminar el fallback.

---

## Archivos Clave del Sistema de Auth

| Archivo | Rol |
|---------|-----|
| `backend/app/Http/Controllers/Auth/AuthController.php` | Login, logout, Google OAuth, seteo de cookies |
| `backend/app/Services/Auth/AuthService.php` | Logica de auth, creacion de tokens Sanctum |
| `backend/app/Http/Middleware/ExtractTokenFromCookie.php` | Cookie -> Authorization header |
| `backend/app/Http/Middleware/EnsureIsWorker.php` | Validacion rol worker |
| `backend/app/Http/Middleware/EnsureIsAdmin.php` | Validacion rol admin |
| `backend/bootstrap/app.php` | Registro de middleware |
| `backend/config/sanctum.php` | Config Sanctum (tokens no expiran) |
| `backend/config/auth.php` | Guards y providers |
| `backend/config/cors.php` | CORS con credentials |
| `backend/Dockerfile` | Build con key:generate en linea 39 |
| `frontend/middleware.ts` | Routing auth (solo checa existencia de cookie) |
| `frontend/lib/api.ts` | API client, 401 handler |
| `frontend/lib/auth.ts` | Manejo de localStorage tokens |
| `frontend/hooks/useAuth.ts` | Hook de auth |
| `frontend/app/login/page.tsx` | Pagina de login |
