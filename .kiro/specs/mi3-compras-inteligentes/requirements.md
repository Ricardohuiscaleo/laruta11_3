# Documento de Requisitos — Compras Inteligentes mi3

## Introducción

App de Compras Inteligentes para La Ruta 11, construida en mi3 (Next.js 14 + Laravel 11) para reemplazar la app actual en caja3 (ComprasApp.jsx). Incluye extracción automática de datos desde fotos de boletas/facturas usando Amazon Nova Lite (Bedrock), aprendizaje basado en datos históricos, actualizaciones en tiempo real vía Laravel Reverb, y una UX mejorada con tabs modulares. Comparte la base de datos MySQL `laruta11` (tablas: compras, compras_detalle, ingredients, products, product_recipes, capital_trabajo, inventory_transactions).

## Glosario

- **Sistema_Compras**: Módulo de compras en mi3-frontend (Next.js 14 + React) accesible desde mi.laruta11.cl/compras
- **API_Compras**: Conjunto de endpoints REST en mi3-backend (Laravel 11) que gestionan operaciones CRUD de compras, inventario y capital de trabajo
- **Extractor_IA**: Servicio en mi3-backend que usa Amazon Nova Lite (Bedrock) para analizar imágenes de múltiples tipos: boletas/facturas chilenas, fotos de productos (cajas, bolsas, envases), fotos de básculas/balanzas (lectura de display digital), y facturas de proveedores conocidos. Extrae datos estructurados adaptados al tipo de imagen detectado
- **Motor_Sugerencias**: Componente que analiza datos históricos de compras para sugerir proveedores frecuentes, precios habituales, ítems recurrentes, y patrones producto-cantidad aprendidos (ej: "caja de tomates = ~3 kg")
- **Pipeline_Entrenamiento**: Proceso batch que procesa imágenes históricas de boletas/facturas/productos almacenadas en S3 para construir y actualizar el dataset de referencia del Extractor_IA, reconstruir el índice de proveedores, y aprender patrones de producto-a-cantidad
- **Canal_Compras**: Canal de WebSocket vía Laravel Reverb para transmitir eventos de compras en tiempo real
- **Boleta**: Documento tributario chileno simplificado emitido a consumidores finales, contiene RUT emisor, monto neto, IVA 19%, total
- **Factura**: Documento tributario chileno detallado con RUT emisor/receptor, desglose de ítems, IVA, monto neto y total
- **RUT**: Rol Único Tributario, identificador fiscal chileno con formato XX.XXX.XXX-Y (dígito verificador)
- **IVA**: Impuesto al Valor Agregado, 19% en Chile
- **S3_Bucket**: Bucket AWS S3 `laruta11-images` donde se almacenan fotos bajo el prefijo `compras/`
- **Validador_Extracción**: Componente que compara los datos extraídos por el Extractor_IA contra datos conocidos de compras históricas para medir precisión

## Requisitos

### Requisito 1: Registro de Compras

**User Story:** Como administrador de La Ruta 11, quiero registrar compras con sus ítems y respaldos fotográficos, para mantener un control preciso del inventario y gastos.

#### Criterios de Aceptación

1. WHEN el usuario envía un formulario de compra válido con proveedor, fecha, tipo_compra, metodo_pago e ítems, THE API_Compras SHALL crear un registro en la tabla `compras` y registros correspondientes en `compras_detalle`, actualizar el stock en `ingredients` o `products` según item_type, y actualizar `capital_trabajo` del día, todo dentro de una transacción atómica.
2. WHEN el usuario busca un ítem en el formulario de registro, THE Sistema_Compras SHALL realizar búsqueda fuzzy combinando ingredientes y productos activos, mostrando nombre, stock actual, unidad y último precio de compra.
3. WHEN el usuario selecciona un ingrediente existente, THE Motor_Sugerencias SHALL pre-llenar el precio unitario con el último precio registrado en `compras_detalle` para ese ingrediente.
4. WHEN el usuario escribe en el campo proveedor, THE Sistema_Compras SHALL mostrar autocompletado con proveedores distintos obtenidos de la tabla `compras`.
5. WHEN el usuario activa el toggle de IVA para un ítem, THE Sistema_Compras SHALL calcular el precio unitario neto dividiendo el precio total ingresado por (1 + 0.19) y luego por la cantidad.
6. WHEN un ítem buscado no existe en la base de datos, THE Sistema_Compras SHALL ofrecer crear un nuevo ingrediente con nombre, categoría, unidad, costo por unidad y proveedor.
7. WHEN el registro de compra se completa, THE API_Compras SHALL registrar el snapshot de stock_antes y stock_despues en cada registro de `compras_detalle`.
8. IF la transacción de registro falla en cualquier paso, THEN THE API_Compras SHALL revertir todos los cambios (rollback) y retornar un mensaje de error descriptivo.
9. WHEN el monto total de la compra supera el saldo disponible calculado, THE Sistema_Compras SHALL mostrar una advertencia visual al usuario antes de confirmar el registro.

### Requisito 2: Carga y Gestión de Imágenes de Respaldo

**User Story:** Como administrador, quiero subir fotos de boletas y facturas asociadas a cada compra, para tener respaldo digital de los gastos.

#### Criterios de Aceptación

1. THE Sistema_Compras SHALL permitir subir múltiples imágenes por compra mediante selección de archivo o arrastrar y soltar (drag & drop).
2. WHEN el usuario sube una imagen, THE API_Compras SHALL almacenarla en el S3_Bucket bajo el prefijo `compras/respaldo_{compra_id}_{timestamp}.jpg` y agregar la URL al array JSON del campo `imagen_respaldo` de la compra correspondiente.
3. WHEN una imagen supera 500KB de tamaño, THE API_Compras SHALL comprimir la imagen antes de subirla a S3.
4. WHEN el usuario sube una imagen durante el registro de una nueva compra (antes de tener compra_id), THE Sistema_Compras SHALL almacenar las imágenes temporalmente y asociarlas al registro una vez creado.
5. THE Sistema_Compras SHALL mostrar previsualizaciones (thumbnails) de las imágenes subidas con opción de ampliar a tamaño completo.

### Requisito 3: Extracción Inteligente de Datos desde Boletas/Facturas

**User Story:** Como administrador, quiero que al subir una foto de boleta o factura, el sistema extraiga automáticamente los datos relevantes, para agilizar el registro de compras.

#### Criterios de Aceptación

1. WHEN el usuario sube una imagen de boleta o factura en el formulario de registro, THE Extractor_IA SHALL enviar la imagen a Amazon Nova Lite (Bedrock) y extraer: nombre del proveedor, RUT del proveedor, lista de ítems con nombre, cantidad, unidad y precio, monto neto, IVA y monto total.
2. WHEN el Extractor_IA obtiene resultados, THE Sistema_Compras SHALL pre-llenar el formulario de registro con los datos extraídos, permitiendo al usuario revisar y corregir antes de confirmar.
3. WHEN el Extractor_IA extrae un nombre de proveedor, THE Motor_Sugerencias SHALL intentar hacer match con proveedores existentes en la base de datos usando similitud de texto, y sugerir el proveedor más cercano.
4. WHEN el Extractor_IA extrae nombres de ítems, THE Motor_Sugerencias SHALL intentar hacer match con ingredientes y productos existentes usando búsqueda fuzzy, y pre-seleccionar los matches con confianza superior al 80%.
5. WHEN el Extractor_IA no puede extraer datos de una imagen (imagen borrosa, formato no reconocido), THE Sistema_Compras SHALL informar al usuario que la extracción falló y permitir el ingreso manual.
6. THE Extractor_IA SHALL retornar un nivel de confianza (0.0 a 1.0) para cada campo extraído.
7. WHEN un campo extraído tiene confianza inferior a 0.7, THE Sistema_Compras SHALL resaltar visualmente ese campo para que el usuario lo revise.
8. THE Extractor_IA SHALL procesar boletas chilenas reconociendo el formato de RUT (XX.XXX.XXX-Y), montos en pesos chilenos (formato $XX.XXX) e IVA del 19%.
9. THE Extractor_IA SHALL completar la extracción de una imagen en un tiempo máximo de 10 segundos.
10. WHEN el usuario sube una foto de un producto (caja de tomates, bolsa de pan, bandeja de carne, etc.), THE Extractor_IA SHALL identificar el producto, estimar la cantidad basándose en patrones aprendidos (ej: caja estándar de tomates = ~3 kg), y retornar el tipo_imagen como "producto".
11. WHEN el usuario sube una foto de una báscula o balanza digital, THE Extractor_IA SHALL leer el número del display digital, identificar la unidad (kg/g), y si es visible, identificar el producto que se está pesando.
12. WHEN el Extractor_IA detecta una factura de un proveedor conocido (ej: Shipo), THE Extractor_IA SHALL usar los patrones aprendidos de ese proveedor (ítems habituales, formato de factura) para mejorar la precisión de la extracción.
13. THE Extractor_IA SHALL incluir en el prompt de Bedrock el contexto aprendido: proveedores conocidos, ingredientes del negocio, patrones producto-cantidad del historial, y correcciones frecuentes del usuario.

### Requisito 4: Pipeline de Entrenamiento y Mejora Continua

**User Story:** Como administrador, quiero que el sistema aprenda de las compras históricas y fotos existentes en S3, para mejorar la precisión de las sugerencias y la extracción de datos con el tiempo.

#### Criterios de Aceptación

1. THE Pipeline_Entrenamiento SHALL procesar las imágenes históricas almacenadas en el S3_Bucket (prefijo `compras/`) y asociarlas con los datos registrados en las tablas `compras` y `compras_detalle` para construir un dataset de referencia.
2. WHEN el Pipeline_Entrenamiento procesa una imagen histórica, THE Pipeline_Entrenamiento SHALL extraer datos con el Extractor_IA y comparar los resultados contra los datos reales registrados en la base de datos para esa compra.
3. THE Pipeline_Entrenamiento SHALL generar un reporte de precisión que incluya: porcentaje de acierto en proveedor, porcentaje de acierto en ítems (nombre, cantidad, precio), y porcentaje de acierto en monto total.
4. WHEN el usuario confirma una compra con datos corregidos (el usuario modificó datos pre-llenados por el Extractor_IA), THE Motor_Sugerencias SHALL almacenar la corrección como feedback para mejorar futuras sugerencias.
5. THE Motor_Sugerencias SHALL mantener un índice de proveedores frecuentes con: nombre normalizado, frecuencia de compra, ítems habituales por proveedor, y rango de precios históricos por ítem.
6. THE Motor_Sugerencias SHALL actualizar el índice de proveedores frecuentes cada vez que se registra una nueva compra.
7. WHEN el Pipeline_Entrenamiento se ejecuta, THE Pipeline_Entrenamiento SHALL procesar las imágenes en lotes de 10 para evitar sobrecargar la API de Bedrock.

### Requisito 5: Validación de Calidad de Extracción IA

**User Story:** Como administrador, quiero poder ejecutar tests de validación sobre la extracción IA, para asegurar que la calidad de la extracción es aceptable antes de confiar en ella.

#### Criterios de Aceptación

1. THE Validador_Extracción SHALL comparar los datos extraídos por el Extractor_IA contra los datos reales de compras históricas y calcular métricas de precisión por campo (proveedor, ítems, cantidades, precios, total).
2. THE Validador_Extracción SHALL considerar una extracción de proveedor como correcta cuando el nombre extraído coincide con el registrado usando similitud de texto con umbral de 85%.
3. THE Validador_Extracción SHALL considerar una extracción de monto total como correcta cuando la diferencia absoluta entre el monto extraído y el registrado es menor o igual al 2% del monto registrado.
4. THE Validador_Extracción SHALL considerar una extracción de ítem como correcta cuando el nombre del ítem tiene similitud de texto superior al 80% Y la diferencia en cantidad es menor al 10% Y la diferencia en precio es menor al 5%.
5. THE Validador_Extracción SHALL generar un reporte con: total de imágenes procesadas, precisión global, precisión por campo, y lista de las extracciones fallidas con detalle del error.
6. IF la precisión global del Extractor_IA cae por debajo del 70%, THEN THE Validador_Extracción SHALL emitir una alerta indicando que la calidad de extracción requiere revisión.

### Requisito 6: Historial de Compras

**User Story:** Como administrador, quiero ver el historial de compras con búsqueda y paginación, para consultar y gestionar compras pasadas.

#### Criterios de Aceptación

1. THE Sistema_Compras SHALL mostrar una lista paginada de compras ordenadas por fecha descendente, con 50 registros por página.
2. WHEN el usuario busca en el historial, THE API_Compras SHALL filtrar compras por coincidencia parcial en los campos proveedor y notas.
3. WHEN el usuario selecciona una compra del historial, THE Sistema_Compras SHALL mostrar el detalle completo: proveedor, fecha, tipo, método de pago, monto total, notas, ítems con cantidades y precios, e imágenes de respaldo.
4. WHEN el usuario elimina una compra, THE API_Compras SHALL revertir los cambios de inventario (restar las cantidades de stock) y eliminar el registro de compra y sus detalles dentro de una transacción atómica.
5. WHEN el usuario sube una imagen de respaldo a una compra existente, THE API_Compras SHALL agregar la URL al array JSON de `imagen_respaldo` de esa compra.
6. WHEN el usuario selecciona múltiples compras y genera una rendición, THE Sistema_Compras SHALL generar un resumen formateado para WhatsApp con: lista de compras seleccionadas, montos, total, monto de transferencia y saldo.

### Requisito 7: Gestión de Stock e Inventario

**User Story:** Como administrador, quiero visualizar el estado del inventario en tiempo real con indicadores de criticidad, para tomar decisiones de compra informadas.

#### Criterios de Aceptación

1. THE Sistema_Compras SHALL mostrar el inventario separado en dos vistas: Ingredientes y Bebidas (productos).
2. THE Sistema_Compras SHALL clasificar cada ítem del inventario con un sistema de semáforo: rojo (crítico: stock actual menor al 25% del min_stock_level), amarillo (bajo: stock actual entre 25% y 100% del min_stock_level), verde (ok: stock actual superior al min_stock_level).
3. THE Sistema_Compras SHALL mostrar para cada ítem: stock actual, nivel mínimo, porcentaje de stock, última cantidad comprada, cantidad vendida desde la última compra, y diferencia entre stock esperado y stock real.
4. WHEN el usuario aplica un ajuste masivo de stock mediante markdown, THE API_Compras SHALL parsear el texto markdown, validar los ítems y cantidades, y actualizar el stock de cada ítem dentro de una transacción atómica.
5. THE Sistema_Compras SHALL permitir generar un reporte de bebidas con stock actual, ventas y estado.

### Requisito 8: Proyección de Compras

**User Story:** Como administrador, quiero planificar compras futuras comparando el costo proyectado con el saldo disponible, para gestionar el presupuesto.

#### Criterios de Aceptación

1. THE Sistema_Compras SHALL permitir agregar ítems a una lista de proyección con cantidad, unidad y precio estimado.
2. THE Sistema_Compras SHALL calcular y mostrar el costo total proyectado y compararlo con el saldo disponible actual.
3. WHEN el usuario solicita copiar la proyección, THE Sistema_Compras SHALL generar un texto formateado para WhatsApp con la lista de ítems, cantidades, precios y total proyectado.
4. THE Motor_Sugerencias SHALL sugerir precios estimados para los ítems de proyección basándose en el historial de precios de compras anteriores.

### Requisito 9: KPIs y Métricas Financieras

**User Story:** Como administrador, quiero ver indicadores financieros clave, para monitorear la salud financiera del negocio.

#### Criterios de Aceptación

1. THE Sistema_Compras SHALL mostrar los siguientes KPIs: ventas del mes anterior, ventas del mes actual, total de sueldos, saldo disponible calculado, e historial de saldo.
2. THE API_Compras SHALL calcular el saldo disponible como: ventas_mes_anterior + ventas_mes_actual - sueldos - compras_mes_actual.
3. WHEN el usuario consulta el historial de saldo, THE API_Compras SHALL retornar los movimientos de capital de trabajo ordenados por fecha.

### Requisito 10: Actualizaciones en Tiempo Real

**User Story:** Como administrador, quiero recibir actualizaciones en tiempo real cuando se registra una compra, para mantener la información sincronizada entre usuarios.

#### Criterios de Aceptación

1. WHEN se registra una nueva compra, THE API_Compras SHALL emitir un evento a través del Canal_Compras vía Laravel Reverb con los datos de la compra registrada.
2. WHILE el Sistema_Compras está activo en el navegador, THE Sistema_Compras SHALL mantener una conexión WebSocket al Canal_Compras y escuchar eventos de nuevas compras.
3. WHEN el Sistema_Compras recibe un evento de nueva compra vía WebSocket, THE Sistema_Compras SHALL actualizar automáticamente el historial de compras, los datos de stock y los KPIs sin requerir recarga manual de la página.
4. IF la conexión WebSocket se pierde, THEN THE Sistema_Compras SHALL intentar reconectar automáticamente con backoff exponencial y mostrar un indicador visual del estado de conexión.

### Requisito 11: Serialización y Deserialización de Datos de Extracción

**User Story:** Como desarrollador, quiero que los datos extraídos por la IA se serialicen y deserialicen de forma consistente, para garantizar integridad en el flujo de datos.

#### Criterios de Aceptación

1. THE Extractor_IA SHALL serializar los datos extraídos en un formato JSON estructurado con campos: proveedor (string), rut_proveedor (string), items (array de objetos con nombre, cantidad, unidad, precio_unitario, subtotal), monto_neto (number), iva (number), monto_total (number), confianza (object con score por campo).
2. THE API_Compras SHALL deserializar el JSON de extracción y mapear los campos a las estructuras de formulario del Sistema_Compras.
3. FOR ALL datos de extracción válidos, serializar y luego deserializar SHALL producir un objeto equivalente al original (propiedad round-trip).
4. THE Extractor_IA SHALL formatear montos en pesos chilenos como números enteros (sin decimales) en la serialización.

### Requisito 12: Parseo de Texto de Ajuste Masivo de Stock

**User Story:** Como administrador, quiero pegar un texto con ajustes de stock en formato markdown y que el sistema lo interprete correctamente, para hacer ajustes rápidos.

#### Criterios de Aceptación

1. WHEN el usuario ingresa texto en formato markdown para ajuste masivo, THE Sistema_Compras SHALL parsear cada línea identificando: nombre del ítem y nueva cantidad de stock.
2. THE Sistema_Compras SHALL mostrar una previsualización del ajuste antes de aplicarlo, indicando: ítem, stock actual, nuevo stock propuesto, y diferencia.
3. FOR ALL textos de ajuste válidos, parsear el texto y luego formatearlo de vuelta SHALL producir un texto equivalente al original (propiedad round-trip).
4. IF una línea del texto de ajuste no puede ser parseada, THEN THE Sistema_Compras SHALL marcar esa línea como error y continuar procesando las líneas restantes.
