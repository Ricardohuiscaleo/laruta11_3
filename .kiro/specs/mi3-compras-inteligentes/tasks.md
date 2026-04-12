# Plan de Implementación: Compras Inteligentes mi3

## Visión General

Migración del módulo de compras desde caja3 (PHP puro) a mi3 (Laravel 11 + Next.js 14). Se implementa en orden: migraciones BD → modelos → servicios backend → controllers/rutas → frontend por tab → IA/extracción → WebSocket → pipeline entrenamiento. Cada tarea construye sobre la anterior sin código huérfano.

## Tareas

- [x] 1. Migraciones de BD y modelos Eloquent
  - [x] 1.1 Crear migraciones para las 4 tablas nuevas (`ai_extraction_logs`, `ai_training_dataset`, `supplier_index`, `extraction_feedback`) según el esquema SQL del diseño
    - Ejecutar migraciones y verificar que las tablas se crean correctamente
    - _Requisitos: 3.1, 4.1, 4.4, 5.1_

  - [x] 1.2 Crear modelos Eloquent: `Compra`, `CompraDetalle`, `Ingredient`, `Product`, `CapitalTrabajo`, `AiExtractionLog`, `AiTrainingDataset`, `SupplierIndex`, `ExtractionFeedback`
    - Definir `$table`, `$casts`, `$fillable` y relaciones (`hasMany`, `belongsTo`) según el diseño
    - Los modelos de tablas existentes (`Compra`, `CompraDetalle`, `Ingredient`, `Product`, `CapitalTrabajo`) deben mapear a las tablas ya existentes sin modificarlas
    - _Requisitos: 1.1, 1.7, 3.1, 4.1, 4.5_

- [x] 2. Servicios backend — Lógica de negocio core
  - [x] 2.1 Implementar `CompraService` con registro atómico de compras
    - Método `registrar(data)`: transacción atómica que crea `compras`, inserta `compras_detalle` con snapshots `stock_antes`/`stock_despues`, actualiza stock en `ingredients`/`products` según `item_type`, y actualiza `capital_trabajo` del día
    - Método `eliminar(id)`: transacción atómica que revierte stock y elimina compra + detalles
    - Método `buscarItems(query)`: búsqueda fuzzy combinando ingredientes y productos activos, retornando nombre, stock, unidad, último precio
    - Método `getProveedores(query)`: autocompletado de proveedores distintos desde tabla `compras`
    - Método `crearIngrediente(data)`: crear ingrediente nuevo con nombre, categoría, unidad, costo, proveedor
    - _Requisitos: 1.1, 1.2, 1.4, 1.6, 1.7, 1.8, 6.4_

  - [x]* 2.2 Escribir property test — Propiedad 2: Invariante de snapshot de stock
    - **Propiedad 2: Para cualquier registro en compras_detalle, stock_despues == stock_antes + cantidad**
    - **Valida: Requisito 1.7**

  - [ ]* 2.3 Escribir property test — Propiedad 3: Búsqueda fuzzy retorna resultados relevantes
    - **Propiedad 3: Todos los resultados de búsqueda fuzzy tienen similitud > 0 con el término y contienen nombre, stock, unidad, último precio**
    - **Valida: Requisitos 1.2, 1.4**

  - [x] 2.4 Implementar `StockService` con semáforo y ajuste masivo
    - Método `getInventario()`: retorna ingredientes y productos con clasificación semáforo (rojo/amarillo/verde según min_stock_level)
    - Método `parsearMarkdown(texto)`: parsea texto markdown de ajuste masivo, match fuzzy con ítems existentes, retorna válidos + errores
    - Método `aplicarAjuste(items)`: transacción atómica para actualizar stock de múltiples ítems
    - Método `reporteBebidas()`: reporte de productos/bebidas con stock, ventas y estado
    - _Requisitos: 7.1, 7.2, 7.3, 7.4, 7.5, 12.1, 12.2, 12.4_

  - [ ]* 2.5 Escribir property test — Propiedad 9: Clasificación de semáforo de stock
    - **Propiedad 9: Para cualquier ítem, si stock < min*0.25 → rojo, si stock entre min*0.25 y min → amarillo, si stock > min → verde**
    - **Valida: Requisito 7.2**

  - [ ]* 2.6 Escribir property test — Propiedad 12: Round-trip de parseo markdown
    - **Propiedad 12: Para cualquier lista válida de ajustes, formatear a markdown y parsear produce lista equivalente**
    - **Valida: Requisitos 12.1, 12.3**

  - [ ]* 2.7 Escribir property test — Propiedad 13: Parser markdown maneja líneas inválidas
    - **Propiedad 13: Para texto mixto válido/inválido, resultados exitosos + errores == total líneas procesables**
    - **Valida: Requisito 12.4**

- [x] 3. Servicios backend — Sugerencias y KPIs
  - [x] 3.1 Implementar `SugerenciaService`
    - Método `matchProveedor(nombre)`: match fuzzy contra proveedores conocidos en `supplier_index`
    - Método `matchItems(items)`: match fuzzy de ítems extraídos contra ingredientes/productos, pre-seleccionar con confianza ≥ 80%
    - Método `actualizarIndice(compra)`: actualizar `supplier_index` con frecuencia, ítems habituales, precios
    - Método `registrarFeedback(extractionLogId, correcciones)`: guardar correcciones del usuario en `extraction_feedback`
    - Método `precioHistorico(itemId)`: retornar historial de precios de un ítem
    - Método `sugerirPrecio(itemId)`: retornar último precio registrado para pre-llenar formulario
    - _Requisitos: 1.3, 3.3, 3.4, 4.4, 4.5, 4.6, 8.4_

  - [ ]* 3.2 Escribir property test — Propiedad 4: Match fuzzy de proveedores e ítems
    - **Propiedad 4: Match retorna proveedor con mayor similitud; ítems pre-seleccionados tienen similitud ≥ 0.80**
    - **Valida: Requisitos 3.3, 3.4**

  - [ ]* 3.3 Escribir property test — Propiedad 5: Índice de proveedores se actualiza consistentemente
    - **Propiedad 5: Tras N compras del mismo proveedor, frecuencia == N, ultima_compra == fecha más reciente, items_habituales contiene todos los ítems**
    - **Valida: Requisitos 4.5, 4.6**

  - [x] 3.4 Implementar lógica de KPIs en `CompraService` (o método dedicado)
    - Método `getKpis()`: calcular ventas mes anterior, ventas mes actual, sueldos, saldo disponible (ventas_anterior + ventas_actual - sueldos - compras_actual)
    - Método `historialSaldo()`: retornar movimientos de `capital_trabajo` ordenados por fecha
    - _Requisitos: 9.1, 9.2, 9.3_

  - [ ]* 3.5 Escribir property test — Propiedad 10: Cálculo de saldo disponible
    - **Propiedad 10: saldo == ventas_anterior + ventas_actual - sueldos - compras_actual**
    - **Valida: Requisito 9.2**

- [ ] 4. Checkpoint — Verificar servicios backend
  - Ejecutar todos los tests, asegurar que los servicios core funcionan correctamente. Preguntar al usuario si hay dudas.

- [x] 5. Controllers y rutas API
  - [x] 5.1 Implementar `CompraController` con las rutas definidas en el diseño
    - `store`: registro atómico (POST /api/v1/admin/compras)
    - `index`: historial paginado con búsqueda (GET /api/v1/admin/compras)
    - `show`: detalle de compra (GET /api/v1/admin/compras/{id})
    - `destroy`: eliminar + rollback stock (DELETE /api/v1/admin/compras/{id})
    - `items`: búsqueda fuzzy ingredientes + productos (GET /api/v1/admin/compras/items)
    - `proveedores`: autocompletado (GET /api/v1/admin/compras/proveedores)
    - `crearIngrediente`: crear ingrediente nuevo (POST /api/v1/admin/compras/ingrediente)
    - `uploadImagen`: subir imagen a compra existente (POST /api/v1/admin/compras/{id}/imagen)
    - Registrar rutas en `routes/api.php` bajo middleware `auth:sanctum` y prefijo `v1/admin`
    - _Requisitos: 1.1, 1.2, 1.4, 1.6, 1.8, 2.2, 6.1, 6.2, 6.3, 6.4, 6.5_

  - [ ]* 5.2 Escribir property test — Propiedad 7: Paginación retorna slices correctos
    - **Propiedad 7: Paginación retorna min(50, restantes) registros, ordenados por fecha desc, página N+1 empieza después de página N**
    - **Valida: Requisito 6.1**

  - [ ]* 5.3 Escribir property test — Propiedad 8: Búsqueda en historial filtra correctamente
    - **Propiedad 8: Todos los resultados contienen el término de búsqueda (case-insensitive) en proveedor o notas**
    - **Valida: Requisito 6.2**

  - [x] 5.4 Implementar `StockController` con rutas de inventario
    - `index`: inventario con semáforo (GET /api/v1/admin/stock)
    - `bebidas`: reporte bebidas (GET /api/v1/admin/stock/bebidas)
    - `ajusteMasivo`: ajuste markdown (POST /api/v1/admin/stock/ajuste-masivo)
    - `previewAjuste`: previsualización (GET /api/v1/admin/stock/preview-ajuste)
    - Registrar rutas en `routes/api.php`
    - _Requisitos: 7.1, 7.2, 7.3, 7.4, 7.5, 12.1, 12.2_

  - [x] 5.5 Implementar `KpiController` con rutas de métricas
    - `index`: KPIs financieros (GET /api/v1/admin/kpis)
    - `historialSaldo`: historial capital (GET /api/v1/admin/kpis/historial-saldo)
    - `proyeccion`: proyección de compras (GET /api/v1/admin/kpis/proyeccion)
    - `precioHistorico`: precio histórico por ítem (GET /api/v1/admin/kpis/precio-historico/{id})
    - Registrar rutas en `routes/api.php`
    - _Requisitos: 8.4, 9.1, 9.2, 9.3_

- [x] 6. Gestión de imágenes y upload a S3
  - [x] 6.1 Implementar upload temporal y definitivo de imágenes en `CompraController` / servicio auxiliar
    - Upload temporal: `POST /api/v1/admin/compras/upload-temp` → almacenar en S3 bajo prefijo temporal, retornar `{tempUrl, tempKey}`
    - Compresión automática si imagen > 500KB (usar Intervention Image o similar)
    - Al confirmar compra: mover imágenes de temporal a `compras/respaldo_{compra_id}_{timestamp}.jpg`
    - Agregar URLs al campo JSON `imagen_respaldo` de la compra
    - _Requisitos: 2.1, 2.2, 2.3, 2.4, 2.5_

- [ ] 7. Checkpoint — Verificar API completa
  - Ejecutar todos los tests backend. Verificar que las rutas responden correctamente. Preguntar al usuario si hay dudas.

- [x] 8. Frontend — Layout y estructura de páginas
  - [x] 8.1 Crear layout de compras con tabs (`ComprasLayout.tsx`)
    - Layout con tabs: Registro, Historial, Stock, Proyección, KPIs
    - Estructura de carpetas: `app/admin/compras/layout.tsx`, `page.tsx` (redirect a registro), y subcarpetas `registro/`, `historial/`, `stock/`, `proyeccion/`, `kpis/`
    - Configurar navegación entre tabs
    - _Requisitos: Todos (estructura base)_

  - [x] 8.2 Crear hooks y utilidades compartidas del frontend
    - Hook `useComprasApi` para llamadas a la API (fetch wrapper con manejo de errores y toast)
    - Función `calcularIVA(precioTotal, cantidad)`: calcula precio neto = Math.round(precioTotal / 1.19 / cantidad)
    - Función `formatearPesosCLP(monto)`: formato $XX.XXX
    - Tipos TypeScript: `ExtractionResult`, `ExtractionItem`, `Compra`, `CompraDetalle`, `StockItem`, `Kpi`
    - _Requisitos: 1.5, 11.1_

  - [ ]* 8.3 Escribir property test — Propiedad 1: Cálculo de IVA es inversible
    - **Propiedad 1: Para cualquier precio total entero positivo y cantidad positiva, calcular neto y recalcular total produce valor dentro de ±1 peso**
    - Usar fast-check en el frontend
    - **Valida: Requisito 1.5**

- [x] 9. Frontend — Tab Registro de Compras
  - [x] 9.1 Implementar componente `RegistroCompra.tsx`
    - Formulario con campos: proveedor (autocompletado), fecha, tipo_compra, metodo_pago, notas
    - Lista dinámica de ítems con búsqueda fuzzy (`ItemSearch.tsx`), cantidad, unidad, precio, toggle IVA
    - Cálculo automático de subtotales y total
    - Advertencia visual si monto total supera saldo disponible
    - Botón confirmar que envía POST a `/api/v1/admin/compras`
    - _Requisitos: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.9_

  - [x] 9.2 Implementar componente `ItemSearch.tsx`
    - Input con debounce que llama a GET `/api/v1/admin/compras/items?q={query}`
    - Dropdown con resultados mostrando nombre, stock actual, unidad, último precio
    - Opción "Crear nuevo ingrediente" si no hay resultados
    - _Requisitos: 1.2, 1.6_

  - [x] 9.3 Implementar componente `ImageUploader.tsx`
    - Drag & drop y selección de archivo para múltiples imágenes
    - Preview (thumbnails) con opción de ampliar
    - Upload temporal vía POST `/api/v1/admin/compras/upload-temp`
    - Almacenar `tempKeys` para asociar al confirmar compra
    - _Requisitos: 2.1, 2.4, 2.5_

- [x] 10. Frontend — Tab Historial
  - [x] 10.1 Implementar componente `HistorialCompras.tsx`
    - Lista paginada (50 por página) con búsqueda por proveedor/notas
    - Cada fila muestra: fecha, proveedor, monto total, método pago, cantidad de ítems
    - Click en fila abre detalle (`DetalleCompra.tsx`)
    - Selección múltiple para generar rendición WhatsApp
    - _Requisitos: 6.1, 6.2, 6.3, 6.6_

  - [x] 10.2 Implementar componentes `DetalleCompra.tsx` y `RendicionWhatsApp.tsx`
    - `DetalleCompra`: modal/vista con detalle completo (proveedor, fecha, tipo, método pago, monto, notas, ítems con cantidades/precios, imágenes de respaldo), botón eliminar compra, botón subir imagen adicional
    - `RendicionWhatsApp`: generar texto formateado para WhatsApp con lista de compras seleccionadas, montos, total, transferencia y saldo
    - _Requisitos: 6.3, 6.4, 6.5, 6.6_

  - [ ]* 10.3 Escribir property test — Propiedad 14: Formateo WhatsApp contiene todos los datos
    - **Propiedad 14: Para cualquier conjunto de compras, el texto WhatsApp contiene cada proveedor, monto, total correcto y fecha**
    - Usar fast-check
    - **Valida: Requisitos 6.6, 8.3**

- [x] 11. Frontend — Tab Stock
  - [x] 11.1 Implementar componente `StockDashboard.tsx`
    - Dos vistas: Ingredientes y Bebidas (toggle/tabs internos)
    - Cada ítem muestra: nombre, stock actual, nivel mínimo, porcentaje, semáforo (rojo/amarillo/verde), última cantidad comprada, vendido desde última compra, diferencia stock esperado vs real
    - Datos desde GET `/api/v1/admin/stock` y GET `/api/v1/admin/stock/bebidas`
    - _Requisitos: 7.1, 7.2, 7.3, 7.5_

  - [x] 11.2 Implementar componente `AjusteMasivo.tsx`
    - Textarea para pegar texto markdown
    - Botón "Previsualizar" que llama a GET `/api/v1/admin/stock/preview-ajuste`
    - Tabla de previsualización: ítem, stock actual, nuevo stock, diferencia
    - Líneas con error resaltadas en rojo
    - Botón "Aplicar ajuste" que llama a POST `/api/v1/admin/stock/ajuste-masivo`
    - _Requisitos: 7.4, 12.1, 12.2, 12.4_

- [x] 12. Frontend — Tabs Proyección y KPIs
  - [x] 12.1 Implementar componente `ProyeccionCompras.tsx`
    - Lista editable de ítems con cantidad, unidad, precio estimado (sugerido por historial)
    - Cálculo de costo total proyectado vs saldo disponible
    - Botón copiar proyección formateada para WhatsApp
    - _Requisitos: 8.1, 8.2, 8.3, 8.4_

  - [x] 12.2 Implementar componente `KpisDashboard.tsx`
    - Cards con KPIs: ventas mes anterior, ventas mes actual, sueldos, saldo disponible
    - Historial de saldo (tabla o gráfico simple)
    - Datos desde GET `/api/v1/admin/kpis` y GET `/api/v1/admin/kpis/historial-saldo`
    - _Requisitos: 9.1, 9.2, 9.3_

- [ ] 13. Checkpoint — Verificar frontend base
  - Ejecutar todos los tests. Verificar que las 5 tabs renderizan y se comunican con la API. Preguntar al usuario si hay dudas.

- [x] 14. Extracción IA con Amazon Nova Lite
  - [x] 14.1 Implementar `ExtraccionService` en Laravel
    - Método `extractFromImage(imageUrl)`: enviar imagen a Bedrock (Nova Lite) con el prompt del diseño, parsear respuesta JSON, calcular scores de confianza por campo, guardar log en `ai_extraction_logs`, retornar `ExtractionResult`
    - Timeout de 10 segundos, manejo de errores (imagen borrosa, respuesta no parseable)
    - Formatear montos como enteros (pesos chilenos sin decimales)
    - _Requisitos: 3.1, 3.2, 3.5, 3.6, 3.7, 3.8, 3.9, 11.1, 11.4_

  - [ ]* 14.2 Escribir property test — Propiedad 11: Round-trip serialización de extracción
    - **Propiedad 11: Para cualquier ExtractionResult válido, serializar a JSON y deserializar produce objeto equivalente; montos son enteros**
    - **Valida: Requisitos 11.1, 11.2, 11.3, 11.4**

  - [x] 14.3 Implementar `ExtraccionController` con rutas de extracción
    - `uploadTemp`: upload temporal (POST /api/v1/admin/compras/upload-temp)
    - `extract`: extracción IA (POST /api/v1/admin/compras/extract) — llama a ExtraccionService, luego SugerenciaService para match de proveedor/ítems
    - `quality`: métricas de calidad (GET /api/v1/admin/compras/extraction-quality)
    - Registrar rutas en `routes/api.php`
    - _Requisitos: 3.1, 3.2, 3.3, 3.4, 5.1_

  - [x] 14.4 Integrar extracción IA en el frontend (`ExtractionPreview.tsx`)
    - Al subir imagen en `ImageUploader`, opción "Extraer datos" que llama a POST `/api/v1/admin/compras/extract`
    - Componente `ExtractionPreview.tsx`: muestra datos extraídos con indicadores de confianza por campo
    - Campos con confianza < 0.7 resaltados con borde amarillo/naranja
    - Botón "Usar datos" que pre-llena el formulario de registro
    - Si extracción falla, mostrar mensaje y permitir ingreso manual
    - _Requisitos: 3.2, 3.5, 3.7, 11.2_

- [x] 15. Validación de calidad IA y pipeline de entrenamiento
  - [x] 15.1 Implementar `ValidacionService`
    - Método `compararExtraccion(extracted, real)`: comparar datos extraídos vs reales aplicando umbrales (proveedor ≥ 85% similitud, monto ≤ 2% diferencia, ítem: nombre ≥ 80% AND cantidad < 10% AND precio < 5%)
    - Método `generarReporte()`: total procesadas, precisión global, precisión por campo, lista de fallidas
    - Alerta si precisión global < 70%
    - _Requisitos: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6_

  - [ ]* 15.2 Escribir property test — Propiedad 6: Umbrales de precisión por campo
    - **Propiedad 6: Proveedor correcto si similitud ≥ 85%; monto correcto si diferencia ≤ 2%; ítem correcto si nombre ≥ 80% AND cantidad < 10% AND precio < 5%**
    - **Valida: Requisitos 5.1, 5.2, 5.3, 5.4**

  - [x] 15.3 Implementar `PipelineService`
    - Método `ejecutar()`: procesar imágenes históricas en S3 (prefijo `compras/`) en lotes de 10, asociar con datos reales de `compras`/`compras_detalle`, extraer con IA, comparar con ValidacionService, guardar en `ai_training_dataset`
    - Método `reporte()`: retornar reporte de precisión del último batch
    - Rutas: POST `/api/v1/admin/compras/pipeline/run`, GET `/api/v1/admin/compras/pipeline/report`
    - _Requisitos: 4.1, 4.2, 4.3, 4.7_

- [x] 16. WebSocket — Actualizaciones en tiempo real
  - [x] 16.1 Implementar evento `CompraRegistrada` y broadcast vía Laravel Reverb
    - Crear evento `App\Events\CompraRegistrada` que implementa `ShouldBroadcast`
    - Canal: `Channel("compras")`, evento: `compra.registrada`
    - Payload: `{ compra_id, proveedor, monto_total, items_count, timestamp }`
    - Disparar evento al final de `CompraService::registrar()`
    - _Requisitos: 10.1_

  - [x] 16.2 Integrar WebSocket en el frontend
    - En `ComprasLayout.tsx`: conectar a Laravel Echo, escuchar canal `compras`
    - Al recibir evento `compra.registrada`: actualizar historial, stock y KPIs sin recarga
    - Indicador visual de estado de conexión WebSocket
    - Reconexión automática con backoff exponencial (1s, 2s, 4s, 8s, max 30s)
    - _Requisitos: 10.2, 10.3, 10.4_

- [ ] 17. Checkpoint final — Verificar sistema completo
  - Ejecutar todos los tests (backend + frontend). Verificar flujo completo: registro con extracción IA → historial → stock → proyección → KPIs → WebSocket. Preguntar al usuario si hay dudas.

## Notas

- Las tareas marcadas con `*` son opcionales (property-based tests) y pueden omitirse para un MVP más rápido
- Cada tarea referencia requisitos específicos para trazabilidad
- Los checkpoints permiten validación incremental
- Los property tests validan las 14 propiedades de correctitud del diseño usando Eris (PHP) y fast-check (TypeScript)
- Los unit tests complementan los property tests con ejemplos específicos y edge cases
