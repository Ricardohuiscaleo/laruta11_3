# Instrucciones para KPIs y Dashboard

Este documento explica cómo configurar y utilizar los scripts para generar KPIs y visualizaciones en el dashboard.

## Archivos Incluidos

1. **seed_data.php**: Script para poblar la base de datos con datos de ejemplo.
2. **get_dashboard_kpis.php**: API para obtener KPIs y datos para gráficos.
3. **Dashboard actualizado**: Página principal con visualizaciones de KPIs.

## Configuración Inicial

### 1. Asegúrate de que las tablas estén creadas

Primero, ejecuta el script `setup_tables.php` para crear las tablas necesarias:

```
http://localhost:80/api/setup_tables.php
```

### 2. Poblar la base de datos con datos de ejemplo

Ejecuta el script `seed_data.php` para insertar datos de ejemplo:

```
http://localhost:80/api/seed_data.php
```

Este script insertará:
- Activos (food trucks, equipamiento)
- Empleados
- Proyecciones financieras para los últimos 12 meses
- Detalles de proyección por carro

## KPIs Disponibles

El dashboard muestra los siguientes KPIs:

1. **Ticket Promedio**: Precio promedio por venta.
2. **Ventas Diarias**: Cantidad promedio de unidades vendidas por día.
3. **Margen Bruto**: Porcentaje después de costos variables.
4. **Utilidad Neta**: Porcentaje después de todos los costos.

## Gráficos Disponibles

1. **Ventas Mensuales**: Gráfico de barras que muestra las ventas totales por mes.
2. **Proyección vs Real**: Gráfico de líneas que compara las ventas proyectadas con las reales.
3. **Distribución de Costos**: Gráfico circular que muestra la distribución de los diferentes tipos de costos.
4. **Tendencia de Ticket Promedio**: Gráfico de líneas que muestra la evolución del ticket promedio.

## Filtros

El dashboard permite filtrar los datos por:
- **Año**: Selecciona el año para los datos (2022, 2023).
- **Mes**: Selecciona un mes específico o "Todos" para ver datos de todo el año.

## API de KPIs

Para obtener los datos de KPIs programáticamente, puedes usar:

```
GET /api/get_dashboard_kpis.php?anio=2023&mes=5
```

Parámetros:
- `anio`: Año para los datos (obligatorio)
- `mes`: Mes específico (opcional, si no se proporciona, se muestran datos de todo el año)

Respuesta:
```json
{
  "kpis": {
    "ticket_promedio": 7000,
    "ventas_diarias_promedio": 50,
    "margen_bruto_porcentaje": 55,
    "utilidad_porcentaje": 25
  },
  "graficos": {
    "ventas_mensuales": [...],
    "proyeccion_vs_real": [...],
    "distribucion_costos": [...],
    "ticket_tendencia": [...]
  }
}
```

## Personalización

Para personalizar los KPIs o añadir nuevos gráficos:

1. Modifica `get_dashboard_kpis.php` para incluir nuevas consultas SQL.
2. Actualiza el archivo `index.astro` para mostrar los nuevos KPIs o gráficos.

## Notas Importantes

- Los datos "reales" son simulados con una variación aleatoria sobre las proyecciones.
- Para un entorno de producción, deberías reemplazar estos datos simulados con datos reales de ventas.
- Asegúrate de tener Chart.js instalado para que los gráficos funcionen correctamente.