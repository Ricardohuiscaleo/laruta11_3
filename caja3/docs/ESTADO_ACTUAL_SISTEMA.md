# 📋 Estado Actual del Sistema - La Ruta 11

**Fecha de documentación**: 15 de Octubre 2025  
**Versión**: Pre-implementación de sistema de extras

---

## 🏗️ Arquitectura General

### **Base de Datos**
- **Base Principal**: `u958525313_app`
- **Usuario**: `u958525313_app`
- **Servidor**: localhost
- **Configuración**: `api/config.php` con búsqueda automática hasta 5 niveles

### **Tecnologías**
- **Frontend**: Astro + React/JavaScript
- **Backend**: PHP + MySQL
- **Estilos**: CSS personalizado + Tailwind (parcial)
- **Icons**: Lucide Icons
- **PWA**: Configurado con service worker

---

## 📱 APLICACIÓN PRINCIPAL (`/`)

### **Funcionalidades Actuales**
✅ **Menú de productos por categorías**
- Grid responsivo de productos con imágenes
- Filtrado por categorías (hamburguesa menu)
- Carrito de compras funcional
- Modal de detalles de productos
- Sistema de combos con selección personalizable

✅ **Carrito y Checkout**
- Agregar/quitar productos
- Modificar cantidades
- Cálculo automático de totales
- Formulario de datos del cliente
- Integración con sistema de pagos

✅ **Sistema de Combos**
- Modal de personalización de combos
- Selección de bebidas y opciones
- Productos fijos + seleccionables
- Cálculo de precios con extras

### **Categorías de Productos Disponibles**
1. **La Ruta 11** (ID: 1) - 🥩
2. **Sandwiches** (ID: 2) - 🥪
3. **Hamburguesas** (ID: 3) - 🍔
4. **Completos** (ID: 4) - 🌭
5. **Snacks** (ID: 5) - 🍟
6. **Personalizar** (ID: 6) - ⚙️ ❌ **PROBLEMA: No aparece en formularios**
7. **Extras** (ID: 7) - 🎁
8. **Combos** (ID: 8) - 🍽️

### **Archivos Principales**
- `src/pages/index.astro` - Aplicación principal
- `src/components/modals/ComboModal.jsx` - Modal de combos
- `public/` - Imágenes de productos

---

## 🏪 SISTEMA DE CAJA (`/caja`)

### **Funcionalidades Actuales**
✅ **Interfaz de Punto de Venta**
- Vista optimizada para tablet horizontal
- Navegación por categorías con hamburguesa en móvil
- Grid de productos (desktop) / Lista (móvil)
- Carrito lateral con gestión completa

✅ **Gestión de Pedidos**
- Numeración secuencial de órdenes
- Datos opcionales del cliente
- Múltiples métodos de pago:
  - Efectivo
  - Tarjeta POS (integración TUU)
  - Transferencia

✅ **Sistema de Pagos**
- Integración con terminal POS
- Monitoreo en tiempo real de pagos
- Confirmación manual de pagos
- Registro automático en base de datos

✅ **Herramientas Adicionales**
- Calculadora integrada
- Atajos de teclado (F1, F2, Escape)
- Notificaciones toast
- Loading states

### **Responsive Design**
- **Desktop**: Categorías visibles, grid de productos
- **Mobile**: Hamburguesa menu, lista de productos, subcategorías en 2 filas

### **Archivos Principales**
- `src/pages/caja/index.astro` - Sistema completo de caja

---

## ⚙️ PANEL ADMINISTRATIVO (`/admin`)

### **Dashboard Principal**
✅ **KPIs y Métricas**
- Usuarios registrados
- Ventas del día/mes
- Total de productos
- Calidad promedio
- Gráficos de ventas (diario/semanal/mensual)

✅ **Analytics Avanzado**
- Visitas últimos 7 días
- Interacciones por tipo
- Productos más vistos
- Actividad por horas
- Tipos de dispositivos
- Conversión de carrito

### **Gestión de Productos**
✅ **CRUD Completo**
- Crear, editar, eliminar productos
- Gestión masiva (bulk actions)
- Filtros por estado (activo/inactivo)
- Búsqueda en tiempo real
- Subida de imágenes

✅ **Campos de Producto**
- Información básica (nombre, descripción, precio)
- Categoría y subcategoría
- Gestión de stock
- Tiempo de preparación
- Popularidad
- Estado activo/inactivo
- Imágenes

❌ **PROBLEMA IDENTIFICADO**: Categoría "Personalizar" (ID: 6) no aparece en dropdown

### **Gestión de Ingredientes**
✅ **Sistema de Inventario**
- CRUD de ingredientes
- Control de stock actual/mínimo
- Proveedores y costos
- Fechas de vencimiento
- Categorización por tipo

### **Gestión de Recetas**
✅ **Recetas de Productos**
- Asignación de ingredientes a productos
- Cantidades específicas por ingrediente
- Cálculo automático de costos
- Descuento de inventario en ventas

### **Sistema de Combos**
✅ **Gestión Avanzada**
- Creación de combos
- Productos fijos + seleccionables
- Grupos de selección (bebidas, etc.)
- Precios adicionales por opción

### **Control de Calidad**
✅ **Checklists Diarios**
- Maestro Planchero (14 preguntas)
- Cajero (6 preguntas)
- Secciones: Pre-servicio, Durante, Post-servicio
- Evidencia fotográfica
- Scoring automático
- Integración con dashboard

### **Sistema de Concurso/Torneo**
✅ **Torneo EN VIVO**
- 8 participantes eliminatorios
- Control manual de progresión
- Vista pública en tiempo real
- Actualización cada 1 segundo

### **Archivos Principales**
- `src/pages/admin/index.astro` - Dashboard principal
- `src/pages/admin/edit-product.astro` - Editor de productos
- `src/pages/admin/calidad/index.astro` - Control de calidad
- `src/concurso/admin/index.astro` - Admin torneo
- `src/concurso/live/index.astro` - Vista pública torneo

---

## 🗄️ BASE DE DATOS - Tablas Principales

### **Productos y Categorías**
```sql
productos (id, name, description, price, category_id, image_url, is_active, ...)
categories (id, name, icon, ...)
subcategories (id, name, category_id, ...)
```

### **Inventario y Recetas**
```sql
ingredientes (id, name, category, unit, cost_per_unit, current_stock, ...)
recetas (id, product_id, ingredient_id, quantity, ...)
```

### **Sistema de Combos**
```sql
combos (id, name, description, price, image_url, active, ...)
combo_items (id, combo_id, product_id, quantity, is_selectable, ...)
combo_selections (id, combo_id, selection_group, product_id, additional_price, ...)
```

### **Ventas y Órdenes**
```sql
orders (id, order_number, customer_name, total_amount, status, ...)
order_items (id, order_id, product_id, quantity, price, ...)
```

### **Control de Calidad**
```sql
quality_questions (id, role, question, requires_photo, order_index, ...)
quality_checklists (id, role, checklist_date, responses, score_percentage, ...)
```

---

## 🔧 APIs DISPONIBLES (80+ endpoints)

### **Productos**
- `get_productos.php` - Obtener productos
- `add_producto.php` - Agregar producto
- `update_producto.php` - Actualizar producto
- `create_producto.php` - Crear producto

### **Categorías**
- `get_categories.php` - Obtener categorías
- `save_category.php` - Guardar categoría
- `categorias_hardcoded.php` - Categorías predefinidas

### **Ingredientes y Recetas**
- `get_ingredientes.php` - Obtener ingredientes
- `save_ingrediente.php` - Guardar ingrediente
- `get_recetas.php` - Obtener recetas
- `update_receta.php` - Actualizar receta

### **Combos**
- `get_combos.php` - Obtener combos con productos
- `save_combo.php` - Crear/editar combos

### **Ventas y Caja**
- `caja_registrar_orden.php` - Registrar orden desde caja
- `process_sale_inventory.php` - Procesar descuento de inventario
- `registrar_venta.php` - Registrar venta

### **Pagos (Integración TUU)**
- `tuu/create_remote_payment.php` - Crear pago remoto
- `tuu/check_payment_tuu.php` - Verificar estado de pago
- `tuu/update_order_status.php` - Actualizar estado de orden

### **Control de Calidad**
- `get_questions.php` - Obtener preguntas por rol
- `save_checklist.php` - Guardar checklist
- `get_quality_score.php` - Obtener score de calidad

---

## ⚠️ PROBLEMAS IDENTIFICADOS

### **1. Categoría "Personalizar" Faltante**
- **Problema**: ID 6 "Personalizar" no aparece en formularios de productos
- **Impacto**: No se pueden crear productos extras
- **Ubicación**: Formularios de admin

### **2. Sistema de Extras Incompleto**
- **Problema**: Los extras no descontan inventario automáticamente
- **Estado Actual**: Extras incluidos directamente en preparación
- **Necesidad**: Sistema de extras con descuento de ingredientes

### **3. Productos Extras Faltantes**
**Extras Requeridos**:
- Cebolla Extra ($300)
- Merkén Ahumado Sureño ($200)
- Palta Extra ($300)
- Papas Fritas Extra ($500)
- Queso Extra ($300)

---

## 🎯 PRÓXIMOS PASOS PLANIFICADOS

### **Fase 1: Arreglar Categoría "Personalizar"**
1. Verificar por qué no aparece en formularios
2. Corregir dropdown de categorías
3. Probar creación de productos en esta categoría

### **Fase 2: Crear Productos Extras**
1. Crear los 5 productos extras listados
2. Asignar ingredientes base a cada extra
3. Configurar recetas con cantidades específicas

### **Fase 3: Sistema de Extras con Inventario**
1. Modificar sistema de carrito para manejar extras
2. Integrar descuento de inventario para extras
3. Probar flujo completo de venta con extras

---

## 📊 INGREDIENTES DISPONIBLES (51 items)

### **Proteínas (16 items)**
**Carnes**: Churrasco, Lomo Cerdo, Carne Mechada, Milanesa Vacuno, Tocino, Hamburguesa R11, etc.
**Aves**: Filete Pechuga Pollo, Filete Pollo Ruta 11
**Pescados**: Merluza
**Embutidos**: Vienesa, Jamón, Longaniza, Montina
**Lácteos**: Queso Chanco/Gauda, Huevo, Queso Cheddar

### **Otros Ingredientes**
**Panes**: Pan Hotdog, Ciabatta, Pan Brioche, Pan Completo XL
**Vegetales**: Palta, Tomate, Cebolla, Lechuga, Papas, etc.
**Salsas**: Mayonesa, Ketchup, Salsa al Olivo
**Condimentos**: Sal, Pimienta, Orégano
**Packaging**: Cajas, Papel, Bolsas

---

## 🚀 CARACTERÍSTICAS PWA

✅ **Optimizaciones**
- Cache busting con timestamps
- Offline básico
- Instalable como app
- Responsive design
- Analytics integrado
- Session management con cookies

---

**📝 Nota**: Esta documentación refleja el estado del sistema al 15 de Octubre 2025, antes de implementar el sistema de extras con categoría "Personalizar".