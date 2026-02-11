# Plan de Análisis de Inventarios - La Ruta 11

## 📊 Situación Actual (Diciembre 2024)

### Métricas Clave
- **Valor Total Inventario**: $1.089.049
- **Items Activos**: 57
- **Rotación Mensual**: 0.56x
- **Item Más Estancado**: Hamburguesa R11 200gr ($77.760 - 7.1%)
- **Costo Ventas Mes**: ~$610.000

### Diagnóstico
⚠️ **Rotación Baja**: 0.56x indica que tardas 1.8 meses en renovar tu inventario completo.

## 🎯 Objetivos

### Corto Plazo (1-2 meses)
- Aumentar rotación de **0.56x → 0.8x**
- Reducir inventario estancado en 20%
- Identificar productos de baja rotación

### Mediano Plazo (3-6 meses)
- Alcanzar rotación de **1.0x - 1.5x** (ideal para restaurantes)
- Liberar $300K-$400K de capital inmovilizado
- Optimizar niveles de stock por producto

## 📋 Funcionalidades a Implementar

### 1. Dashboard de Análisis de Inventarios
**Ubicación**: `/admin/inventarios` (nueva sección)

#### KPIs Principales
- ✅ Valor total inventario (ya existe)
- ✅ Rotación mensual (ya existe)
- ✅ Item más estancado (ya existe)
- 🔲 Días de inventario disponible
- 🔲 Valor de inventario obsoleto (>90 días sin movimiento)
- 🔲 Cobertura de stock (días hasta quiebre)

#### Gráficos
1. **Evolución de Rotación** (últimos 6 meses)
   - Línea temporal mostrando rotación mensual
   - Meta ideal (1.0x-1.5x) como referencia

2. **Top 10 Items Estancados**
   - Gráfico de barras: valor en inventario
   - Ordenado por monto descendente
   - % del inventario total

3. **Distribución por Categoría**
   - Pie chart: valor inventario por categoría
   - Ingredientes vs Productos terminados

4. **Análisis ABC**
   - A: 20% items = 80% valor (alta prioridad)
   - B: 30% items = 15% valor (media prioridad)
   - C: 50% items = 5% valor (baja prioridad)

### 2. Tabla Detallada de Inventario

#### Columnas
| Campo | Descripción | Cálculo |
|-------|-------------|---------|
| Item | Nombre ingrediente/producto | - |
| Categoría | Tipo de item | - |
| Stock Actual | Cantidad disponible | `current_stock` |
| Valor Stock | Dinero inmovilizado | `stock × cost_per_unit` |
| % Inventario | Proporción del total | `(valor_item / valor_total) × 100` |
| Consumo Mensual | Promedio último mes | Desde `inventory_transactions` |
| Días Cobertura | Stock disponible en días | `stock_actual / consumo_diario` |
| Rotación Item | Veces que rota al mes | `consumo_mes / stock_promedio` |
| Último Movimiento | Fecha última transacción | `MAX(created_at)` |
| Estado | Clasificación | 🟢 Normal / 🟡 Lento / 🔴 Estancado |

#### Filtros
- Por categoría (Carnes, Lácteos, Bebidas, etc.)
- Por estado (Normal, Lento, Estancado)
- Por rotación (Alta, Media, Baja)
- Por valor (Top 20%, Medio 30%, Bajo 50%)

#### Acciones
- 🔍 Ver historial de movimientos
- 📊 Gráfico de consumo histórico
- ⚠️ Ajustar stock mínimo/máximo
- 🗑️ Marcar como obsoleto

### 3. Alertas Inteligentes

#### Tipos de Alertas
1. **Stock Estancado** (>60 días sin movimiento)
   - Notificación en dashboard
   - Sugerencia: reducir orden de compra

2. **Sobre-Stock** (cobertura >30 días)
   - Item con inventario excesivo
   - Sugerencia: promocionar o reducir precio

3. **Rotación Crítica** (<0.3x mensual)
   - Items que casi no rotan
   - Sugerencia: evaluar discontinuar

4. **Capital Inmovilizado** (>$50K en un item)
   - Concentración de riesgo
   - Sugerencia: diversificar compras

### 4. Reportes Automáticos

#### Reporte Semanal
- Resumen de rotación
- Top 5 items estancados
- Alertas activas
- Recomendaciones de acción

#### Reporte Mensual
- Análisis completo de inventario
- Comparativa mes anterior
- Proyección próximo mes
- Plan de optimización

## 🔧 Implementación Técnica

### Base de Datos

#### Nuevas Consultas Necesarias
```sql
-- Consumo mensual por item
SELECT 
    ingredient_id,
    SUM(quantity) as consumo_mes,
    AVG(quantity) as consumo_promedio,
    COUNT(*) as num_movimientos
FROM inventory_transactions
WHERE transaction_type = 'sale'
AND DATE_FORMAT(created_at, '%Y-%m') = ?
GROUP BY ingredient_id;

-- Días sin movimiento
SELECT 
    i.id,
    i.name,
    DATEDIFF(NOW(), MAX(it.created_at)) as dias_sin_movimiento
FROM ingredients i
LEFT JOIN inventory_transactions it ON i.id = it.ingredient_id
GROUP BY i.id, i.name
HAVING dias_sin_movimiento > 60;

-- Análisis ABC
SELECT 
    name,
    (current_stock * cost_per_unit) as valor,
    SUM(valor) OVER (ORDER BY valor DESC) / SUM(valor) OVER () * 100 as acumulado_percent
FROM ingredients
WHERE is_active = 1;
```

### APIs a Crear

1. **`/api/get_inventory_analysis.php`**
   - Retorna análisis completo de inventario
   - Incluye rotación, cobertura, alertas

2. **`/api/get_inventory_history.php`**
   - Historial de movimientos por item
   - Parámetros: item_id, fecha_inicio, fecha_fin

3. **`/api/get_inventory_alerts.php`**
   - Lista de alertas activas
   - Clasificadas por prioridad

4. **`/api/update_stock_levels.php`**
   - Actualizar min/max de stock
   - Marcar items como obsoletos

### Frontend

#### Página Principal: `/admin/inventarios`
```
┌─────────────────────────────────────────────┐
│  📊 Análisis de Inventarios                 │
├─────────────────────────────────────────────┤
│  KPIs (4 tarjetas)                          │
│  ┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐      │
│  │Valor │ │Rotac.│ │Días  │ │Alert.│      │
│  └──────┘ └──────┘ └──────┘ └──────┘      │
├─────────────────────────────────────────────┤
│  Gráficos (2 columnas)                      │
│  ┌──────────────┐ ┌──────────────┐         │
│  │ Evolución    │ │ Top Estancad.│         │
│  │ Rotación     │ │              │         │
│  └──────────────┘ └──────────────┘         │
├─────────────────────────────────────────────┤
│  Tabla Detallada                            │
│  [Filtros: Categoría | Estado | Rotación]  │
│  ┌────────────────────────────────────────┐ │
│  │ Item | Stock | Valor | Días | Estado  │ │
│  └────────────────────────────────────────┘ │
└─────────────────────────────────────────────┘
```

## 📈 Métricas de Éxito

### Indicadores Clave
1. **Rotación de Inventario**
   - Actual: 0.56x
   - Meta 3 meses: 0.8x
   - Meta 6 meses: 1.2x

2. **Capital Liberado**
   - Actual: $1.089M inmovilizado
   - Meta: Reducir a $700K-$800K
   - Liberar: $300K-$400K

3. **Items Estancados**
   - Actual: Por determinar
   - Meta: <5% del inventario total

4. **Días de Cobertura**
   - Actual: ~54 días (1.8 meses)
   - Meta: 25-30 días

## 🚀 Roadmap de Implementación

### Fase 1: Análisis Básico (Semana 1-2)
- [ ] Crear página `/admin/inventarios`
- [ ] Implementar KPIs principales
- [ ] Tabla con datos básicos de inventario
- [ ] Filtros por categoría

### Fase 2: Visualizaciones (Semana 3-4)
- [ ] Gráfico evolución rotación
- [ ] Top 10 items estancados
- [ ] Distribución por categoría
- [ ] Análisis ABC

### Fase 3: Alertas (Semana 5-6)
- [ ] Sistema de alertas automáticas
- [ ] Notificaciones en dashboard
- [ ] Recomendaciones de acción
- [ ] Historial de alertas

### Fase 4: Optimización (Semana 7-8)
- [ ] Ajuste automático de stock min/max
- [ ] Proyecciones de compra
- [ ] Reportes automáticos
- [ ] Integración con plan de compras

## 💡 Recomendaciones Inmediatas

### Acciones Prioritarias
1. **Revisar Hamburguesas** ($77K estancado)
   - ¿Realmente necesitas tanto stock?
   - Reducir orden de compra próxima
   - Promocionar para acelerar rotación

2. **Identificar Items >90 días**
   - Listar productos sin movimiento
   - Evaluar discontinuar o liquidar
   - Liberar espacio y capital

3. **Optimizar Compras**
   - Comprar más frecuente, menor cantidad
   - Negociar entregas más seguidas
   - Reducir lote mínimo de compra

4. **Promociones Estratégicas**
   - Ofertar items de baja rotación
   - Combos con productos estancados
   - Descuentos por volumen

## 📊 Benchmarks de la Industria

### Rotación de Inventario - Restaurantes
- **Fast Food**: 2.0x - 3.0x mensual
- **Casual Dining**: 1.5x - 2.0x mensual
- **Fine Dining**: 1.0x - 1.5x mensual
- **Tu Ruta 11**: 0.56x (⚠️ mejorar)

### Días de Inventario
- **Ideal**: 20-30 días
- **Aceptable**: 30-45 días
- **Problema**: >45 días
- **Tu Ruta 11**: ~54 días (⚠️ reducir)

---

**Última actualización**: Diciembre 2024  
**Próxima revisión**: Enero 2025
