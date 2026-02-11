# 🚀 DEPLOYMENT CHECKLIST - Cashback 1%

**Fecha**: 28 Enero 2026  
**Versión**: 1.0  
**Ambiente**: Staging → Producción

---

## ✅ Pre-Deployment (Staging)

### 1. Verificar Cambios en Staging
```bash
# Verificar que los archivos fueron actualizados
ls -la api/generate_cashback.php
ls -la src/components/CheckoutApp.jsx
ls -la src/components/modals/ProfileModalModern.jsx
ls -la api/create_order.php
```

### 2. Ejecutar Tests Manuales
```
Test 1: Compra de $100 → Debe generar $1 cashback
Test 2: Compra de $50.000 → Debe generar $500 cashback
Test 3: Verificar que ProfileModal muestra puntos correctos
Test 4: Verificar que no hay errores en consola
```

### 3. Verificar Base de Datos
```sql
-- Verificar que NO hay referencias a niveles
SELECT COUNT(*) FROM usuarios 
WHERE cashback_level_bronze IS NOT NULL 
OR cashback_level_silver IS NOT NULL 
OR cashback_level_gold IS NOT NULL;
-- Debe retornar: 0

-- Verificar transacciones recientes
SELECT * FROM wallet_transactions 
WHERE description LIKE 'Cashback 1%' 
ORDER BY created_at DESC LIMIT 5;
```

### 4. Verificar Logs
```bash
# Revisar logs de PHP por errores
tail -f /var/log/php-fpm/error.log | grep -i cashback

# Revisar logs de aplicación
tail -f /var/log/apache2/error.log | grep -i cashback
```

---

## 📦 Deployment a Producción

### Paso 1: Backup de Base de Datos
```bash
# Crear backup antes de cambios
mysqldump -u [user] -p [database] > backup_cashback_$(date +%Y%m%d_%H%M%S).sql

# Guardar en lugar seguro
cp backup_cashback_*.sql /backups/
```

### Paso 2: Actualizar Archivos PHP
```bash
# Copiar archivos actualizados
cp api/generate_cashback.php /var/www/html/api/
cp api/create_order.php /var/www/html/api/

# Verificar permisos
chmod 644 /var/www/html/api/generate_cashback.php
chmod 644 /var/www/html/api/create_order.php
```

### Paso 3: Actualizar Frontend (Build)
```bash
# Compilar cambios de React/Astro
npm run build

# Copiar dist a servidor
cp -r dist/* /var/www/html/

# Limpiar caché
rm -rf /var/www/html/.cache
```

### Paso 4: Verificar Deployment
```bash
# Verificar que archivos están en lugar correcto
curl -I https://app.laruta11.cl/api/generate_cashback.php

# Verificar que no hay errores 500
curl -X POST https://app.laruta11.cl/api/generate_cashback.php \
  -H "Content-Type: application/json" \
  -d '{"user_id": 5, "amount": 100}'
```

### Paso 5: Monitoreo Post-Deployment
```bash
# Monitorear logs en tiempo real
tail -f /var/log/php-fpm/error.log

# Monitorear transacciones de cashback
watch -n 5 'mysql -u [user] -p[pass] [db] -e "SELECT COUNT(*) FROM wallet_transactions WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR) AND description LIKE \"Cashback 1%\";"'
```

---

## 🔍 Validación Post-Deployment

### Verificación Inmediata (Primeros 30 minutos)
- [ ] No hay errores 500 en logs
- [ ] Usuarios pueden hacer compras normalmente
- [ ] Cashback aparece en wallet después de compra
- [ ] ProfileModal muestra puntos correctos

### Verificación a 1 Hora
- [ ] Al menos 5 transacciones de cashback registradas
- [ ] Saldos de wallet se actualizan correctamente
- [ ] No hay duplicados de transacciones

### Verificación a 24 Horas
- [ ] Mínimo 50 transacciones de cashback
- [ ] Promedio de cashback es ~1% del subtotal
- [ ] No hay anomalías en base de datos
- [ ] Usuarios reportan saldos correctos

---

## ⚠️ Rollback Plan

Si algo sale mal, revertir cambios:

### Opción 1: Rollback Rápido (< 5 minutos)
```bash
# Restaurar archivos PHP anteriores
git checkout HEAD~1 api/generate_cashback.php
git checkout HEAD~1 api/create_order.php

# Restaurar frontend
git checkout HEAD~1 src/components/CheckoutApp.jsx
git checkout HEAD~1 src/components/modals/ProfileModalModern.jsx

# Recompilar
npm run build
cp -r dist/* /var/www/html/
```

### Opción 2: Rollback Completo (Base de Datos)
```bash
# Restaurar backup
mysql -u [user] -p[pass] [database] < backup_cashback_YYYYMMDD_HHMMSS.sql

# Verificar integridad
REPAIR TABLE wallet_transactions;
REPAIR TABLE user_wallet;
```

---

## 📊 Métricas a Monitorear

### Diarias
- Número de transacciones de cashback
- Promedio de cashback por transacción
- Saldo total en wallets
- Errores en logs

### Semanales
- Tendencia de cashback generado
- Usuarios activos con cashback
- Tasa de uso de cashback
- Satisfacción de usuarios

---

## 📝 Documentación

Después del deployment, actualizar:
- [ ] Wiki interna con cambios
- [ ] Documentación de API
- [ ] Guía de usuario (si aplica)
- [ ] Changelog del proyecto

---

## 🎯 Criterios de Éxito

✅ **Técnico**:
- Cero errores 500 en logs
- Cashback se genera automáticamente
- Cálculo es exactamente 1%
- Base de datos íntegra

✅ **Funcional**:
- Usuarios reciben cashback correcto
- ProfileModal muestra datos correctos
- Historial de transacciones completo
- Saldo se actualiza en tiempo real

✅ **Negocio**:
- Ahorro de 90% en cashback generado
- Usuarios satisfechos con cambios
- Sistema más simple y mantenible
- Cero fraudes detectados

---

## 📞 Contacto de Emergencia

Si hay problemas durante deployment:
1. Revisar `/TESTING_CASHBACK_1PERCENT.md`
2. Ejecutar rollback si es necesario
3. Contactar al equipo de desarrollo
4. Documentar el incidente

---

## ✨ Notas Finales

- Este deployment es **CRÍTICO** - Fix de error 10% → 1%
- Requiere **monitoreo cercano** las primeras 24 horas
- Tiene **rollback plan** en caso de problemas
- Todos los cambios están **documentados y testeados**

---

**Deployment Autorizado**: ✅  
**Fecha Estimada**: 28 Enero 2026  
**Responsable**: Equipo de Desarrollo  
**Estado**: LISTO PARA PRODUCCIÓN
