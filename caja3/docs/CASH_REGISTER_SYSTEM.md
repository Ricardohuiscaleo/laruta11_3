# Sistema de Arqueo de Caja

## 📋 Descripción

Sistema completo para gestionar sesiones de caja con apertura, cierre y arqueo automático de ventas.

## 🗄️ Base de Datos

### Crear Tabla

**Opción 1: Ejecutar PHP**
```
https://caja.laruta11.cl/api/setup_cash_register_table.php
```

**Opción 2: Ejecutar SQL directamente en MySQL**
```sql
-- Ver archivo: api/setup_cash_register.sql
```

### Estructura de la Tabla `cash_register_sessions`

```sql
- id: ID único de la sesión
- session_date: Fecha de la sesión (DATE)
- opened_at: Fecha/hora de apertura (DATETIME)
- closed_at: Fecha/hora de cierre (DATETIME)
- opened_by: Usuario que abrió (VARCHAR)
- closed_by: Usuario que cerró (VARCHAR)

-- Totales por método de pago
- cash_total, cash_count: Efectivo
- card_total, card_count: Tarjetas POS
- transfer_total, transfer_count: Transferencias
- pedidosya_total, pedidosya_count: PedidosYA
- webpay_total, webpay_count: App Webpay

-- Totales generales
- total_amount: Monto total
- total_orders: Cantidad de pedidos

-- Estado y notas
- status: ENUM('open', 'closed')
- opening_notes: Notas de apertura
- closing_notes: Notas de cierre
- whatsapp_sent: Si se envió por WhatsApp
- whatsapp_sent_at: Cuándo se envió
```

## 🔌 APIs Disponibles

### 1. Verificar Estado de Caja
```
GET /api/get_cash_register_status.php
```
Retorna si hay sesión abierta hoy.

### 2. Abrir Caja
```
POST /api/open_cash_register.php
Body: {
  "opened_by": "Nombre del cajero",
  "opening_notes": "Notas opcionales"
}
```

### 3. Cerrar Caja
```
POST /api/close_cash_register.php
Body: {
  "session_id": 123, // Opcional, usa sesión abierta si no se proporciona
  "closed_by": "Nombre del cajero",
  "closing_notes": "Notas opcionales"
}
```
Al cerrar, automáticamente:
- Calcula totales desde `opened_at` hasta ahora
- Guarda resumen por método de pago
- Cambia status a 'closed'

### 4. Obtener Resumen de Ventas
```
GET /api/get_sales_summary.php
```
Si hay sesión abierta, muestra ventas desde apertura.
Si no hay sesión, muestra ventas del día completo.

## 🖥️ Interfaz de Usuario

### Botón Flotante en Caja
- Ubicación: Izquierda, debajo del header
- Texto: "$ Ventas" (se reduce a "$" al hacer scroll)
- Click: Redirige a `/arqueo`

### Página de Arqueo (`/arqueo`)

**Funcionalidades:**
1. **Abrir Caja**: Botón visible cuando no hay sesión abierta
2. **Cerrar Caja**: Botón visible cuando hay sesión abierta
3. **Ver Resumen**: Tarjetas con totales por método de pago
4. **Enviar WhatsApp**: Mensaje estructurado con arqueo completo
5. **Volver a Caja**: Regresa a la pantalla principal

**Tarjetas Mostradas:**
- 💵 Efectivo
- 💳 Tarjetas (POS)
- 🏦 Transferencias
- 🛵 PedidosYA
- 💳 App (Webpay)
- 📊 TOTAL GENERAL

## 📱 Mensaje de WhatsApp

Formato del mensaje al cerrar caja:
```
*ARQUEO DE CAJA - LA RUTA 11*

*Fecha:* 24/10/2024
*Hora:* 14:30

*💵 Efectivo:* $45.000 (12 pedidos)
*💳 Tarjetas:* $78.500 (8 pedidos)
*🏦 Transferencias:* $32.000 (5 pedidos)
*🛵 PedidosYA:* $15.000 (2 pedidos)
*💳 App (Webpay):* $89.000 (10 pedidos)

*📊 TOTAL:* $259.500
*Total Pedidos:* 37

Arqueo generado desde App Caja
```

## 🔄 Flujo de Trabajo

### Inicio del Día
1. Cajero abre la app
2. Click en botón "$ Ventas"
3. Click en "🔓 Abrir Caja"
4. Sistema registra hora de apertura
5. Comienza a registrar ventas

### Durante el Día
- Todas las ventas se registran en `tuu_orders`
- El arqueo muestra ventas desde apertura
- Se puede consultar en cualquier momento

### Cierre del Día
1. Click en "🔒 Cerrar Caja"
2. Sistema calcula totales automáticamente
3. Guarda resumen en `cash_register_sessions`
4. Envía arqueo por WhatsApp automáticamente
5. Sesión queda cerrada

## 🔍 Consultas Útiles

### Ver sesiones de hoy
```sql
SELECT * FROM cash_register_sessions 
WHERE session_date = CURDATE();
```

### Ver sesiones abiertas
```sql
SELECT * FROM cash_register_sessions 
WHERE status = 'open';
```

### Ver histórico de sesiones
```sql
SELECT 
    session_date,
    opened_at,
    closed_at,
    total_amount,
    total_orders,
    status
FROM cash_register_sessions
ORDER BY session_date DESC, opened_at DESC
LIMIT 30;
```

### Totales por método de pago (último mes)
```sql
SELECT 
    SUM(cash_total) as total_efectivo,
    SUM(card_total) as total_tarjetas,
    SUM(transfer_total) as total_transferencias,
    SUM(pedidosya_total) as total_pedidosya,
    SUM(webpay_total) as total_webpay,
    SUM(total_amount) as total_general
FROM cash_register_sessions
WHERE session_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
AND status = 'closed';
```

## ⚠️ Notas Importantes

1. **Una sesión por día**: Solo se puede tener una sesión abierta por fecha
2. **Cierre automático**: Al cerrar, se calculan totales desde apertura
3. **Datos en tiempo real**: El resumen siempre muestra datos actuales de `tuu_orders`
4. **WhatsApp automático**: Al cerrar caja, se sugiere enviar arqueo por WhatsApp
5. **Histórico completo**: Todas las sesiones quedan registradas para auditoría

## 🚀 Próximas Mejoras

- [ ] Reportes históricos de sesiones
- [ ] Comparación entre días
- [ ] Gráficos de tendencias
- [ ] Exportar a Excel/PDF
- [ ] Múltiples cajeros por sesión
- [ ] Diferencias de caja (esperado vs real)
