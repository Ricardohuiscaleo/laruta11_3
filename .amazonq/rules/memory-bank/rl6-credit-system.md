# Sistema de Crédito RL6 - La Ruta 11

## 📊 Resumen
Sistema de crédito exclusivo para militares del Regimiento Logístico N°6 (RL6) con pago mensual el día 21.

## 🗄️ Estructura de Base de Datos

### Tabla: `usuarios`
Campos relacionados con crédito RL6:
- `es_militar_rl6` (tinyint): 1 si es militar RL6
- `credito_aprobado` (tinyint): 1 si tiene crédito aprobado
- `limite_credito` (decimal): Límite total de crédito (ej: $50,000)
- `credito_usado` (decimal): Monto actualmente usado
- `grado_militar` (varchar): Grado del militar
- `unidad_trabajo` (varchar): Unidad de trabajo
- `credito_bloqueado` (tinyint): 1 si está bloqueado por falta de pago
- `fecha_ultimo_pago` (date): Fecha del último pago realizado

### Tabla: `rl6_credit_transactions`
Registro de todas las transacciones de crédito:
```sql
CREATE TABLE rl6_credit_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    type ENUM('credit','debit','refund') NOT NULL,
    description VARCHAR(255),
    order_id VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES usuarios(id)
);
```

**Tipos de transacciones:**
- `debit`: Compra con crédito (resta del límite)
- `refund`: Reembolso por anulación o pago (suma al límite)
- `credit`: Ajuste manual de crédito (no usado actualmente)

### Tabla: `tuu_orders`
Órdenes de pago (incluye pagos de crédito RL6):
- Campo `pagado_con_credito_rl6`: 1 si la orden fue pagada con crédito RL6
- Campo `monto_credito_rl6`: Monto usado del crédito RL6
- Campo `payment_method`: Incluye opción 'rl6_credit'

## 🔄 Flujos del Sistema

### 1. Compra con Crédito RL6
```
Usuario selecciona productos → Checkout
→ Selecciona "Pagar con Crédito RL6"
→ Validaciones:
   ✓ es_militar_rl6 = 1
   ✓ credito_aprobado = 1
   ✓ credito_bloqueado = 0
   ✓ (credito_usado + monto_compra) <= limite_credito
→ Si OK:
   - Inserta debit en rl6_credit_transactions
   - Actualiza usuarios.credito_usado += monto
   - Crea orden con pagado_con_credito_rl6 = 1
→ Procesa inventario normalmente
```

### 2. Anulación de Pedido
```
Cashier anula pedido pagado con RL6
→ Inserta refund en rl6_credit_transactions
→ Actualiza usuarios.credito_usado -= monto
→ Restaura inventario
```

### 3. Pago de Crédito (Día 21)
```
Usuario accede a estado de cuenta
→ Ve saldo pendiente (credito_usado)
→ Click "Pagar con TUU"
→ create_payment.php:
   - Crea orden RL6-xxx en tuu_orders
   - Monto = credito_usado (pago total)
   - Redirige a Webpay
→ Usuario paga en Webpay
→ payment_callback.php:
   - Valida: payment_status='paid' AND tuu_message='Transaccion aprobada'
   - Si OK:
     * Inserta refund en rl6_credit_transactions
     * Actualiza usuarios.credito_usado = 0
     * Actualiza usuarios.fecha_ultimo_pago = NOW()
     * Envía email de confirmación
```

## 🚫 Sistema de Bloqueos

### ¿Cuándo se bloquea un usuario?

**PENDIENTE DE IMPLEMENTAR:**

1. **Bloqueo automático día 22** (si no pagó el día 21):
   - Cron job diario que verifica:
     - Si `credito_usado > 0`
     - Si `fecha_ultimo_pago < día 21 del mes actual`
   - Acción: `credito_bloqueado = 1`

2. **Validación en checkout**:
   - Si `credito_bloqueado = 1` → No permite usar crédito
   - Mensaje: "Tu crédito está bloqueado por falta de pago. Por favor paga tu saldo pendiente."

3. **Desbloqueo automático**:
   - Al confirmar pago exitoso → `credito_bloqueado = 0`

### Implementación Pendiente

**Archivo a crear:** `app3/api/rl6/check_overdue_payments.php`
```php
// Ejecutar diariamente vía cron (día 22 de cada mes)
// Bloquear usuarios con credito_usado > 0 que no pagaron
```

**Archivo a crear:** `app3/api/rl6/validate_credit_purchase.php`
```php
// Validar antes de permitir compra con crédito
// Verificar credito_bloqueado = 0
```

## 📧 Sistema de Notificaciones

### Emails Implementados:
1. ✅ **Estado de cuenta mensual** (día 1-5 de cada mes)
2. ✅ **Confirmación de pago** (al pagar con TUU)

### Email Configuration:
- **Sender Name**: "La Ruta 11 <saboresdelaruta11@gmail.com>" (includes name in From header)
- **Gmail API**: OAuth tokens stored in MySQL (`gmail_tokens` table) for persistence
- **Auto-refresh**: GitHub Actions renews tokens every 30 minutes
- **CC**: Automatic copy to business email on critical emails (payments, failures)

### Email Design Standards:
- **Mobile-First**: Outer padding 5px, internal padding 20px for mobile optimization
- **Layout**: Table-based with inline styles for email client compatibility
- **Header**: Horizontal layout with logo and text inline (single row)
- **Cards**: Consistent div-based structure with equal widths on mobile
- **Buttons**: Blue gradient (#3b82f6 → #2563eb) for primary actions
- **Instructions**: Step-by-step numbered circles for payment flows
- **Escaping**: Use `&quot;` HTML entities for quotes in inline styles

### Emails Pendientes:
3. ⏳ **Recordatorio de pago** (día 18-19)
4. ⏳ **Aviso de bloqueo** (día 22 si no pagó)
5. ⏳ **Confirmación de desbloqueo** (al pagar después de bloqueo)

## 📊 Reportes y Consultas

### Endpoints Implementados:
- `/api/rl6/get_credit.php` - Info de crédito del usuario
- `/api/rl6/get_statement.php` - Estado de cuenta completo
- `/api/rl6/create_payment.php` - Iniciar pago con TUU
- `/api/rl6/payment_callback.php` - Procesar pago TUU

### Endpoints Pendientes:
- `/api/rl6/validate_purchase.php` - Validar compra con crédito
- `/api/rl6/check_overdue.php` - Verificar pagos atrasados
- `/api/rl6/admin_report.php` - Reporte para administración

## 🔐 Seguridad

### Validaciones Críticas:
1. ✅ Solo usuarios con `es_militar_rl6 = 1` pueden acceder
2. ✅ Solo usuarios con `credito_aprobado = 1` pueden usar crédito
3. ✅ Refund solo si `payment_status='paid'` AND `tuu_message='Transaccion aprobada'`
4. ⏳ Validar `credito_bloqueado = 0` antes de compra
5. ⏳ Validar límite de crédito disponible

## 📝 Notas Importantes

### Tabla `rl6_payments` - NO SE USA
- Inicialmente propuesta pero **NO implementada**
- Usamos `tuu_orders` existente para pagos de crédito
- Identificamos pagos RL6 por: `order_number` empieza con 'RL6-'

### Cálculo de Saldo
- **Saldo a pagar** = `usuarios.credito_usado`
- **Crédito disponible** = `limite_credito - credito_usado`
- NO calculamos desde transacciones (puede haber inconsistencias por refunds con order_id diferentes)

### Fecha de Pago
- **Día oficial**: 21 de cada mes
- **Gracia**: Hasta día 21 a las 23:59
- **Bloqueo**: Día 22 a las 00:00 (automático vía cron)

## 🚀 Próximos Pasos

### Prioridad Alta:
1. [ ] Implementar sistema de bloqueos automáticos
2. [ ] Validación de crédito en checkout
3. [ ] Email recordatorio de pago (día 18-19)
4. [ ] Cron job para bloqueos (día 22)

### Prioridad Media:
5. [ ] Panel admin para gestionar créditos
6. [ ] Reporte de morosidad
7. [ ] Historial de pagos en app3
8. [ ] Notificaciones push para recordatorios

### Prioridad Baja:
9. [ ] Estadísticas de uso de crédito
10. [ ] Exportar estados de cuenta a PDF
11. [ ] Sistema de cuotas (si se requiere en futuro)

## 📞 Contacto y Soporte
- Email: saboresdelaruta11@gmail.com
- Teléfono: +56 9 3622 7422
- Soporte técnico: +56 9 4539 2581
