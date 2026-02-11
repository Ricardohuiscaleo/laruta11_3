# Sistema de Ventas y KPIs para Calcularuta11

Este documento explica cómo configurar y utilizar el sistema de ventas y KPIs para el dashboard.

## Estructura de Tablas

El sistema utiliza las siguientes tablas en MySQL:

1. **ventas**: Almacena las ventas realizadas
2. **detalles_venta**: Almacena los productos vendidos en cada venta
3. **estadisticas_diarias**: Almacena estadísticas agregadas por día y carro
4. **proyecciones_financieras**: Almacena las proyecciones financieras
5. **detalles_proyeccion**: Almacena los detalles de cada proyección por carro

## Configuración Inicial

### 1. Crear las tablas necesarias

Ejecuta los siguientes scripts para crear las tablas:

```
http://localhost:80/api/setup_tables.php       # Tablas de proyecciones
http://localhost:80/api/setup_ventas_tables.php # Tablas de ventas
```

## Registrar Ventas Reales

Para registrar ventas reales, utiliza el endpoint `registrar_venta.php`:

```
POST /api/registrar_venta.php
```

Ejemplo de datos a enviar:

```json
{
  "fecha_venta": "2023-05-15",
  "hora_venta": "14:30:00",
  "monto_total": 7500,
  "metodo_pago": "efectivo",
  "carro_id": 1,
  "empleado_id": 2,
  "notas": "Venta en hora pico",
  "detalles": [
    {
      "producto_id": 1,
      "cantidad": 1,
      "precio_unitario": 7500
    }
  ]
}
```

## Dashboard y KPIs

El dashboard muestra KPIs y gráficos basados en datos reales de ventas. Si no hay datos reales disponibles para algún período, se utilizan las proyecciones financieras como respaldo.

### KPIs disponibles:

1. **Ticket Promedio**: Precio promedio por venta
2. **Ventas Diarias**: Cantidad promedio de unidades vendidas por día
3. **Margen Bruto**: Porcentaje después de costos variables
4. **Utilidad Neta**: Porcentaje después de todos los costos

### Gráficos disponibles:

1. **Ventas Mensuales**: Ventas totales por mes
2. **Proyección vs Real**: Comparación entre ventas proyectadas y reales
3. **Distribución de Costos**: Distribución de los diferentes tipos de costos
4. **Tendencia de Ticket Promedio**: Evolución del ticket promedio

## API de KPIs

Para obtener los datos de KPIs programáticamente:

```
GET /api/get_dashboard_kpis.php?anio=2023&mes=5
```

Parámetros:
- `anio`: Año para los datos (obligatorio)
- `mes`: Mes específico (opcional)

## Importante

- Todos los datos mostrados en el dashboard provienen directamente de la base de datos MySQL.
- No hay datos hardcodeados en el código.
- El sistema prioriza los datos reales de ventas cuando están disponibles.
- Si no hay datos reales para un período específico, se utilizan las proyecciones como respaldo.

## Flujo de Datos

1. Las ventas se registran a través de `registrar_venta.php`
2. Las estadísticas diarias se actualizan automáticamente con cada venta
3. El dashboard consulta los datos a través de `get_dashboard_kpis.php`
4. Los KPIs y gráficos se generan dinámicamente basados en los datos de la base de datos