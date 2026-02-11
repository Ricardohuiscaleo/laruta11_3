# 📦 Sistema de Compras y Capital de Trabajo - Plan de Implementación

## 🎯 Objetivo
Implementar un sistema completo de gestión de compras, capital de trabajo y flujo de caja que se integre con inventario y dashboard.

---

## 🔧 FASE 1: Arreglar Lógica de Turnos en Dashboard

### Problema Actual
- Dashboard usa días calendario (1-30/31 del mes)
- No considera que turnos cruzan medianoche
- Ventas de madrugada (00:00-03:00) se cuentan en día incorrecto

### Solución
```javascript
// En src/pages/admin/index.astro línea ~803
const today = new Date();
const currentHour = today.getHours();

// Si estamos en madrugada (00:00-03:59), ajustar al día anterior
let adjustedToday = new Date(today);
if (currentHour >= 0 && currentHour < 4) {
  adjustedToday.setDate(adjustedToday.getDate() - 1);
}

const startOfMonth = new Date(adjustedToday);
startOfMonth.setDate(1);
const startDate = startOfMonth.toISOString().split('T')[0];
const endDate = adjustedToday.toISOString().split('T')[0];
```

---

## 💰 FASE 2: Sistema de Capital de Trabajo

### 2.1 Nueva Tabla: `capital_trabajo`
```sql
CREATE TABLE capital_trabajo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATE NOT NULL,
    saldo_inicial DECIMAL(12,2) NOT NULL DEFAULT 0,
    ingresos_ventas DECIMAL(12,2) NOT NULL DEFAULT 0,
    egresos_compras DECIMAL(12,2) NOT NULL DEFAULT 0,
    egresos_gastos DECIMAL(12,2) NOT NULL DEFAULT 0,
    saldo_final DECIMAL(12,2) NOT NULL DEFAULT 0,
    notas TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_fecha (fecha)
);
```

### 2.2 Inicialización
- Definir capital inicial (ej: $500.000)
- Registrar en tabla con fecha de inicio de operaciones

---

## 🛒 FASE 3: Sistema de Compras

### 3.1 Nueva Tabla: `compras`
```sql
CREATE TABLE compras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha_compra DATETIME NOT NULL,
    proveedor VARCHAR(255),
    tipo_compra ENUM('ingredientes', 'insumos', 'equipamiento', 'otros') NOT NULL,
    monto_total DECIMAL(10,2) NOT NULL,
    metodo_pago ENUM('efectivo', 'transferencia', 'tarjeta', 'credito') NOT NULL,
    estado ENUM('pendiente', 'pagado', 'cancelado') DEFAULT 'pendiente',
    notas TEXT,
    usuario VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### 3.2 Nueva Tabla: `compras_detalle`
```sql
CREATE TABLE compras_detalle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    compra_id INT NOT NULL,
    ingrediente_id INT,
    cantidad DECIMAL(10,2) NOT NULL,
    unidad VARCHAR(50),
    precio_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (compra_id) REFERENCES compras(id) ON DELETE CASCADE,
    FOREIGN KEY (ingrediente_id) REFERENCES ingredientes(id)
);
```

### 3.3 APIs Necesarias
- `api/compras/registrar_compra.php` - Registrar nueva compra
- `api/compras/get_compras.php` - Listar compras
- `api/compras/get_saldo_disponible.php` - Calcular saldo disponible
- `api/compras/update_compra.php` - Actualizar estado de compra
- `api/compras/delete_compra.php` - Eliminar compra

---

## 📊 FASE 4: Integración con Inventario

### 4.1 Modificar Página Inventario
**Ubicación:** `src/pages/inventario/index.astro`

**Nuevas Funcionalidades:**
1. **Tab "Compras"** junto a "Ajustes"
2. **Formulario de Compra:**
   - Proveedor
   - Fecha
   - Método de pago
   - Lista de ingredientes con cantidades y precios
   - Total automático
   - Botón "Registrar Compra"

3. **Saldo Disponible:**
   ```
   💰 Saldo Disponible para Compras: $XXX.XXX
   ├─ Capital Inicial: $XXX.XXX
   ├─ + Ingresos Ventas: $XXX.XXX
   ├─ - Compras Realizadas: $XXX.XXX
   └─ - Gastos Operacionales: $XXX.XXX
   ```

4. **Historial de Compras:**
   - Tabla con últimas compras
   - Filtros por fecha, proveedor, tipo
   - Acciones: Ver detalle, Editar, Eliminar

### 4.2 Flujo de Compra
```
1. Usuario registra compra
   ↓
2. Sistema valida saldo disponible
   ↓
3. Si hay saldo suficiente:
   - Registra compra en tabla `compras`
   - Registra detalle en `compras_detalle`
   - Actualiza inventario (suma cantidades)
   - Actualiza capital_trabajo (resta egreso)
   - Actualiza saldo_caja si es efectivo
   ↓
4. Si NO hay saldo:
   - Muestra alerta: "Saldo insuficiente"
   - Sugiere: "Saldo disponible: $XXX"
```

---

## 📈 FASE 5: Dashboard Mejorado

### 5.1 Nuevas Métricas
```javascript
// Agregar a stats-grid
{
  icon: '💰',
  label: 'Capital Disponible',
  value: '$XXX.XXX',
  sublabel: 'Para compras'
}

{
  icon: '🛒',
  label: 'Compras del Mes',
  value: '$XXX.XXX',
  sublabel: 'XX compras'
}

{
  icon: '📊',
  label: 'Flujo de Caja',
  value: '+$XXX.XXX',
  sublabel: 'Ingresos - Egresos',
  color: 'green/red'
}
```

### 5.2 Nuevo Gráfico: Flujo de Caja
```javascript
// Gráfico de líneas
- Eje X: Días del mes
- Eje Y: Saldo acumulado
- Líneas:
  * Saldo disponible (verde)
  * Ingresos acumulados (azul)
  * Egresos acumulados (rojo)
```

### 5.3 Tabla de Compras Recientes
```
Últimas Compras (Top 5)
┌─────────────┬──────────────┬──────────┬──────────┐
│ Fecha       │ Proveedor    │ Tipo     │ Monto    │
├─────────────┼──────────────┼──────────┼──────────┤
│ 01/11/2025  │ Proveedor A  │ Ingred.  │ $50.000  │
│ 30/10/2025  │ Proveedor B  │ Insumos  │ $30.000  │
└─────────────┴──────────────┴──────────┴──────────┘
```

---

## 🔄 FASE 6: Integración Completa

### 6.1 Flujo de Dinero Completo
```
INGRESOS (Ventas)
    ↓
SALDO EN CAJA
    ↓
CAPITAL DE TRABAJO
    ↓
COMPRAS → INVENTARIO
    ↓
COSTOS (al vender)
    ↓
UTILIDAD
```

### 6.2 Cálculos Automáticos
```javascript
// Al registrar venta
1. Sumar a ingresos_ventas en capital_trabajo
2. Actualizar saldo_caja
3. Descontar inventario
4. Calcular costo de venta
5. Calcular utilidad

// Al registrar compra
1. Validar saldo disponible
2. Restar de capital_trabajo (egresos_compras)
3. Actualizar inventario (sumar stock)
4. Si es efectivo: restar de saldo_caja
```

---

## 📱 FASE 7: UI/UX

### 7.1 Página Inventario Mejorada
```
┌─────────────────────────────────────────┐
│ 📦 Gestión de Inventario                │
├─────────────────────────────────────────┤
│ [Ingredientes] [Ajustes] [Compras] ←NEW │
├─────────────────────────────────────────┤
│                                          │
│ 💰 Saldo Disponible: $XXX.XXX           │
│ 🛒 Compras del Mes: $XXX.XXX            │
│                                          │
│ ┌─ Registrar Nueva Compra ─────────┐   │
│ │ Proveedor: [_______________]      │   │
│ │ Fecha: [01/11/2025]               │   │
│ │ Método: [Efectivo ▼]              │   │
│ │                                    │   │
│ │ Ingredientes:                      │   │
│ │ ┌──────────────────────────────┐  │   │
│ │ │ [Seleccionar ▼] Cant: [__]   │  │   │
│ │ │ Precio Unit: $[____]          │  │   │
│ │ │ [+ Agregar]                   │  │   │
│ │ └──────────────────────────────┘  │   │
│ │                                    │   │
│ │ Total: $XXX.XXX                   │   │
│ │ [Registrar Compra]                │   │
│ └────────────────────────────────────┘   │
│                                          │
│ Historial de Compras                    │
│ [Tabla con últimas compras]             │
└─────────────────────────────────────────┘
```

---

## ⚡ Prioridades de Implementación

### Sprint 1 (Crítico)
- [ ] Arreglar lógica de turnos en dashboard
- [ ] Crear tablas de BD (capital_trabajo, compras, compras_detalle)
- [ ] API básica de compras

### Sprint 2 (Alto)
- [ ] Integrar tab "Compras" en inventario
- [ ] Formulario de registro de compras
- [ ] Cálculo de saldo disponible

### Sprint 3 (Medio)
- [ ] Métricas de capital en dashboard
- [ ] Gráfico de flujo de caja
- [ ] Historial de compras

### Sprint 4 (Bajo)
- [ ] Reportes avanzados
- [ ] Alertas de saldo bajo
- [ ] Proyecciones de compras

---

## 🎯 Beneficios Esperados

1. **Control Total:** Saber exactamente cuánto dinero hay disponible
2. **Trazabilidad:** Cada compra registrada y vinculada a inventario
3. **Decisiones Informadas:** Dashboard muestra flujo de caja real
4. **Prevención:** Alertas cuando saldo es bajo
5. **Auditoría:** Historial completo de movimientos

---

## 📝 Notas Importantes

- **Capital Inicial:** Definir monto al iniciar sistema
- **Sincronización:** Ventas actualizan capital automáticamente
- **Validaciones:** No permitir compras sin saldo suficiente
- **Permisos:** Solo admin puede registrar compras
- **Backup:** Respaldar datos de capital y compras diariamente

---

**Fecha de Creación:** 01/11/2025  
**Última Actualización:** 01/11/2025  
**Estado:** Pendiente de Aprobación
