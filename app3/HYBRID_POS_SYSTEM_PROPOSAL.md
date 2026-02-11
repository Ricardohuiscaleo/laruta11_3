# 🏪 Sistema Híbrido de Caja - La Ruta 11
## Plataforma Unificada para Ventas Físicas y Online

---

## 🎯 **Objetivo**
Crear una plataforma de caja que unifique las ventas físicas (POS TUU) y online (app actual) con control automático de inventarios e ingredientes en tiempo real.

---

## 📊 **Estado Actual del Sistema TUU**

### ✅ **Infraestructura Existente**
- **POS Físico**: 315 transacciones sincronizadas ($910,370 capturados)
- **Pagos Online**: Sistema TUU/Webpay operativo
- **Base de Datos**: `tuu_pos_transactions` + `tuu_orders`
- **Sincronización**: Automática cada 5 minutos
- **APIs**: 60+ endpoints funcionales

### ✅ **Componentes Disponibles**
- **Frontend**: React/Astro app optimizada
- **Backend**: PHP/MySQL robusto
- **Inventario**: Sistema móvil independiente
- **Analytics**: Tracking avanzado de usuarios
- **Calidad**: Sistema de control integrado

---

## 🏗️ **Arquitectura del Sistema Híbrido**

### **1. Capa de Presentación**
```
┌─────────────────────────────────────────────────────────┐
│                 INTERFAZ UNIFICADA                      │
├─────────────────┬─────────────────┬─────────────────────┤
│   Caja Física   │   App Online    │   Panel Admin       │
│   (Tablet/PC)   │   (Móvil/Web)   │   (Dashboard)       │
└─────────────────┴─────────────────┴─────────────────────┘
```

### **2. Capa de Lógica de Negocio**
```
┌─────────────────────────────────────────────────────────┐
│              MOTOR DE VENTAS HÍBRIDO                    │
├─────────────────┬─────────────────┬─────────────────────┤
│  Procesamiento  │   Validación    │   Sincronización    │
│   de Órdenes    │  de Inventario  │   Multi-canal       │
└─────────────────┴─────────────────┴─────────────────────┘
```

### **3. Capa de Datos**
```
┌─────────────────────────────────────────────────────────┐
│                BASE DE DATOS UNIFICADA                  │
├─────────────────┬─────────────────┬─────────────────────┤
│    Productos    │   Ingredientes  │   Transacciones     │
│   + Recetas     │   + Stock       │   + Inventario      │
└─────────────────┴─────────────────┴─────────────────────┘
```

---

## 💡 **Funcionalidades Clave**

### **🛒 Gestión Unificada de Ventas**
- **Punto de Venta Físico**: Interfaz táctil para cajeros
- **Pedidos Online**: Integración con app existente
- **Órdenes Híbridas**: Pedido online + pago físico
- **Multi-canal**: Mismo inventario, múltiples canales

### **📦 Control Automático de Inventario**
- **Descuento Automático**: Al confirmar venta (física/online)
- **Alertas de Stock**: Notificaciones en tiempo real
- **Reposición Inteligente**: Sugerencias basadas en ventas
- **Trazabilidad Completa**: Historial de movimientos

### **🧪 Gestión de Ingredientes**
- **Recetas Digitales**: Cada producto consume ingredientes específicos
- **Cálculo Automático**: Descuento proporcional por venta
- **Control de Costos**: Margen real por producto
- **Predicción de Compras**: Basado en proyecciones

### **📊 Analytics Avanzado**
- **Dashboard Unificado**: Ventas físicas + online
- **KPIs en Tiempo Real**: Ticket promedio, margen, rotación
- **Reportes Automáticos**: Diarios, semanales, mensuales
- **Comparativas**: Canales, productos, períodos

---

## 🛠️ **Implementación Técnica**

### **Frontend: Interfaz de Caja**
```javascript
// Componente principal de caja
const CajaHibrida = () => {
  const [ventaActual, setVentaActual] = useState([]);
  const [inventario, setInventario] = useState({});
  const [metodoPago, setMetodoPago] = useState('efectivo');
  
  const procesarVenta = async () => {
    // 1. Validar stock disponible
    const stockValido = await validarInventario(ventaActual);
    
    // 2. Procesar pago (efectivo/tarjeta/TUU)
    const pagoExitoso = await procesarPago(metodoPago);
    
    // 3. Descontar inventario automáticamente
    await actualizarInventario(ventaActual);
    
    // 4. Registrar venta en sistema unificado
    await registrarVenta({
      items: ventaActual,
      canal: 'pos_fisico',
      timestamp: new Date()
    });
  };
};
```

### **Backend: Motor de Inventario**
```php
// API unificada de ventas
class VentasHibridasController {
    
    public function procesarVenta($data) {
        // 1. Validar inventario disponible
        $stockDisponible = $this->validarStock($data['items']);
        
        if (!$stockDisponible) {
            return ['error' => 'Stock insuficiente'];
        }
        
        // 2. Calcular consumo de ingredientes
        $consumoIngredientes = $this->calcularConsumoIngredientes($data['items']);
        
        // 3. Procesar transacción
        DB::beginTransaction();
        try {
            // Registrar venta
            $venta = $this->registrarVenta($data);
            
            // Actualizar inventario productos
            $this->actualizarInventarioProductos($data['items']);
            
            // Actualizar inventario ingredientes
            $this->actualizarInventarioIngredientes($consumoIngredientes);
            
            // Sincronizar con TUU si es pago con tarjeta
            if ($data['metodo_pago'] === 'tarjeta') {
                $this->sincronizarConTUU($venta);
            }
            
            DB::commit();
            return ['success' => true, 'venta_id' => $venta->id];
            
        } catch (Exception $e) {
            DB::rollback();
            return ['error' => $e->getMessage()];
        }
    }
    
    private function calcularConsumoIngredientes($items) {
        $consumo = [];
        
        foreach ($items as $item) {
            $receta = $this->obtenerReceta($item['producto_id']);
            
            foreach ($receta as $ingrediente) {
                $cantidad = $ingrediente['cantidad'] * $item['cantidad'];
                $consumo[$ingrediente['id']] = 
                    ($consumo[$ingrediente['id']] ?? 0) + $cantidad;
            }
        }
        
        return $consumo;
    }
}
```

### **Base de Datos: Estructura Unificada**
```sql
-- Tabla de recetas (nueva)
CREATE TABLE recetas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    producto_id INT,
    ingrediente_id INT,
    cantidad_necesaria DECIMAL(10,3),
    unidad VARCHAR(20),
    FOREIGN KEY (producto_id) REFERENCES products(id),
    FOREIGN KEY (ingrediente_id) REFERENCES ingredients(id)
);

-- Tabla de movimientos de inventario (nueva)
CREATE TABLE inventario_movimientos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tipo ENUM('entrada', 'salida', 'ajuste'),
    producto_id INT NULL,
    ingrediente_id INT NULL,
    cantidad_anterior DECIMAL(10,3),
    cantidad_movimiento DECIMAL(10,3),
    cantidad_nueva DECIMAL(10,3),
    motivo VARCHAR(100),
    venta_id INT NULL,
    usuario VARCHAR(50),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de ventas unificadas (extender existente)
ALTER TABLE ventas ADD COLUMN canal ENUM('pos_fisico', 'app_online', 'hibrido');
ALTER TABLE ventas ADD COLUMN tuu_transaction_id VARCHAR(100) NULL;
ALTER TABLE ventas ADD COLUMN inventario_actualizado BOOLEAN DEFAULT FALSE;
```

---

## 🎨 **Diseño de Interfaz**

### **Pantalla Principal de Caja**
```
┌─────────────────────────────────────────────────────────┐
│  🏪 LA RUTA 11 - CAJA HÍBRIDA           👤 Cajero: Ana │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  📱 PEDIDO ACTUAL                    📦 INVENTARIO      │
│  ┌─────────────────────────┐        ┌─────────────────┐ │
│  │ 1x Completo Italiano    │        │ 🟢 Disponible   │ │
│  │ 1x Papas Ruta 11       │        │ 🟡 Stock Bajo   │ │
│  │ 1x Coca Cola 350ml     │        │ 🔴 Agotado      │ │
│  └─────────────────────────┘        └─────────────────┘ │
│                                                         │
│  💰 TOTAL: $8,500                   🧪 INGREDIENTES    │
│                                     ┌─────────────────┐ │
│  💳 MÉTODO DE PAGO                  │ Pan: 45 unid.   │ │
│  ○ Efectivo  ● Tarjeta  ○ TUU      │ Palta: 2.5 kg  │ │
│                                     │ Tomate: 1.2 kg  │ │
│  [🛒 PROCESAR VENTA]                └─────────────────┘ │
└─────────────────────────────────────────────────────────┘
```

### **Dashboard Administrativo**
```
┌─────────────────────────────────────────────────────────┐
│  📊 DASHBOARD HÍBRIDO - HOY                             │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  💰 VENTAS                          📦 INVENTARIO       │
│  ┌─────────────────────────┐        ┌─────────────────┐ │
│  │ Físicas:    $125,000    │        │ Productos: 85%  │ │
│  │ Online:     $89,500     │        │ Ingredientes:   │ │
│  │ TOTAL:      $214,500    │        │ - Stock OK: 12  │ │
│  └─────────────────────────┘        │ - Stock Bajo: 3 │ │
│                                     │ - Agotados: 1   │ │
│  🎯 KPIs                            └─────────────────┘ │
│  ┌─────────────────────────┐                           │
│  │ Ticket Promedio: $7,200 │        🔔 ALERTAS        │
│  │ Margen Bruto: 68%       │        ┌─────────────────┐ │
│  │ Rotación: 2.3x          │        │ ⚠️ Pan agotado   │ │
│  └─────────────────────────┘        │ 🟡 Palta < 1kg  │ │
│                                     └─────────────────┘ │
└─────────────────────────────────────────────────────────┘
```

---

## 🔄 **Flujos de Trabajo**

### **Flujo 1: Venta Física Tradicional**
1. **Cajero** selecciona productos en interfaz táctil
2. **Sistema** valida stock disponible en tiempo real
3. **Cliente** paga (efectivo/tarjeta/TUU)
4. **Sistema** procesa pago y actualiza inventario automáticamente
5. **Ingredientes** se descontan según recetas configuradas

### **Flujo 2: Pedido Online → Retiro en Local**
1. **Cliente** hace pedido en app
2. **Sistema** reserva inventario temporalmente
3. **Cliente** llega al local para retirar
4. **Cajero** confirma entrega en sistema
5. **Inventario** se actualiza definitivamente

### **Flujo 3: Pedido Híbrido (Online + Pago Físico)**
1. **Cliente** arma pedido en app pero no paga
2. **Sistema** genera código QR/número de pedido
3. **Cliente** llega al local con código
4. **Cajero** escanea código y procesa pago físico
5. **Venta** se registra como híbrida

### **Flujo 4: Reposición Automática**
1. **Sistema** detecta stock bajo (< nivel mínimo)
2. **Alerta** se envía a administrador
3. **Sugerencia** de compra basada en rotación histórica
4. **Recepción** de mercadería actualiza inventario
5. **Trazabilidad** completa del movimiento

---

## 📱 **Componentes del Sistema**

### **1. Interfaz de Caja (Tablet/PC)**
```javascript
// Componentes principales
- ProductSelector: Catálogo táctil de productos
- CartManager: Gestión del pedido actual
- PaymentProcessor: Métodos de pago unificados
- InventoryValidator: Validación de stock en tiempo real
- ReceiptPrinter: Impresión de boletas/facturas
```

### **2. App Móvil (Existente + Mejoras)**
```javascript
// Nuevas funcionalidades
- HybridOrderMode: Pedido sin pago inmediato
- QRCodeGenerator: Códigos para retiro en local
- StockRealTime: Disponibilidad en tiempo real
- PickupNotifications: Alertas de pedido listo
```

### **3. Panel Administrativo**
```javascript
// Módulos de gestión
- UnifiedDashboard: KPIs físico + online
- InventoryManager: Control de stock y ingredientes
- RecipeEditor: Configuración de recetas
- ReportsGenerator: Reportes automáticos
- AlertsCenter: Notificaciones del sistema
```

---

## 🔧 **APIs Necesarias**

### **Ventas Unificadas**
```php
POST /api/hybrid/process-sale          # Procesar venta (física/online)
GET  /api/hybrid/validate-stock        # Validar disponibilidad
POST /api/hybrid/reserve-inventory     # Reservar stock temporalmente
POST /api/hybrid/confirm-pickup        # Confirmar retiro de pedido
```

### **Inventario Inteligente**
```php
GET  /api/inventory/real-time-stock    # Stock en tiempo real
POST /api/inventory/update-stock       # Actualizar inventario
GET  /api/inventory/low-stock-alerts   # Alertas de stock bajo
POST /api/inventory/receive-goods      # Recepción de mercadería
```

### **Recetas y Costos**
```php
GET  /api/recipes/by-product/{id}      # Receta de producto
POST /api/recipes/calculate-cost       # Calcular costo real
GET  /api/recipes/ingredient-usage     # Uso de ingredientes
POST /api/recipes/update-recipe        # Actualizar receta
```

### **Reportes Híbridos**
```php
GET  /api/reports/unified-sales        # Ventas físicas + online
GET  /api/reports/inventory-rotation   # Rotación de inventario
GET  /api/reports/ingredient-consumption # Consumo de ingredientes
GET  /api/reports/profitability        # Rentabilidad por producto
```

---

## 💰 **Beneficios del Sistema**

### **Operacionales**
- ✅ **Control Total**: Inventario unificado físico + online
- ✅ **Eficiencia**: Automatización de procesos manuales
- ✅ **Precisión**: Eliminación de errores de conteo
- ✅ **Trazabilidad**: Historial completo de movimientos

### **Financieros**
- ✅ **Reducción de Pérdidas**: Control exacto de ingredientes
- ✅ **Optimización de Compras**: Basado en datos reales
- ✅ **Margen Real**: Costos exactos por producto
- ✅ **Flujo de Caja**: Mejor control de ingresos

### **Estratégicos**
- ✅ **Escalabilidad**: Preparado para múltiples locales
- ✅ **Integración**: Aprovecha infraestructura existente
- ✅ **Flexibilidad**: Múltiples canales de venta
- ✅ **Competitividad**: Tecnología de vanguardia

---

## 🚀 **Plan de Implementación**

### **Fase 1: Fundación (2 semanas)**
- ✅ Diseño de base de datos unificada
- ✅ APIs básicas de inventario híbrido
- ✅ Interfaz de caja MVP
- ✅ Integración con sistema TUU existente

### **Fase 2: Core (3 semanas)**
- ✅ Sistema de recetas digitales
- ✅ Motor de descuento automático
- ✅ Validación de stock en tiempo real
- ✅ Dashboard administrativo básico

### **Fase 3: Avanzado (2 semanas)**
- ✅ Pedidos híbridos (online + pago físico)
- ✅ Alertas inteligentes de reposición
- ✅ Reportes automáticos
- ✅ Optimizaciones de rendimiento

### **Fase 4: Refinamiento (1 semana)**
- ✅ Testing exhaustivo
- ✅ Capacitación del personal
- ✅ Documentación completa
- ✅ Monitoreo y métricas

---

## 🎯 **Métricas de Éxito**

### **KPIs Operacionales**
- **Precisión de Inventario**: >98%
- **Tiempo de Procesamiento**: <30 segundos por venta
- **Disponibilidad del Sistema**: >99.5%
- **Errores de Stock**: <1% de transacciones

### **KPIs Financieros**
- **Reducción de Pérdidas**: -25% vs método manual
- **Optimización de Compras**: -15% de sobrestock
- **Margen de Precisión**: ±2% vs costo real
- **ROI del Sistema**: >300% en 6 meses

---

## 🔮 **Futuras Expansiones**

### **Inteligencia Artificial**
- **Predicción de Demanda**: ML para proyecciones
- **Optimización de Precios**: Precios dinámicos
- **Detección de Patrones**: Análisis de comportamiento
- **Automatización Avanzada**: Procesos autónomos

### **Integración Externa**
- **Proveedores**: Pedidos automáticos
- **Contabilidad**: Sincronización con sistemas contables
- **Delivery**: Integración con apps de delivery
- **Loyalty**: Programa de fidelización

### **Multi-local**
- **Franquicias**: Sistema para múltiples locales
- **Centralización**: Control desde oficina central
- **Comparativas**: Benchmarking entre locales
- **Estandarización**: Procesos unificados

---

## 💡 **Conclusión**

El sistema híbrido propuesto aprovecha completamente la infraestructura TUU existente y la robusta app actual para crear una plataforma de caja de nueva generación que:

1. **Unifica** ventas físicas y online
2. **Automatiza** el control de inventario
3. **Optimiza** la gestión de ingredientes
4. **Maximiza** la rentabilidad del negocio

**Resultado**: Una solución integral que posiciona a La Ruta 11 como líder tecnológico en el sector gastronómico, con control total sobre sus operaciones y máxima eficiencia operacional.

---

**Desarrollado por**: Amazon Q  
**Fecha**: Enero 2025  
**Versión**: 1.0 - Propuesta Técnica  
**Estado**: 📋 Listo para Implementación