# Requirements: Acciones Masivas en Recetas + Sección Ventas

## Feature 1: Checkboxes + Acciones Masivas + Toggle ON/OFF

### REQ-1: Checkboxes de selección en listados
- Cada fila en Recetas, Bebidas y Combos tiene checkbox a la izquierda
- Checkbox "seleccionar todos" en el header de cada tabla
- Selección persiste al buscar/filtrar

### REQ-2: Barra de acciones masivas flotante
- Aparece cuando hay 1+ productos seleccionados
- Muestra: "X seleccionados" + botones: +$100, -$100, input custom, Eliminar, Toggle ON/OFF
- Sticky bottom en mobile, floating bar en desktop

### REQ-3: Toggle ON/OFF individual por producto
- Badge ON (verde) / OFF (rojo) en cada fila
- Click cambia is_active en BD via API
- Producto inactivo se muestra con opacidad reducida

### REQ-4: Modificación masiva de precios
- +$100 / -$100 aplica a todos los seleccionados
- Input custom permite cifra libre (ej: +500, -200)
- Confirmación antes de aplicar
- Backend: endpoint PUT /admin/productos/bulk-price

### REQ-5: Eliminación masiva (soft delete)
- Botón "Eliminar" desactiva (is_active=0) los seleccionados
- Confirmación con lista de nombres

## Feature 2: Sección Ventas en mi3

### REQ-6: Nueva sección "Ventas" en AdminShell
- URL: /admin/ventas
- Icono: DollarSign o Receipt
- Posición en sidebar: después de Recetas

### REQ-7: KPIs en tiempo real
- Ventas totales (sin delivery fee), Costo total, Utilidad, Cantidad pedidos
- Datos del turno actual por defecto
- Actualización via Reverb WebSocket (canal admin.ventas)

### REQ-8: Listado de transacciones eficiente
- Tabla con: #orden, cliente, monto, delivery_fee, método pago, fuente (app/caja/pedidosya), hora
- Filtros: turno_hoy, hoy, semana, mes, rango custom
- Búsqueda por nombre/orden
- Paginación server-side (50 por página)
- Query optimizada con índices en tuu_orders(created_at, payment_status)

### REQ-9: Desglose por método de pago
- Sección colapsable con totales por: efectivo, tarjeta, transferencia, webpay, pedidosya
- Cantidad de pedidos y monto por cada método
