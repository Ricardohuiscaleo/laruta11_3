# Sistema de Control de Horarios y Pedidos Programados

## 📋 Resumen

Se ha implementado un sistema completo de control de horarios de atención con la capacidad de programar pedidos fuera del horario de servicio.

## ⏰ Horarios de Atención (Hora Chile)

- **Lunes a Jueves**: 18:00 - 00:30
- **Viernes y Sábado**: 18:00 - 02:30
- **Domingo**: 18:00 - 00:00

## 🚀 Archivos Creados/Modificados

### Nuevos Archivos

1. **`src/utils/businessHours.js`**
   - Utilidad para verificar horarios de atención
   - Genera slots disponibles para programar pedidos
   - Maneja zona horaria de Chile (America/Santiago)

2. **`src/components/ScheduleOrderModal.jsx`**
   - Modal para seleccionar fecha y hora de entrega
   - Muestra slots disponibles en rangos de 1 hora
   - Validación de horarios disponibles

3. **`api/check_business_hours.php`**
   - API backend para validar horarios
   - Retorna estado actual del negocio
   - Zona horaria: America/Santiago

4. **`api/add_scheduled_columns.php`**
   - Script de migración de base de datos
   - Agrega columnas `scheduled_time` e `is_scheduled`

### Archivos Modificados

1. **`src/components/CheckoutApp.jsx`**
   - Integración de verificación de horarios
   - Botón "Programar Pedido" cuando está cerrado
   - Muestra banner de estado (abierto/cerrado)
   - Envía información de pedido programado

2. **`api/create_transfer_order.php`**
   - Soporte para pedidos programados
   - Guarda `scheduled_time` e `is_scheduled`

## 📦 Instalación

### 1. Ejecutar Migración de Base de Datos

```bash
# Acceder a la URL en el navegador
https://app.laruta11.cl/api/add_scheduled_columns.php
```

O ejecutar SQL directamente:

```sql
ALTER TABLE tuu_orders 
ADD COLUMN scheduled_time DATETIME NULL COMMENT 'Fecha y hora programada para el pedido',
ADD COLUMN is_scheduled TINYINT(1) DEFAULT 0 COMMENT 'Indica si es un pedido programado';
```

### 2. Verificar Archivos

Asegúrate de que todos los archivos nuevos estén en su lugar:

```
ruta11app/
├── src/
│   ├── utils/
│   │   └── businessHours.js
│   └── components/
│       ├── CheckoutApp.jsx (modificado)
│       └── ScheduleOrderModal.jsx
└── api/
    ├── check_business_hours.php
    ├── add_scheduled_columns.php
    └── create_transfer_order.php (modificado)
```

### 3. Rebuild del Proyecto

```bash
npm run build
```

## 🎯 Funcionalidades

### Dentro de Horario
- ✅ Compra normal sin restricciones
- ✅ Selección de horario de retiro inmediato
- ✅ Pago online o transferencia

### Fuera de Horario
- ✅ Banner informativo "Cerrado - Abre [día] a las [hora]"
- ✅ Botón "Programar Pedido" reemplaza botones de pago
- ✅ Modal con slots disponibles (rangos de 1 hora)
- ✅ Confirmación visual del horario programado
- ✅ Información incluida en WhatsApp y orden

## 📱 Flujo de Usuario

### Escenario 1: Dentro de Horario
1. Usuario agrega productos al carrito
2. Va a checkout
3. Ve horarios disponibles para retiro inmediato
4. Procede al pago normalmente

### Escenario 2: Fuera de Horario
1. Usuario agrega productos al carrito
2. Va a checkout
3. Ve banner "Cerrado - Abre [día] a las [hora]"
4. Click en "Programar Pedido"
5. Selecciona fecha y hora deseada (slots de 1 hora)
6. Confirma programación
7. Ve confirmación visual con horario seleccionado
8. Procede al pago (transferencia o online)
9. Mensaje de WhatsApp incluye "⏰ PEDIDO PROGRAMADO: [fecha y hora]"

## 🔧 Configuración

### Modificar Horarios

Editar `src/utils/businessHours.js`:

```javascript
export const BUSINESS_HOURS = {
  1: { open: '18:00', close: '00:30', name: 'Lunes' },
  // ... modificar según necesidad
};
```

### Cambiar Intervalo de Slots

Por defecto: 1 hora. Para cambiar a 30 minutos, modificar en `businessHours.js`:

```javascript
// Línea ~60
for (let hour = startHour; hour < endHour; hour++) {
  // Agregar slots cada 30 minutos
  for (let minute of [0, 30]) {
    // ...
  }
}
```

## 🗄️ Base de Datos

### Nuevas Columnas en `tuu_orders`

```sql
scheduled_time DATETIME NULL
  - Fecha y hora programada (formato: YYYY-MM-DD HH:MM:SS)
  - NULL si es pedido inmediato

is_scheduled TINYINT(1) DEFAULT 0
  - 0: Pedido inmediato
  - 1: Pedido programado
```

### Consultas Útiles

```sql
-- Ver pedidos programados
SELECT order_number, customer_name, scheduled_time, status
FROM tuu_orders
WHERE is_scheduled = 1
ORDER BY scheduled_time ASC;

-- Pedidos programados para hoy
SELECT * FROM tuu_orders
WHERE is_scheduled = 1
AND DATE(scheduled_time) = CURDATE()
ORDER BY scheduled_time ASC;

-- Pedidos programados pendientes
SELECT * FROM tuu_orders
WHERE is_scheduled = 1
AND status = 'unpaid'
AND scheduled_time >= NOW()
ORDER BY scheduled_time ASC;
```

## 📊 Monitoreo

### API de Estado

```bash
# Verificar estado actual del negocio
curl https://app.laruta11.cl/api/check_business_hours.php
```

Respuesta:
```json
{
  "success": true,
  "status": {
    "isOpen": false,
    "currentDay": "Lunes",
    "openTime": "18:00",
    "closeTime": "00:30",
    "message": "Cerrado - Abre Lunes a las 18:00",
    "currentTime": "14:30",
    "timezone": "America/Santiago"
  }
}
```

## 🎨 Personalización UI

### Colores del Banner

En `CheckoutApp.jsx`, línea ~200:

```jsx
<div className="bg-orange-100 border border-orange-300 rounded-lg p-2 mb-3 text-center">
  {/* Cambiar colores aquí */}
</div>
```

### Texto del Modal

En `ScheduleOrderModal.jsx`, línea ~30:

```jsx
<p className="text-sm text-orange-800">
  Estamos fuera de horario. Programa tu pedido...
</p>
```

## ⚠️ Consideraciones

1. **Zona Horaria**: Todo el sistema usa `America/Santiago`
2. **Slots**: Se generan hasta 7 días en el futuro
3. **Validación**: Backend valida horarios antes de crear orden
4. **WhatsApp**: Mensaje incluye horario programado automáticamente
5. **Cierre después de medianoche**: Manejado correctamente (ej: Viernes hasta 02:30)

## 🐛 Troubleshooting

### Problema: Horarios incorrectos
**Solución**: Verificar zona horaria del servidor
```bash
date
# Debe mostrar hora de Chile
```

### Problema: No aparece botón "Programar Pedido"
**Solución**: Verificar que `businessHours.js` esté importado correctamente

### Problema: Error en base de datos
**Solución**: Ejecutar migración nuevamente
```bash
https://app.laruta11.cl/api/add_scheduled_columns.php
```

## 📞 Soporte

Para dudas o problemas, revisar:
1. Console del navegador (F12)
2. Logs del servidor PHP
3. Estado de la API: `/api/check_business_hours.php`

## ✅ Testing

### Checklist de Pruebas

- [ ] Verificar horarios en `businessHours.js`
- [ ] Ejecutar migración de BD
- [ ] Probar compra dentro de horario
- [ ] Probar compra fuera de horario
- [ ] Verificar modal de programación
- [ ] Confirmar slots disponibles
- [ ] Validar mensaje de WhatsApp
- [ ] Revisar orden en base de datos
- [ ] Probar en diferentes días de la semana
- [ ] Verificar cierre después de medianoche

---

**Versión**: 1.0.0  
**Fecha**: 2024  
**Autor**: Amazon Q Developer
