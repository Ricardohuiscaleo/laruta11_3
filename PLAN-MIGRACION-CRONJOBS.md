# 📅 PLAN: Migración de Cronjobs - Hostinger → VPS

## 🎯 Objetivo
Migrar los 2 cronjobs de Hostinger a VPS antes de cancelar el hosting.

---

## 📋 Cronjobs Actuales en Hostinger

### 1. Gmail Token Refresh ✅ MIGRADO
- **Frecuencia**: Cada 40 minutos (`*/40 * * * *`)
- **Método**: GitHub Actions
- **URL**: `https://caja.laruta11.cl/api/gmail/refresh_token_cron.php`
- **Propósito**: Renovar token de Gmail OAuth para envío de emails
- **Estado**: ✅ Funcionando correctamente desde 2026-02-17

### 2. Daily Checklists ✅ MIGRADO
- **Frecuencia**: Diario a las 8 AM Chile (`0 11 * * *` UTC)
- **Método**: GitHub Actions
- **URL**: `https://caja.laruta11.cl/api/cron/create_daily_checklists.php`
- **Propósito**: Crear checklists diarios para operaciones de caja
- **Estado**: ✅ Configurado correctamente desde 2026-02-17

---

## 🚀 Opciones de Implementación

### ✅ OPCIÓN 1: Cron-Job.org (RECOMENDADA)
**Ventajas**: Gratis, fácil, sin SSH, monitoreo incluido

**Pasos**:
1. Ir a https://cron-job.org
2. Crear cuenta gratuita
3. Agregar 2 cronjobs:

#### Job 1: Gmail Token Refresh
```
Nombre: Gmail Token Refresh - La Ruta 11
URL: https://app.laruta11.cl/api/cron/refresh_gmail_token.php
Schedule: */30 * * * * (cada 30 minutos)
Método: GET
Timeout: 30 segundos
```

#### Job 2: Daily Checklists
```
Nombre: Daily Checklists - La Ruta 11
URL: https://caja.laruta11.cl/api/cron/create_daily_checklists.php
Schedule: 0 8 * * * (8 AM diario)
Método: GET
Timeout: 30 segundos
```

4. Activar notificaciones por email en caso de fallo
5. Guardar credenciales de acceso

---

### OPCIÓN 2: Cron Nativo VPS
**Ventajas**: Control total, sin dependencias externas

**Pasos**:
1. Conectar al VPS por SSH
2. Editar crontab:
```bash
crontab -e
```

3. Agregar estas líneas:
```bash
# Gmail Token Refresh (cada 30 min)
0,30 * * * * curl -s https://app.laruta11.cl/api/cron/refresh_gmail_token.php > /dev/null 2>&1

# Daily Checklists (8 AM diario)
0 8 * * * curl -s https://caja.laruta11.cl/api/cron/create_daily_checklists.php > /dev/null 2>&1
```

4. Guardar y salir
5. Verificar con: `crontab -l`

---

### OPCIÓN 3: GitHub Actions
**Ventajas**: Gratis, versionado, automático

**Pasos**:
1. Crear archivo `.github/workflows/cronjobs.yml`:

```yaml
name: Cronjobs La Ruta 11

on:
  schedule:
    - cron: '0,30 * * * *'  # Cada 30 min
    - cron: '0 8 * * *'      # 8 AM diario

jobs:
  gmail-token:
    runs-on: ubuntu-latest
    steps:
      - name: Refresh Gmail Token
        run: curl -s https://app.laruta11.cl/api/cron/refresh_gmail_token.php

  daily-checklists:
    runs-on: ubuntu-latest
    if: github.event.schedule == '0 8 * * *'
    steps:
      - name: Create Daily Checklists
        run: curl -s https://caja.laruta11.cl/api/cron/create_daily_checklists.php
```

2. Commit y push
3. Verificar en GitHub → Actions

---

## ✅ Checklist de Migración

### Pre-Migración
- [ ] Verificar que las URLs funcionan:
  ```bash
  curl https://app.laruta11.cl/api/cron/refresh_gmail_token.php
  curl https://caja.laruta11.cl/api/cron/create_daily_checklists.php
  ```
- [ ] Documentar horarios actuales de ejecución
- [ ] Backup de logs de Hostinger

### Durante Migración
- [ ] Configurar cronjobs en nueva plataforma
- [ ] Ejecutar manualmente para probar
- [ ] Verificar logs de ejecución
- [ ] Mantener Hostinger activo 24h más

### Post-Migración
- [ ] Monitorear ejecuciones durante 3 días
- [ ] Verificar que Gmail tokens se renuevan
- [ ] Validar que checklists se crean a las 8 AM
- [ ] Desactivar cronjobs en Hostinger
- [ ] Cancelar hosting Hostinger

---

## 🔍 Monitoreo

### Verificar Gmail Token
```bash
# Debe ejecutarse cada 30 min
curl https://app.laruta11.cl/api/cron/refresh_gmail_token.php
```

### Verificar Checklists
```bash
# Debe ejecutarse a las 8 AM
curl https://caja.laruta11.cl/api/cron/create_daily_checklists.php
```

---

## 🚨 Troubleshooting

### Si Gmail deja de enviar emails
- Verificar que el cronjob se ejecuta cada 30 min
- Revisar logs en `/api/cron/refresh_gmail_token.php`
- Renovar manualmente el token OAuth

### Si no se crean checklists
- Verificar horario (debe ser 8 AM Chile)
- Revisar conexión a base de datos
- Ejecutar manualmente para debug

---

## 📅 Timeline

**Día 1 (2026-02-17)**:
- ✅ Documentar cronjobs actuales
- ✅ Gmail Token Refresh migrado a GitHub Actions
- ✅ Daily Checklists migrado a GitHub Actions

**Día 2**:
- ⏳ Monitorear ejecuciones
- ⏳ Ajustar si hay errores

**Día 3**:
- ⏳ Validar funcionamiento completo
- ⏳ Desactivar Hostinger

**Día 4**:
- ⏳ Cancelar hosting Hostinger

---

## 💡 Recomendación Final

**Usar cron-job.org** porque:
- ✅ No requiere acceso SSH
- ✅ Interfaz web fácil
- ✅ Notificaciones automáticas si falla
- ✅ Logs de ejecución
- ✅ Gratis para 2 jobs
- ✅ Más confiable que GitHub Actions

---

**Fecha de creación**: 2026-02-12
**Última actualización**: 2026-02-17
**Responsable**: Ricardo
**Estado**: ✅ COMPLETADO (2/2 migrados)

---

## ✅ Progreso Actual

### Gmail Token Refresh - ✅ COMPLETADO
- **Plataforma**: GitHub Actions
- **Archivo**: `.github/workflows/gmail-token-refresh.yml`
- **Frecuencia**: Cada 40 minutos
- **Última ejecución**: 2026-02-17 17:19:42 (exitosa)
- **Próximos pasos**: Ninguno, funcionando correctamente

### Daily Checklists - ✅ COMPLETADO
- **Plataforma**: GitHub Actions
- **Archivo**: `.github/workflows/daily-checklists.yml`
- **Frecuencia**: Diario a las 8 AM Chile (11:00 UTC)
- **Próxima ejecución**: Mañana a las 8:00 AM
- **Próximos pasos**: Monitorear primera ejecución automática

---

## 🎉 Migración Completada

**Ambos cronjobs migrados exitosamente a GitHub Actions:**
1. ✅ Gmail Token Refresh (cada 40 min)
2. ✅ Daily Checklists (8 AM diario)

**Próximos pasos:**
1. Monitorear ejecuciones durante 3 días
2. Desactivar cronjobs en Hostinger
3. Cancelar hosting Hostinger
