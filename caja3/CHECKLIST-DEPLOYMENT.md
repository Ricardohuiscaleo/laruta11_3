# 🚀 CHECKLIST DEPLOYMENT - CAJA3 VPS

## ✅ FASE 1: PREPARACIÓN (COMPLETADO)
- [x] Simplificar config.php (usa getenv)
- [x] Vaciar load-env.php
- [x] Crear config.php en /public
- [x] Corregir check_config.php
- [x] Crear herramientas de verificación
- [x] Auditar APIs (92.5% OK)
- [x] Subir cambios a Git

## 📋 FASE 2: DEPLOYMENT EN VPS (SIGUIENTE)

### Paso 1: Pull en VPS
```bash
# Conectar al VPS
ssh usuario@vps

# Ir al directorio de caja
cd /var/www/caja.laruta11.cl

# Pull de cambios
git pull origin main
```

### Paso 2: Verificar Variables de Entorno en EasyPanel
Ir a EasyPanel → caja.laruta11.cl → Environment Variables

**Variables Críticas:**
```env
# Base de Datos
RUTA11_DB_HOST=localhost
RUTA11_DB_NAME=ruta11
RUTA11_DB_USER=usuario
RUTA11_DB_PASS=password

# APIs
GEMINI_API_KEY=tu_key
TUU_API_KEY=tu_key
AWS_ACCESS_KEY_ID=tu_key
AWS_SECRET_ACCESS_KEY=tu_key

# Auth
ADMIN_PASSWORD=tu_password
RICARDO_PASSWORD=tu_password
CAJA_PASSWORD_CAJERA=tu_password
CAJA_PASSWORD_ADMIN=tu_password
```

### Paso 3: Reiniciar Aplicación
En EasyPanel:
- Ir a caja.laruta11.cl
- Click en "Restart"
- Esperar que esté "Running"

### Paso 4: Verificaciones Inmediatas
```bash
# 1. Health Check
curl https://caja.laruta11.cl/api_health_check.php

# 2. Verificar Migración
curl https://caja.laruta11.cl/verify_migration.php

# 3. Check Config (sin warnings)
curl https://caja.laruta11.cl/api/check_config.php

# 4. Reporte Visual
# Abrir en navegador:
https://caja.laruta11.cl/api_status_report.php
```

**Resultado Esperado:**
```json
{
  "summary": {
    "overall_status": "OK",
    "database_connects": true,
    "config_loads": true
  }
}
```

## 🧪 FASE 3: PRUEBAS FUNCIONALES

### Test 1: Conexión a Base de Datos
```bash
curl https://caja.laruta11.cl/api/check_config.php | jq '.database.connected'
# Esperado: true
```

### Test 2: Cargar Productos
```bash
curl https://caja.laruta11.cl/api/get_productos.php | jq '.success'
# Esperado: true
```

### Test 3: Cargar Ingredientes
```bash
curl https://caja.laruta11.cl/api/get_ingredientes.php | jq '.success'
# Esperado: true
```

### Test 4: Órdenes Pendientes
```bash
curl https://caja.laruta11.cl/api/get_pending_orders.php | jq '.success'
# Esperado: true
```

### Test 5: Abrir Interfaz Web
```
https://caja.laruta11.cl
```
- [ ] Login funciona
- [ ] Dashboard carga
- [ ] Productos se muestran
- [ ] Inventario accesible

## 🔍 FASE 4: MONITOREO

### Logs en Tiempo Real
```bash
# En el VPS
tail -f /var/log/php-error.log | grep -i "config\|undefined\|error"
```

### Verificación Periódica (cada 5 min)
```bash
watch -n 300 'curl -s https://caja.laruta11.cl/api_health_check.php | jq .summary.overall_status'
```

## 🐛 FASE 5: TROUBLESHOOTING

### Si health_check falla:

**Problema: "Config no encontrado"**
```bash
# Verificar archivos
ls -la /var/www/caja.laruta11.cl/config.php
ls -la /var/www/caja.laruta11.cl/public/config.php
```

**Problema: "Database connection failed"**
```bash
# Verificar variables de entorno
printenv | grep RUTA11_DB

# Verificar MySQL
mysql -u usuario -p -e "SHOW DATABASES;"
```

**Problema: "Undefined variable"**
```bash
# Ver logs específicos
tail -100 /var/log/php-error.log | grep "Undefined"
```

## ✅ FASE 6: VALIDACIÓN FINAL

### Checklist de Funcionalidad
- [ ] Login de cajero funciona
- [ ] Registrar venta funciona
- [ ] Inventario se actualiza
- [ ] Callbacks de TUU funcionan
- [ ] Páginas pending cargan
- [ ] No hay warnings en logs
- [ ] Health check retorna OK

### Métricas de Éxito
- ✅ 0 errores críticos en logs
- ✅ Tiempo de respuesta < 500ms
- ✅ 100% uptime en primeras 24h
- ✅ Todas las transacciones procesadas

## 📞 CONTACTOS DE EMERGENCIA

**Si algo falla:**
1. Revisar logs: `/var/log/php-error.log`
2. Verificar variables de entorno en EasyPanel
3. Ejecutar health_check.php
4. Revisar documentación: `GUIA-VERIFICACION-APIS.md`

## 🎉 DEPLOYMENT EXITOSO

El deployment es exitoso cuando:
- ✅ Health check retorna "OK"
- ✅ Todas las APIs críticas funcionan
- ✅ No hay errores en logs
- ✅ Interfaz web carga correctamente
- ✅ Transacciones se procesan

---

**Fecha de inicio:** $(date)
**Responsable:** Ricardo
**Versión:** 1.0
