# Guía de Verificación de APIs - Post Migración VPS

## 🎯 Objetivo

Verificar que todas las APIs, callbacks y páginas pending puedan cargar correctamente el `config.php` después de la migración.

## 📊 Estado Actual

### Patrones de Carga Encontrados

1. **Patrón Estándar** (49 archivos): `require_once '../config.php'`
2. **Patrón Subdirectorio** (1 archivo): `require_once '../../config.php'`
3. **Patrón Multi-nivel** (callbacks): Busca en múltiples niveles

## ✅ Archivos Creados

### 1. `config_loader.php` (raíz)
Helper universal que busca config.php en múltiples niveles.

**Uso:**
```php
$config = require_once __DIR__ . '/config_loader.php';
```

### 2. `public/api_health_check.php`
Script de verificación completa del sistema.

**Acceso:**
```bash
curl https://caja.laruta11.cl/api_health_check.php
```

### 3. `public/verify_migration.php`
Verificación básica de archivos de configuración.

## 🔍 Verificación en VPS

### Paso 1: Verificar Archivos de Config
```bash
curl https://caja.laruta11.cl/verify_migration.php
```

**Esperado:**
```json
{
  "status": "success",
  "files_checked": {
    "config.php (root)": true,
    "config.php (public)": true,
    "load-env.php (root)": true,
    "load-env.php (public)": true
  }
}
```

### Paso 2: Health Check Completo
```bash
curl https://caja.laruta11.cl/api_health_check.php
```

**Esperado:**
```json
{
  "summary": {
    "all_configs_ok": true,
    "all_apis_ok": true,
    "all_callbacks_ok": true,
    "config_loads": true,
    "database_connects": true,
    "overall_status": "OK"
  }
}
```

### Paso 3: Verificar API Específica
```bash
curl https://caja.laruta11.cl/api/check_config.php
```

**Esperado:** Sin warnings de PHP, respuesta JSON limpia.

## 🔧 APIs Críticas a Verificar

### Callbacks (Pagos)
- ✅ `/api/tuu/callback.php` - Callback TUU POS
- ✅ `/api/tuu-pagos-online/callback.php` - Callback TUU Online
- ✅ `/api/concurso_pago_callback.php` - Callback Concurso
- ✅ `/api/auth/google/callback.php` - Callback Google OAuth

### Páginas Pending
- `/src/pages/transfer-pending.astro`
- `/src/pages/card-pending.astro`
- `/src/pages/cash-pending.astro`
- `/src/pages/pedidosya-pending.astro`

### APIs de Inventario
- `/api/get_ingredientes.php`
- `/api/update_ingrediente.php`
- `/api/registrar_venta.php`
- `/api/process_sale_inventory.php`

### APIs de Productos
- `/api/get_productos.php`
- `/api/create_producto.php`
- `/api/update_producto.php`

### APIs de Ventas
- `/api/get_pending_orders.php`
- `/api/registrar_venta.php`
- `/api/ventas_update.php`

## 🐛 Problemas Comunes y Soluciones

### Problema 1: "Config no encontrado"
**Causa:** Ruta incorrecta al config.php

**Solución:**
```php
// Reemplazar:
require_once '../config.php';

// Por:
$config = require_once __DIR__ . '/../config_loader.php';
```

### Problema 2: "Undefined variable $conn"
**Causa:** No se inicializa la conexión a BD

**Solución:**
```php
$conn = @mysqli_connect(
    $config['ruta11_db_host'],
    $config['ruta11_db_user'],
    $config['ruta11_db_pass'],
    $config['ruta11_db_name']
);
```

### Problema 3: Variables de entorno no cargadas
**Causa:** getenv() no encuentra las variables

**Verificar en EasyPanel:**
- Variables de entorno configuradas
- Aplicación reiniciada después de cambios
- Formato correcto: `NOMBRE_VARIABLE=valor`

## 📝 Checklist de Migración

### Pre-Deploy
- [x] Config.php simplificado (sin require load-env)
- [x] load-env.php vaciado
- [x] Archivos en /public creados
- [x] config_loader.php creado
- [x] Scripts de verificación creados

### Post-Deploy
- [ ] Verificar variables de entorno en EasyPanel
- [ ] Ejecutar verify_migration.php
- [ ] Ejecutar api_health_check.php
- [ ] Probar check_config.php (sin warnings)
- [ ] Probar callback de TUU
- [ ] Probar páginas pending
- [ ] Verificar conexión a BD
- [ ] Probar registro de venta
- [ ] Probar actualización de inventario

## 🚨 Monitoreo Continuo

### Logs a Revisar
```bash
# En el servidor VPS
tail -f /var/log/php-error.log | grep -i "config\|undefined"
```

### Endpoints de Monitoreo
```bash
# Cada 5 minutos
curl -s https://caja.laruta11.cl/api_health_check.php | jq '.summary.overall_status'
```

## 📞 Soporte

Si encuentras errores:

1. Revisar logs de PHP
2. Verificar variables de entorno
3. Ejecutar health check
4. Revisar este documento

## 🎉 Éxito

El sistema está funcionando correctamente cuando:
- ✅ api_health_check.php retorna "overall_status": "OK"
- ✅ No hay warnings de PHP en check_config.php
- ✅ Callbacks procesan pagos correctamente
- ✅ Inventario se actualiza en ventas
- ✅ Páginas pending cargan sin errores

---

**Última actualización:** $(date)
**Versión:** 1.0
