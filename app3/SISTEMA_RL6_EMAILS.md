# 📧 Sistema de Emails RL6

## Infraestructura Existente

**Sistema**: Gmail API OAuth  
**Ubicación**: `/api/tracker/send_candidate_email.php`  
**Token**: `/api/auth/gmail/gmail_token.json`  
**Configuración**: `config.php` → `gmail_sender_email`

---

## Emails RL6 a Enviar

### 1. Email de Registro Exitoso
**Cuándo**: Inmediatamente después de registrarse  
**Asunto**: ✅ Tu registro en Sistema RL6 - Regimiento Logístico N°6

**Contenido**:
- Confirmación de datos recibidos
- Resumen: Nombre, RUT, Grado, Unidad
- Estado: EN REVISIÓN
- Mensaje: "Recibirás notificación cuando sea aprobado"

### 2. Email de Aprobación de Crédito
**Cuándo**: Cuando admin aprueba el crédito  
**Asunto**: 🎖️ ¡Tu crédito ha sido aprobado!

**Contenido**:
- Felicitaciones
- Límite de crédito asignado
- Crédito disponible
- Instrucciones de uso en app

### 3. Email de Rechazo
**Cuándo**: Cuando admin rechaza la solicitud  
**Asunto**: ℹ️ Actualización de tu solicitud RL6

**Contenido**:
- Información de rechazo
- Motivo (si aplica)
- Opción de apelar o contactar

---

## Implementación

**Archivo**: `/api/rl6/send_rl6_emails.php`

**Características**:
- Reutiliza función `sendGmailEmail()` de sistema existente
- Genera HTML personalizado para RL6
- Llamado desde `register_militar.php` y `admin_approve_credit.php`
- Logging de errores para debugging

**Funciones**:
- `sendRegistroEmail($email, $nombre, $rut, $grado, $unidad)`
- `sendAprobacionEmail($email, $nombre, $limite_credito)`
- `sendRechazoEmail($email, $nombre, $motivo)`

---

## Integración en APIs

### En `register_militar.php`:
```php
// Después de crear usuario exitosamente
require_once __DIR__ . '/send_rl6_emails.php';
sendRegistroEmail($email, $nombre, $rut, $grado_militar, $unidad_trabajo);
```

### En `admin_approve_credit.php`:
```php
// Después de aprobar crédito
require_once __DIR__ . '/send_rl6_emails.php';
if ($aprobado) {
    sendAprobacionEmail($email, $nombre, $limite_credito);
} else {
    sendRechazoEmail($email, $nombre, $motivo_rechazo);
}
```

---

## Estado

✅ Sistema de emails existente verificado  
✅ Infraestructura Gmail API disponible  
⏳ Crear `/api/rl6/send_rl6_emails.php`  
⏳ Integrar en APIs de RL6
