# La Ruta 11 - Instrucciones para Copilot

## Stack Tecnológico

### Frontend
- **app3/caja3/landing3**: Astro 4.x + React 18 + TailwindCSS 3.4
- **mi3-frontend**: Next.js 14 App Router + React + TailwindCSS + TypeScript
- **Icons**: lucide-react, react-icons
- **Charts**: recharts (app3, caja3)

### Backend
- **app3/caja3**: PHP REST APIs (200-300+ endpoints), MySQLi
- **mi3-backend**: Laravel 11 + PHP 8.3 + Sanctum + Eloquent + Reverb
- **Auth**: Google OAuth (clientes), username/password (cajeros), Sanctum tokens (mi3)

### Database
- **MySQL 8** compartida entre app3 y caja3
- **65+ tablas**: productos, ventas, usuarios, compras, ingredientes, recetas, combos, etc.
- Conexión: `app_db_*` variables (NO `ruta11_db_*` — no están configuradas en servidor)

### Infraestructura
- **Deploy**: Coolify (Docker containers)
- **Server**: VPS 76.13.126.63 (Ubuntu + Docker)
- **Storage**: AWS S3 (laruta11-images, us-east-1)
- **CDN**: Cloudflare
- **SSL**: Let's Encrypt via Traefik

## Reglas Críticas

### S3 y Uploads
- **NUNCA** usar `Storage::disk('s3')->put()` — Flysystem falla silenciosamente
- Usar PUT directo con SigV4 (como `ImagenService` y `PhotoAnalysisService`)
- Si compresión falla (HEIC en iPhone), subir original sin bloquear al usuario
- URL directa: `https://{bucket}.s3.amazonaws.com/{key}` > `Storage::url()`

### Deploys
- Verificar estado con GET `/api/v1/deployments/{uuid}` → `status: finished/failed`
- `queued` ≠ `finished`
- Dockerfile `composer require` debe incluir TODOS los paquetes
- Nunca guardar tokens/secrets en archivos dentro de Docker — usar BD o env vars

### Frontend
- `apiFetch`: NO setear Content-Type para FormData (browser setea `multipart/form-data; boundary=...`)
- Optimistic UI > re-fetch para acciones frecuentes
- Types TypeScript deben reflejar la API real, no lo ideal
- Null-check funciones de formateo (`formatearPesosCLP`, etc.)
- Parámetros opcionales (`aiScore?`) → coalescer con `?? null`

### IA / Bedrock
- Modelo: `amazon.nova-pro-v1:0` (Nova Lite NO sirve para OCR de boletas)
- SigV4 con curl nativo (NO Guzzle/Laravel HTTP que double-encoda `:` en model ID)
- Prompts deben reflejar la realidad del negocio, no suposiciones
- **Doble mapeo**: prompt (best effort) + `mapPersonToSupplier()` server-side (garantizado)
- Formato de imagen para Bedrock: detectar de la extensión URL, no hardcodear `jpeg`
- Si la IA analiza algo, mostrar el resultado al usuario inmediatamente

### Checklists (mi3)
- 3 condiciones para visibilidad: `personal_id` + turno asignado + rol (`cajero`/`planchero`)
- Cierre solo visible después de 18:00 Chile
- Upload foto = marcar completado inmediato. Análisis IA en background
- Items de foto son transversales a todos los roles
- Contexto de foto debe coincidir con el prompt de IA

### Auth
- Remember token = true por defecto (app interna, 5 usuarios)
- Sanctum stateless con tokens en `personal_access_tokens`. Tabla `sessions` vacía es correcta

### Proveedores / Compras
- **ARIAKA**: normalizar cualquier variante a exactamente "ARIAKA"
- Ricardo Huiscaleo (emisor) → null, no es proveedor
- Mercado Pago → null, no es proveedor real
- Ariztía, Agrosuper, Ideal, agro-lucila, ARIAKA, JumboAPP → siempre `metodo_pago: transfer`
- RUT solo en facturas/boletas de supermercado, no en ferias/agro

### Infraestructura
- app3 y caja3 son **contenedores Docker independientes** — no comparten filesystem
- **NUNCA** usar paths relativos cross-container (`../../app3/...`)
- Si caja3 necesita funcionalidad de app3: replicar localmente o hacer llamada HTTP
- SSH: `ssh root@76.13.126.63`
- Contenedores cambian nombre en cada deploy (UUID + sufijo), BD NO cambian

### Chile Localization
- Number format: `toLocaleString('es-CL')` con punto (.) como miles
- Currency: `$` antes del monto
- Date: "21 de enero, 2024"
- IVA boletas chilenas: Total SIEMPRE es IVA incluido

## Patrones de Código

### PHP API Pattern (caja3/app3)
```php
// Config path pattern (5 niveles de búsqueda)
$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php',
    __DIR__ . '/../../../../../config.php'
];
foreach ($config_paths as $path) {
    if (file_exists($path)) { $config = require_once $path; break; }
}
// Conexión:
$pdo = new PDO("mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4", 
               $config['app_db_user'], $config['app_db_pass']);
```

### React Component Pattern
```tsx
// Types deben reflejar API real
interface Product {
  id: number;
  name: string;
  price: number;
  aiScore?: number | null; // coalescer antes de usar
}
// Null-check en funciones de formateo
const formatPrice = (amount: number | null | undefined) => {
  if (amount == null) return '$0';
  return `$${amount.toLocaleString('es-CL')}`;
};
```

## Errores Comunes a Evitar

1. **Usar `ruta11_db_*` en caja3** → siempre usar `app_db_*`
2. **Hardcodear categorías en frontend** → todo viene de BD (`is_active` flag)
3. **JSON.parse en frontend** → API parsea JSON server-side
4. **Storage::disk('s3')** → usar PUT directo con SigV4
5. **Cross-container paths** → replicar o llamada HTTP
6. **Nova Lite para OCR** → usar Nova Pro
7. **Confiar 100% en prompts IA** → siempre validación server-side
8. **No verificar deploy status** → `queued` ≠ `finished`

## Apps y URLs

| App | URL | Stack |
|-----|-----|-------|
| app3 | app.laruta11.cl | Astro + React + PHP |
| caja3 | caja.laruta11.cl | Astro + React + PHP |
| landing3 | laruta11.cl | Astro |
| mi3-frontend | mi.laruta11.cl | Next.js 14 |
| mi3-backend | api-mi3.laruta11.cl | Laravel 11 |

## Variables de Entorno Clave

- DB: `APP_DB_HOST`, `APP_DB_NAME`, `APP_DB_USER`, `APP_DB_PASS`
- AWS: `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `S3_BUCKET`
- APIs: `GEMINI_API_KEY`, `RUTA11_GOOGLE_MAPS_API_KEY`
- TUU: `TUU_API_KEY`, `TUU_ONLINE_RUT`, `TUU_DEVICE_SERIAL`
- Telegram: `TELEGRAM_TOKEN`, `TELEGRAM_CHAT_ID`
- Gmail: `GMAIL_CLIENT_ID`, `GMAIL_CLIENT_SECRET`, `GMAIL_SENDER_EMAIL`
