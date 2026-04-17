# Bugfix Requirements Document

## Introduction

El sistema de checklists de caja3 (POS) integrado con mi3-backend presenta 3 bugs en producción que afectan la operación diaria del food truck "La Ruta 11". Los problemas impactan la asignación correcta de trabajadores a checklists, el formato de moneda chilena en la verificación de caja, y la duplicación del análisis IA de fotos. Adicionalmente, se observa un "Invalid Date" en la vista de detalle del checklist en mi3-frontend.

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN el endpoint `GET /api/v1/public/checklists/today?rol=cajero` retorna los checklists del día THEN el sistema filtra solo por `rol` y `scheduled_date`, devolviendo TODOS los checklists de cajero para ese día — incluyendo los de workers que no son cajeras regulares (ej. Ricardo, que es administrador/seguridad y le fue asignado rol cajero temporalmente para testing). Esto resulta en que el checklist de Ricardo aparece junto al de Camila (la cajera real), y como el frontend usa `checklists.find(c => c.type === activeTab)` toma el PRIMERO encontrado — que es el de Ricardo, no el de Camila. Camila entonces ve y completa el checklist asignado a Ricardo, quedando su propio checklist en 0%. Desde caja3, `/checklist` es solo para cajeras y no debería mostrar checklists de otros workers.

1.2 WHEN el usuario ingresa un monto numérico en el campo de verificación de caja (ej. "29000") THEN el sistema envía el valor raw `29000` al backend pero el frontend NO formatea visualmente el valor como moneda chilena — el `<input type="number">` muestra "29000" sin formato, y cuando el resultado se muestra completado, `Number(item.cash_actual).toLocaleString('es-CL')` funciona correctamente para valores guardados en BD, pero el problema real es que el input `type="number"` no muestra formato de miles mientras el usuario escribe, lo que causa confusión (ej. el usuario ve "29" pensando que escribió $29.000 pero en realidad envió $29)

1.3 WHEN caja3 sube una foto para un ítem del checklist vía `POST /{id}/items/{itemId}/photo` THEN el frontend NO envía el parámetro `contexto` en el request — el controlador usa `$request->input('contexto', 'interior_apertura')` como default, lo que significa que TODAS las fotos (interior, exterior, plancha, etc.) se analizan con el prompt de `interior_apertura`, produciendo análisis IA idénticos o irrelevantes para fotos que no son del interior

1.4 WHEN mi3-frontend muestra el detalle de un checklist THEN aparece "Invalid Date" en la fecha, lo que sugiere que el campo `scheduled_date` (cast como `date` en el modelo Eloquent) se serializa como un objeto `Carbon` que el frontend no puede parsear correctamente como fecha legible

### Expected Behavior (Correct)

2.1 WHEN el endpoint `today` retorna checklists para `rol=cajero` THEN el sistema SHALL devolver únicamente el checklist de la cajera que realmente está trabajando el turno del día — Ricardo (administrador/seguridad) no debería tener checklists de cajero generados. El sistema debe filtrar en `crearChecklistsDiarios()` para que solo workers cuyo rol principal incluya `cajero` o `planchero` reciban checklists, excluyendo administradores/seguridad que tengan el rol de cajero agregado temporalmente. Si hay un solo checklist por tipo (apertura/cierre), el frontend lo mostrará correctamente.

2.2 WHEN el usuario ingresa un monto numérico en el campo de verificación de caja THEN el sistema SHALL formatear visualmente el valor como moneda chilena con separador de miles (puntos) y prefijo "$" mientras el usuario escribe — por ejemplo, al teclear "29000" el input debe mostrar "$29.000". El valor enviado al backend debe ser el número limpio (29000), sin formato.

2.3 WHEN caja3 sube una foto para un ítem del checklist THEN el frontend SHALL enviar el parámetro `contexto` correcto correspondiente al tipo de foto (ej. `interior_apertura`, `exterior_apertura`, `plancha_apertura`, etc.), de modo que el análisis IA use el prompt adecuado para cada tipo de foto y genere observaciones independientes y relevantes por foto.

2.4 WHEN mi3-frontend muestra el detalle de un checklist THEN el sistema SHALL mostrar la fecha en formato legible (ej. "15/04/2026" o "15 de abril de 2026"), sin mostrar "Invalid Date". El backend debe serializar `scheduled_date` como string en formato `Y-m-d` en las respuestas JSON.

### Unchanged Behavior (Regression Prevention)

3.1 WHEN un checklist con items pendientes se consulta vía el endpoint `today` THEN el sistema SHALL CONTINUE TO refrescar el `cash_expected` de items `cash_verification` no completados usando el último `saldo_nuevo` de `caja_movimientos`

3.2 WHEN un worker sube una foto para un ítem que requiere foto THEN el sistema SHALL CONTINUE TO comprimir la imagen en el frontend (max 800px, JPEG 0.8), subirla a S3 vía el backend, marcar el ítem como completado, y ejecutar el análisis IA de forma no bloqueante

3.3 WHEN un worker marca "Sí" en la verificación de caja (confirmando que el monto es correcto) THEN el sistema SHALL CONTINUE TO enviar `confirmed: true` con `actual_amount` igual al `cash_expected`, registrar resultado "ok", y notificar vía Telegram

3.4 WHEN los checklists de apertura y cierre se crean automáticamente para un día THEN el sistema SHALL CONTINUE TO leer los turnos del día, determinar el worker efectivo (considerando `reemplazado_por`), crear checklists por rol y tipo, y poblar los items desde los templates

3.5 WHEN un checklist ya está completado THEN el sistema SHALL CONTINUE TO mostrar el estado "Completado" con el porcentaje 100% y el ícono de check verde, sin permitir modificaciones adicionales

3.6 WHEN se detecta una discrepancia de caja THEN el sistema SHALL CONTINUE TO enviar notificaciones push y Telegram a los administradores con el detalle del monto esperado, real, y la diferencia
