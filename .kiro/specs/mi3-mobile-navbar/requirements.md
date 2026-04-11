# Documento de Requisitos: mi3 Mobile Navbar

## Introducción

Este documento define los requisitos para la navegación móvil de mi3 (mi.laruta11.cl), la app de autoservicio RRHH para trabajadores de La Ruta 11. El objetivo es reemplazar la navegación móvil actual (sidebar deslizable) por un patrón tipo app nativa con header fijo superior y bottom navbar fijo inferior, manteniendo la experiencia desktop existente sin cambios.

## Glosario

- **MobileBottomNav**: Componente de barra de navegación fija en la parte inferior de la pantalla, visible solo en viewport móvil (<768px)
- **MobileHeader**: Componente de header fijo en la parte superior de la pantalla, visible solo en viewport móvil (<768px)
- **MobileNavLayout**: Componente wrapper que combina MobileHeader y MobileBottomNav y aplica padding al contenido
- **WorkerSidebar**: Componente de sidebar de navegación existente, visible solo en viewport desktop (≥768px)
- **Bottom_Sheet**: Panel deslizable desde la parte inferior que muestra los items de navegación secundarios
- **Item_Primario**: Enlace de navegación que aparece directamente en el MobileBottomNav (máximo 4)
- **Item_Secundario**: Enlace de navegación que aparece dentro del Bottom_Sheet al presionar "Más"
- **Configuración_de_Navegación**: Módulo centralizado (`lib/navigation.ts`) que define todos los items de navegación, sus rutas, labels e íconos
- **Viewport_Móvil**: Ancho de pantalla menor a 768px (breakpoint `md` de Tailwind)
- **Viewport_Desktop**: Ancho de pantalla igual o mayor a 768px

## Requisitos

### Requisito 1: Configuración centralizada de navegación

**Historia de Usuario:** Como desarrollador, quiero una configuración centralizada de navegación, para que los items de navegación se definan en un solo lugar y se reutilicen en todos los componentes.

#### Criterios de Aceptación

1. THE Configuración_de_Navegación SHALL definir un array `primaryNavItems` con exactamente 4 items: Inicio (/dashboard), Turnos (/dashboard/turnos), Sueldo (/dashboard/liquidacion) y Crédito (/dashboard/credito)
2. THE Configuración_de_Navegación SHALL definir un array `secondaryNavItems` con los items: Perfil (/dashboard/perfil), Asistencia (/dashboard/asistencia), Cambios (/dashboard/cambios) y Notificaciones (/dashboard/notificaciones)
3. THE Configuración_de_Navegación SHALL exportar un array `allNavItems` que sea la unión de `primaryNavItems` y `secondaryNavItems`
4. THE Configuración_de_Navegación SHALL garantizar que cada `href` sea único en la unión de items primarios y secundarios
5. THE Configuración_de_Navegación SHALL garantizar que cada `href` comience con `/dashboard`
6. THE Configuración_de_Navegación SHALL exportar una función `getPageTitle` que reciba un pathname y retorne el label correspondiente del item de navegación

### Requisito 2: Resolución de título de página

**Historia de Usuario:** Como trabajador, quiero ver el nombre de la sección actual en el header, para saber en qué parte de la app me encuentro.

#### Criterios de Aceptación

1. WHEN la función `getPageTitle` recibe un pathname que coincide con un `href` en `allNavItems`, THE Configuración_de_Navegación SHALL retornar el `label` correspondiente de ese item
2. WHEN la función `getPageTitle` recibe un pathname que no coincide con ningún `href` en `allNavItems`, THE Configuración_de_Navegación SHALL retornar `'mi3'` como valor por defecto

### Requisito 3: Barra de navegación inferior móvil

**Historia de Usuario:** Como trabajador usando mi celular, quiero una barra de navegación en la parte inferior de la pantalla, para acceder rápidamente a las secciones principales con una sola mano.

#### Criterios de Aceptación

1. THE MobileBottomNav SHALL renderizar exactamente 5 elementos: 4 enlaces de Item_Primario más un botón "Más"
2. THE MobileBottomNav SHALL posicionarse con `position: fixed` en `bottom: 0` con `z-index: 50`
3. WHEN el viewport es Viewport_Móvil, THE MobileBottomNav SHALL ser visible
4. WHEN el viewport es Viewport_Desktop, THE MobileBottomNav SHALL estar oculto
5. THE MobileBottomNav SHALL mantener el orden de los items constante: Inicio, Turnos, Sueldo, Crédito, Más

### Requisito 4: Determinación de item activo en navegación

**Historia de Usuario:** Como trabajador, quiero ver resaltada la sección donde me encuentro en la barra de navegación, para tener orientación visual de mi ubicación en la app.

#### Criterios de Aceptación

1. WHEN la ruta actual es `/dashboard`, THE MobileBottomNav SHALL marcar como activo únicamente el item "Inicio" usando coincidencia exacta
2. WHEN la ruta actual comienza con el `href` de un Item_Primario distinto de `/dashboard`, THE MobileBottomNav SHALL marcar como activo ese Item_Primario usando coincidencia por prefijo
3. WHEN la ruta actual coincide con el `href` de un Item_Secundario, THE MobileBottomNav SHALL marcar como activo el botón "Más"
4. WHEN la ruta actual no coincide con ningún item de navegación, THE MobileBottomNav SHALL no marcar ningún item como activo
5. THE MobileBottomNav SHALL mostrar el item activo con color amber-600 y los items inactivos con color gray-400/gray-500

### Requisito 5: Menú "Más" con items secundarios

**Historia de Usuario:** Como trabajador, quiero acceder a las secciones menos frecuentes desde un menú "Más", para que la barra principal no esté sobrecargada.

#### Criterios de Aceptación

1. WHEN el trabajador presiona el botón "Más" en el MobileBottomNav, THE MobileBottomNav SHALL abrir un Bottom_Sheet que muestre todos los Item_Secundario
2. WHEN el trabajador selecciona un enlace dentro del Bottom_Sheet, THE MobileBottomNav SHALL navegar a la ruta correspondiente y cerrar el Bottom_Sheet automáticamente
3. THE Bottom_Sheet SHALL mostrar cada Item_Secundario con su ícono y label correspondiente

### Requisito 6: Header móvil fijo

**Historia de Usuario:** Como trabajador, quiero ver un header fijo en la parte superior con el nombre de la sección actual, para mantener contexto mientras navego por el contenido.

#### Criterios de Aceptación

1. THE MobileHeader SHALL posicionarse con `position: fixed` en `top: 0` con `z-index: 40`
2. THE MobileHeader SHALL mostrar "mi3" como branding a la izquierda
3. THE MobileHeader SHALL mostrar el título de la página actual derivado de la ruta usando la función `getPageTitle`
4. WHEN el viewport es Viewport_Móvil, THE MobileHeader SHALL ser visible
5. WHEN el viewport es Viewport_Desktop, THE MobileHeader SHALL estar oculto
6. THE MobileHeader SHALL mostrar un ícono de notificaciones (Bell) a la derecha con badge de conteo de notificaciones no leídas

### Requisito 7: Layout de navegación móvil

**Historia de Usuario:** Como trabajador, quiero que el contenido de las páginas no quede oculto detrás del header ni de la barra inferior, para poder ver toda la información sin obstrucciones.

#### Criterios de Aceptación

1. THE MobileNavLayout SHALL aplicar padding-top de al menos 56px (h-14) al contenido para compensar la altura del MobileHeader
2. THE MobileNavLayout SHALL aplicar padding-bottom de al menos 64px (h-16) al contenido para compensar la altura del MobileBottomNav
3. THE MobileNavLayout SHALL renderizar MobileHeader y MobileBottomNav como componentes hijos
4. WHEN el viewport es Viewport_Móvil, THE MobileNavLayout SHALL ser visible
5. WHEN el viewport es Viewport_Desktop, THE MobileNavLayout SHALL estar oculto

### Requisito 8: Exclusividad de sistemas de navegación

**Historia de Usuario:** Como trabajador, quiero ver solo un sistema de navegación a la vez (sidebar o bottom nav), para evitar confusión visual y elementos duplicados.

#### Criterios de Aceptación

1. WHEN el viewport es Viewport_Móvil, THE WorkerSidebar SHALL estar completamente oculto incluyendo el botón hamburguesa y el overlay
2. WHEN el viewport es Viewport_Desktop, THE WorkerSidebar SHALL ser visible y funcionar como actualmente
3. WHEN el viewport cambia de Viewport_Móvil a Viewport_Desktop o viceversa, THE sistema SHALL mostrar únicamente el sistema de navegación correspondiente al viewport actual

### Requisito 9: Cobertura completa de rutas de navegación

**Historia de Usuario:** Como trabajador, quiero acceder a todas las secciones disponibles desde la navegación móvil, para no perder funcionalidad al usar mi celular.

#### Criterios de Aceptación

1. THE Configuración_de_Navegación SHALL incluir en la unión de items primarios y secundarios todas las rutas presentes en el WorkerSidebar actual: /dashboard, /dashboard/perfil, /dashboard/turnos, /dashboard/liquidacion, /dashboard/credito, /dashboard/asistencia, /dashboard/cambios y /dashboard/notificaciones
2. WHEN un trabajador usa Viewport_Móvil, THE MobileBottomNav SHALL proveer acceso a todas las rutas del dashboard ya sea directamente en los items primarios o a través del Bottom_Sheet

### Requisito 10: Badge de notificaciones no leídas

**Historia de Usuario:** Como trabajador, quiero ver un indicador de notificaciones no leídas, para saber cuándo tengo mensajes pendientes sin necesidad de entrar a la sección.

#### Criterios de Aceptación

1. WHEN existen notificaciones no leídas, THE MobileHeader SHALL mostrar un badge numérico junto al ícono de notificaciones indicando la cantidad
2. WHEN no existen notificaciones no leídas, THE MobileHeader SHALL mostrar el ícono de notificaciones sin badge
3. IF la API de notificaciones falla o no responde, THEN THE MobileHeader SHALL ocultar el badge y mantener la navegación funcionando normalmente

### Requisito 11: Manejo de rutas no reconocidas

**Historia de Usuario:** Como trabajador, quiero que la navegación funcione correctamente incluso si accedo a una ruta no estándar del dashboard, para no quedar sin opciones de navegación.

#### Criterios de Aceptación

1. WHEN el trabajador navega a una ruta bajo `/dashboard/` que no está en `allNavItems`, THE MobileHeader SHALL mostrar `'mi3'` como título por defecto
2. WHEN el trabajador navega a una ruta no reconocida, THE MobileBottomNav SHALL permanecer funcional sin ningún item marcado como activo
