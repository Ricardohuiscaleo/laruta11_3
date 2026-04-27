# Documento de Requisitos — Merma y Arqueo Inline en caja3

## Introducción

Actualmente en caja3 (caja.laruta11.cl), las secciones de Merma y Arqueo/Caja son páginas separadas (`/mermas` y `/arqueo`) que requieren navegación completa con `window.location.href`, causando tiempos de carga lentos y pérdida de contexto. Este feature convierte ambas secciones en paneles/modales inline que se renderizan dentro de `MenuApp.jsx`, eliminando la navegación de página completa. Además, se enriquece la sección de Merma aprovechando la estructura de datos completa del backend mi3 (categorías de ingredientes, sub-recetas, niveles de stock, costo por unidad, métodos de preparación, etc.).


## Glosario

- **MenuApp**: Componente React principal de caja3, renderizado en `index.astro` en la ruta `/`. Contiene el menú POS, carrito, y la barra de navegación inferior.
- **Panel_Inline**: Componente React que se renderiza como overlay/panel dentro de MenuApp sin navegación de página. Ocupa la pantalla completa sobre el contenido del menú.
- **Navbar_Inferior**: Barra de navegación fija en la parte inferior de MenuApp con botones "Mermar", búsqueda, y "Caja".
- **MermasApp**: Componente actual (`MermasApp.jsx`) que gestiona el registro de mermas (desperdicios). Actualmente se renderiza como página independiente en `/mermas`.
- **ArqueoApp**: Componente actual (`ArqueoApp.jsx`) que muestra el resumen de ventas/arqueo de caja. Actualmente se renderiza como página independiente en `/arqueo`.
- **SaldoCajaModal**: Modal existente para gestionar movimientos de saldo de caja, actualmente acoplado a ArqueoApp vía eventos `window.dispatchEvent`.
- **API_Mi3**: API REST del backend mi3 en `https://api-mi3.laruta11.cl/api/v1/`, que contiene datos completos de ingredientes con categorías, sub-recetas, stock, costos, y proveedores.
- **API_Caja3**: APIs PHP locales de caja3 en `/api/`, usadas para operaciones de merma, ventas, y caja.
- **Ingrediente_Compuesto**: Ingrediente marcado como `is_composite=true` en mi3 que tiene sub-recetas (componentes hijos) definidas en la tabla `ingredient_recipes`.
- **Categoría_Ingrediente**: Clasificación de ingredientes según el enum de mi3: Carnes, Vegetales, Salsas, Condimentos, Panes, Embutidos, Pre-elaborados, Lácteos, Bebidas, Gas, Servicios, Packaging, Limpieza.

## Requisitos

### Requisito 1: Navegación Inline sin Recarga de Página

**User Story:** Como cajero, quiero acceder a Merma y Arqueo sin salir del menú principal, para no perder tiempo en recargas de página.

#### Criterios de Aceptación

1. WHEN el cajero presiona el botón "Mermar" en la Navbar_Inferior, THE MenuApp SHALL renderizar el Panel_Inline de Merma como overlay sobre el contenido del menú sin ejecutar navegación de página.
2. WHEN el cajero presiona el botón "Caja" en la Navbar_Inferior, THE MenuApp SHALL renderizar el Panel_Inline de Arqueo como overlay sobre el contenido del menú sin ejecutar navegación de página.
3. WHEN un Panel_Inline está visible, THE MenuApp SHALL mostrar un botón de cierre que permita volver al menú principal sin recarga de página.
4. WHILE un Panel_Inline está visible, THE MenuApp SHALL ocultar el contenido del menú y la Navbar_Inferior de categorías, manteniendo visible solo el panel activo.
5. WHEN el cajero cierra un Panel_Inline, THE MenuApp SHALL restaurar el estado previo del menú incluyendo la categoría activa y la posición de scroll.

### Requisito 2: UX Mobile-First y Simplicidad para Cajera

**User Story:** Como cajera usando un celular durante el turno, quiero que la sección de merma sea extremadamente simple e intuitiva, para registrar desperdicios rápido sin confundirme.

#### Criterios de Aceptación

1. THE Panel_Inline de Merma SHALL usar un diseño mobile-first con botones grandes (mínimo 44px de alto), textos legibles (mínimo 14px), y espaciado generoso entre elementos táctiles.
2. THE Panel_Inline de Merma SHALL mostrar un flujo de 3 pasos claros y visibles: (1) Buscar y seleccionar item, (2) Ingresar cantidad y motivo, (3) Confirmar. Cada paso SHALL tener un título descriptivo visible.
3. WHEN el cajero busca un ingrediente, THE Panel_Inline SHALL mostrar resultados como tarjetas grandes con nombre prominente, categoría como badge de color, stock actual visible, y un botón "Seleccionar" claro.
4. THE Panel_Inline de Merma SHALL usar lenguaje simple y descriptivo en español chileno: "¿Qué se perdió?", "¿Cuánto?", "¿Por qué?", en lugar de terminología técnica.
5. WHEN el cajero selecciona un motivo de merma, THE Panel_Inline SHALL mostrar los motivos como botones grandes con emoji descriptivo: 🧪 Prueba/Producto nuevo, 🤮 Podrido, ⏰ Vencido, � Quemado, � Dañado, 🫗 Caído/Derramado, 🤢 Mal estado, 🐛 Contaminado, ❄️ Mal refrigerado, 🔄 Devolución cliente, 🎓 Capacitación, ❓ Otro.
6. THE Panel_Inline de Merma SHALL mostrar el costo de la merma en formato grande y prominente (font-size mínimo 20px, color rojo) para que la cajera tenga conciencia del impacto económico.
7. WHEN el cajero completa un registro de merma exitoso, THE Panel_Inline SHALL mostrar una confirmación visual clara (✅ animado) por al menos 2 segundos antes de limpiar el formulario.
8. THE Panel_Inline de Merma SHALL funcionar correctamente en pantallas de 320px a 428px de ancho (rango típico de celulares), con scroll vertical fluido y sin overflow horizontal.

### Requisito 3: Panel Inline de Merma con Datos Enriquecidos

**User Story:** Como cajero, quiero ver los ingredientes organizados por categoría con información completa de stock y costo, para registrar mermas de forma precisa y eficiente.

#### Criterios de Aceptación

1. WHEN el Panel_Inline de Merma se abre, THE Panel_Inline SHALL cargar la lista de ingredientes activos desde la API_Caja3 (`/api/get_ingredientes.php`) incluyendo nombre, categoría, unidad, stock actual, y costo por unidad.
2. THE Panel_Inline de Merma SHALL mostrar los ingredientes agrupados por Categoría_Ingrediente, con secciones colapsables para cada categoría.
3. WHEN el cajero escribe en el campo de búsqueda, THE Panel_Inline SHALL filtrar ingredientes y productos usando coincidencia difusa (fuzzy match) mostrando un máximo de 10 resultados ordenados por relevancia.
4. WHEN el cajero selecciona un ingrediente, THE Panel_Inline SHALL mostrar información detallada: nombre, categoría, stock actual, unidad, costo por unidad, y nivel mínimo de stock.
5. IF un Ingrediente_Compuesto es seleccionado, THEN THE Panel_Inline SHALL mostrar la lista de componentes hijos (sub-receta) con sus cantidades y unidades.
6. WHEN el cajero ingresa una cantidad de merma que excede el stock actual del ingrediente, THE Panel_Inline SHALL mostrar una advertencia con el stock disponible y bloquear el registro.
7. THE Panel_Inline de Merma SHALL permitir alternar entre modo "Ingredientes" y modo "Productos" para registrar mermas de ambos tipos.

### Requisito 3: Indicadores Visuales de Stock en Merma

**User Story:** Como cajero, quiero ver indicadores visuales del nivel de stock de cada ingrediente, para priorizar el registro de mermas y detectar ingredientes en estado crítico.

#### Criterios de Aceptación

1. THE Panel_Inline de Merma SHALL mostrar un indicador de color junto a cada ingrediente: verde cuando el stock actual supera el doble del nivel mínimo, amarillo cuando el stock está entre el nivel mínimo y el doble del nivel mínimo, y rojo cuando el stock está por debajo del nivel mínimo.
2. WHEN el Panel_Inline de Merma se abre, THE Panel_Inline SHALL mostrar un resumen en la parte superior con el conteo de ingredientes en estado crítico (stock bajo el mínimo).
3. WHEN el cajero registra una merma que dejaría el stock por debajo del nivel mínimo, THE Panel_Inline SHALL mostrar una alerta visual indicando que el ingrediente quedará en estado crítico.

### Requisito 4: Registro de Merma con Cálculo de Costo

**User Story:** Como cajero, quiero que el sistema calcule automáticamente el costo de cada merma, para tener visibilidad del impacto económico del desperdicio.

#### Criterios de Aceptación

1. WHEN el cajero agrega un item a la lista de merma, THE Panel_Inline SHALL calcular el subtotal multiplicando la cantidad por el costo por unidad del ingrediente o producto.
2. THE Panel_Inline de Merma SHALL mostrar el costo total acumulado de todos los items en la lista de merma actual.
3. WHEN el cajero confirma el registro de merma, THE Panel_Inline SHALL enviar cada item a la API_Caja3 (`/api/registrar_merma.php`) con tipo de item, ID, cantidad, y motivo.
4. WHEN el registro de merma es exitoso, THE Panel_Inline SHALL limpiar la lista de items, resetear el motivo, y cambiar a la vista de historial.
5. IF el registro de merma falla, THEN THE Panel_Inline SHALL mostrar un mensaje de error descriptivo y mantener los datos ingresados para reintentar.
6. THE Panel_Inline de Merma SHALL requerir la selección de un motivo de merma antes de permitir el envío. Los motivos disponibles son: Prueba/Producto nuevo, Podrido, Vencido, Quemado, Dañado, Caído/Derramado, Mal estado, Contaminado, Mal refrigerado, Devolución cliente, Capacitación, Otro.

### Requisito 5: Historial de Mermas Mejorado

**User Story:** Como cajero, quiero ver el historial de mermas con resúmenes, para entender los patrones de desperdicio.

#### Criterios de Aceptación

1. WHEN el cajero accede a la pestaña de historial en el Panel_Inline de Merma, THE Panel_Inline SHALL cargar las mermas registradas desde la API_Caja3 (`/api/get_mermas.php`).
2. THE Panel_Inline SHALL mostrar cada merma del historial con: nombre del item, cantidad y unidad, costo, motivo, y fecha de registro formateada en formato chileno (dd/mm/yyyy).
3. THE Panel_Inline SHALL mostrar un resumen del costo total de mermas del día actual en la parte superior del historial.

### Requisito 6: Panel Inline de Arqueo de Caja

**User Story:** Como cajero, quiero ver el arqueo de caja como panel inline, para consultar las ventas del turno sin perder el contexto del menú.

#### Criterios de Aceptación

1. WHEN el Panel_Inline de Arqueo se abre, THE Panel_Inline SHALL cargar los datos de ventas del turno actual desde la API_Caja3 (`/api/get_sales_summary.php`).
2. THE Panel_Inline de Arqueo SHALL mostrar la tabla de ventas por método de pago (Tarjetas, Transferencia, Efectivo, Webpay, PedidosYA Online, PedidosYA Efectivo, Crédito RL6, Crédito R11, Delivery) con conteo de pedidos y total por método.
3. THE Panel_Inline de Arqueo SHALL mostrar el total general de ventas, el saldo en caja, y un reloj en tiempo real con la hora de Santiago de Chile.
4. WHEN el cajero presiona los botones de navegación temporal, THE Panel_Inline SHALL cargar los datos de ventas del día anterior o siguiente según corresponda.
5. WHEN el cajero presiona "Saldo en Caja", THE Panel_Inline SHALL abrir el SaldoCajaModal para registrar movimientos de caja (ingresos y retiros).
6. WHEN el cajero presiona "Ver Detalle", THE Panel_Inline SHALL navegar a la página de detalle de ventas (`/ventas-detalle`) con los parámetros de período correspondientes.
7. THE Panel_Inline de Arqueo SHALL actualizar el saldo de caja automáticamente cada 15 segundos mediante polling a la API_Caja3 (`/api/get_saldo_caja.php`).

### Requisito 7: Gestión de Estado entre Paneles y Menú

**User Story:** Como cajero, quiero que al cerrar un panel inline el menú vuelva exactamente donde lo dejé, para no perder mi flujo de trabajo.

#### Criterios de Aceptación

1. WHEN el cajero abre un Panel_Inline, THE MenuApp SHALL preservar el estado actual del menú: categoría activa, posición de scroll, contenido del carrito, y query de búsqueda.
2. WHEN el cajero cierra un Panel_Inline, THE MenuApp SHALL restaurar la posición de scroll previa del menú.
3. WHILE un Panel_Inline está abierto, THE MenuApp SHALL detener cualquier polling o actualización periódica del menú para optimizar rendimiento.
4. IF el cajero tiene items en el carrito al abrir un Panel_Inline, THEN THE MenuApp SHALL preservar el carrito intacto al cerrar el panel.

### Requisito 8: Eliminación de Navegación por Página Completa

**User Story:** Como desarrollador, quiero eliminar las dependencias de navegación por página para Merma y Arqueo, para simplificar la arquitectura y mejorar el rendimiento.

#### Criterios de Aceptación

1. THE Navbar_Inferior SHALL reemplazar las llamadas `window.location.href = '/mermas'` y `window.location.href = '/arqueo'` por funciones que activan los Panel_Inline correspondientes mediante cambio de estado React.
2. THE Panel_Inline de Arqueo SHALL integrar el SaldoCajaModal directamente sin depender de eventos `window.dispatchEvent('openSaldoCajaModal')`.
3. WHEN el Panel_Inline de Merma se cierra, THE Panel_Inline SHALL ejecutar un cambio de estado React en lugar de la navegación `window.location.href = '/'` existente en MermasApp.