# 📦 Sistema de Gestión de Compras - La Ruta 11

## 🎯 Resumen del Sistema

Sistema completo de gestión de compras con soporte para **ingredientes** y **productos**, control de inventario automático, proyección de presupuesto, respaldos fotográficos de facturas/boletas, y generación de rendiciones de gastos para WhatsApp.

---

## 🚀 Características Principales

### 1. **Búsqueda Unificada de Items**
- ✅ Busca en tablas `ingredients` Y `products`
- ✅ Fuzzy matching inteligente
- ✅ Autocompletado de precios históricos
- ✅ Identificación visual de productos (🥤 emoji para bebidas)
- ✅ Creación automática de ingredientes si no existen

### 2. **Registro de Compras**
- ✅ Formulario completo con proveedor, fecha, método de pago
- ✅ Búsqueda inteligente de proveedores con fuzzy matching
- ✅ Agregado de múltiples items por compra
- ✅ Cálculo automático de totales y subtotales
- ✅ Preview de stock antes/después de la compra
- ✅ Validación de saldo disponible en tiempo real
- ✅ **Adjuntar foto de boleta/factura** (opcional)

### 3. **Proyección de Presupuesto**
- ✅ Simulador de compras sin afectar inventario
- ✅ Cálculo en tiempo real de saldo restante
- ✅ Alertas visuales (verde/rojo) según disponibilidad
- ✅ Limpieza rápida de proyección

### 4. **Historial de Compras**
- ✅ Vista detallada de todas las compras
- ✅ Desglose de items con cantidades y precios
- ✅ Snapshots de inventario (antes → después)
- ✅ **Subir respaldo** si no se adjuntó al registrar
- ✅ **Ver respaldo** (abre imagen en nueva pestaña)
- ✅ Eliminar compras con reversión automática de inventario
- ✅ Selección múltiple para rendición de gastos

### 5. **Rendición de Gastos**
- ✅ Selección de múltiples compras
- ✅ Generación de mensaje estructurado para WhatsApp
- ✅ Incluye desglose de items por compra
- ✅ **Links de respaldos fotográficos** en el mensaje
- ✅ Cálculo automático de saldo a devolver/favor
- ✅ Formato con emojis y markdown de WhatsApp
- ✅ Cantidades sin decimales innecesarios (5 en vez de 5.00)

### 6. **Control Financiero**
- ✅ Dashboard con 4 tarjetas resumen:
  - Ventas mes anterior
  - Ventas mes actual
  - Sueldos
  - Saldo disponible para compras
- ✅ Código de colores en saldo (rojo/amarillo/verde)
- ✅ Historial de movimientos de saldo
- ✅ Animación al cambiar saldo

### 7. **Gestión de Inventario**
- ✅ Actualización automática de `ingredients.current_stock`
- ✅ Actualización automática de `products.stock_quantity`
- ✅ Reversión de inventario al eliminar compras
- ✅ Snapshots históricos de stock

### 8. **Respaldos Fotográficos**
- ✅ Subida de imágenes a AWS S3
- ✅ Compresión automática de imágenes
- ✅ Almacenamiento en carpeta `compras/`
- ✅ Adjuntar al registrar O después desde historial
- ✅ Preview de imagen antes de subir
- ✅ Links incluidos en rendición de gastos

---

## 📊 Estructura de Base de Datos

### Tabla: `compras`
```sql
CREATE TABLE compras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha_compra DATE NOT NULL,
    proveedor VARCHAR(255) NOT NULL,
    tipo_compra ENUM('ingredientes', 'insumos') DEFAULT 'ingredientes',
    monto_total DECIMAL(10,2) NOT NULL,
    metodo_pago ENUM('cash', 'transfer', 'card', 'credit') DEFAULT 'cash',
    estado ENUM('pendiente', 'pagado', 'cancelado') DEFAULT 'pendiente',
    notas TEXT,
    imagen_respaldo VARCHAR(500) NULL,  -- ⭐ NUEVO
    usuario VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Tabla: `compras_detalle`
```sql
CREATE TABLE compras_detalle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    compra_id INT NOT NULL,
    ingrediente_id INT NULL,
    product_id INT NULL,                -- ⭐ NUEVO
    item_type ENUM('ingredient', 'product') DEFAULT 'ingredient',  -- ⭐ NUEVO
    nombre_item VARCHAR(255) NOT NULL,
    cantidad DECIMAL(10,2) NOT NULL,
    unidad VARCHAR(50) NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    stock_antes DECIMAL(10,2) NULL,
    stock_despues DECIMAL(10,2) NULL,
    FOREIGN KEY (compra_id) REFERENCES compras(id) ON DELETE CASCADE,
    FOREIGN KEY (ingrediente_id) REFERENCES ingredients(id) ON DELETE SET NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL  -- ⭐ NUEVO
);
```

### SQL de Migración
```sql
-- Agregar columna para respaldos
ALTER TABLE compras 
ADD COLUMN imagen_respaldo VARCHAR(500) NULL AFTER notas;

-- Agregar soporte para productos
ALTER TABLE compras_detalle 
ADD COLUMN product_id INT NULL AFTER ingrediente_id;

ALTER TABLE compras_detalle 
ADD COLUMN item_type ENUM('ingredient', 'product') DEFAULT 'ingredient' AFTER product_id;

-- Agregar foreign key
ALTER TABLE compras_detalle
ADD CONSTRAINT fk_compras_detalle_product
FOREIGN KEY (product_id) REFERENCES products(id)
ON DELETE SET NULL
ON UPDATE CASCADE;

-- Actualizar registros existentes
UPDATE compras_detalle 
SET item_type = 'product' 
WHERE ingrediente_id IS NULL;
```

---

## 🗂️ Archivos del Sistema

### APIs Backend (`/api/compras/`)

| Archivo | Descripción |
|---------|-------------|
| `get_items_compra.php` | Busca en `ingredients` + `products`, retorna lista unificada |
| `get_compras.php` | Obtiene historial de compras con items detallados |
| `registrar_compra.php` | Registra compra, actualiza inventario, guarda snapshots |
| `delete_compra.php` | Elimina compra y revierte inventario automáticamente |
| `get_saldo_disponible.php` | Calcula saldo disponible para compras |
| `get_historial_saldo.php` | Historial de movimientos de saldo |
| `get_precio_historico.php` | Obtiene último precio pagado por ingrediente |
| `get_proveedores.php` | Lista de proveedores únicos para autocompletado |
| `upload_respaldo.php` | Sube imagen de boleta/factura a AWS S3 |
| `add_product_id_column.php` | Script de migración de BD |

### Frontend (`/src/components/`)

| Archivo | Descripción |
|---------|-------------|
| `ComprasApp.jsx` | Componente principal con 3 tabs (Registro, Proyección, Historial) |

---

## 🔄 Flujos de Trabajo

### Flujo 1: Registrar Compra con Respaldo

```
1. Usuario abre tab "Registrar"
2. Llena proveedor, fecha, método de pago
3. Busca items (ingredientes o productos)
4. Agrega items con cantidad y precio
5. [OPCIONAL] Adjunta foto de boleta/factura
6. Sistema valida saldo disponible
7. Click "Registrar Compra"
8. Sistema:
   - Guarda compra en BD
   - Sube respaldo a AWS S3 (si existe)
   - Actualiza inventario (ingredients o products)
   - Guarda snapshots de stock
   - Actualiza saldo disponible
9. Muestra confirmación con nuevo saldo
```

### Flujo 2: Proyección de Presupuesto

```
1. Usuario abre tab "Proyección"
2. Agrega items simulados
3. Sistema calcula en tiempo real:
   - Saldo disponible
   - Costo proyectado
   - Saldo restante
4. Alertas visuales (verde/rojo)
5. Usuario decide si proceder o ajustar
6. Puede limpiar proyección sin afectar nada
```

### Flujo 3: Rendición de Gastos

```
1. Usuario abre tab "Historial"
2. Selecciona compras con checkbox
3. [OPCIONAL] Sube respaldos faltantes
4. Click "Rendir Gastos"
5. Ingresa monto de transferencia recibida
6. Sistema genera mensaje WhatsApp:
   - Lista de compras con desglose
   - Links de respaldos fotográficos
   - Total gastado
   - Saldo a devolver/favor
7. Click "Copiar para WhatsApp"
8. Pega en WhatsApp y envía
```

### Flujo 4: Eliminar Compra

```
1. Usuario abre tab "Historial"
2. Click botón "🗑️" en compra
3. Confirma eliminación
4. Sistema:
   - Obtiene items de compras_detalle
   - Revierte inventario:
     * Resta de ingredients.current_stock (si es ingredient)
     * Resta de products.stock_quantity (si es product)
   - Elimina registros de BD
   - Recalcula saldo disponible
5. Muestra confirmación
```

---

## 🎨 Interfaz de Usuario

### Tab "Registrar"
- **Formulario superior**: Proveedor, Fecha, Método de Pago, Notas
- **Campo de respaldo**: Botón verde con icono para adjuntar foto
- **Buscador de items**: Fuzzy search con autocompletado
- **Lista de items**: Tabla con cantidad, unidad, precio, subtotal
- **Presupuesto en tiempo real**: Saldo disponible vs costo compra
- **Botón submit**: Verde, deshabilitado si saldo insuficiente

### Tab "Proyección"
- **Interfaz similar a Registro** pero sin guardar
- **Calculadora visual**: Verde si alcanza, rojo si no
- **Botón limpiar**: Resetea proyección

### Tab "Historial"
- **Tarjetas de compras**: Una por compra
- **Desglose de items**: Tabla con stock antes → después
- **Botones por compra**:
  - 📎 Subir (si no hay respaldo)
  - 📎 Ver (si ya hay respaldo)
  - 🗑️ Eliminar
  - ☑️ Checkbox (para rendición)
- **Banner de selección**: Aparece al seleccionar compras
- **Botón "Rendir Gastos"**: Genera mensaje WhatsApp

### Dashboard Financiero
- **4 tarjetas resumen**:
  1. Ventas Octubre (azul)
  2. Ventas al [día] Nov (verde)
  3. Sueldos (rojo)
  4. Saldo para Compras (verde/amarillo/rojo según monto)
- **Click en saldo**: Abre modal con historial de movimientos

---

## 🔧 Configuración Técnica

### Variables de Entorno
```php
// config.php
return [
    'app_db_host' => 'localhost',
    'app_db_name' => 'u958525313_app',
    'app_db_user' => 'u958525313_app',
    'app_db_pass' => 'wEzho0-hujzoz-cevzin'
];
```

### AWS S3 Configuration
- **Bucket**: `laruta11-images.s3.amazonaws.com`
- **Carpeta**: `compras/`
- **Formato**: `respaldo_{compra_id}_{timestamp}.jpg`
- **Compresión**: Automática vía `S3Manager.php`
- **Permisos**: Public-read

### Dependencias Frontend
```json
{
  "dependencies": {
    "react": "^18.x",
    "lucide-react": "^0.x"
  }
}
```

---

## 📱 Responsive Design

- **Desktop**: Grid de 4 columnas en dashboard
- **Mobile**: Grid de 2x2 en dashboard
- **Tabs**: Reducen padding y font-size en móvil
- **Tablas**: Ajustan columnas con overflow hidden
- **Botones**: Mantienen tamaño mínimo legible

---

## 🔐 Seguridad

- ✅ Validación de saldo antes de registrar
- ✅ Transacciones SQL para integridad de datos
- ✅ Sanitización de inputs
- ✅ CORS configurado
- ✅ Compresión de imágenes antes de subir
- ✅ Foreign keys con ON DELETE SET NULL

---

## 📈 Mejoras Futuras Sugeridas

1. **Notificaciones push** cuando saldo < $200,000
2. **Exportar a Excel** historial de compras
3. **Gráficos de gastos** por proveedor/mes
4. **OCR automático** de boletas para extraer datos
5. **Firma digital** en rendiciones
6. **Multi-moneda** (CLP, USD)
7. **Roles de usuario** (admin, cajero, bodeguero)
8. **Alertas de stock bajo** basadas en compras frecuentes

---

## 🐛 Troubleshooting

### Error: "Config no encontrado"
**Solución**: Verificar que `config.php` esté en la raíz del proyecto o en `/api/`

### Error: "S3Manager no encontrado"
**Solución**: Verificar que `S3Manager.php` exista en `/api/`

### Respaldo no se sube
**Solución**: 
1. Verificar credenciales AWS
2. Verificar permisos de escritura en bucket
3. Revisar logs de PHP

### Inventario no se actualiza
**Solución**: Verificar que `item_type` esté correctamente asignado ('ingredient' o 'product')

### Cantidades con decimales innecesarios
**Solución**: Ya corregido - sistema detecta enteros automáticamente

---

## 👥 Créditos

**Desarrollado para**: La Ruta 11  
**Sistema**: Gestión de Compras e Inventario  
**Versión**: 2.0  
**Fecha**: Noviembre 2024  

---

## 📞 Soporte

Para dudas o problemas, revisar:
1. Este README
2. Logs de PHP en servidor
3. Console del navegador (F12)
4. Base de datos directamente en phpMyAdmin

---

**¡Sistema listo para producción! 🚀**
