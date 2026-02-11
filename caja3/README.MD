# La Ruta 11 - Sistema de Gestión de Restaurante

## 🚀 Configuración y Ejecución del Proyecto

### Requisitos Previos
- Node.js (versión 18 o superior)
- Visual Studio Code o editor preferido
- Servidor web con PHP y MySQL

### Instalación

1. **Clonar/Descargar el proyecto**
```bash
git clone [repositorio]
cd ruta11app
```

2. **Instalar dependencias**
```bash
npm install
```

3. **Configurar base de datos**
- Importar estructura de BD desde `api/setup_tables.php`
- Configurar credenciales en `api/config.php`

4. **Iniciar desarrollo**
```bash
npm run dev
```

5. **Acceder a la aplicación**
- Frontend: `http://localhost:4321`
- Admin: `http://localhost:4321/admin`
- Caja: `http://localhost:4321/caja`

# Sistema de Combos - Plan de Implementación

## 📋 Resumen del Requerimiento

Implementar un sistema completo de combos que permita:
- Gestionar combos como conjunto de productos (completo + papas + bebida)
- Seleccionar bebidas específicas en el carrito (APP y CAJA)
- Descuento automático de inventario por ingredientes y productos
- Cálculo automático de costos basado en recetas e ingredientes

## 🎯 Objetivos

### 1. **Gestión de Combos en Admin**
- Agregar productos a combos considerando solo su costo
- Especial atención a bebidas en combos
- Recetas de combos con ingredientes e insumos (excepto bebidas)

### 2. **Experiencia de Usuario (APP/CAJA)**
- Selección de bebidas específicas para combos
- Descuento automático de inventario al comprar
- Vista de carrito mejorada para combos

### 3. **Control de Inventario**
- Descuento de ingredientes según receta del combo
- Descuento de productos específicos (bebidas seleccionadas)
- Ajuste automático de stock de productos basado en ingredientes

## 🏗️ Arquitectura del Sistema

### **Base de Datos**

#### Nuevas Tablas Necesarias:
```sql
-- Tabla principal de combos
CREATE TABLE combos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    image_url VARCHAR(500),
    category_id INT DEFAULT 8,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

-- Productos que componen cada combo
CREATE TABLE combo_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    combo_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT DEFAULT 1,
    is_selectable TINYINT(1) DEFAULT 0,
    selection_group VARCHAR(50),
    FOREIGN KEY (combo_id) REFERENCES combos(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES productos(id)
);

-- Opciones seleccionables para grupos (ej: bebidas)
CREATE TABLE combo_selections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    combo_id INT NOT NULL,
    selection_group VARCHAR(50) NOT NULL,
    product_id INT NOT NULL,
    additional_price DECIMAL(10,2) DEFAULT 0,
    FOREIGN KEY (combo_id) REFERENCES combos(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES productos(id)
);
```

## 📝 Plan de Implementación

### **Fase 1: Backend - Gestión de Combos**

#### 1.1 APIs para Combos
- `api/setup_combo_tables.php` - Configurar tablas
- `api/get_combos.php` - Obtener combos con productos
- `api/save_combo.php` - Crear/editar combos
- `api/delete_combo.php` - Eliminar combos
- Modificación en `api/process_sale_inventory.php` - Manejo de inventario

#### 1.2 Modificar Editor de Productos
- Detectar si `category_id = 8` (Combos)
- Mostrar sección "Gestión de Combo" 
- Permitir agregar productos con costo específico
- Marcar bebidas como seleccionables

### **Fase 2: Frontend Admin - Editor de Combos**

#### 2.1 Interfaz de Gestión
- `src/pages/admin/combos.astro` - Gestión de combos (admin)
- Lista de productos actuales
- Botón "Agregar Producto"
- Checkbox "Es seleccionable" para bebidas
- Campo costo específico

#### 2.2 Modal de Selección de Productos
- Lista de todos los productos disponibles
- Filtro por categoría (especial para bebidas)
- Campo para cantidad y costo específico
- Checkbox para marcar como seleccionable

### **Fase 3: Frontend Usuario - Carrito de Combos**

#### 3.1 Detección de Combos en APP/CAJA
- Modificación en `src/pages/caja/index.astro` - Selector de combos
- Modificación en `src/pages/index.astro` - Integración en app principal
- Modal de personalización
- Productos fijos del combo
- Selección de bebidas disponibles
- Confirmación de selección

#### 3.2 Vista de Carrito Mejorada
- Mostrar combo como item principal
- Listar productos incluidos
- Destacar selecciones personalizables
- Precio total del combo

### **Fase 4: Sistema de Inventario**

#### 4.1 Descuento al Procesar Venta
- Descontar ingredientes según receta del combo
- Descontar productos seleccionados (bebidas)
- Integración completa con `process_sale_inventory.php`

#### 4.2 Cálculo de Stock Disponible
- Considerar ingredientes disponibles para combos
- Considerar productos seleccionables disponibles
- Mostrar stock real del combo

## 🔧 Archivos a Modificar/Crear

### **Nuevos Archivos**
1. `api/setup_combo_tables.php`
2. `api/get_combos.php`
3. `api/save_combo.php`
4. `api/delete_combo.php`
5. `src/pages/admin/combos.astro`

### **Archivos a Modificar**
1. `src/pages/admin/edit-product.astro` - Agregar gestión de combos
2. `src/pages/caja/index.astro` - Integrar selector de combos
3. `src/pages/index.astro` - Integrar selector de combos en APP
4. `api/process_sale_inventory.php` - Manejar inventario de combos
5. `api/get_productos.php` - Incluir información de combos

## 📊 Flujo de Datos

### **Creación de Combo (Admin)**
1. Usuario selecciona categoría "Combos"
2. Sistema detecta `is_combo = true`
3. Muestra interfaz de gestión de combo
4. Admin agrega productos con costos específicos
5. Marca bebidas como seleccionables
6. Sistema calcula costo total del combo

### **Compra de Combo (Usuario)**
1. Usuario selecciona combo en APP/CAJA
2. Sistema muestra productos incluidos
3. Usuario selecciona bebida específica
4. Se agrega al carrito con selecciones
5. Al pagar, se descuenta inventario:
   - Ingredientes según receta del combo
   - Productos seleccionados específicos

### **Control de Stock**
1. Stock de combo = MIN(stock_ingredientes, stock_productos_seleccionables)
2. Al vender combo:
   - Descontar ingredientes de receta
   - Descontar productos seleccionados
   - Recalcular stock disponible

## ⚡ Implementación Prioritaria

### **Sprint 1 (Backend)**
- [ ] Crear tablas de base de datos
- [ ] APIs básicas de gestión de combos
- [ ] Modificar process_sale_inventory.php

### **Sprint 2 (Admin)**
- [ ] Modificar edit-product.astro
- [ ] Crear interfaz de gestión de combos
- [ ] Sistema de cálculo de costos

### **Sprint 3 (Frontend Usuario)**
- [ ] Integrar selector en CAJA
- [ ] Integrar selector en APP
- [ ] Vista de carrito mejorada

### **Sprint 4 (Testing & Optimización)**
- [ ] Pruebas de inventario
- [ ] Optimización de performance
- [ ] Documentación final

## 🎯 Casos de Uso Ejemplo

### **Combo Completo Tradicional**
- **Productos**: Completo Tradicional + Papas Medianas + Bebida (seleccionable)
- **Receta**: Ingredientes del completo + ingredientes de papas
- **Selección**: Usuario elige entre Coca-Cola, Sprite, Fanta
- **Inventario**: Descuenta ingredientes + bebida específica seleccionada

### **Combo Hamburguesa Especial**
- **Productos**: Hamburguesa + Papas + Bebida + Salsa (seleccionable)
- **Receta**: Ingredientes de hamburguesa + papas
- **Selección**: Usuario elige bebida y tipo de salsa
- **Inventario**: Descuenta según receta + productos seleccionados

## 📈 Beneficios Esperados

1. **Gestión Simplificada**: Admin puede crear combos fácilmente
2. **Experiencia Mejorada**: Usuarios pueden personalizar combos
3. **Control Preciso**: Inventario se maneja automáticamente
4. **Costos Exactos**: Cálculo automático basado en ingredientes reales
5. **Escalabilidad**: Sistema flexible para nuevos tipos de combos

---

**Nota**: Este plan se implementará de forma incremental, asegurando que cada fase funcione correctamente antes de proceder a la siguiente.

## Deployment en Hostinger (PWA)

### Preparar para Producción

1. Construye el proyecto:
```bash
npm run build
```

2. Sube la carpeta `dist` completa a tu hosting Hostinger via FTP/cPanel

3. Configura tu dominio para apuntar a la carpeta donde subiste los archivos

### Backend PHP/MySQL

1. Crea una carpeta `api` en tu hosting
2. Configura la conexión a MySQL en tus archivos PHP
3. Actualiza la URL en `components/api.js` con tu dominio real

### Características PWA Incluidas

- ✅ Optimizado para móviles
- ✅ Carga rápida
- ✅ Funciona offline (básico)
- ✅ Instalable como app
- ✅ Responsive design
- ✅ Sistema de Control de Calidad integrado
- ✅ Gestión de sesiones persistentes con cookies
- ✅ Analytics avanzado con tracking de usuarios

### Cache Busting

El sistema implementa técnicas de "Cache Busting" para garantizar que los datos mostrados siempre estén actualizados en tiempo real:

- ✅ **Timestamps únicos** en todas las llamadas API
- ✅ **Headers anti-caché** en respuestas del servidor
- ✅ **Botón refresh** que limpia caché completo
- ✅ **Parámetros dinámicos** para evitar caché del navegador
- ✅ **Versionado automático** de assets estáticos

## Estructura Actual del Proyecto

### Frontend (React/Astro)
- Aplicación de menú con tarjetas de productos
- Loader inicial con logo
- Carrito de compras funcional
- Modales para detalles de productos
- Navegación por categorías
- Imágenes locales para productos principales
- **Sistema de Control de Calidad** (`/admin/calidad`)
  - Checklists para Maestro Planchero y Cajero
  - Respuestas Sí/No con observaciones
  - Subida de fotos evidencia comprimidas
  - Cálculo automático de scores de calidad

### Backend PHP/MySQL

**Configuración de Base de Datos** (`config.php`):
- **Base de Datos Principal (APP)**: `u958525313_app`
- **Usuario**: `u958525313_app`
- **Contraseña**: `wEzho0-hujzoz-cevzin`
- **Servidor**: localhost
- API Gemini integrada
- Búsqueda automática de config.php hasta 5 niveles

**API Endpoints Disponibles** (`api/` - 80+ archivos PHP):

**Productos:**
- `get_productos.php` - Obtener productos
- `add_producto.php` - Agregar producto
- `update_producto.php` - Actualizar producto
- `create_producto.php` - Crear producto

**Ingredientes y Recetas:**
- `get_ingredientes.php` - Obtener ingredientes
- `save_ingrediente.php` - Guardar ingrediente
- `get_recetas.php` - Obtener recetas
- `update_receta.php` - Actualizar receta

**Categorías:**
- `get_categories.php` - Obtener categorías
- `save_category.php` - Guardar categoría
- `categorias_hardcoded.php` - Categorías predefinidas

**Proyecciones Financieras:**
- `proyeccion.php` - Proyección básica
- `proyeccion_v2.php` - Proyección v2
- `proyeccion_v3.php` - Proyección v3
- `get_proyeccion.php` - Obtener proyección
- `save_proyeccion.php` - Guardar proyección

**Ventas:**
- `registrar_venta.php` - Registrar venta
- `ventas_update.php` - Actualizar ventas
- `ventas_get_all.php` - Obtener todas las ventas

**Dashboard y KPIs:**
- `get_dashboard_kpis.php` - Obtener KPIs
- `setup_dashboard_tables.php` - Configurar tablas

**Testing y Debug:**
- `test_api.php` - Probar API
- `test_connection.php` - Probar conexión
- `debug_db_connection.php` - Debug conexión DB

**Control de Calidad:**
- `get_questions.php` - Obtener preguntas por rol desde `quality_questions`
- `save_checklist.php` - Guardar checklists en `quality_checklists`
- `get_quality_score.php` - Obtener promedio de calidad para dashboard
- Tabla `quality_questions` - 20 preguntas (14 planchero + 6 cajero)
- Tabla `quality_checklists` - Almacena respuestas y scores
- Compresión automática de imágenes evidencia
- Integración con sistema de subida AWS existente

**Concurso/Torneo:**
- `get_concurso_live.php` - Obtener estado actual del torneo
- `update_concurso_state.php` - Actualizar estado del torneo
- Sistema de torneo eliminatorio 8 participantes
- Control manual de progresión de etapas
- Vista en tiempo real con actualización cada 1 segundo

**Setup y Configuración:**
- `setup_tables.php` - Configurar tablas
- `check_config.php` - Verificar configuración
- `save_config.php` - Guardar configuración

### Imágenes de Productos (`public/`)
- `icon.png` - Logo de la aplicación
- `Completo-italiano.png` - Completo Tradicional
- `completo-talquino.png` - Completo Talquino
- `salchi-papas.png` - Salchipapas
- `papas-ruta11.png` - Papas Ruta 11
- `toma-provoleta.png` - Tomahawk Provoleta
- `tomahawk-full.png` - Tomahawk Full Ruta 11

## Sistema de Control de Calidad

### Funcionalidades
- **Checklists Diarios**: Maestro Planchero (14 preguntas) y Cajero (6 preguntas)
- **Secciones Organizadas**: Pre-Servicio, Durante Servicio, Post-Servicio
- **Respuestas Estructuradas**: Sí/No + observaciones de texto libre
- **Evidencia Fotográfica**: Compresión automática y subida a AWS S3
- **Scoring Automático**: Cálculo de porcentaje de calidad por rol
- **Dashboard Integration**: Promedio de calidad visible en dashboard principal
- **Histórico**: Un checklist por día por rol (actualizable)
- **Base de Datos**: Usa `u958525313_app` (no Calcularuta11)

### Base de Datos

**Tabla de Preguntas:**
```sql
CREATE TABLE quality_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role ENUM('planchero', 'cajero') NOT NULL,
    question TEXT NOT NULL,
    requires_photo TINYINT(1) DEFAULT 0,
    order_index INT NOT NULL,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**Tabla de Respuestas:**
```sql
CREATE TABLE quality_checklists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role ENUM('planchero', 'cajero') NOT NULL,
    checklist_date DATE NOT NULL,
    responses JSON NOT NULL,
    total_questions INT NOT NULL,
    passed_questions INT NOT NULL,
    score_percentage DECIMAL(5,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_role_date (role, checklist_date)
);
```

### Acceso y Características
- **URL**: `/admin/calidad` (integrado en SaaS admin)
- **Navegación**: Botón "Control Calidad" en menú admin
- **Responsive**: Optimizado para móviles y tablets
- **Acordeones**: Secciones colapsables para mejor UX
- **Progreso Visual**: Barra de progreso en tiempo real
- **Persistencia**: LocalStorage guarda progreso automáticamente
- **Dashboard**: Métrica "Calidad Promedio" en dashboard principal
- **API Endpoints**: 3 APIs dedicados para funcionalidad completa

## Sistema de Concurso/Torneo EN VIVO

### Funcionalidades Principales
- **Torneo Eliminatorio**: 8 participantes → Cuartos → Semifinales → Final → Campeón
- **Admin Panel** (`/concurso/admin`): Control total del torneo
- **Vista EN VIVO** (`/concurso/live`): Transmisión en tiempo real
- **Control Manual**: Admin decide cuándo avanzar cada etapa
- **Actualización Tiempo Real**: Datos se actualizan cada 1 segundo

### Panel de Administración
- **Participantes**: 8 concursantes con seeds del 1-8
- **Enfrentamientos**: Selección manual de ganadores por click
- **Estados Visuales**: 🟢 Activo, 🔴 Eliminado, 👑 Campeón
- **Control de Etapas**: Botón "➡️ Avanzar a Siguiente Etapa"
- **Botones de Control**:
  - ▶️ Iniciar Torneo
  - 🔄 Reiniciar Todo
  - ➡️ Avanzar a Siguiente Etapa (manual)

### Vista EN VIVO (Público)
- **Formato Piramidal**: Campeón → Final → Semifinales → Cuartos
- **Layout Responsivo**: Adaptado para pantallas completas
- **Animaciones Suaves**: Transiciones fluidas en todos los cambios
- **Indicadores Visuales**:
  - 🟢 Fondo verde + escala para ganadores
  - 🔴 "EN VIVO" para matches activos
  - 🏷️ Tag "GANADOR" amarillo
  - 👑 Corona animada para campeón
- **Nombres de Pila**: Solo primer nombre para mejor legibilidad
- **Placeholders**: "Sin foto" en espacios de imágenes 4:5

### API Endpoints del Concurso
- `get_concurso_live.php` - Obtener estado actual del torneo
- `update_concurso_state.php` - Actualizar estado del torneo

### Características Técnicas
- **Actualización Automática**: Polling cada 1000ms (tiempo real)
- **Cache Busting**: Timestamps únicos para datos frescos
- **Responsive Design**: Clamps y viewport units para escalabilidad
- **Animaciones CSS**: Keyframes para efectos visuales
- **Estado Persistente**: Datos guardados en base de datos
- **Control Manual**: Admin tiene control total sobre progresión

### Flujo del Torneo
1. **Preparación**: Admin configura participantes
2. **Cuartos de Final**: 8 → 4 participantes
3. **Control Manual**: Admin hace click "Avanzar a Siguiente Etapa"
4. **Semifinales**: 4 → 2 participantes
5. **Control Manual**: Admin hace click "Avanzar a Siguiente Etapa"
6. **Final**: 2 → 1 participante
7. **Campeón**: Se declara automáticamente

### URLs del Sistema
- **Admin**: `/concurso/admin` - Panel de control
- **Live**: `/concurso/live` - Vista pública en tiempo real

## 🐛 Bug Crítico Resuelto: Referencias en Foreach PHP

### Descripción del Problema

**Síntoma**: En la página de detalle de ventas (`/ventas-detalle`), al cargar órdenes de un turno específico, algunas órdenes aparecían duplicadas mientras que otras faltaban completamente.

**Ejemplo del Bug**:
- Query SQL devolvía correctamente 6 órdenes: `[609, 608, 607, 606, 605, 604]`
- API PHP devolvía 6 órdenes pero con datos incorrectos: `[609, 608, 607, 606, 605, 605]` (605 duplicado, 604 faltante)

### Causa Raíz

El problema estaba en `api/get_sales_detail.php` en el siguiente código:

```php
// ❌ CÓDIGO CON BUG
foreach ($orders as $key => &$order) {
    // Procesar orden...
    $order['items'] = $items;
}
// La referencia &$order persiste aquí

// Más adelante en el código...
foreach ($orders as $order) {
    // Este loop sobrescribe el último elemento
    // porque &$order todavía apunta al último elemento del array
}
```

**¿Por qué ocurre?**

Cuando usas `foreach ($array as &$variable)` con el operador de referencia `&`, PHP crea una referencia que **persiste después de que el loop termina**. Si luego usas otra variable con el mismo nombre en otro loop, esa variable sobrescribirá el último elemento del array original.

### Solución Implementada

```php
// ✅ CÓDIGO CORREGIDO
foreach ($orders as $key => &$order) {
    // Procesar orden...
    $order['items'] = $items;
}
unset($order); // 🔑 CRÍTICO: Liberar la referencia

// Ahora es seguro usar $order en otros loops
foreach ($orders as $order) {
    // Ya no hay problema de sobrescritura
}
```

### Lección Aprendida

**Regla de Oro en PHP**: Siempre que uses referencias en foreach (`&$variable`), debes llamar `unset($variable)` inmediatamente después del loop para liberar la referencia.

```php
// ✅ PATRÓN CORRECTO
foreach ($array as &$item) {
    // Modificar $item
}
unset($item); // Siempre liberar la referencia

// ❌ PATRÓN INCORRECTO
foreach ($array as &$item) {
    // Modificar $item
}
// Falta unset($item) - BUG POTENCIAL
```

### Impacto

- **Antes**: Órdenes duplicadas/faltantes en detalle de ventas
- **Después**: Todas las órdenes se muestran correctamente sin duplicados
- **Archivos Afectados**: `api/get_sales_detail.php` (línea después del foreach con referencia)

### Referencias

- [PHP Manual: foreach](https://www.php.net/manual/es/control-structures.foreach.php)
- [PHP Bug: Reference in foreach](https://stackoverflow.com/questions/3307409/php-pass-by-reference-in-foreach)

---

**Fecha de Resolución**: Enero 2025  
**Severidad**: 🔴 Crítica (pérdida de datos en reportes)

