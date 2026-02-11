# 📱 Sistema de Caja - La Ruta 11

## 🎯 Propósito
Sistema POS (Point of Sale) complementario a la app de clientes, diseñado para que el personal del restaurante gestione pedidos presenciales, delivery y retiro en local.

---

## 🏗️ Arquitectura

### Frontend
- **Framework**: Astro + React (JSX)
- **Ubicación**: `/caja` (ruta principal del sistema)
- **Componente Principal**: `MenuApp.jsx`
- **Estilos**: TailwindCSS
- **Estado**: React Hooks (useState, useEffect)

### Backend
- **Base de Datos**: `u958525313_app` (MySQL)
- **APIs PHP**: 80+ endpoints en carpeta `/api`
- **Servidor**: Hostinger con PHP/MySQL
- **Configuración**: `api/config.php`

---

## 🔑 Funcionalidades Principales

### 1. Gestión de Pedidos
- ✅ Catálogo completo de productos por categorías
- ✅ Carrito de compras en tiempo real
- ✅ Modificación de cantidades y eliminación de items
- ✅ Cálculo automático de totales
- ✅ Personalización de productos (ingredientes, extras)
- ✅ Vista de imágenes en pantalla completa

### 2. Tipos de Entrega

#### 🚴 Delivery
- Costo de envío configurable
- Opción de descuento 40% en envío
- Campo de dirección obligatorio
- Validación de zonas de cobertura

#### 🏪 Retiro en Local
- Descuento 10% en total de compra
- Sin costo de envío
- Tiempo estimado de preparación

### 3. Sistema de Descuentos

```javascript
// Descuentos disponibles:
{
  deliveryDiscount: -40%,    // Solo en costo de envío
  pickupDiscount: -10%,      // En total de compra
  birthdayDiscount: 100%     // Hamburguesa Clásica GRATIS (ID: 9)
}
```

#### Validaciones de Descuentos
- **Delivery (-40%)**: Solo aplica si `deliveryType === 'delivery'`
- **Retiro R11 (-10%)**: Solo aplica si `deliveryType === 'pickup'`
- **Cumpleaños (🎂)**: Requiere Hamburguesa Clásica en carrito

### 4. Datos del Cliente

#### Campos Obligatorios
- ✅ Nombre completo (*)
- ✅ Tipo de entrega (*)

#### Campos Opcionales
- Teléfono
- Dirección (obligatorio solo para delivery)

#### Integración con Usuarios
- Auto-relleno si usuario está logueado
- Campos bloqueados para usuarios registrados
- Datos persistentes en `user` object

### 5. Métodos de Pago

```javascript
paymentMethods = [
  { id: 'cash', name: 'Efectivo', icon: '💵' },
  { id: 'card', name: 'Tarjeta', icon: '💳' },
  { id: 'transfer', name: 'Transferencia', icon: '📱' }
]
```

#### Características
- Selección única de método
- Cálculo automático de vuelto (efectivo)
- Validación de monto recibido
- Confirmación visual de pago

### 6. Control de Inventario

#### Descuento Automático
- Stock de productos al procesar venta
- Ingredientes según recetas
- Validación de disponibilidad en tiempo real
- Alertas de stock bajo

#### APIs de Inventario
```php
- process_sale_inventory.php  // Descuento automático
- get_ingredientes.php        // Consulta de stock
- update_receta.php           // Actualización de recetas
```

---

## 🎨 Interfaz de Usuario

### Componentes Principales

#### MenuApp.jsx
```jsx
// Estados principales
const [cart, setCart] = useState([])
const [customerInfo, setCustomerInfo] = useState({
  name: '',
  phone: '',
  address: '',
  deliveryType: 'delivery',
  deliveryDiscount: false,
  pickupDiscount: false,
  birthdayDiscount: false
})
const [showCheckout, setShowCheckout] = useState(false)
const [selectedCategory, setSelectedCategory] = useState(null)
```

#### Modales Implementados

1. **Checkout Modal** (`showCheckout`)
   - Finalización de pedido
   - Datos del cliente
   - Selección de descuentos
   - Método de pago
   - Confirmación de venta

2. **Product Details Modal**
   - Detalles del producto
   - Personalización de ingredientes
   - Selección de extras
   - Agregar al carrito

3. **Reviews Modal**
   - Reseñas de clientes
   - Calificaciones
   - Comentarios

4. **Image Fullscreen Modal**
   - Zoom de imágenes de productos
   - Navegación táctil
   - Cierre con gesto

### Características UX

- 📱 **Responsive Design**: Móvil-first approach
- 🎨 **Branding**: Gradientes red-orange (#ef4444 → #f97316)
- ⚡ **Real-time Updates**: Sin recargas de página
- 🔔 **Visual Feedback**: Confirmaciones y alertas
- 💾 **Persistencia**: LocalStorage para carrito
- ♿ **Accesibilidad**: Labels, ARIA, contraste

---

## 🔄 Flujo de Trabajo

### Proceso de Venta Completo

```
1. 🛍️  Seleccionar productos
   └─> Agregar al carrito con cantidad

2. ✏️  Modificar carrito
   └─> Cambiar cantidades o eliminar items

3. 💳 Click "Finalizar Pedido"
   └─> Abrir modal de checkout

4. 🚚 Seleccionar tipo de entrega
   ├─> Delivery (con dirección)
   └─> Retiro en local

5. 🎁 Aplicar descuentos disponibles
   ├─> -40% Delivery
   ├─> -10% Retiro R11
   └─> 🎂 Cumpleaños

6. 👤 Ingresar datos del cliente
   ├─> Nombre (obligatorio)
   ├─> Teléfono (opcional)
   └─> Dirección (si delivery)

7. 💰 Seleccionar método de pago
   ├─> Efectivo (calcular vuelto)
   ├─> Tarjeta
   └─> Transferencia

8. ✅ Confirmar y procesar venta
   └─> Llamada a API registrar_venta.php

9. 📦 Descuento automático de inventario
   └─> process_sale_inventory.php

10. 🧾 Generar ticket/comprobante
    └─> Mostrar confirmación
```

---

## 💾 Integración con Base de Datos

### Tablas Principales

```sql
-- Productos y Catálogo
productos (id, name, price, category_id, image_url, active)
categories (id, name, icon, order_index)

-- Inventario
ingredientes (id, name, stock, unit, cost)
recetas (id, product_id, ingredient_id, quantity)

-- Ventas
ventas (id, total, payment_method, created_at)
orders (id, customer_name, delivery_type, status)
order_items (id, order_id, product_id, quantity, price)

-- Control de Calidad
quality_questions (id, role, question, requires_photo)
quality_checklists (id, role, responses, score_percentage)
```

### APIs Críticas para Caja

#### Productos y Catálogo
```php
get_productos.php       // Obtener catálogo completo
get_categories.php      // Obtener categorías
add_producto.php        // Agregar nuevo producto
update_producto.php     // Actualizar producto
```

#### Procesamiento de Ventas
```php
registrar_venta.php           // Registrar venta completa
process_sale_inventory.php    // Descontar inventario
ventas_get_all.php           // Obtener historial
```

#### Inventario
```php
get_ingredientes.php    // Consultar stock
save_ingrediente.php    // Actualizar ingrediente
get_recetas.php         // Obtener recetas
update_receta.php       // Actualizar receta
```

---

## 🎯 Diferencias con App de Clientes

| Característica | App Clientes (`/`) | Sistema Caja (`/caja`) |
|----------------|-------------------|------------------------|
| **Usuario** | Cliente final | Personal del restaurante |
| **Acceso** | Público | Restringido |
| **Pago** | Online/anticipado | Presencial (efectivo/tarjeta) |
| **Descuentos** | Automáticos | Manuales (seleccionables) |
| **Datos Cliente** | Auto-relleno (login) | Ingreso manual |
| **Inventario** | Solo visualización | Control total |
| **Precios** | Fijos | Con descuentos aplicables |
| **Checkout** | Pasarela de pago | Confirmación directa |

---

## 🔐 Seguridad y Validaciones

### Validaciones Frontend
```javascript
// Validación de stock
if (product.stock <= 0) {
  alert('Producto sin stock disponible')
  return
}

// Validación de descuento cumpleaños
if (!cart.some(item => item.id === 9)) {
  alert('Debes agregar Hamburguesa Clásica')
  return
}

// Validación de campos obligatorios
if (!customerInfo.name) {
  alert('El nombre es obligatorio')
  return
}

// Validación de dirección para delivery
if (deliveryType === 'delivery' && !address) {
  alert('La dirección es obligatoria para delivery')
  return
}
```

### Validaciones Backend
```php
// Verificar stock antes de procesar
$stock_check = checkProductStock($product_id, $quantity);
if (!$stock_check) {
    return ['error' => 'Stock insuficiente'];
}

// Validar método de pago
$valid_methods = ['cash', 'card', 'transfer'];
if (!in_array($payment_method, $valid_methods)) {
    return ['error' => 'Método de pago inválido'];
}

// Registro de transacciones
logTransaction($order_id, $user_id, $action);
```

---

## 📊 Características Técnicas

### Performance

#### Cache Busting
```javascript
// Timestamps únicos en todas las llamadas
const timestamp = new Date().getTime()
fetch(`/api/get_productos.php?t=${timestamp}`)

// Headers anti-caché
headers: {
  'Cache-Control': 'no-cache, no-store, must-revalidate',
  'Pragma': 'no-cache',
  'Expires': '0'
}
```

#### Optimizaciones
- Lazy loading de imágenes
- Debounce en búsquedas
- Memoización de cálculos
- Compresión de assets
- Minificación de JS/CSS

### PWA Features

```javascript
// manifest.json
{
  "name": "La Ruta 11 - Caja",
  "short_name": "R11 Caja",
  "start_url": "/caja",
  "display": "standalone",
  "theme_color": "#ef4444",
  "background_color": "#ffffff"
}
```

- ✅ Instalable como app
- ✅ Funciona offline (básico)
- ✅ Service Worker activo
- ✅ Manifest configurado
- ✅ Iconos adaptivos

### Responsive Design

```css
/* Breakpoints principales */
@media (max-width: 640px)   { /* Mobile */ }
@media (min-width: 641px)   { /* Tablet */ }
@media (min-width: 1024px)  { /* Desktop */ }

/* Clamps para escalabilidad */
font-size: clamp(0.875rem, 2vw, 1rem);
padding: clamp(1rem, 3vw, 2rem);
```

---

## 🚀 Próximas Mejoras (Roadmap)

### Sistema de Combos (En desarrollo)
- [ ] Gestión de combos con productos seleccionables
- [ ] Descuento automático de ingredientes
- [ ] Personalización de bebidas en combos
- [ ] Cálculo de costos basado en recetas

### Mejoras Planificadas

#### Corto Plazo (1-2 meses)
- [ ] Impresión de tickets térmica (ESC/POS)
- [ ] Integración con sistema de turnos
- [ ] Reportes de ventas en tiempo real
- [ ] Notificaciones push para cocina
- [ ] Sistema de propinas

#### Mediano Plazo (3-6 meses)
- [ ] Dashboard de métricas en vivo
- [ ] Integración con delivery apps (Uber Eats, Rappi)
- [ ] Sistema de fidelización de clientes
- [ ] Programa de puntos y recompensas
- [ ] Multi-caja (sincronización)

#### Largo Plazo (6-12 meses)
- [ ] IA para predicción de demanda
- [ ] Análisis de patrones de compra
- [ ] Recomendaciones personalizadas
- [ ] Sistema de reservas
- [ ] Integración con contabilidad

---

## 📱 Acceso y URLs

### Producción
- **Sistema Caja**: `https://laruta11.com/caja`
- **App Clientes**: `https://laruta11.com/`
- **Admin Panel**: `https://laruta11.com/admin`
- **Control Calidad**: `https://laruta11.com/admin/calidad`
- **Concurso Live**: `https://laruta11.com/concurso/live`

### Desarrollo
- **Local Caja**: `http://localhost:4321/caja`
- **Local App**: `http://localhost:4321/`
- **Local Admin**: `http://localhost:4321/admin`

---

## 🛠️ Instalación y Configuración

### Requisitos Previos
```bash
Node.js >= 18.0.0
npm >= 9.0.0
PHP >= 7.4
MySQL >= 5.7
```

### Instalación

```bash
# 1. Clonar repositorio
git clone [repositorio]
cd ruta11caja

# 2. Instalar dependencias
npm install

# 3. Configurar base de datos
# Importar estructura desde api/setup_tables.php

# 4. Configurar credenciales
# Editar api/config.php con tus datos

# 5. Iniciar desarrollo
npm run dev

# 6. Construir para producción
npm run build
```

### Configuración de Base de Datos

```php
// api/config.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'u958525313_app');
define('DB_USER', 'u958525313_app');
define('DB_PASS', 'wEzho0-hujzoz-cevzin');
```

---

## 📚 Documentación Adicional

### Archivos Clave
- `src/pages/caja/index.astro` - Página principal de caja
- `src/components/MenuApp.jsx` - Componente principal React
- `api/registrar_venta.php` - Procesamiento de ventas
- `api/process_sale_inventory.php` - Control de inventario

### Logs y Debug
```javascript
// Activar modo debug
localStorage.setItem('debug', 'true')

// Ver logs de API
console.log('API Response:', response)

// Verificar estado del carrito
console.log('Cart State:', cart)
```

---

## 🐛 Bugs Conocidos y Soluciones

### Bug: Referencias en Foreach PHP
**Problema**: Órdenes duplicadas en detalle de ventas  
**Solución**: Usar `unset($variable)` después de foreach con referencias

```php
// ❌ INCORRECTO
foreach ($orders as &$order) {
    // código
}

// ✅ CORRECTO
foreach ($orders as &$order) {
    // código
}
unset($order); // Liberar referencia
```

---

## 📞 Soporte y Contacto

### Equipo de Desarrollo
- **Desarrollador Principal**: Ricardo Huisca
- **Repositorio**: GitHub (privado)
- **Hosting**: Hostinger

### Recursos
- [Documentación Astro](https://docs.astro.build)
- [React Docs](https://react.dev)
- [TailwindCSS](https://tailwindcss.com)
- [PHP Manual](https://www.php.net/manual/es/)

---

**Última Actualización**: Enero 2025  
**Versión**: 2.0.0  
**Estado**: ✅ Producción Estable

---

## 📝 Resumen Ejecutivo

Sistema POS completo y robusto que complementa la app de clientes, permitiendo al personal gestionar ventas presenciales con:

✅ Control total de inventario en tiempo real  
✅ Descuentos flexibles y personalizables  
✅ Múltiples métodos de pago  
✅ Integración con usuarios registrados  
✅ Diseño responsive y PWA  
✅ Cache busting para datos frescos  
✅ Validaciones exhaustivas  
✅ Performance optimizado  

Diseñado para ser **rápido**, **intuitivo** y **confiable** en ambiente de alta demanda.
