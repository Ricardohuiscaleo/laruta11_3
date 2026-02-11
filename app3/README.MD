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

## Sistema de Niveles, Premios y Cupones

### Funcionalidades Principales
- **Sistema de Puntos**: $10 gastados = 1 punto, 1.000 puntos = 1 sello
- **3 Niveles de Recompensa**: Bronze, Silver, Gold (cada uno con 6 sellos)
- **Cashback Automático**: Se genera al completar cada nivel (6 sellos)
- **Cupones Canjeables**: Delivery Gratis (2 sellos) y Papas + Bebida (4 sellos)
- **Wallet Integrado**: Saldo de cashback visible en checkout

### Sistema de Niveles

**Nivel Bronze** 🥉
- 6 sellos ($60.000 gastados)
- Cashback: $6.000 (automático)
- Valor por sello: $1.000

**Nivel Silver** 🥈
- 6 sellos adicionales ($120.000 totales)
- Cashback: $12.000 (automático)
- Valor por sello: $2.000

**Nivel Gold** 🥇
- 6 sellos adicionales ($180.000 totales)
- Cashback: $18.000 (automático)
- Valor por sello: $3.000

### Recompensas Canjeables

**1. Delivery Gratis** (2 sellos)
- Se aplica en checkout
- Descuenta costo de delivery
- Cupón se crea al aplicar en checkout

**2. Papas + Bebida Gratis** (4 sellos)
- Usuario elige papas (category 12/subcategory 57)
- Usuario elige bebida (14 opciones disponibles)
- Productos se agregan gratis al carrito
- Cupón se crea al aplicar en checkout

### Base de Datos

**Tabla: user_coupons** ✅
```sql
CREATE TABLE user_coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    coupon_type ENUM('delivery_free', 'papas_bebida') NOT NULL,
    status ENUM('available', 'used') DEFAULT 'available',
    stamps_used INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    used_at TIMESTAMP NULL,
    order_id VARCHAR(50) NULL,
    FOREIGN KEY (user_id) REFERENCES usuarios(id)
);
```

**Tabla: user_wallet** ✅
```sql
CREATE TABLE user_wallet (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    balance DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES usuarios(id)
);
```

**Tabla: wallet_transactions** ✅
```sql
CREATE TABLE wallet_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    type ENUM('credit', 'debit') NOT NULL,
    description VARCHAR(255),
    order_id VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES usuarios(id)
);
```

### API Endpoints

**Sistema de Cupones** (`/api/coupons/`):
- `create_coupon.php` - Crear cupón al canjear recompensa
- `get_user_coupons.php` - Obtener cupones disponibles/usados
- `use_coupon.php` - Marcar cupón como usado

**Sistema de Wallet**:
- `get_wallet_balance.php` - Obtener saldo de cashback
- `use_wallet_balance.php` - Usar cashback en compra
- `generate_cashback.php` - Generar cashback automático (trigger)

**Sistema de Órdenes**:
- `get_user_tuu_orders.php` - Obtener pedidos y calcular puntos/sellos
- Campo `reward_stamps_consumed` en `tuu_orders` para tracking

### Flujo de Usuario

**1. Acumular Puntos**
- Usuario compra en la app
- Sistema calcula: monto / 10 = puntos
- Cada 1.000 puntos = 1 sello

**2. Ver Recompensas (ProfileModalModern)**
- Muestra puntos, sellos y nivel actual
- Botones "Canjear" son informativos
- Explican cómo usar recompensas en checkout

**3. Aplicar Recompensas (CheckoutApp)**
- Checkout verifica sellos disponibles
- Muestra premios según sellos (2 o 4)
- Usuario aplica premio → se crea cupón
- Descuento/productos gratis se aplican

**4. Completar Compra**
- Al pagar, cupón se marca como usado
- Sistema verifica si completó nivel
- Genera cashback automático si aplica

### Características Técnicas
- **Triggers MySQL**: Generan cashback automático al completar niveles
- **Cálculo Dinámico**: Puntos y sellos se calculan en tiempo real
- **Sin Expiración**: Cupones no expiran
- **Acumulables**: Usuario puede tener múltiples cupones
- **Integración Checkout**: CheckoutApp.jsx maneja aplicación de recompensas

### Documentación Completa
Ver `SISTEMA_NIVELES_PREMIOS.md` para detalles técnicos completos

---

## 📚 Documentación del Sistema de Cashback

### Estado Actual
- ✅ **Backend**: 100% implementado y funcional
- ❌ **Frontend**: 0% implementado (pendiente)
- ⏱️ **Tiempo estimado**: 1.5 horas para completar

### Documentos Disponibles

#### **INDICE_CASHBACK.md** 📚
**Documento maestro** con índice de toda la documentación
- Guía rápida de qué documento leer según tu necesidad
- Estado visual del proyecto
- Próximos pasos priorizados
- Testing rápido

#### **ANALISIS_CASHBACK_COMPLETO.md** 🔍
**Análisis técnico detallado** del sistema completo
- Estado de base de datos y tablas
- Análisis de APIs disponibles
- Problemas críticos detectados
- Plan de acción inmediato
- Consultas SQL útiles

#### **CASHBACK_WALLET_ESTADO_ACTUAL.md** 📊
**Documentación técnica completa** del sistema
- Estructura de base de datos
- Sistema de niveles (Bronze, Silver, Gold)
- APIs implementadas con ejemplos
- Flujo completo del sistema
- Integración con frontend
- Ejemplos de desglose en checkout

#### **CASHBACK_RESUMEN_RAPIDO.md** ⚡
**Guía rápida de implementación** (30 minutos)
- Lo que funciona vs lo que falta
- Código listo para copiar/pegar
- Implementación mínima paso a paso
- Prioridades claras

### Sistema de Cashback

**Conversión de Puntos**:
```
$10 gastados = 1 punto
1.000 puntos = 1 sello
```

**Niveles y Cashback** (1% de retorno):
- 🥉 **Bronze**: 6 sellos ($600.000) → $6.000 cashback
- 🥈 **Silver**: 12 sellos ($1.200.000) → $12.000 cashback
- 🥇 **Gold**: 18 sellos ($1.800.000) → $18.000 cashback

**APIs Disponibles**:
- `get_wallet_balance.php` → Obtener saldo y transacciones ✅
- `use_wallet_balance.php` → Descontar cashback usado ✅
- `generate_cashback.php` → Generar cashback por niveles ✅

**Pendiente de Implementación**:
1. Llamar `generate_cashback.php` después de cada compra
2. UI para aplicar cashback en CheckoutApp.jsx
3. Visualización de saldo en ProfileModalModern.jsx
4. Notificaciones cuando se gana cashback

### Inicio Rápido

**Para desarrolladores**:
1. Lee `INDICE_CASHBACK.md` para orientarte
2. Lee `CASHBACK_RESUMEN_RAPIDO.md` para implementar rápido
3. Copia el código de ejemplo y adapta

**Para product owners**:
1. Lee `INDICE_CASHBACK.md` (sección "Estado Actual")
2. Revisa `CASHBACK_RESUMEN_RAPIDO.md` (sección "Lo que falta")

**Para QA/testers**:
1. Lee `ANALISIS_CASHBACK_COMPLETO.md` (sección "Consultas SQL")
2. Usa los comandos de testing en `INDICE_CASHBACK.md`

