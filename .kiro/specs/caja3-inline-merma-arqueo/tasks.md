# Plan de Implementación: Merma y Arqueo Inline en caja3

## Resumen

Convertir las páginas separadas de Merma (`/mermas`) y Arqueo (`/arqueo`) en paneles inline lazy-loaded dentro de `MenuApp.jsx`. Se crea `MermaPanel.jsx` con UX mobile-first rediseñada y `ArqueoPanel.jsx` adaptado de `ArqueoApp.jsx`. Se reutilizan las APIs PHP existentes sin cambios en backend.

## Tareas

- [-] 1. Configurar framework de testing y funciones utilitarias puras
  - [ ] 1.1 Instalar vitest, fast-check, @testing-library/react y jsdom como devDependencies en caja3
    - Crear `caja3/vitest.config.js` con entorno jsdom y alias de paths
    - Agregar script `"test": "vitest --run"` en package.json
    - _Requisitos: Estrategia de Testing del diseño_

  - [x] 1.2 Crear módulo de funciones utilitarias puras `caja3/src/utils/mermaUtils.js`
    - Implementar `getStockColor(currentStock, minStockLevel)` — retorna 'green', 'yellow', o 'red' según la lógica del diseño
    - Implementar `countCriticalIngredients(ingredients)` — cuenta ingredientes con stock bajo mínimo
    - Implementar `calculateMermaSubtotal(cantidad, costoUnitario)` — retorna cantidad × costo
    - Implementar `calculateMermaTotal(items)` — suma de subtotales
    - Implementar `validateMermaQuantity(quantity, currentStock)` — retorna `{ blocked, alertCritical }` 
    - Implementar `canSubmitMerma(items, reason)` — valida que haya items y motivo seleccionado
    - Implementar `fuzzyMatch(str, pattern)` — reutilizar algoritmo de MermasApp con score
    - Implementar `filterAndSortItems(items, searchTerm, maxResults=10)` — filtra, ordena por score desc, limita a max
    - Implementar `groupByCategory(ingredients)` — agrupa ingredientes por categoría
    - Implementar `getDailyMermaTotal(mermas, targetDate)` — suma costos de mermas del día indicado
    - Implementar `formatDateChilean(dateString)` — formatea fecha a dd/mm/yyyy
    - _Requisitos: 3.2, 3.3, 3.6, 3b.1, 3b.2, 3b.3, 4.1, 4.2, 4.6, 5.2, 5.3_

  - [ ]* 1.3 Escribir property tests para funciones utilitarias de merma
    - **Propiedad 6: Indicador de color de stock** — Generar pares (stock, minLevel) aleatorios, verificar color correcto según reglas del diseño
    - **Valida: Requisito 3b.1**
    - **Propiedad 7: Conteo de ingredientes críticos** — Generar listas de ingredientes con stocks y mínimos aleatorios, verificar conteo
    - **Valida: Requisito 3b.2**
    - **Propiedad 8: Cálculo de costo de merma** — Generar items con costos y cantidades aleatorias, verificar subtotal = cantidad × costo y total = Σ subtotales
    - **Valida: Requisitos 4.1, 4.2**
    - **Propiedad 9: Motivo requerido para envío** — Generar listas de items sin motivo, verificar que canSubmitMerma retorna false
    - **Valida: Requisito 4.6**

  - [ ]* 1.4 Escribir property tests para fuzzy match y agrupación
    - **Propiedad 3: Agrupación correcta por categoría** — Generar listas de ingredientes con categorías aleatorias, verificar que cada ingrediente aparece exactamente en su grupo y la unión es completa
    - **Valida: Requisito 3.2**
    - **Propiedad 4: Restricciones del fuzzy match** — Generar listas de items y términos de búsqueda, verificar máximo 10 resultados, orden descendente por score, scores positivos
    - **Valida: Requisito 3.3**
    - **Propiedad 5: Validación de cantidad contra stock** — Generar ingredientes con stocks y cantidades aleatorias, verificar bloqueo cuando Q > S y alerta cuando (S-Q) < M
    - **Valida: Requisitos 3.6, 3b.3**
    - **Propiedad 11: Total diario de mermas** — Generar mermas con fechas aleatorias, verificar que la suma del día actual es correcta
    - **Valida: Requisito 5.3**

- [ ] 2. Checkpoint — Verificar que tests pasan
  - Ejecutar `npm test` en caja3, asegurar que todas las funciones utilitarias y property tests pasan. Preguntar al usuario si hay dudas.

- [-] 3. Integrar estado de panel inline en MenuApp.jsx
  - [x] 3.1 Agregar estado `activePanel` y funciones de apertura/cierre en MenuApp.jsx
    - Agregar `const [activePanel, setActivePanel] = useState(null)` — valores: null, 'merma', 'arqueo'
    - Agregar `savedScrollRef` y `savedCategoryRef` con `useRef`
    - Implementar `openPanel(panel)` — guarda scrollY y categoría activa, setea activePanel, scroll a 0
    - Implementar `closePanel()` — setea activePanel a null, restaura scroll y categoría con requestAnimationFrame
    - Agregar lazy imports: `const MermaPanel = React.lazy(() => import('./MermaPanel.jsx'))` y `const ArqueoPanel = React.lazy(() => import('./ArqueoPanel.jsx'))`
    - _Requisitos: 1.1, 1.2, 1.4, 1.5, 7.1, 7.2, 8.1_

  - [x] 3.2 Modificar renderizado condicional y navbar inferior en MenuApp.jsx
    - Cuando `activePanel !== null`: renderizar solo el panel activo envuelto en `<React.Suspense>` con fallback de spinner
    - Cuando `activePanel === null`: renderizar el menú normal (sin cambios)
    - En la navbar inferior, reemplazar `window.location.href = '/mermas'` → `openPanel('merma')`
    - En la navbar inferior, reemplazar `window.location.href = '/arqueo'` → `openPanel('arqueo')`
    - _Requisitos: 1.1, 1.2, 1.3, 1.4, 8.1_

  - [ ]* 3.3 Escribir property test para round-trip de estado del menú
    - **Propiedad 1: Round-trip de estado del menú al abrir/cerrar panel** — Generar estados aleatorios (categoría, scrollY), simular openPanel + closePanel, verificar que se restauran los valores originales
    - **Valida: Requisitos 1.5, 7.1, 7.2, 7.4**

- [-] 4. Implementar MermaPanel.jsx — Panel inline de merma
  - [x] 4.1 Crear estructura base de MermaPanel.jsx con header, tabs, y flujo de 3 pasos
    - Crear `caja3/src/components/MermaPanel.jsx` con prop `onClose`
    - Implementar header fijo con título "Gestión de Mermas" y botón X para cerrar (llama `onClose`)
    - Implementar tabs "Mermar" / "Historial" con estado `activeTab`
    - Implementar `StepIndicator` — indicador visual de 3 pasos: "¿Qué se perdió?", "¿Cuánto?", "¿Por qué?"
    - Implementar estado del flujo: `step` (1, 2, 3), `itemType` ('ingredient' | 'product'), `searchTerm`, `selectedItem`, `mermaItems`, `reason`
    - Diseño mobile-first: botones mínimo 44px alto, textos mínimo 14px, espaciado generoso
    - _Requisitos: 1.1, 1.3, 2.1, 2.2, 2.4, 2.8_

  - [x] 4.2 Implementar Paso 1 — Búsqueda y selección de items con datos enriquecidos
    - Cargar ingredientes desde `/api/get_ingredientes.php` y productos desde `/api/get_productos.php` al montar
    - Implementar toggle "Ingredientes" / "Productos" con botones grandes
    - Implementar campo de búsqueda que usa `filterAndSortItems` de mermaUtils
    - Renderizar resultados como tarjetas grandes con: nombre prominente, badge de color por categoría, indicador de stock (usando `getStockColor`), stock actual y unidad
    - Mostrar resumen superior con conteo de ingredientes críticos (usando `countCriticalIngredients`)
    - Al seleccionar un item, mostrar detalle: nombre, categoría, stock, unidad, costo/unidad, nivel mínimo
    - Manejo de error: si falla la carga, mostrar "Error al cargar datos. Toca para reintentar" con botón retry
    - Manejo de búsqueda vacía: mostrar "No se encontraron items para '{término}'"
    - _Requisitos: 2.3, 3.1, 3.2, 3.3, 3.4, 3.5, 3.7, 3b.1, 3b.2_

  - [x] 4.3 Implementar Paso 2 — Cantidad, motivo, y lista acumulada
    - Implementar input de cantidad con validación usando `validateMermaQuantity`
    - Si cantidad excede stock: mostrar advertencia inline con stock disponible, bloquear botón "Agregar"
    - Si merma dejaría stock bajo mínimo: mostrar alerta amarilla informativa (no bloqueante)
    - Implementar `ReasonSelector` — grid de botones grandes con emojis usando constante `MERMA_REASONS` del diseño
    - Implementar `MermaItemsList` — lista de items agregados con nombre, cantidad, unidad, subtotal (usando `calculateMermaSubtotal`), y botón eliminar
    - Mostrar costo total acumulado en formato grande y rojo (mínimo 20px) usando `calculateMermaTotal`
    - Bloquear envío si no hay motivo seleccionado (usando `canSubmitMerma`)
    - _Requisitos: 2.5, 2.6, 3.6, 3b.3, 4.1, 4.2, 4.6_

  - [x] 4.4 Implementar Paso 3 — Confirmación y envío de merma
    - Implementar envío secuencial de cada item a `/api/registrar_merma.php` con payload: `{ item_type, item_id, quantity, reason }`
    - En éxito: mostrar confirmación visual (✅ animado) por 2 segundos, limpiar formulario, cambiar a tab historial
    - En error: mostrar toast de error descriptivo, mantener datos ingresados para reintentar
    - _Requisitos: 2.7, 4.3, 4.4, 4.5_

  - [x] 4.5 Implementar vista de historial de mermas
    - Cargar historial desde `/api/get_mermas.php` al activar tab "Historial"
    - Renderizar cada merma con: nombre del item, cantidad y unidad, costo, motivo, fecha formateada con `formatDateChilean`
    - Mostrar resumen de costo total del día actual en la parte superior usando `getDailyMermaTotal`
    - Manejo de error: si falla la carga, mostrar mensaje con botón retry
    - _Requisitos: 5.1, 5.2, 5.3_

  - [ ]* 4.6 Escribir property test para completitud de información en búsqueda
    - **Propiedad 2: Completitud de información de ingredientes** — Para cualquier ingrediente activo, verificar que la tarjeta de resultado contiene nombre, categoría, stock, unidad, y costo
    - **Valida: Requisitos 2.3, 3.4**

- [ ] 5. Checkpoint — Verificar MermaPanel funcional
  - Ejecutar tests, verificar que MermaPanel se renderiza correctamente como panel inline. Preguntar al usuario si hay dudas.

- [-] 6. Implementar ArqueoPanel.jsx — Panel inline de arqueo
  - [x] 6.1 Crear ArqueoPanel.jsx adaptado de ArqueoApp.jsx
    - Crear `caja3/src/components/ArqueoPanel.jsx` con prop `onClose`
    - Copiar lógica de `ArqueoApp.jsx`: estados, loadSalesData, loadSaldoCaja, updateClock, polling cada 15s
    - Agregar header con título "Arqueo de Caja" y botón X para cerrar (llama `onClose`)
    - Eliminar botón "Volver a Caja" (`window.location.href`) — reemplazado por botón X del header
    - _Requisitos: 1.2, 1.3, 6.1, 6.3, 6.7_

  - [x] 6.2 Integrar SaldoCajaModal directamente en ArqueoPanel
    - Importar `SaldoCajaModal` desde `./modals/SaldoCajaModal.jsx`
    - Agregar estado local `showSaldoModal` con useState
    - Reemplazar `window.dispatchEvent(new CustomEvent('openSaldoCajaModal'))` → `setShowSaldoModal(true)`
    - Renderizar `<SaldoCajaModal isOpen={showSaldoModal} onClose={() => setShowSaldoModal(false)} />`
    - Mantener navegación temporal (ayer/hoy) y botón "Ver Detalle" que navega a `/ventas-detalle`
    - _Requisitos: 6.2, 6.4, 6.5, 6.6, 8.2_

  - [ ]* 6.3 Escribir property test para tabla de ventas por método de pago
    - **Propiedad 12: Tabla de ventas por método de pago** — Para cualquier datos de resumen de ventas, verificar que la tabla contiene una fila por cada método de pago con conteo y total
    - **Valida: Requisito 6.2**

- [ ] 7. Limpieza y verificación final
  - [ ] 7.1 Eliminar navegación legacy de window.location.href
    - Verificar que no existen llamadas a `window.location.href` para `/mermas` o `/arqueo` en MenuApp.jsx
    - Verificar que ArqueoPanel no usa `window.dispatchEvent` para SaldoCajaModal
    - Verificar que MermaPanel no usa `window.location.href = '/'` para cerrar
    - _Requisitos: 8.1, 8.2, 8.3_

  - [ ]* 7.2 Escribir tests de integración
    - Test flujo completo de merma: abrir panel → buscar → seleccionar → cantidad → motivo → confirmar → historial
    - Test flujo de arqueo: abrir panel → ver ventas → cambiar día → ver saldo → cerrar
    - Test que no existen llamadas a `window.location.href` para `/mermas` o `/arqueo`
    - _Requisitos: 1.1, 1.2, 8.1, 8.2, 8.3_

- [ ] 8. Checkpoint final — Verificar todo integrado
  - Ejecutar todos los tests, verificar que ambos paneles funcionan como inline sin navegación de página. Preguntar al usuario si hay dudas.

## Notas

- Las tareas marcadas con `*` son opcionales y pueden omitirse para un MVP más rápido
- Cada tarea referencia requisitos específicos para trazabilidad
- Los checkpoints aseguran validación incremental
- Los property tests usan fast-check y validan propiedades de correctitud del diseño
- Los unit tests validan ejemplos específicos y edge cases
- No se requieren cambios en backend — se reutilizan las APIs PHP existentes
