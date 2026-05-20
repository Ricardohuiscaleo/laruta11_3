# Skill: Revisión de Seguridad

## Descripción
Especialista en revisión de seguridad para el proyecto La Ruta 11.

## Cuándo usar
- Code review de features nuevas
- Antes de deploys a producción
- Cuando se agregan nuevas APIs
- Cuando se manejan datos sensibles
- Revisión periódica de seguridad

## Stack
- PHP (REST APIs y Laravel)
- React/Astro (Frontend)
- MySQL
- AWS S3
- Docker/Coolify

## Checklist de Seguridad

### 🔴 Crítico

#### Autenticación y Autorización
- [ ] Validar tokens en CADA request protegido
- [ ] No confiar en `localStorage` para autorización server-side
- [ ] Sanctum stateless: verificar tokens en `personal_access_tokens`
- [ ] Session hijacking protection
- [ ] Rate limiting en APIs de auth

#### Datos Sensibles
- [ ] NO hardcodear secrets/tokens en código fuente
- [ ] NO guardar tokens en archivos dentro de Docker
- [ ] Usar variables de entorno para secrets
- [ ] Gmail tokens en BD (`gmail_tokens`), no filesystem
- [ ] AWS credenciales via env vars, no hardcodeadas

#### SQL Injection
- [ ] Usar prepared statements SIEMPRE
- [ ] Nunca concatenar user input en queries
- [ ] Validar y sanitizar inputs
- [ ] Revisar APIs legacy por vulnerabilidades

#### File Uploads
- [ ] Validar tipo de archivo (no solo extensión)
- [ ] Validar tamaño máximo
- [ ] Scan de contenido (no solo MIME type)
- [ ] S3: usar PUT directo con SigV4, NO `Storage::disk('s3')->put()`
- [ ] Si compresión falla, subir original sin bloquear

### 🟡 Importante

#### XSS
- [ ] Escapar output en frontend
- [ ] Sanitizar HTML si se permite rich text
- [ ] Content Security Policy headers
- [ ] No usar `dangerouslySetInnerHTML` sin sanitizar

#### CSRF
- [ ] Tokens CSRF en forms
- [ ] Validar Origin/Referer headers
- [ ] SameSite cookies

#### APIs
- [ ] Rate limiting en endpoints públicos
- [ ] No exponer información sensible en errores
- [ ] Validar todos los inputs server-side
- [ ] CORS policies correctas
- [ ] No exponer datos de otros usuarios

#### Infraestructura
- [ ] SSH: solo key-based auth
- [ ] Docker: no correr como root cuando sea posible
- [ ] Firewall: solo puertos necesarios abiertos
- [ ] SSL/TLS: Let's Encrypt vigente
- [ ] Secrets management: env vars o BD, nunca en código

### 🟢 Buenas Prácticas

#### Código
- [ ] No loggear información sensible
- [ ] No exponer stack traces en producción
- [ ] Validar tipos de datos
- [ ] Manejar errores graceful
- [ ] Revisar dependencias por vulnerabilidades

#### BD
- [ ] Usuario BD con mínimos privilegios
- [ ] Encriptar datos sensibles en reposo
- [ ] Backups regulares
- [ ] Auditar accesos

#### Red
- [ ] HTTPS everywhere
- [ ] HSTS headers
- [ ] No mixed content
- [ ] WebSocket seguro (wss://)

## Patrones Seguros

### PHP API
```php
// ✅ Prepared statement
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);

// ❌ NUNCA hacer esto
$query = "SELECT * FROM users WHERE id = $userId";
```

### React
```tsx
// ✅ Escapar output
<div>{userInput}</div>

// ❌ NUNCA sin sanitizar
<div dangerouslySetInnerHTML={{ __html: userInput }} />
```

### File Upload
```php
// ✅ Validar tipo y contenido
$allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($file['type'], $allowedTypes)) {
    throw new Exception('Invalid file type');
}
// Subir a S3 con PUT directo + SigV4
```

## Herramientas
- `composer audit` — Revisar vulnerabilidades PHP
- `npm audit` — Revisar vulnerabilidades JS
- Docker scan — Revisar imágenes Docker
- MySQL audit log — Auditar queries

## Reporte
Documentar hallazgos con:
- Severidad (Critical/High/Medium/Low)
- Descripción
- Impacto
- Recomendación
- Archivos afectados
