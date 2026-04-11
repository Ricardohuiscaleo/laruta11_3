# Plan de Implementación: mi3 Mobile Navbar

## Resumen

Implementar navegación móvil tipo app nativa para mi3 con header fijo superior, bottom navbar fijo inferior y menú "Más" con items secundarios. Se mantiene el sidebar desktop existente sin cambios funcionales.

## Tareas

- [x] 1. Crear configuración centralizada de navegación
  - [x] 1.1 Crear `mi3/frontend/lib/navigation.ts` con interfaces, arrays de items y función `getPageTitle`
    - Definir interface `NavItem` con `href`, `label` e `icon`
    - Exportar `primaryNavItems` con 4 items: Inicio, Turnos, Sueldo, Crédito
    - Exportar `secondaryNavItems` con 4 items: Perfil, Asistencia, Cambios, Notificaciones
    - Exportar `allNavItems` como unión de primary + secondary
    - Exportar función `getPageTitle(pathname)` que retorna el label o `'mi3'` como fallback
    - Exportar función `isNavItemActive(pathname, itemHref)` con coincidencia exacta para `/dashboard` y prefijo para sub-rutas
    - _Requisitos: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 2.1, 2.2_

  - [ ]* 1.2 Escribir test de propiedad para `getPageTitle`
    - **Propiedad 1: Consistencia de getPageTitle**
    - Para cualquier item en `allNavItems`, `getPageTitle(item.href)` retorna `item.label`; para strings aleatorios no reconocidos, retorna `'mi3'`
    - **Valida: Requisitos 2.1, 2.2, 11.1**

  - [ ]* 1.3 Escribir test de propiedad para `isNavItemActive`
    - **Propiedad 2: Determinación correcta de item activo**
    - Para `/dashboard` usa coincidencia exacta; para otros items usa prefijo; rutas secundarias activan "Más"; rutas no reconocidas no activan nada
    - **Valida: Requisitos 4.1, 4.2, 4.3, 4.4**

  - [ ]* 1.4 Escribir test de propiedad para cobertura de rutas
    - **Propiedad 4: Cobertura completa de rutas de navegación**
    - Verificar que `allNavItems` contiene todos los hrefs del WorkerSidebar original
    - **Valida: Requisitos 9.1, 9.2**

- [x] 2. Checkpoint - Verificar que la configuración de navegación es correcta
  - Ensure all tests pass, ask the user if questions arise.

- [x] 3. Implementar componentes móviles
  - [x] 3.1 Crear `mi3/frontend/components/mobile/MobileBottomNav.tsx`
    - Componente client (`'use client'`) con `usePathname` para detectar ruta activa
    - Renderizar 4 items primarios como `Link` + botón "Más" con ícono `MoreHorizontal`
    - Implementar lógica de item activo usando `isNavItemActive` de `lib/navigation.ts`
    - Marcar botón "Más" como activo cuando la ruta coincide con un item secundario
    - Implementar bottom sheet (estado local `useState`) con items secundarios al presionar "Más"
    - Cerrar sheet automáticamente al seleccionar un enlace
    - Aplicar `fixed bottom-0 z-50 md:hidden`, colores amber-600 (activo) / gray-400 (inactivo)
    - _Requisitos: 3.1, 3.2, 3.3, 3.4, 3.5, 4.1, 4.2, 4.3, 4.4, 4.5, 5.1, 5.2, 5.3_

  - [x] 3.2 Crear `mi3/frontend/components/mobile/MobileHeader.tsx`
    - Componente client con `usePathname` y `getPageTitle` para título dinámico
    - Mostrar "mi3" como branding a la izquierda, título de página al centro
    - Mostrar ícono Bell a la derecha con badge de notificaciones no leídas (fetch desde API)
    - Ocultar badge si la API falla (graceful degradation)
    - Aplicar `fixed top-0 z-40 md:hidden`, fondo blanco con borde inferior
    - _Requisitos: 6.1, 6.2, 6.3, 6.4, 6.5, 6.6, 10.1, 10.2, 10.3_

  - [x] 3.3 Crear `mi3/frontend/components/mobile/MobileNavLayout.tsx`
    - Wrapper que renderiza `MobileHeader` + `children` + `MobileBottomNav`
    - Aplicar `pt-14` (compensar header h-14) y `pb-20` (compensar bottom nav h-16 + margen)
    - Solo visible en móvil via `md:hidden`
    - _Requisitos: 7.1, 7.2, 7.3, 7.4, 7.5_

  - [ ]* 3.4 Escribir test de propiedad para invariantes del bottom nav
    - **Propiedad 3: Invariantes del bottom nav**
    - Verificar que `primaryNavItems` tiene exactamente 4 elementos y que el orden es fijo: Inicio, Turnos, Sueldo, Crédito
    - **Valida: Requisitos 3.1, 3.5**

- [x] 4. Modificar componentes existentes e integrar navegación móvil
  - [x] 4.1 Modificar `mi3/frontend/components/layouts/WorkerSidebar.tsx`
    - Agregar `hidden md:flex` al contenedor raíz del sidebar para ocultarlo en móvil
    - Eliminar el botón hamburguesa móvil (botón con `md:hidden`)
    - Eliminar el overlay móvil (div con `md:hidden`)
    - Eliminar el estado `open` y lógica de toggle (ya no se necesitan en móvil)
    - Mantener toda la funcionalidad desktop intacta
    - _Requisitos: 8.1, 8.2, 8.3_

  - [x] 4.2 Actualizar `mi3/frontend/app/dashboard/layout.tsx`
    - Importar `MobileNavLayout`
    - Agregar `MobileNavLayout` al layout del dashboard
    - Ajustar clases de `main` para que el padding móvil lo maneje `MobileNavLayout`
    - _Requisitos: 7.3, 8.3_

  - [ ]* 4.3 Escribir unit tests para integración de componentes
    - Verificar que MobileBottomNav se oculta en desktop y se muestra en móvil
    - Verificar que WorkerSidebar se oculta en móvil y se muestra en desktop
    - Verificar que el contenido tiene padding correcto para no quedar oculto
    - _Requisitos: 3.3, 3.4, 7.1, 7.2, 8.1, 8.2_

- [x] 5. Checkpoint final - Verificar integración completa
  - Ensure all tests pass, ask the user if questions arise.

## Notas

- Las tareas marcadas con `*` son opcionales y pueden omitirse para un MVP más rápido
- Cada tarea referencia requisitos específicos para trazabilidad
- Los checkpoints aseguran validación incremental
- Los tests de propiedad usan fast-check y validan las propiedades de correctitud del diseño
- Los unit tests validan ejemplos específicos y casos borde
