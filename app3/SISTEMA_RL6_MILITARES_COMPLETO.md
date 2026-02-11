# 🎖️ Sistema RL6 - Registro Exclusivo para Militares
## Regimiento Logístico N°6 Pisagua - Sistema de Créditos

---

## 📋 Resumen Ejecutivo

Sistema de registro exclusivo para personal militar del Regimiento Logístico N°6 Pisagua que extiende el sistema de usuarios existente con datos adicionales específicos para gestión de créditos militares.

### Objetivo Principal
Crear una página de registro especializada (`rl6.astro`) que capture información militar adicional y la almacene en la tabla `usuarios` existente, aprovechando la infraestructura actual de autenticación y subida de imágenes a AWS S3.

---

## 💳 Sistema de Crédito RL6

### **Lógica Simple (Idéntica a Cashback)**
- Usuario ID 4: $50.000 límite → usa $10.000 → quedan $40.000
- Campos: `limite_credito`, `credito_usado`
- Disponible = límite - usado

### **Validar Saldo ANTES de Compra**
```sql
SELECT (limite_credito - credito_usado) as credito_disponible
FROM usuarios
WHERE id = [USER_ID] AND es_militar_rl6 = 1 AND credito_aprobado = 1;
```

### **Descontar DESPUÉS de Compra Exitosa**
```sql
UPDATE usuarios SET credito_usado = credito_usado + [MONTO]
WHERE id = [USER_ID];

INSERT INTO rl6_credit_transactions 
(user_id, amount, type, description, order_id)
VALUES ([USER_ID], [MONTO], 'debit', 'Compra orden #[ORDER_ID]', [ORDER_ID]);
```

### **Integración con tuu_orders**
- Independiente de Webpay
- Agregar: `pagado_con_credito_rl6` (TINYINT), `monto_credito_rl6` (DECIMAL)
- Registrar cada compra con crédito

### **Pestaña "Crédito" en App**
- Solo para militares RL6 (`es_militar_rl6 = 1`)
- Mostrar: límite, usado, disponible
- Historial últimas 20 transacciones
- Sin notificaciones push ni banners

---

## 🔐 Seguridad

### **Rate Limiting Básico**
- Máximo 5 registros por IP en 1 hora
- En `/api/rl6/register_militar.php`
- Protege contra bots automáticos

### **Validación Manual**
- Admin llama al militar para confirmar
- Solicita selfie como parte del proceso
- Revisa carnets (frontal/trasero)
- Valida RUT con rutificador web

### **Auditoría Completa**
- Tabla `rl6_credit_audit` registra cambios
- Acciones: approve, reject, update_limit, delete_user
- Timestamp y admin_id en cada acción

---

## 📧 Sistema de Emails (Gmail API)

### **Email 1: Registro Exitoso** (inmediato)
- Confirmación de datos recibidos
- Resumen: Nombre, RUT, Grado, Unidad
- Estado: EN REVISIÓN

### **Email 2: Aprobación de Crédito** (cuando admin aprueba)
- Felicitaciones
- Límite asignado
- Crédito disponible
- Instrucciones de uso

### **Email 3: Rechazo** (cuando admin rechaza)
- Información de rechazo
- Opción de apelar
- Contacto para consultas

---

## 🗄️ Tablas Nuevas

### **rl6_credit_transactions**
- Historial de movimientos (crédito/débito)
- Saldo anterior y nuevo
- Vinculado a `tuu_orders`
- Auditoría completa

### **rl6_credit_audit**
- Cambios realizados por admin
- Acciones: approve, reject, update_limit, delete_user
- Motivo de rechazo
- Timestamp y admin_id

---

## 🎯 Flujos Principales

### **Registro Militar**
1. Accede a `/rl6`
2. Completa formulario (datos + carnets)
3. Rate limiting: máx 5 por IP/hora
4. Sube carnets a S3
5. Crea usuario con `es_militar_rl6 = 1`
6. Envía email de registro
7. Estado: EN REVISIÓN

### **Aprobación (Admin en caja.laruta11.cl)**
1. Revisa militar pendiente
2. Verifica carnets
3. Valida RUT
4. Ingresa límite de crédito
5. Aprueba o rechaza
6. Si rechaza: elimina usuario
7. Registra en auditoría
8. Envía email al militar

### **Uso de Crédito**
1. Militar compra en app
2. Valida saldo disponible
3. Si OK: procesa compra
4. Descuenta crédito usado
5. Registra en `rl6_credit_transactions`
6. Registra en `tuu_orders`
7. Saldo se actualiza automáticamente

---

## 📋 Detalles Técnicos

### **Validación de RUT**
- Solo formato + dígito verificador
- Validación manual: humano revisa carnet + rutificador web
- No hay API gratis de validación real

### **Rechazo de Solicitud**
- Si rechaza → eliminar usuario
- Puede intentar de nuevo (nuevo registro)
- Solo 1 intento por sesión

### **Expiración de Crédito**
- NO expira
- Es saldo permanente
- Admin asigna nuevo crédito cuando paga

### **Admin Panel (caja.laruta11.cl)**
- Acceso: sistema admin existente
- Roles: super_admin, gerentes (a definir)
- Funciones: listar, aprobar, rechazar, ver historial, auditoría

### **Integración con Checkout**
- Validar saldo disponible ANTES
- Descontar DESPUÉS de pago exitoso
- Registrar en `tuu_orders`
- Actualizar `credito_usado` automáticamente

---

## 📊 Queries SQL Listas

Ver documento: `SISTEMA_RL6_QUERIES_SQL.md`

---

## 📁 Archivos a Crear

### **Backend APIs**
- `/api/rl6/register_militar.php` - Registro con rate limiting
- `/api/rl6/update_militar_data.php` - Actualizar datos
- `/api/rl6/get_militar_profile.php` - Obtener perfil
- `/api/rl6/send_rl6_emails.php` - Enviar emails
- `/api/rl6/setup_rl6_tables.php` - Crear tablas

### **Frontend**
- `/src/pages/rl6.astro` - Página de registro
- `/src/components/RL6CarnetUpload.jsx` - Subida de carnets
- `/src/utils/rutValidator.js` - Validador de RUT

### **Admin (caja.laruta11.cl)**
- `/admin/militares-rl6.astro` - Panel de gestión
- APIs de aprobación/rechazo

---

## ⏱️ Estimación de Tiempos

| Fase | Tarea | Tiempo |
|------|-------|--------|
| 1 | Setup BD (queries) | 30 min |
| 2 | APIs Backend | 2 horas |
| 3 | Frontend RL6 | 3 horas |
| 4 | Panel Admin | 2 horas |
| 5 | Testing | 1 hora |
| 6 | Deployment | 30 min |
| **TOTAL** | | **9 horas** |

---

## 📞 Documentos Relacionados

- `SISTEMA_RL6_QUERIES_SQL.md` - Queries listas para copiar/pegar
- `SISTEMA_RL6_EMAILS.md` - Detalles del sistema de emails

---

**Estado**: ✅ Planificación Completa con Todos los Insights
**Versión**: 2.0
**Última actualización**: Enero 2025
