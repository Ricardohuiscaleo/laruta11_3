# Documento de Requisitos — Verificación de Efectivo en Checklist + Test IA + Training

## Introducción

Este spec cubre tres funcionalidades relacionadas al sistema de checklists de mi3:

1. **Verificación de Caja Interactiva**: Bloque interactivo para el cajero que muestra el saldo esperado, pide confirmación sí/no, y notifica discrepancias al admin via `@laruta11_bot`.
2. **Tab "Test IA"**: Nueva pestaña en el admin de checklists para probar los prompts de IA con fotos reales y ver scores/observaciones en tiempo real.
3. **Sistema de Training para Fotos**: Feedback loop que permite al admin corregir evaluaciones de IA y mejorar los prompts automáticamente basándose en correcciones acumuladas.

## Glosario

- **Sistema_Checklist**: El sistema de checklists diarios de mi3 que gestiona ítems de apertura y cierre para cajeros y plancheros.
- **Ítem_Verificación_Caja**: Un tipo especial de ítem dentro del checklist que presenta una interfaz interactiva de verificación de efectivo en lugar de un checkbox estándar.
- **Saldo_Esperado**: El valor del campo `saldo_nuevo` del último registro de la tabla `caja_movimientos`, que representa el saldo teórico de la caja.
- **Cajero**: Trabajador con rol "cajero" en la tabla `personal`, responsable de operar la caja registradora.
- **Administrador**: Trabajador con rol "administrador" en la tabla `personal`, que recibe notificaciones de discrepancias.
- **Bot_LaRuta11**: Bot de Telegram `@laruta11_bot` usado para notificaciones operacionales del negocio. Token en env var `TELEGRAM_TOKEN` de caja3.
- **Grupo_Pedidos_11**: Grupo de Telegram configurado como destino de mensajes operacionales via `@laruta11_bot`.
- **Discrepancia**: La diferencia entre el Saldo_Esperado y el monto real contado por el Cajero. Puede ser sobrante (monto real > esperado) o faltante (monto real < esperado).
- **Servicio_Notificaciones**: Conjunto de servicios que incluye `TelegramService` (via `@laruta11_bot`), `NotificacionMi3` y `PushNotificationService` para enviar mensajes y alertas.
- **PhotoAnalysisService**: Servicio que evalúa fotos de checklist usando IA (AWS Bedrock) con prompts específicos por contexto (plancha, lavaplatos, interior, exterior).
- **Training_Dataset_Fotos**: Tabla que almacena evaluaciones de IA con correcciones del admin para mejorar los prompts automáticamente.

## Requisitos

### Requisito 1: Tipo especial de ítem en checklist_templates

**Historia de Usuario:** Como administrador del sistema, quiero definir un tipo especial de ítem en las plantillas de checklist, para que el sistema genere automáticamente ítems de verificación de caja al crear los checklists diarios.

#### Criterios de Aceptación

1. THE Sistema_Checklist SHALL soportar un campo `item_type` en la tabla `checklist_templates` con valores posibles "standard" (por defecto) y "cash_verification".
2. WHEN el comando `mi3:create-daily-checklists` crea un checklist diario, THE Sistema_Checklist SHALL copiar el valor de `item_type` desde la plantilla al ítem creado en `checklist_items`.
3. THE Sistema_Checklist SHALL soportar un campo `item_type` en la tabla `checklist_items` con valores posibles "standard" (por defecto) y "cash_verification".

### Requisito 2: Consulta del saldo esperado de caja

**Historia de Usuario:** Como cajero, quiero ver el saldo esperado de caja al momento de verificar, para saber cuánto dinero debería haber en la caja.

#### Criterios de Aceptación

1. WHEN el Cajero abre un checklist que contiene un Ítem_Verificación_Caja, THE Sistema_Checklist SHALL consultar el Saldo_Esperado ejecutando `SELECT saldo_nuevo FROM caja_movimientos ORDER BY id DESC LIMIT 1`.
2. WHEN la tabla `caja_movimientos` no contiene registros, THE Sistema_Checklist SHALL mostrar el Saldo_Esperado como $0.
3. THE Sistema_Checklist SHALL devolver el Saldo_Esperado en la respuesta de la API junto con los datos del ítem de verificación de caja.

### Requisito 3: Interfaz interactiva de verificación de caja

**Historia de Usuario:** Como cajero, quiero una tarjeta interactiva que me pregunte si el efectivo en caja coincide con el saldo esperado, para confirmar o reportar discrepancias de forma rápida.

#### Criterios de Aceptación

1. WHEN el frontend renderiza un Ítem_Verificación_Caja, THE Sistema_Checklist SHALL mostrar una tarjeta con el texto "¿En caja hay $X?" donde X es el Saldo_Esperado formateado en pesos chilenos.
2. THE Sistema_Checklist SHALL mostrar dos botones en la tarjeta: "Sí" y "No".
3. WHEN el Cajero presiona "No", THE Sistema_Checklist SHALL mostrar un campo de entrada numérico para que el Cajero ingrese el monto real en caja.
4. WHEN el campo de monto real es visible, THE Sistema_Checklist SHALL mostrar la Discrepancia calculada (monto real menos Saldo_Esperado) indicando si es sobrante o faltante.

### Requisito 4: Confirmación de saldo correcto

**Historia de Usuario:** Como cajero, quiero confirmar que el saldo en caja es correcto para completar el ítem de verificación y notificar al grupo.

#### Criterios de Aceptación

1. WHEN el Cajero presiona "Sí" en el Ítem_Verificación_Caja, THE Sistema_Checklist SHALL marcar el ítem como completado registrando `is_completed = true` y `completed_at` con la fecha y hora actual.
2. WHEN el Cajero confirma el saldo correcto, THE Servicio_Notificaciones SHALL enviar un mensaje via Bot_LaRuta11 al Grupo_Pedidos_11 con el formato: "✅ Caja verificada por [nombre cajero] — Saldo: $X — [Apertura/Cierre] [fecha]".
3. WHEN el Cajero confirma el saldo correcto, THE Sistema_Checklist SHALL almacenar los datos de verificación: saldo esperado, monto verificado (igual al esperado), y resultado "ok" en el Ítem_Verificación_Caja.

### Requisito 5: Reporte de discrepancia

**Historia de Usuario:** Como cajero, quiero reportar una discrepancia cuando el efectivo en caja no coincide con el saldo esperado, para que el administrador sea notificado.

#### Criterios de Aceptación

1. WHEN el Cajero ingresa el monto real y confirma la discrepancia, THE Sistema_Checklist SHALL marcar el ítem como completado registrando `is_completed = true` y `completed_at` con la fecha y hora actual.
2. WHEN el Cajero reporta una discrepancia, THE Sistema_Checklist SHALL almacenar los datos de verificación: saldo esperado, monto real ingresado, diferencia calculada, y resultado "discrepancia" en el Ítem_Verificación_Caja.
3. WHEN el Cajero reporta una discrepancia, THE Servicio_Notificaciones SHALL crear una notificación en `notificaciones_mi3` para cada Administrador activo con tipo "discrepancia_caja", título "Discrepancia de Caja", y mensaje con los detalles: nombre del cajero, saldo esperado, monto real, diferencia (sobrante o faltante), tipo de checklist (apertura/cierre), y fecha.
4. WHEN el Cajero reporta una discrepancia, THE Servicio_Notificaciones SHALL enviar una push notification a cada Administrador activo con el título "⚠️ Discrepancia de Caja" y el detalle de la diferencia.
5. WHEN el Cajero reporta una discrepancia, THE Servicio_Notificaciones SHALL enviar un mensaje via Bot_LaRuta11 al Grupo_Pedidos_11 con el formato: "⚠️ Discrepancia de caja — [nombre cajero] — Esperado: $X — Real: $Y — Diferencia: $Z ([sobrante/faltante]) — [Apertura/Cierre] [fecha]".

### Requisito 6: Almacenamiento de datos de verificación

**Historia de Usuario:** Como administrador, quiero que los datos de cada verificación de caja queden registrados, para poder auditar el historial de verificaciones.

#### Criterios de Aceptación

1. THE Sistema_Checklist SHALL almacenar en la tabla `checklist_items` los campos adicionales para ítems de tipo "cash_verification": `cash_expected` (decimal 10,2), `cash_actual` (decimal 10,2), `cash_difference` (decimal 10,2), y `cash_result` (enum: ok, discrepancia).
2. WHEN un Ítem_Verificación_Caja es completado, THE Sistema_Checklist SHALL registrar todos los campos de verificación de caja de forma atómica junto con la marca de completado.

### Requisito 7: Integración con el flujo existente de checklist

**Historia de Usuario:** Como cajero, quiero que el ítem de verificación de caja se integre naturalmente con el flujo del checklist existente, para no tener que aprender un proceso diferente.

#### Criterios de Aceptación

1. WHEN un Ítem_Verificación_Caja es completado, THE Sistema_Checklist SHALL actualizar el progreso del checklist (completed_items, completion_percentage, status) de la misma forma que un ítem estándar.
2. THE Sistema_Checklist SHALL permitir que el Ítem_Verificación_Caja coexista con ítems estándar y con ítems que requieren foto dentro del mismo checklist.
3. WHILE un Ítem_Verificación_Caja no ha sido completado, THE Sistema_Checklist SHALL impedir que el checklist sea marcado como completado si el ítem de verificación de caja es obligatorio.

### Requisito 8: Tab "Test IA" en admin de checklists

**Historia de Usuario:** Como administrador, quiero una pestaña para revisar las evaluaciones de IA sobre fotos reales de checklists, dar feedback, probar prompts, y entrenar el sistema, todo desde una interfaz visual.

#### Criterios de Aceptación

1. THE Sistema_Checklist SHALL agregar una pestaña "Test IA" después de "Ideas" en la página admin de checklists (`/admin/checklists`).
2. WHEN el Administrador abre la tab "Test IA", THE Sistema_Checklist SHALL mostrar una lista de fotos recientes de checklists completados (tanto de mi3 como del sistema legacy caja3), agrupadas por contexto (plancha, lavaplatos+mesón, interior, exterior), con thumbnail, score IA (si existe), observaciones, fecha, y nombre del trabajador. Las fotos legacy (sin `ai_score`) se muestran como "Sin evaluar" y pueden ser evaluadas on-demand.
3. THE Sistema_Checklist SHALL mostrar cada foto con botones de feedback: "✅ Correcto" y "❌ Incorrecto". Si el admin marca "Incorrecto", SHALL mostrar un campo para escribir qué debería haber dicho la IA y un slider para indicar el score correcto.
4. THE tab "Test IA" SHALL incluir un selector de contexto (dropdown con todos los contextos disponibles desde `checklist_ai_prompts`) para filtrar las fotos por tipo.
5. THE tab "Test IA" SHALL incluir una sección "Probar prompt" donde el admin puede: (a) subir una foto nueva desde el dispositivo, (b) seleccionar una foto existente de la biblioteca interna (fotos de checklists legacy y actuales almacenadas en S3), elegir un contexto, y ver el resultado de la IA en tiempo real (score + observaciones + prompt usado).
6. WHEN el admin selecciona una foto legacy (sin `ai_score`) para evaluar, THE Sistema_Checklist SHALL ejecutar el PhotoAnalysisService con el contexto seleccionado y guardar el resultado como si fuera una evaluación nueva, permitiendo dar feedback inmediato.
7. THE tab "Test IA" SHALL mostrar un resumen de tareas IA pendientes por contexto: problemas activos, mejorados, y escalados (del Requisito 10).
8. WHEN se ejecuta la migración inicial, THE Sistema_Checklist SHALL insertar los prompts base actuales de `PhotoAnalysisService::PROMPTS` en la tabla `checklist_ai_prompts` como versión 1 activa para cada contexto.

### Requisito 9: Sistema de training y feedback para fotos de checklist

**Historia de Usuario:** Como administrador, quiero que las correcciones que hago a las evaluaciones de IA se usen para mejorar los prompts automáticamente, para que la IA sea cada vez más precisa.

#### Criterios de Aceptación

1. THE Sistema_Checklist SHALL almacenar cada evaluación de foto en una tabla `checklist_ai_training` con campos: `checklist_item_id`, `photo_url`, `contexto`, `ai_score`, `ai_observations`, `admin_feedback` (enum: correct, incorrect, null), `admin_notes` (text), `prompt_used` (text), `created_at`.
2. WHEN el Administrador marca una evaluación como "incorrecta" con notas, THE Sistema_Checklist SHALL almacenar el feedback en `checklist_ai_training` como dato de entrenamiento.
3. WHEN el PhotoAnalysisService genera un prompt para evaluar una foto, THE Sistema_Checklist SHALL inyectar al final del prompt un bloque "ANTECEDENTES DE CORRECCIONES PREVIAS" con las últimas 5 correcciones del admin para ese mismo contexto, incluyendo: qué dijo la IA, qué debería haber dicho según el admin, y el score original vs el esperado.
4. THE Sistema_Checklist SHALL recalcular la precisión del prompt por contexto: `(evaluaciones correctas / total evaluaciones con feedback) * 100`. Si la precisión cae bajo 70%, THE Sistema_Checklist SHALL marcar el contexto como "necesita revisión" en la tab Test IA.

### Requisito 10: Seguimiento inteligente de tareas generadas por IA

**Historia de Usuario:** Como administrador, quiero que la IA recuerde los problemas detectados en fotos anteriores y verifique si se corrigieron en la siguiente foto, para tener un sistema de mejora continua real.

#### Criterios de Aceptación

1. WHEN el PhotoAnalysisService detecta un problema en una foto (score < 70 o observación con ⚠️/🚨), THE Sistema_Checklist SHALL registrar el problema como "tarea pendiente IA" en una tabla `checklist_ai_tasks` con campos: `contexto`, `problema_detectado`, `foto_url_origen`, `checklist_item_id_origen`, `status` (pendiente, mejorado, no_mejorado, escalado), `created_at`.
2. WHEN el PhotoAnalysisService evalúa la siguiente foto del mismo contexto (siguiente turno/día), THE Sistema_Checklist SHALL inyectar en el prompt: "EN LA FOTO ANTERIOR SE DETECTARON ESTOS PROBLEMAS: [lista]. Verifica si fueron corregidos y reporta explícitamente cuáles mejoraron ✅ y cuáles persisten ⚠️."
3. WHEN la IA reporta que un problema persiste (no mejoró), THE Sistema_Checklist SHALL actualizar la tarea a status "no_mejorado" e incrementar un contador `veces_detectado`.
4. WHEN una tarea tiene `veces_detectado >= 3` (problema persiste 3 turnos consecutivos), THE Sistema_Checklist SHALL escalar: enviar notificación push al Administrador con título "🚨 Problema recurrente" y detalle del problema, y enviar mensaje via Bot_LaRuta11 al Grupo_Pedidos_11.
5. WHEN la IA reporta que un problema fue corregido, THE Sistema_Checklist SHALL actualizar la tarea a status "mejorado" y registrar la foto que confirma la mejora.
6. THE Sistema_Checklist SHALL mostrar en la tab "Test IA" un resumen de tareas pendientes por contexto: cuántos problemas activos, cuántos mejorados, cuántos escalados.

### Requisito 11: Prompts dinámicos desde BD con auto-mejora

**Historia de Usuario:** Como administrador, quiero que los prompts de evaluación de fotos se almacenen en la base de datos y se mejoren automáticamente basándose en el feedback acumulado, para no depender de cambios de código para calibrar la IA.

#### Criterios de Aceptación

1. THE Sistema_Checklist SHALL almacenar los prompts de evaluación en una tabla `checklist_ai_prompts` con campos: `id`, `contexto` (unique, ej: plancha_apertura), `prompt_base` (text), `prompt_version` (int, auto-increment por contexto), `is_active` (boolean), `created_at`, `updated_at`.
2. WHEN el PhotoAnalysisService necesita un prompt para evaluar una foto, THE Sistema_Checklist SHALL obtener el prompt activo desde `checklist_ai_prompts` WHERE `contexto = X` AND `is_active = true`, en lugar de leer la constante `PROMPTS` hardcodeada en PHP.
3. THE Sistema_Checklist SHALL migrar los prompts actuales de `PhotoAnalysisService::PROMPTS` a la tabla `checklist_ai_prompts` como versión 1 de cada contexto.
4. WHEN el Administrador edita un prompt desde la tab "Test IA", THE Sistema_Checklist SHALL crear una nueva versión del prompt (incrementar `prompt_version`), marcar la anterior como `is_active = false`, y activar la nueva.
5. THE Sistema_Checklist SHALL mantener historial de todas las versiones de prompts para poder revertir a una versión anterior si la nueva tiene peor precisión.
6. WHEN la tabla `checklist_ai_training` acumula 10 o más correcciones para un contexto, THE Sistema_Checklist SHALL generar automáticamente un prompt mejorado: enviar a la IA el prompt actual + las correcciones acumuladas + instrucción "Reescribe este prompt incorporando las correcciones del administrador para mejorar la precisión", guardar el resultado como nueva versión candidata con `is_active = false`.
7. WHEN se genera un prompt candidato, THE Sistema_Checklist SHALL mostrarlo en la tab "Test IA" con un botón "Activar" para que el Administrador lo revise y active manualmente. No se activa automáticamente.
8. THE tab "Test IA" SHALL mostrar para cada contexto: prompt activo actual, versión, precisión (% de evaluaciones correctas), cantidad de correcciones pendientes, y prompt candidato si existe.
