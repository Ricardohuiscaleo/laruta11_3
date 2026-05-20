# Skill: Compras IA - Análisis de Boletas y Facturas

## Descripción
Especialista en el pipeline de análisis de compras usando IA (Gemini) para extraer datos de boletas, facturas y productos de imágenes.

## Cuándo usar
- Analizar una nueva boleta/factura de compra
- Corregir extracciones incorrectas de la IA
- Mejorar prompts de GeminiService
- Validar datos extraídos antes de guardar en BD
- Reconciliar discrepancias entre extracción IA y datos reales

## Stack
- PHP (Laravel 11 en mi3-backend)
- Gemini API (Google AI)
- AWS S3 (almacenamiento de imágenes)
- MySQL (tablas: compras, compra_items, extraction_feedback)

## Pipeline de 4 Fases

### Fase 1: Visión (Clasificación)
- Determinar tipo de documento: boleta, factura, producto suelto, báscula
- Validar calidad de imagen
- Rechazar si es ilegible o no es documento de compra

### Fase 2: Análisis (Extracción)
- Extraer: proveedor, fecha, items, montos, método de pago
- Para productos: nombre, cantidad, unidad, precio unitario, total
- Para boletas: RUT (solo supermercados), IVA incluido

### Fase 3: Validación
- Verificar totales (suma de items = total documento)
- Normalizar montos (IVA incluido en boletas chilenas)
- Mapear proveedores (ARIAKA, Ariztía, etc.)
- Validar método de pago (transfer para ciertos proveedores)

### Fase 4: Reconciliación
- Comparar con órdenes de compra previas
- Detectar duplicados
- Preguntar al usuario sobre discrepancias
- Guardar feedback para auto-aprendizaje

## Reglas de Extracción

### Proveedores
- **ARIAKA**: Normalizar cualquier variante a exactamente "ARIAKA"
- Ricardo Huiscaleo (emisor) → null, no es proveedor
- Mercado Pago → null, no es proveedor real
- Ariztía, Agrosuper, Ideal, agro-lucila, ARIAKA, JumboAPP → siempre `metodo_pago: transfer`

### Productos
- PACKAGING/LIMPIEZA/INSUMOS: tipo_compra="insumos"
- NxPrecio: N unidades por precio total (no inventar precios)
- SOBRES/SACHETS: <100g → unidad, leer nombre exacto empaque
- Peso empaque: usar peso exacto (500g→0.5kg)

### Montos
- IVA boletas chilenas: Total SIEMPRE es IVA incluido
- `normalizeAmounts()`: safety net corrige si Gemini trata total como neto
- No inventar precios sin dato visible en imagen

### Doble Mapeo
- Prompt (best effort) + `mapPersonToSupplier()` server-side (garantizado)
- El prompt le dice a la IA qué hacer, pero no siempre obedece
- El mapeo server-side corrige forzadamente

## Archivos Clave
- `mi3/backend/app/Services/Compra/GeminiService.php` - Servicio principal
- `mi3/backend/app/Services/Compra/FeedbackService.php` - Auto-aprendizaje
- `mi3/backend/app/Services/Compra/ReconciliationService.php` - Reconciliación
- `caja3/api/GeminiService.php` - Versión legacy (caja3)

## API Endpoints
- `POST /api/compras/analyze` - Analizar imagen
- `POST /api/compras/validate` - Validar extracción
- `POST /api/compras/reconcile` - Reconciliar con órdenes
- `GET /api/compras/feedback` - Obtener feedback histórico

## Errores Comunes
1. Gemini trata total como neto en vez de IVA incluido
2. No detecta correctamente unidades (kg vs unidad vs caja)
3. Mapea proveedor incorrecto (Cecilia Rojas como proveedor separado)
4. Inventa precios unitarios cuando solo hay precio total
5. No normaliza ARIAKA correctamente

## Testing
- 8 property tests con 25K+ assertions
- Test con imágenes reales de producción
- Verificar `normalizeAmounts()` con edge cases
