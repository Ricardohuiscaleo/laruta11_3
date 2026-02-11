# 📋 PLAN DE IMPLEMENTACIÓN - DASHBOARD DE REPORTES

## 🎯 OBJETIVO

Crear una sección "Reportes" en el admin con gráficos interactivos para visualizar:
- Volumen de pedidos por mes
- Ventas totales
- Rentabilidad
- Canales de venta
- Productos top
- Ventas diarias

---

## 📁 ESTRUCTURA DE ARCHIVOS

```
/api/
├── get_financial_reports.php ✅ (YA EXISTE)
├── get_sales_by_product.php (CREAR)
├── get_daily_sales.php (CREAR)
└── get_sales_analytics.php (CREAR)

/src/pages/admin/
├── reportes.astro (CREAR)
└── reportes-detallado.astro (CREAR)
```

---

## 🔧 APIs A CREAR

### 1. get_sales_by_product.php
**Propósito**: Obtener productos más vendidos por mes

**Parámetros**:
- `mes` (opcional): Mes específico (YYYY-MM)
- `limit` (opcional): Top N productos (default: 10)

**Respuesta**:
```json
{
  "success": true,
  "mes": "2025-11",
  "productos": [
    {
      "nombre": "Pichanga Familiar",
      "veces_vendido": 30,
      "ingresos": 548092,
      "precio_promedio": 18270
    }
  ]
}
```

---

### 2. get_daily_sales.php
**Propósito**: Obtener ventas diarias para gráfico de línea

**Parámetros**:
- `mes` (requerido): Mes (YYYY-MM)

**Respuesta**:
```json
{
  "success": true,
  "mes": "2026-01",
  "dias": [
    {
      "fecha": "2026-01-01",
      "pedidos": 5,
      "ventas": 50000,
      "delivery": 2500
    }
  ]
}
```

---

### 3. get_sales_analytics.php
**Propósito**: Obtener datos agregados para todos los gráficos

**Respuesta**:
```json
{
  "success": true,
  "volumenes": [...],
  "ventas": [...],
  "rentabilidad": [...],
  "canales": [...],
  "productos_top": [...],
  "diarios": [...]
}
```

---

## 🎨 GRÁFICOS A IMPLEMENTAR

### Gráfico 1: Volumen de Pedidos (Línea)
```
Librería: Chart.js
Tipo: Line
Datos: Últimos 12 meses
Eje X: Mes
Eje Y: Cantidad de pedidos
Color: Azul
```

### Gráfico 2: Ventas Totales (Barras Apiladas)
```
Librería: Chart.js
Tipo: Bar (Stacked)
Datos: Últimos 12 meses
Eje X: Mes
Eje Y: Ventas ($)
Series: Ventas + Delivery
Colores: Verde (Ventas), Naranja (Delivery)
```

### Gráfico 3: Rentabilidad (Barras Coloreadas)
```
Librería: Chart.js
Tipo: Bar
Datos: Últimos 12 meses
Eje X: Mes
Eje Y: Utilidad Neta ($)
Color: Rojo si negativo, Verde si positivo
```

### Gráfico 4: Canales de Venta (Pie)
```
Librería: Chart.js
Tipo: Pie
Datos: Card, Cash, Transfer, PedidosYa, Webpay
Colores: Diferentes para cada canal
```

### Gráfico 5: Productos Top 5 (Barras Horizontales)
```
Librería: Chart.js
Tipo: HorizontalBar
Datos: Top 5 productos
Eje X: Ingresos ($)
Eje Y: Nombre del producto
```

### Gráfico 6: Ventas Diarias (Línea)
```
Librería: Chart.js
Tipo: Line
Datos: Mes actual (Enero)
Eje X: Día del mes
Eje Y: Ventas diarias ($)
Color: Naranja
```

---

## 📄 PÁGINA: reportes.astro

**Ubicación**: `/src/pages/admin/reportes.astro`

**Secciones**:
1. Header con filtros (Mes, Año)
2. KPIs principales (4 tarjetas)
3. Grid de 6 gráficos (2x3)
4. Tabla de datos detallados
5. Botón "Descargar PDF"

**KPIs a mostrar**:
- Total Ventas
- Total Pedidos
- Ticket Promedio
- Utilidad Neta

---

## 🔄 FLUJO DE DATOS

```
Admin abre /admin/reportes
    ↓
Página carga get_financial_reports.php
    ↓
Obtiene datos de últimos 12 meses
    ↓
Renderiza 6 gráficos con Chart.js
    ↓
Usuario puede filtrar por mes
    ↓
Gráficos se actualizan dinámicamente
```

---

## 📊 DATOS NECESARIOS POR GRÁFICO

| Gráfico | API | Datos Necesarios |
|---------|-----|------------------|
| Volumen | get_financial_reports | mes, pedidos |
| Ventas | get_financial_reports | mes, ventas, delivery |
| Rentabilidad | get_financial_reports | mes, utilidad_neta |
| Canales | get_financial_reports | canal, ventas |
| Productos | get_sales_by_product | producto, ingresos |
| Diarios | get_daily_sales | fecha, ventas |

---

## ⚙️ CONFIGURACIÓN TÉCNICA

**Librerías**:
- Chart.js 4.4.0 (ya incluida en admin)
- Tailwind CSS (ya incluida)

**Responsive**:
- Desktop: 2 gráficos por fila
- Tablet: 1 gráfico por fila
- Mobile: 1 gráfico por fila (full width)

**Actualización**:
- Datos se cargan al abrir página
- Botón "Actualizar" para refrescar
- Auto-refresh cada 5 minutos (opcional)

---

## 🎯 PRIORIDAD DE IMPLEMENTACIÓN

### Fase 1 (CRÍTICA)
- [ ] Crear get_financial_reports.php ✅ (HECHO)
- [ ] Crear página reportes.astro
- [ ] Implementar Gráfico 1 (Volumen)
- [ ] Implementar Gráfico 2 (Ventas)
- [ ] Implementar Gráfico 3 (Rentabilidad)

### Fase 2 (IMPORTANTE)
- [ ] Crear get_sales_by_product.php
- [ ] Implementar Gráfico 4 (Canales)
- [ ] Implementar Gráfico 5 (Productos)

### Fase 3 (COMPLEMENTARIA)
- [ ] Crear get_daily_sales.php
- [ ] Implementar Gráfico 6 (Diarios)
- [ ] Agregar filtros avanzados
- [ ] Exportar a PDF

---

## 📌 NOTAS IMPORTANTES

1. **Datos en tiempo real**: Los gráficos usan datos de `tuu_orders`
2. **Gastos fijos**: Hardcodeados en $1.500.000 (actualizar si cambia)
3. **Margen**: Asumido 40% de costo (ajustar si es diferente)
4. **Período**: Últimos 12 meses por defecto
5. **Caché**: Considerar caché de 5 minutos para no sobrecargar BD

---

## ✅ CHECKLIST DE IMPLEMENTACIÓN

- [ ] APIs creadas y testeadas
- [ ] Página reportes.astro creada
- [ ] 6 gráficos implementados
- [ ] Filtros funcionales
- [ ] Responsive en móvil
- [ ] Datos actualizados en tiempo real
- [ ] Botón "Actualizar" funcional
- [ ] Documentación completada
- [ ] Testing en producción

---

## 🚀 PRÓXIMO PASO

¿Empezamos con la Fase 1? Necesito crear:
1. Página `/src/pages/admin/reportes.astro`
2. Integrar get_financial_reports.php
3. Implementar 3 gráficos principales

¿Procedo?
