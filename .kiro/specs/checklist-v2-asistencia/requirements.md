# Documento de Requerimientos — Checklist v2 + Asistencia

## Introducción

Migración y rediseño del sistema de checklists operacionales desde caja3 (PHP) hacia mi3 (Laravel 11 + Next.js 14) para La Ruta 11. El nuevo sistema reduce los ítems de 22 a 11 (50%), separa tareas por rol (cajero/planchero), incorpora análisis de fotos con IA (Amazon Nova Lite vía Bedrock), vincula la asistencia al completado de checklists, y automatiza descuentos por inasistencia ($40.000 CLP). Incluye un checklist virtual para trabajadores cuyo compañero no asistió.

## Glosario

- **Sistema_Checklist**: Módulo backend (Laravel) que gestiona la creación, asignación y completado de checklists diarios
- **App_Mi3**: Aplicación frontend (Next.js 14) donde los trabajadores interactúan con sus checklists
- **Analizador_IA**: Servicio que envía fotos a Amazon Nova Lite (Bedrock) y retorna puntaje + observaciones
- **Motor_Asistencia**: Componente que determina asistencia/inasistencia basándose en el completado de checklists
- **Scheduler**: Laravel Scheduler que ejecuta tareas programadas (creación diaria de checklists, detección de inasistencias)
- **Cajero**: Trabajador con rol "cajero" que opera la caja del food truck
- **Planchero**: Trabajador con rol "planchero" que opera la cocina del food truck
- **Turno**: Registro en la tabla `turnos` que asigna un trabajador a una fecha de trabajo
- **Checklist_Presencial**: Checklist completo (apertura + cierre) que un trabajador completa cuando asiste al food truck
- **Checklist_Virtual**: Checklist de 1 paso que completa un trabajador cuando su compañero de turno no asistió
- **Ajuste_Inasistencia**: Registro negativo en `ajustes_sueldo` con categoría "inasistencia" por $40.000 CLP
- **Panel_Admin**: Vistas del administrador en mi3 para supervisar checklists, asistencia y análisis de IA

## Requerimientos

### Requerimiento 1: Creación diaria de checklists por rol

**User Story:** Como administrador, quiero que el sistema cree automáticamente los checklists diarios separados por rol, para que cada trabajador vea solo las tareas que le corresponden.

#### Criterios de Aceptación

1. WHEN el Scheduler ejecuta la tarea diaria de creación, THE Sistema_Checklist SHALL crear checklists de apertura y cierre para cada rol (cajero, planchero) asignado a un turno en esa fecha
2. WHEN se crea un checklist de apertura para el rol cajero, THE Sistema_Checklist SHALL incluir exactamente 3 ítems: (1) "Encender PedidosYa + verificar carga TUU", (2) "Verificar saldo en caja", (3) "📸 FOTO interior" con requires_photo=true
3. WHEN se crea un checklist de apertura para el rol planchero, THE Sistema_Checklist SHALL incluir exactamente 3 ítems: (1) "Sacar aderezos, vitrina, basureros, televisor", (2) "Llenar jugos + salsas + preparar delivery", (3) "📸 FOTO exterior" con requires_photo=true
4. WHEN se crea un checklist de cierre para el rol cajero, THE Sistema_Checklist SHALL incluir exactamente 2 ítems: (1) "Apagar PedidosYa + verificar saldo final", (2) "📸 FOTO interior" con requires_photo=true
5. WHEN se crea un checklist de cierre para el rol planchero, THE Sistema_Checklist SHALL incluir exactamente 3 ítems: (1) "Guardar todo + limpiar superficies", (2) "Desconectar gas + agua + equipos", (3) "📸 FOTO exterior" con requires_photo=true
6. WHEN ya existe un checklist para un rol, tipo y fecha dados, THE Sistema_Checklist SHALL omitir la creación duplicada sin generar error
7. WHEN un trabajador no tiene turno asignado en una fecha, THE Sistema_Checklist SHALL omitir la creación de checklists para ese trabajador en esa fecha

### Requerimiento 2: Visualización y completado de checklist presencial

**User Story:** Como trabajador (cajero o planchero), quiero ver y completar mi checklist del día en mi3, para cumplir con mis tareas operacionales de apertura y cierre.

#### Criterios de Aceptación

1. WHEN un trabajador con turno asignado accede a la sección de checklist en App_Mi3, THE App_Mi3 SHALL mostrar los checklists pendientes (apertura y/o cierre) filtrados por el rol del trabajador
2. WHEN un trabajador marca un ítem como completado, THE Sistema_Checklist SHALL registrar el timestamp de completado y actualizar el porcentaje de progreso del checklist
3. WHEN un trabajador completa todos los ítems de un checklist, THE Sistema_Checklist SHALL marcar el checklist como "completed" y registrar la hora de finalización
4. WHILE un checklist tiene ítems pendientes, THE App_Mi3 SHALL mostrar una barra de progreso con el porcentaje de completado y la cantidad de ítems restantes
5. WHEN un trabajador no tiene turno asignado para el día actual, THE App_Mi3 SHALL mostrar un mensaje indicando que no tiene checklists pendientes
6. WHEN un ítem requiere foto (requires_photo=true), THE App_Mi3 SHALL impedir marcar el ítem como completado hasta que se suba una foto

### Requerimiento 3: Subida de fotos y análisis con IA

**User Story:** Como administrador, quiero que las fotos del checklist sean analizadas automáticamente por IA, para detectar problemas de limpieza y orden sin revisión manual.

#### Criterios de Aceptación

1. WHEN un trabajador sube una foto para un ítem de checklist, THE Sistema_Checklist SHALL almacenar la foto en S3 (bucket laruta11-images, path checklist/YYYY/MM/) y guardar la URL en el registro del ítem
2. WHEN una foto se almacena exitosamente en S3, THE Analizador_IA SHALL enviar la foto a Amazon Nova Lite (Bedrock) con un prompt específico según el tipo de foto (interior/exterior, apertura/cierre)
3. WHEN el Analizador_IA recibe la respuesta de Nova Lite, THE Sistema_Checklist SHALL almacenar el puntaje (0-100), las observaciones textuales y el timestamp del análisis en el registro del ítem
4. IF el servicio de Bedrock no responde dentro de 15 segundos, THEN THE Analizador_IA SHALL registrar el timeout, marcar el análisis como "pendiente" y permitir que el trabajador continúe con el checklist
5. IF la subida a S3 falla, THEN THE App_Mi3 SHALL mostrar un mensaje de error y permitir reintentar la subida

### Requerimiento 4: Sistema de asistencia vinculado a checklist

**User Story:** Como administrador, quiero que la asistencia se determine automáticamente por el completado de checklists, para eliminar el registro manual de asistencia.

#### Criterios de Aceptación

1. THE Motor_Asistencia SHALL determinar asistencia basándose exclusivamente en el completado de checklists: un trabajador con turno asignado que completa al menos el checklist de apertura se considera presente
2. WHEN el Scheduler ejecuta la detección de inasistencias al final del día, THE Motor_Asistencia SHALL marcar como ausente a todo trabajador que tenía turno asignado y no completó ningún checklist (ni presencial ni virtual)
3. WHEN el Motor_Asistencia detecta una inasistencia, THE Sistema_Checklist SHALL crear un registro de Ajuste_Inasistencia en `ajustes_sueldo` con monto -40000, categoría "inasistencia" y concepto descriptivo incluyendo la fecha
4. WHEN un trabajador completa un Checklist_Virtual, THE Motor_Asistencia SHALL registrar asistencia para ese trabajador sin descuento, independientemente de que no haya asistido físicamente al food truck
5. WHEN un trabajador tiene turno como reemplazante (campo reemplazado_por en tabla turnos), THE Motor_Asistencia SHALL aplicar las mismas reglas de asistencia que para un turno titular

### Requerimiento 5: Checklist virtual por ausencia de compañero

**User Story:** Como trabajador cuyo compañero no asistió, quiero completar un checklist virtual para confirmar mi disponibilidad y aportar ideas de mejora, para que no se me descuente y mi día sea productivo.

#### Criterios de Aceptación

1. WHEN el Motor_Asistencia detecta que el compañero de turno de un trabajador no asistió (no completó checklist de apertura antes de la hora límite), THE Sistema_Checklist SHALL habilitar un Checklist_Virtual para el trabajador afectado
2. WHEN se habilita un Checklist_Virtual, THE App_Mi3 SHALL mostrar un único paso con el texto: "Al marcar este checklist confirmo que no asistiré a foodtruck porque mi compañero/a no asistirá este día. No se me descontará. No obstante estaré a disposición de otras tareas."
3. THE App_Mi3 SHALL requerir un campo de texto obligatorio con el label: "Para completar este checklist, indica ideas de cómo mejorar nuestros servicios actuales (preparación nueva, procedimiento nuevo, oportunidad de mejora)" con un mínimo de 20 caracteres
4. WHEN un trabajador completa el Checklist_Virtual, THE Sistema_Checklist SHALL almacenar la idea de mejora en un campo dedicado y marcar el checklist virtual como completado con timestamp
5. IF un trabajador no completa el Checklist_Virtual antes del fin del día, THEN THE Motor_Asistencia SHALL tratar al trabajador como ausente y aplicar el Ajuste_Inasistencia de $40.000

### Requerimiento 6: Detección de compañero ausente

**User Story:** Como sistema, necesito detectar automáticamente cuándo un compañero de turno no asistió, para habilitar el checklist virtual al trabajador afectado.

#### Criterios de Aceptación

1. THE Motor_Asistencia SHALL definir un turno como un par de trabajadores (1 cajero + 1 planchero) asignados a la misma fecha en la tabla `turnos`
2. WHEN la hora límite de apertura pasa y uno de los dos trabajadores del turno no ha iniciado su checklist de apertura, THE Motor_Asistencia SHALL considerar a ese trabajador como "ausente temprano"
3. WHEN un trabajador es marcado como "ausente temprano", THE Sistema_Checklist SHALL habilitar el Checklist_Virtual para su compañero de turno dentro de los 30 minutos siguientes a la hora límite
4. IF ambos trabajadores del turno no inician su checklist de apertura antes de la hora límite, THEN THE Motor_Asistencia SHALL marcar a ambos como ausentes sin habilitar checklist virtual para ninguno

### Requerimiento 7: Panel de administrador para checklists y asistencia

**User Story:** Como administrador (Ricardo), quiero ver todos los checklists, resultados de IA y registros de asistencia, para supervisar las operaciones diarias del food truck.

#### Criterios de Aceptación

1. WHEN el administrador accede al Panel_Admin de checklists, THE App_Mi3 SHALL mostrar una lista de checklists del día con estado (pendiente, activo, completado, perdido), progreso y trabajador asignado
2. WHEN el administrador selecciona un checklist completado, THE App_Mi3 SHALL mostrar el detalle de cada ítem incluyendo: descripción, hora de completado, foto (si aplica) y resultado del análisis de IA (puntaje y observaciones)
3. WHEN el administrador accede a la vista de asistencia, THE App_Mi3 SHALL mostrar un resumen mensual con: días trabajados, inasistencias, checklists virtuales completados y monto total de descuentos por trabajador
4. WHEN el administrador filtra por fecha, THE Panel_Admin SHALL mostrar los checklists y registros de asistencia correspondientes a la fecha seleccionada
5. THE Panel_Admin SHALL mostrar las ideas de mejora recopiladas de los Checklists_Virtuales, ordenadas por fecha descendente, con el nombre del trabajador que las aportó

### Requerimiento 8: Migración de datos y cronjob desde caja3

**User Story:** Como administrador, quiero que el sistema de checklists migre completamente de caja3 a mi3, para centralizar la gestión de trabajadores en una sola aplicación.

#### Criterios de Aceptación

1. THE Sistema_Checklist SHALL reutilizar las tablas existentes `checklists` y `checklist_items` de la base de datos `laruta11`, agregando las columnas nuevas necesarias (personal_id, rol, ai_score, ai_observations, ai_analyzed_at, improvement_idea, checklist_type)
2. WHEN se ejecuta la migración de esquema, THE Sistema_Checklist SHALL preservar los datos históricos existentes en las tablas `checklists` y `checklist_items` sin modificarlos ni eliminarlos
3. THE Scheduler SHALL reemplazar el cronjob de caja3 (`createDaily()`) con un comando Artisan de Laravel que cree los checklists diarios según la nueva estructura por rol
4. WHEN se completa la migración, THE Sistema_Checklist SHALL crear una nueva categoría "inasistencia" en `ajustes_categorias` con slug "inasistencia", icono "❌" y signo_defecto "-"

### Requerimiento 9: Navegación y acceso en mi3

**User Story:** Como trabajador, quiero acceder fácilmente a mis checklists desde la navegación de mi3, para completar mis tareas sin fricciones.

#### Criterios de Aceptación

1. THE App_Mi3 SHALL agregar un ítem "Checklist" en la navegación secundaria del trabajador, con ícono ClipboardCheck, que dirija a `/dashboard/checklist`
2. WHEN un trabajador tiene checklists pendientes para el día, THE App_Mi3 SHALL mostrar un indicador visual (badge) en el ítem de navegación "Checklist"
3. THE App_Mi3 SHALL agregar un ítem "Checklists" en la navegación del administrador que dirija a `/admin/checklists`
4. WHEN el administrador accede a `/admin/checklists`, THE App_Mi3 SHALL mostrar la vista del Panel_Admin descrita en el Requerimiento 7

### Requerimiento 10: Notificaciones de checklist y asistencia

**User Story:** Como trabajador, quiero recibir notificaciones sobre mis checklists pendientes y el estado de asistencia, para no olvidar completar mis tareas.

#### Criterios de Aceptación

1. WHEN el Scheduler crea los checklists diarios, THE Sistema_Checklist SHALL enviar una notificación push al trabajador asignado indicando que tiene checklists pendientes
2. WHEN se habilita un Checklist_Virtual para un trabajador, THE Sistema_Checklist SHALL enviar una notificación push informando que su compañero no asistió y que debe completar el checklist virtual
3. WHEN el Motor_Asistencia registra una inasistencia con descuento, THE Sistema_Checklist SHALL enviar una notificación push al trabajador ausente informando el descuento de $40.000
4. WHEN el Motor_Asistencia registra una inasistencia, THE Sistema_Checklist SHALL crear una notificación in-app en `notificaciones_mi3` para el administrador con los detalles de la inasistencia
