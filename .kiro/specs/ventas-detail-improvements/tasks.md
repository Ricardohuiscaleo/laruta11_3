# Implementation Plan: Ventas Detail Improvements

## Overview

Mejoras a la sección Ventas del admin panel: nuevo endpoint de detalle de orden, helper de timezone Chile, reestructuración de columnas de tabla, y filas expandibles con detalle de ítems/ingredientes. Backend en PHP (Laravel), frontend en TypeScript (React).

## Tasks

- [-] 1. Backend — Agregar `getOrderDetail()` a VentasService
  - [x] 1.1 Implementar método `getOrderDetail(string $orderNumber): ?array` en `mi3/backend/app/Services/Ventas/VentasService.php`
    - Query 1: Buscar orden en `tuu_orders` por `order_number` con `payment_status = 'paid'`
    - Query 2: Obtener ítems de `tuu_order_items` por `order_reference`
    - Query 3: Obtener consumo real de ingredientes desde `inventory_transactions` (type `sale`) JOIN `ingredients`
    - Query 4 (fallback): Calcular consumo teórico desde `product_recipes` JOIN `ingredients` para ítems sin transacciones
    - Determinar `stock_status` (`ok` / `warning`) comparando `new_stock` con `min_stock_level`
    - Calcular totales: subtotal, costo total, utilidad total
    - Retornar `null` si la orden no existe
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8, 5.1, 5.2, 5.3, 5.4, 5.5_

- [ ] 2. Backend — Agregar método `detail()` a VentasController
  - [x] 2.1 Implementar método `detail(string $orderNumber): JsonResponse` en `mi3/backend/app/Http/Controllers/Admin/VentasController.php`
    - Delegar a `VentasService::getOrderDetail($orderNumber)`
    - Retornar 404 con `{ success: false, message: "Orden no encontrada" }` si el servicio retorna `null`
    - Retornar 200 con `{ success: true, data: ... }` si la orden existe
    - _Requirements: 3.1, 3.8_

- [ ] 3. Backend — Registrar ruta `GET ventas/{orderNumber}/detail`
  - [x] 3.1 Agregar la ruta en `mi3/backend/routes/api.php` **antes** de la ruta `GET ventas` existente
    - `Route::get('ventas/{orderNumber}/detail', [VentasController::class, 'detail']);`
    - Verificar que no conflicte con rutas existentes (`ventas/kpis`, `ventas`)
    - _Requirements: 3.1_

- [x] 4. Checkpoint — Verificar backend
  - Ensure all tests pass, ask the user if questions arise.

- [-] 5. Frontend — Agregar helper `formatChileDateTime` y reemplazar `formatTime`
  - [x] 5.1 Crear función `formatChileDateTime(dateStr: string): string` en `mi3/frontend/components/admin/VentasPageContent.tsx`
    - Usar `Intl.DateTimeFormat` con `timeZone: 'America/Santiago'`, formato `dd/MM HH:mm`, `hour12: false`
    - Reemplazar todos los usos de `formatTime()` por `formatChileDateTime()`
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 2.2_

- [ ] 6. Frontend — Reestructurar columnas de la tabla de transacciones
  - [x] 6.1 Modificar la tabla desktop en `mi3/frontend/components/admin/VentasPageContent.tsx`
    - Eliminar columnas "Fuente" y "Hora"
    - Agregar columna "Fecha" al final con formato `dd/MM HH:mm` usando `formatChileDateTime()`
    - Actualizar `colSpan` de filas vacías de 7 a 6
    - _Requirements: 2.1, 2.2, 2.3_
  - [x] 6.2 Modificar las cards mobile en `mi3/frontend/components/admin/VentasPageContent.tsx`
    - Reemplazar el badge de fuente por la fecha/hora en formato `dd/MM HH:mm` usando `formatChileDateTime()`
    - _Requirements: 2.4_

- [ ] 7. Frontend — Agregar detalle expandible con `OrderDetailPanel`
  - [x] 7.1 Agregar interfaces TypeScript para la respuesta del endpoint
    - Definir `OrderDetail`, `OrderDetailItem`, `IngredientConsumption` según el diseño
    - _Requirements: 3.1, 3.2, 3.3_
  - [x] 7.2 Agregar estado local para expand/collapse y fetch del detalle
    - `expandedOrder`, `orderDetail`, `detailLoading`, `detailError`
    - Implementar lógica: click expande/colapsa, solo una orden expandida a la vez
    - Fetch `GET /api/v1/admin/ventas/{orderNumber}/detail` al expandir
    - _Requirements: 4.1, 4.6, 4.7, 4.8_
  - [x] 7.3 Implementar componente `OrderDetailPanel` inline
    - Header: fecha/hora (TZ Chile), número de orden, método de pago
    - Tabla de ítems: nombre × cantidad, precio unitario, costo, utilidad
    - Sub-tabla de ingredientes por ítem: nombre, cantidad usada (con unidad), stock antes → después, indicador ✓/⚠
    - Footer: subtotal, costo total, utilidad total
    - Etiquetas en español
    - _Requirements: 4.2, 4.3, 4.4, 4.5, 4.10_
  - [x] 7.4 Integrar fila expandible en la tabla desktop
    - Insertar `<tr>` con `<td colSpan={6}>` después de la fila clickeada
    - Agregar ícono chevron para indicar estado abierto/cerrado
    - Mostrar spinner mientras carga, mensaje de error si falla
    - _Requirements: 4.1, 4.7, 4.8_
  - [x] 7.5 Integrar detalle expandible en las cards mobile
    - Agregar `<div>` expandible debajo de la card clickeada
    - Adaptar layout del `OrderDetailPanel` para pantallas pequeñas
    - _Requirements: 4.9_

- [x] 8. Final checkpoint — Verificar integración completa
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- No se crean tablas nuevas ni se modifican las existentes en la base de datos
- El backend retorna `created_at` en UTC; la conversión a Chile TZ se hace en frontend con `Intl.DateTimeFormat`
- El detalle se carga lazy (bajo demanda al expandir), no se precarga
- Cada tarea referencia los requisitos específicos para trazabilidad
