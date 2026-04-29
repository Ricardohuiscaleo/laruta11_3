# Ventas Dashboard Pro — Requirements

## Contexto

El panel de ventas actual (`mi.laruta11.cl/admin/ventas`) muestra KPIs básicos, desglose por método de pago, y una lista de transacciones con detalle expandible. Necesita evolucionar a un dashboard profesional de Estado de Resultados con gráficos, mapa de concentración, monitor en vivo, y layout split desktop/mobile-first.

## Archivos existentes relevantes

- #[[file:mi3/frontend/components/admin/VentasPageContent.tsx]] — Componente principal actual
- #[[file:mi3/frontend/components/admin/sections/VentasSection.tsx]] — Wrapper con tabs período
- #[[file:mi3/backend/app/Services/Ventas/VentasService.php]] — Backend: KPIs, transacciones, breakdown, detalle
- #[[file:mi3/backend/app/Http/Controllers/Admin/VentasController.php]] — Controller API
- #[[file:mi3/frontend/hooks/useDeliveryTracking.ts]] — Referencia de patrón Echo/Reverb realtime

## Requirements

### REQ-1: Layout Split Desktop 50/50
- **Desktop (md+)**: Dividir en 2 columnas — izquierda (50%) secciones colapsables con datos, derecha (50%) gráficos y mapa
- **Mobile**: Apilar todo en una columna, con Estado de Resultados al principio (arriba de todo)
- Mantener tabs de período existentes (Turno, Hoy, Semana, Mes) en el header

### REQ-2: Monitor Ventas en Vivo (arriba de todo)
- Banner/card arriba del Estado de Resultados con las ventas del turno actual en tiempo real
- Cuando llega una venta nueva via WebSocket (Echo/Reverb canal `admin.ventas` evento `.venta.nueva`), mostrar notificación animada con el pedido
- Chevron expandible para ver detalle de las últimas ventas en vivo
- Indicador de conexión WebSocket (verde = conectado, rojo = desconectado)
- Sonido opcional de notificación (toggle on/off)
- Contador de pedidos del turno con animación al incrementar

### REQ-3: Chevron "Ventas Netas" (Estado de Resultados)
- Sección colapsable con chevron ▸/▾
- **Resumen cerrado**: Ventas Netas $X.XXX.XXX | Margen XX% | N pedidos
- **Expandido**: Tabla Estado de Resultados completo:
  - Ventas netas (sin delivery)
  - + Delivery cobrado
  - = Ingreso bruto total
  - − Costo de venta (CMV)
  - = **Utilidad bruta**
  - Margen bruto %
  - Ticket promedio
  - Pedidos/día promedio
- Sub-sección: Desglose por método de pago (ya existe, mover aquí dentro)
  - Cada método: pedidos, venta neta, costo, utilidad, margen %
- Sub-sección: Totales por producto (ingresos)
  - Top productos ordenados por venta total
  - Cada producto: cantidad vendida, venta total, % del total de ventas

### REQ-4: Chevron "Costo Ingredientes (CMV)"
- Sección colapsable con chevron ▸/▾
- **Resumen cerrado**: CMV $XXX.XXX | % sobre ventas
- **Expandido**: Tabla de ingredientes ordenados por costo total descendente
  - Ingrediente, cantidad consumida, unidad, costo total, % del CMV total
  - Highlight en rojo los ingredientes que representan >10% del CMV
- Requiere nuevo endpoint backend que agrupe `inventory_transactions` por ingrediente para el período

### REQ-5: Chevron "Nómina"
- Sección colapsable con chevron ▸/▾
- **Resumen cerrado**: Nómina $XXX.XXX | N trabajadores
- **Expandido**: Resumen de nómina del período
  - Total sueldos pagados
  - Total adelantos
  - Total créditos trabajador (R11)
  - Costo nómina como % de ventas
- Consume datos del endpoint existente `GET /admin/payroll`

### REQ-6: Gráfico Ventas por Mes (Barras Apiladas)
- Gráfico de barras apiladas (stacked bar chart) — últimos 6 meses
- Cada barra tiene 3 segmentos apilados: Venta neta (verde), Costo (rojo), Delivery (azul)
- Eje Y en CLP, eje X meses
- Tooltip al hover con valores exactos
- Librería: **Recharts** (ya es dependencia común en Next.js, ligera, SSR-friendly)
- Requiere nuevo endpoint backend con datos mensuales agregados

### REQ-7: Mapa Concentración de Ventas en Arica
- Mapa ligero de carga rápida — **Leaflet + OpenStreetMap** (NO Google Maps, gratis, rápido)
- Centrado en Arica (-18.4747, -70.2989), zoom 13
- Heatmap/clusters de puntos de entrega basado en `delivery_address` geocodificado
- Posibilidad de pintar/editar zonas manualmente (polígonos) para definir zonas de delivery
- Las zonas pintadas se guardan en BD (nueva tabla `delivery_zones`)
- Colores por densidad de pedidos: verde (pocos) → amarillo → rojo (muchos)
- Requiere geocodificar direcciones de delivery (cache en BD para no repetir)

### REQ-8: Gráfico Productos Más Vendidos (Horizontal Bar)
- Gráfico de barras horizontales — Top 10 productos
- Cada barra dividida en 3 segmentos: Costo (rojo), Utilidad (verde), Margen % (label)
- Ordenado por cantidad vendida (el que más se vende arriba)
- Segundo ordenamiento disponible: por utilidad total (el que más utilidad deja)
- Toggle para cambiar entre "Más vendidos" y "Más rentables"
- Requiere nuevo endpoint backend con top productos agregados

### REQ-9: Todo en Realtime
- Todos los KPIs, gráficos y contadores se actualizan automáticamente cuando llega una venta nueva
- Usar el canal WebSocket existente `admin.ventas` con evento `.venta.nueva`
- Al recibir evento: refrescar KPIs, incrementar contadores, actualizar gráficos sin reload
- Animación sutil en los números que cambian (counter animation)
- El monitor de ventas en vivo muestra la nueva venta con animación slide-in

### REQ-10: Transacciones (existente, mejorar)
- Mantener la lista de transacciones actual con detalle expandible
- Mover al final (después de los gráficos en desktop, después de todo en mobile)
- Ya tiene chevron expandible por orden — mantener el diseño compacto actual con stock Antes|Consumo|Después

## Restricciones técnicas

- **Frontend**: Next.js 14, React, Tailwind CSS, Lucide icons
- **Gráficos**: Recharts (instalar si no existe)
- **Mapa**: Leaflet + react-leaflet + OpenStreetMap tiles (instalar)
- **Realtime**: Laravel Echo + Reverb (ya configurado)
- **Backend**: Laravel 11, PHP 8.3, MySQL
- **Mobile-first**: Todo debe funcionar bien en móvil, desktop es bonus
- **Performance**: Lazy load gráficos y mapa (dynamic import con `ssr: false`)
- **Accesibilidad**: aria-expanded en chevrones, role="tablist" en toggles, aria-label en gráficos
