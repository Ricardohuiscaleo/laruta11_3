# Plan de Migración: Sistema de Emails RL6 (Test → Producción)

## 📋 Estado Actual

### ✅ Archivos de Test (Funcionando)
- `app3/api/rl6/add_test_debt.php` - Agregar deuda de prueba
- `app3/api/rl6/simulate_callback_web.php` - Simular callback de pago
- `app3/api/rl6/check_email_logs.php` - Ver logs de emails
- `app3/api/rl6/test_email.php` - Probar envío de email

### ✅ Archivos de Producción (Ya Actualizados)
- `app3/api/gmail/send_payment_confirmation.php` - Email de confirmación con diseño moderno verde
- `app3/api/gmail/get_token_db.php` - Función `getValidGmailToken()` con auto-refresh
- `caja3/api/gmail/send_credit_statement.php` - Email de estado de cuenta con diseño moderno naranja
- `app3/api/rl6/payment_callback.php` - Callback con logging y prevención de duplicados
- `app3/api/rl6/create_payment.php` - Creación de pago con todos los campos TUU

### ✅ Base de Datos
- Tabla `email_logs` creada y funcionando
- Tabla `gmail_tokens` con tokens válidos y auto-refresh

## 🎯 Tareas de Migración

### 1. Actualizar `payment_callback.php` para Producción

**Archivo**: `app3/api/rl6/payment_callback.php`

**Cambios necesarios**:
- ✅ Ya tiene logging a `email_logs`
- ✅ Ya tiene prevención de duplicados
- ✅ Ya llama a `send_payment_confirmation.php`
- ✅ Ya usa `getValidGmailToken()` indirectamente
- ⚠️ **VERIFICAR**: Que no tenga parámetro `simulate=1` hardcodeado

**Acción**: Revisar y confirmar que está listo para producción

---

### 2. Actualizar `send_credit_statement.php` para usar `getValidGmailToken()`

**Archivo**: `caja3/api/gmail/send_credit_statement.php`

**Cambios necesarios**:
```php
// ANTES (línea ~20):
require_once __DIR__ . '/get_token_db.php';
$token_data = get_gmail_token_from_db();
$access_token = $token_data['access_token'];

// DESPUÉS:
require_once __DIR__ . '/../../app3/api/gmail/get_token_db.php';
$access_token = getValidGmailToken();
if (!$access_token) {
    error_log("Failed to get valid Gmail token");
    return false;
}
```

**Acción**: Actualizar función de obtención de token con auto-refresh

---

### 3. Agregar Logging a `send_credit_statement.php`

**Archivo**: `caja3/api/gmail/send_credit_statement.php`

**Cambios necesarios**:
```php
// Después de enviar email exitosamente (línea ~200):
if ($response_code === 200) {
    $response_data = json_decode($response_body, true);
    
    // Guardar en email_logs
    $log_stmt = $pdo->prepare("
        INSERT INTO email_logs (
            user_id, email_to, email_type, subject, 
            gmail_message_id, gmail_thread_id, status, sent_at
        ) VALUES (?, ?, 'credit_statement', ?, ?, ?, 'sent', NOW())
        ON DUPLICATE KEY UPDATE 
            gmail_message_id = VALUES(gmail_message_id),
            gmail_thread_id = VALUES(gmail_thread_id),
            status = 'sent',
            sent_at = NOW()
    ");
    
    $log_stmt->execute([
        $user_id,
        $user_email,
        $subject,
        $response_data['id'] ?? null,
        $response_data['threadId'] ?? null
    ]);
}
```

**Acción**: Agregar logging después de envío exitoso

---

### 4. Copiar `get_token_db.php` a caja3

**Opción A**: Copiar archivo completo
```bash
cp app3/api/gmail/get_token_db.php caja3/api/gmail/get_token_db.php
```

**Opción B**: Usar require desde app3 (más limpio)
```php
// En caja3/api/gmail/send_credit_statement.php
require_once __DIR__ . '/../../../app3/api/gmail/get_token_db.php';
```

**Acción**: Decidir estrategia y aplicar

---

### 5. Actualizar `emails.astro` para usar endpoint real

**Archivo**: `caja3/src/pages/admin/emails.astro`

**Cambios necesarios**:
```typescript
// ANTES:
const response = await fetch('/api/gmail/send_credit_statement_test.php', {

// DESPUÉS:
const response = await fetch('/api/gmail/send_credit_statement.php', {
```

**Acción**: Cambiar endpoint de test a producción

---

### 6. Eliminar Archivos de Test

**Archivos a eliminar**:
```bash
rm app3/api/rl6/add_test_debt.php
rm app3/api/rl6/simulate_callback_web.php
rm app3/api/rl6/check_email_logs.php
rm app3/api/rl6/test_email.php
```

**Acción**: Eliminar después de confirmar que producción funciona

---

## 🔍 Checklist de Verificación

### Pre-Migración
- [ ] Confirmar que `gmail_tokens` tiene token válido
- [ ] Confirmar que `email_logs` tabla existe
- [ ] Backup de archivos actuales de producción
- [ ] Verificar que `getValidGmailToken()` funciona en test

### Durante Migración
- [ ] Actualizar `send_credit_statement.php` con `getValidGmailToken()`
- [ ] Agregar logging a `send_credit_statement.php`
- [ ] Copiar/referenciar `get_token_db.php` en caja3
- [ ] Actualizar `emails.astro` para usar endpoint real
- [ ] Verificar que `payment_callback.php` no tiene `simulate=1`

### Post-Migración
- [ ] Probar envío de estado de cuenta desde caja3
- [ ] Probar pago RL6 completo (crear deuda → pagar → verificar email)
- [ ] Verificar logs en `email_logs` tabla
- [ ] Verificar que no hay duplicados
- [ ] Verificar que tokens se auto-refrescan
- [ ] Eliminar archivos de test

### Monitoreo
- [ ] Revisar logs de PHP por errores
- [ ] Revisar `email_logs` por fallos
- [ ] Verificar que emails llegan correctamente
- [ ] Verificar diseño en Gmail/Outlook/Apple Mail

---

## 🚨 Rollback Plan

Si algo falla:

1. **Restaurar archivos originales** desde backup
2. **Verificar logs** en `email_logs` para identificar error
3. **Revisar token** en `gmail_tokens` (puede estar expirado)
4. **Usar archivos de test** para debugging
5. **Contactar soporte** si es problema de Gmail API

---

## 📊 Archivos Afectados

### Archivos a Modificar
1. `caja3/api/gmail/send_credit_statement.php` - Agregar getValidGmailToken() y logging
2. `caja3/src/pages/admin/emails.astro` - Cambiar endpoint de test a producción
3. `caja3/api/gmail/get_token_db.php` - Copiar desde app3 (o referenciar)

### Archivos a Verificar
1. `app3/api/rl6/payment_callback.php` - Confirmar que está listo
2. `app3/api/gmail/send_payment_confirmation.php` - Confirmar que funciona
3. `app3/api/gmail/get_token_db.php` - Confirmar que auto-refresh funciona

### Archivos a Eliminar (después de verificar)
1. `app3/api/rl6/add_test_debt.php`
2. `app3/api/rl6/simulate_callback_web.php`
3. `app3/api/rl6/check_email_logs.php`
4. `app3/api/rl6/test_email.php`

---

## 🎯 Orden de Ejecución

### Paso 1: Preparación
```bash
# Backup de archivos críticos
cp caja3/api/gmail/send_credit_statement.php caja3/api/gmail/send_credit_statement.php.backup
cp caja3/src/pages/admin/emails.astro caja3/src/pages/admin/emails.astro.backup
```

### Paso 2: Actualizar send_credit_statement.php
- Cambiar obtención de token a `getValidGmailToken()`
- Agregar logging a `email_logs`
- Agregar require de `get_token_db.php`

### Paso 3: Actualizar emails.astro
- Cambiar endpoint de test a producción

### Paso 4: Testing
- Enviar email de prueba desde caja3
- Verificar en `email_logs`
- Verificar recepción de email

### Paso 5: Cleanup
- Eliminar archivos de test
- Commit y push a GitHub

---

## 📝 Notas Importantes

### Diferencias entre Test y Producción
- **Test**: Usa `simulate=1` para evitar redirects
- **Producción**: Redirects normales después de callback
- **Test**: Archivos separados para debugging
- **Producción**: Archivos integrados en flujo normal

### Funciones Clave
- `getValidGmailToken()`: Auto-refresh de token Gmail
- `email_logs`: Prevención de duplicados y auditoría
- `payment_callback.php`: Procesa pago y envía email
- `send_payment_confirmation.php`: Email verde de confirmación
- `send_credit_statement.php`: Email naranja de estado de cuenta

### Endpoints Críticos
- `/api/rl6/payment_callback.php` - Procesa pagos TUU
- `/api/gmail/send_payment_confirmation.php` - Email de confirmación
- `/api/gmail/send_credit_statement.php` - Email de estado de cuenta
- `/api/gmail/get_token_db.php` - Obtención de token con auto-refresh

---

## ✅ Criterios de Éxito

1. ✅ Emails de confirmación se envían automáticamente después de pago
2. ✅ Emails de estado de cuenta se envían desde caja3
3. ✅ Todos los emails se registran en `email_logs`
4. ✅ No hay duplicados en `email_logs`
5. ✅ Tokens se auto-refrescan sin intervención manual
6. ✅ Diseño de emails se ve bien en todos los clientes
7. ✅ No hay errores en logs de PHP
8. ✅ Archivos de test eliminados

---

## 🔗 Referencias

- **Diseño de Emails**: Ver `caja3-fixes.md` para detalles de diseño
- **Sistema RL6**: Ver `rl6-credit-system.md` para flujo completo
- **Gmail API**: Ver `vps-migration.md` para configuración de tokens
