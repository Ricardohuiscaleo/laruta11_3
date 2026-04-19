# Documento de Requisitos: Mejora del Sistema de Categorías de Ingredientes

## Introducción

El sistema actual de gestión de stock en La Ruta 11 utiliza un campo de texto libre (`category` varchar 50) en la tabla `ingredients` para clasificar ítems. Esto genera inconsistencias, categorías vacías legacy, problemas de encoding, y un frontend en caja3 que solo muestra 2 tabs hardcodeados ("Ingredientes" y "Bebidas") en lugar de todas las categorías existentes. Además, cuando la IA (GeminiService) crea ingredientes nuevos desde boletas, no asigna categoría.

Esta mejora busca: corregir datos corruptos en BD, limpiar categorías legacy, actualizar el frontend de Stock para mostrar todas las categorías dinámicamente, y hacer que la IA infiera la categoría al crear ítems.

## Glosario

- **Sistema_Stock**: El módulo de gestión de inventario compartido entre caja3 (frontend) y mi3 (backend)
- **Frontend_Stock**: El componente ComprasApp.jsx en caja3 que muestra el estado del inventario con tabs de filtrado
- **API_Items**: El endpoint get_items_compra.php que retorna ingredientes y productos combinados
- **GeminiService**: El servicio de IA en mi3 backend que extrae ítems de imágenes de boletas/facturas
- **CompraService**: El servicio en mi3 backend que gestiona la creación de ingredientes y compras
- **Tabla_Ingredients**: La tabla `ingredients` en MySQL que almacena todos los ingredientes con su campo `category`
- **Categoría_Válida**: Una de las categorías activas del sistema: Carnes, Vegetales, Salsas, Condimentos, Panes, Embutidos, Pre-elaborados, Lácteos, Bebidas, Gas, Servicios, Packaging, Limpieza
- **Tab_Categoría**: Un botón de filtro en el Frontend_Stock que permite ver ítems de una categoría específica


## Requisitos

### Requisito 1: Corrección de encoding de categoría "Lácteos"

**User Story:** Como administrador, quiero que la categoría "Lácteos" se muestre correctamente en todo el sistema, para que no aparezca con caracteres corruptos (LÃ¡cteos).

#### Criterios de Aceptación

1. WHEN el Sistema_Stock carga ingredientes de la Tabla_Ingredients, THE Sistema_Stock SHALL mostrar la categoría como "Lácteos" con encoding UTF-8 correcto
2. THE Tabla_Ingredients SHALL almacenar el valor "Lácteos" con encoding UTF-8 válido para todos los registros que actualmente contienen "LÃ¡cteos"

### Requisito 2: Eliminación de categoría legacy vacía

**User Story:** Como administrador, quiero que la categoría vacía "Ingredientes" (0 ítems) sea eliminada del sistema, para que no genere confusión con categorías activas.

#### Criterios de Aceptación

1. THE Tabla_Ingredients SHALL contener cero registros con el valor de categoría "Ingredientes"
2. IF un ingrediente tiene la categoría "Ingredientes", THEN THE CompraService SHALL reasignar la categoría a una Categoría_Válida antes de guardar

### Requisito 3: Tabs dinámicos por categoría en Frontend Stock

**User Story:** Como cajero, quiero ver todas las categorías de ingredientes como tabs en la vista de Stock, para poder filtrar y revisar el inventario por cada categoría real del sistema.

#### Criterios de Aceptación

1. WHEN el Frontend_Stock se carga, THE Frontend_Stock SHALL mostrar un Tab_Categoría por cada categoría distinta presente en los datos retornados por la API_Items
2. WHEN el usuario selecciona un Tab_Categoría, THE Frontend_Stock SHALL filtrar la lista mostrando solo los ítems cuya categoría coincida con el tab seleccionado
3. THE Frontend_Stock SHALL mostrar un tab "Todos" que muestre todos los ítems sin filtro de categoría
4. THE Frontend_Stock SHALL mantener el tab "Bebidas" existente que filtra productos con subcategory_id en [10, 11, 27, 28]
5. WHEN la API_Items retorna una nueva categoría no vista previamente, THE Frontend_Stock SHALL generar un Tab_Categoría para esa categoría sin requerir cambios en el código
6. THE Frontend_Stock SHALL mostrar los tabs en un contenedor con scroll horizontal cuando el número de categorías exceda el ancho disponible de la pantalla

### Requisito 4: API retorna categorías disponibles

**User Story:** Como desarrollador, quiero que la API de ítems de compra incluya la lista de categorías únicas, para que el frontend pueda construir los tabs dinámicamente.

#### Criterios de Aceptación

1. WHEN se consulta la API_Items, THE API_Items SHALL retornar la lista de categorías únicas extraídas de los ingredientes activos en la Tabla_Ingredients
2. THE API_Items SHALL excluir categorías vacías (sin ingredientes activos asociados) de la lista de categorías retornada
3. THE API_Items SHALL retornar cada categoría con su nombre y la cantidad de ingredientes activos asociados

### Requisito 5: Inferencia de categoría por IA al crear ingredientes

**User Story:** Como administrador, quiero que cuando la IA crea un ingrediente nuevo desde una boleta, asigne automáticamente la categoría más probable, para que no queden ingredientes sin categorizar.

#### Criterios de Aceptación

1. WHEN el GeminiService extrae un ítem nuevo de una boleta, THE GeminiService SHALL incluir un campo `categoria_sugerida` en el resultado de extracción
2. THE GeminiService SHALL inferir la categoría basándose en el nombre del ítem y la lista de Categorías_Válidas del sistema
3. WHEN el CompraService crea un ingrediente nuevo, THE CompraService SHALL asignar la categoría sugerida por el GeminiService si está disponible
4. IF el GeminiService no puede inferir una categoría con confianza, THEN THE GeminiService SHALL asignar el valor "Sin categoría" como categoria_sugerida
5. WHEN se crea un ingrediente inline desde el frontend de mi3, THE CompraService SHALL recibir y aplicar la categoría proporcionada por el usuario o inferida por la IA

### Requisito 6: Validación de categorías al crear/editar ingredientes

**User Story:** Como administrador, quiero que el sistema valide las categorías al crear o editar ingredientes, para evitar categorías con typos o inconsistentes.

#### Criterios de Aceptación

1. WHEN se crea o actualiza un ingrediente via el StockController, THE StockController SHALL validar que la categoría proporcionada sea una Categoría_Válida existente en el sistema
2. IF se proporciona una categoría que no existe en la lista de Categorías_Válidas, THEN THE StockController SHALL rechazar la operación con un mensaje de error descriptivo
3. THE API_Items SHALL exponer un endpoint o incluir en su respuesta la lista de Categorías_Válidas para que los frontends puedan mostrar selectores en lugar de campos de texto libre