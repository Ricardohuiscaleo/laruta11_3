# Documento de Requerimientos — Crédito R11

## Introducción

Sistema de crédito para trabajadores de La Ruta 11 y personas de confianza (no militares), que replica la lógica del sistema RL6 existente para militares. El crédito R11 permite a los beneficiarios comprar productos a crédito con cobro mensual por descuento de sueldo el día 1 de cada mes. El sistema abarca la app del cliente (app3), el POS/caja (caja3), y procesos automáticos de cobro y bloqueo.

## Glosario

- **Sistema_App3**: Aplicación web del cliente en app.laruta11.cl
- **Sistema_Caja3**: Aplicación POS/cajero en caja.laruta11.cl
- **Sistema_Crédito_R11**: Módulo de crédito para trabajadores de La Ruta 11 y personas de confianza
- **Beneficiario_R11**: Usuario con `es_credito_r11 = 1` que puede acceder al crédito R11
- **Administrador**: Usuario con rol admin en caja3 que gestiona créditos
- **Crédito_Disponible**: Resultado de `limite_credito_r11 - credito_r11_usado`
- **Tabla_Usuarios**: Tabla `usuarios` de la base de datos MySQL compartida
- **Tabla_R11_Transactions**: Tabla `r11_credit_transactions` para registro de transacciones de crédito R11
- **Tabla_Tuu_Orders**: Tabla `tuu_orders` para órdenes de pago
- **Relación_R11**: Tipo de vínculo del beneficiario: 'trabajador', 'familiar' o 'confianza'
- **Prefijo_R11C**: Prefijo `R11C-` usado en números de orden de pagos de crédito R11
- **ArqueoApp**: Módulo de arqueo de caja en caja3 que consolida ventas por método de pago

## Requerimientos

### Requerimiento 1: Estructura de Base de Datos R11

**User Story:** Como Administrador, quiero que existan campos y tablas dedicados al crédito R11 en la base de datos, para que el sistema pueda gestionar créditos de trabajadores de forma independiente al sistema RL6.

#### Criterios de Aceptación

1. THE Sistema_Crédito_R11 SHALL agregar los campos `es_credito_r11` (TINYINT DEFAULT 0), `credito_r11_aprobado` (TINYINT DEFAULT 0), `limite_credito_r11` (DECIMAL 10,2 DEFAULT 0.00), `credito_r11_usado` (DECIMAL 10,2 DEFAULT 0.00), `credito_r11_bloqueado` (TINYINT DEFAULT 0), `fecha_aprobacion_r11` (TIMESTAMP NULL), `fecha_ultimo_pago_r11` (DATE NULL) y `relacion_r11` (VARCHAR 100 NULL) a la Tabla_Usuarios.
2. THE Sistema_Crédito_R11 SHALL crear la Tabla_R11_Transactions con columnas: `id` (PK AUTO_INCREMENT), `user_id` (INT NOT NULL FK → usuarios), `amount` (DECIMAL 10,2 NOT NULL), `type` (ENUM 'credit','debit','refund' NOT NULL), `description` (VARCHAR 255), `order_id` (VARCHAR 50), `created_at` (TIMESTAMP DEFAULT CURRENT_TIMESTAMP).
3. THE Sistema_Crédito_R11 SHALL agregar los campos `pagado_con_credito_r11` (TINYINT DEFAULT 0) y `monto_credito_r11` (DECIMAL 10,2 DEFAULT 0.00) a la Tabla_Tuu_Orders.
4. THE Sistema_Crédito_R11 SHALL agregar el valor `r11_credit` al ENUM del campo `payment_method` de la Tabla_Tuu_Orders.

### Requerimiento 2: API de Consulta de Crédito R11

**User Story:** Como Beneficiario_R11, quiero consultar mi información de crédito R11, para que pueda ver mi límite, saldo usado, crédito disponible e historial de transacciones.

#### Criterios de Aceptación

1. WHEN un Beneficiario_R11 solicita su información de crédito, THE Sistema_App3 SHALL retornar el límite de crédito, crédito usado, Crédito_Disponible, relación R11 y fecha de aprobación del Beneficiario_R11.
2. WHEN un Beneficiario_R11 solicita su información de crédito, THE Sistema_App3 SHALL retornar las últimas 20 transacciones de la Tabla_R11_Transactions ordenadas por fecha descendente.
3. WHEN un usuario sin `es_credito_r11 = 1` o sin `credito_r11_aprobado = 1` solicita información de crédito R11, THE Sistema_App3 SHALL retornar un error indicando que el usuario no tiene crédito R11 aprobado.

### Requerimiento 3: Uso de Crédito R11 en Compras

**User Story:** Como Beneficiario_R11, quiero pagar mis compras con crédito R11, para que el monto se descuente de mi cupo disponible.

#### Criterios de Aceptación

1. WHEN un Beneficiario_R11 selecciona `r11_credit` como método de pago en el checkout, THE Sistema_App3 SHALL validar que `es_credito_r11 = 1`, `credito_r11_aprobado = 1` y `credito_r11_bloqueado = 0`.
2. WHEN un Beneficiario_R11 realiza una compra con crédito R11, THE Sistema_App3 SHALL validar que la suma de `credito_r11_usado` más el monto de la compra sea menor o igual a `limite_credito_r11`.
3. IF el Crédito_Disponible del Beneficiario_R11 es menor al monto de la compra, THEN THE Sistema_App3 SHALL retornar un error indicando crédito insuficiente junto con el Crédito_Disponible actual y el monto solicitado.
4. WHEN una compra con crédito R11 es aprobada, THE Sistema_App3 SHALL insertar un registro de tipo 'debit' en la Tabla_R11_Transactions con el monto, user_id, order_id y descripción de la compra.
5. WHEN una compra con crédito R11 es aprobada, THE Sistema_App3 SHALL incrementar el campo `credito_r11_usado` del Beneficiario_R11 en el monto de la compra.
6. WHEN una compra con crédito R11 es aprobada, THE Sistema_App3 SHALL marcar la orden en Tabla_Tuu_Orders con `pagado_con_credito_r11 = 1`, `monto_credito_r11` igual al monto, `payment_method = 'r11_credit'` y `payment_status = 'paid'`.
7. WHEN una compra con crédito R11 es aprobada, THE Sistema_App3 SHALL descontar el inventario de ingredientes y productos de forma inmediata dentro de la misma transacción de base de datos.
8. IF el campo `credito_r11_bloqueado` del Beneficiario_R11 es igual a 1, THEN THE Sistema_App3 SHALL rechazar la compra con un mensaje indicando que el crédito está bloqueado por falta de pago.

### Requerimiento 4: Estado de Cuenta R11

**User Story:** Como Beneficiario_R11, quiero ver mi estado de cuenta detallado, para que pueda revisar todas mis compras y pagos del período actual.

#### Criterios de Aceptación

1. WHEN un Beneficiario_R11 accede a su estado de cuenta, THE Sistema_App3 SHALL mostrar el resumen con límite de crédito, crédito usado, Crédito_Disponible y relación R11.
2. WHEN un Beneficiario_R11 accede a su estado de cuenta, THE Sistema_App3 SHALL listar todas las transacciones del período actual (mes en curso) con fecha, tipo, monto y descripción.
3. THE Sistema_App3 SHALL proveer la página `app3/src/pages/r11.astro` para mostrar el estado de cuenta del crédito R11.

### Requerimiento 5: Pago de Crédito R11

**User Story:** Como Beneficiario_R11, quiero poder pagar mi saldo de crédito R11, para que mi cupo se restablezca.

#### Criterios de Aceptación

1. WHEN un Beneficiario_R11 inicia un pago de crédito R11 vía Webpay, THE Sistema_App3 SHALL crear una orden en Tabla_Tuu_Orders con prefijo Prefijo_R11C, monto igual a `credito_r11_usado` y redirigir al usuario a la pasarela de pago TUU/Webpay.
2. WHEN el callback de pago TUU confirma `payment_status = 'paid'` y `tuu_message = 'Transaccion aprobada'`, THE Sistema_App3 SHALL insertar un registro de tipo 'refund' en la Tabla_R11_Transactions con el monto pagado.
3. WHEN el callback de pago TUU confirma un pago exitoso, THE Sistema_App3 SHALL actualizar `credito_r11_usado = 0`, `fecha_ultimo_pago_r11 = CURDATE()` y `credito_r11_bloqueado = 0` del Beneficiario_R11.
4. WHEN el callback de pago TUU confirma un pago exitoso, THE Sistema_App3 SHALL verificar que no exista un refund previo para la misma orden antes de procesar, para evitar duplicados.
5. WHEN el callback de pago TUU confirma un pago exitoso, THE Sistema_App3 SHALL enviar un email de confirmación de pago al Beneficiario_R11.
6. THE Sistema_App3 SHALL proveer la página `app3/src/pages/pagar-credito-r11.astro` para el flujo de pago del crédito R11.

### Requerimiento 6: Anulación de Pedidos con Crédito R11

**User Story:** Como Administrador, quiero que al anular un pedido pagado con crédito R11 se reintegre el crédito al beneficiario, para que el cupo del Beneficiario_R11 se restaure correctamente.

#### Criterios de Aceptación

1. WHEN un Administrador anula un pedido con `payment_method = 'r11_credit'`, THE Sistema_Caja3 SHALL insertar un registro de tipo 'refund' en la Tabla_R11_Transactions con el monto de la orden, user_id y descripción del motivo.
2. WHEN un Administrador anula un pedido con crédito R11, THE Sistema_Caja3 SHALL decrementar el campo `credito_r11_usado` del Beneficiario_R11 en el monto de la orden anulada.
3. WHEN un Administrador anula un pedido con crédito R11, THE Sistema_Caja3 SHALL marcar la orden como `order_status = 'cancelled'` y `payment_status = 'unpaid'`.
4. WHEN un Administrador anula un pedido con crédito R11, THE Sistema_Caja3 SHALL restaurar el inventario de ingredientes y productos asociados a la orden, registrando transacciones de tipo 'return' en `inventory_transactions`.

### Requerimiento 7: Administración de Créditos R11

**User Story:** Como Administrador, quiero gestionar los créditos R11 desde caja3, para que pueda aprobar, rechazar y consultar beneficiarios del sistema de crédito R11.

#### Criterios de Aceptación

1. WHEN un Administrador accede a la vista de créditos R11, THE Sistema_Caja3 SHALL listar todos los usuarios con `es_credito_r11 = 1`, mostrando nombre, email, teléfono, relación R11, estado de aprobación, límite de crédito, crédito usado y Crédito_Disponible.
2. WHEN un Administrador aprueba un crédito R11, THE Sistema_Caja3 SHALL actualizar `credito_r11_aprobado = 1`, asignar el `limite_credito_r11` indicado y registrar `fecha_aprobacion_r11 = NOW()` en la Tabla_Usuarios.
3. WHEN un Administrador rechaza un crédito R11, THE Sistema_Caja3 SHALL actualizar `credito_r11_aprobado = 0` y `limite_credito_r11 = 0` en la Tabla_Usuarios.
4. WHEN un Administrador filtra por estado 'pending', THE Sistema_Caja3 SHALL retornar solo usuarios con `es_credito_r11 = 1` y `credito_r11_aprobado = 0`.
5. WHEN un Administrador filtra por estado 'approved', THE Sistema_Caja3 SHALL retornar solo usuarios con `es_credito_r11 = 1` y `credito_r11_aprobado = 1`.

### Requerimiento 8: Pago Manual de Crédito R11

**User Story:** Como Administrador, quiero procesar pagos manuales de crédito R11 (efectivo o transferencia), para que pueda registrar descuentos de sueldo y otros pagos presenciales.

#### Criterios de Aceptación

1. WHEN un Administrador procesa un pago manual R11, THE Sistema_Caja3 SHALL insertar un registro de tipo 'refund' en la Tabla_R11_Transactions con el monto, método de pago y notas.
2. WHEN un Administrador procesa un pago manual R11, THE Sistema_Caja3 SHALL decrementar `credito_r11_usado` del Beneficiario_R11 en el monto pagado (sin bajar de 0) y actualizar `fecha_ultimo_pago_r11 = CURDATE()`.
3. WHEN un Administrador procesa un pago manual R11, THE Sistema_Caja3 SHALL crear una orden en Tabla_Tuu_Orders con prefijo `R11C-MANUAL-`, `payment_status = 'paid'` y `order_status = 'completed'` para que aparezca en estadísticas.
4. WHEN un Administrador procesa un pago manual R11, THE Sistema_Caja3 SHALL enviar un email de confirmación de pago al Beneficiario_R11 con el detalle del monto, método y estado actualizado del crédito.

### Requerimiento 9: Cobro Mensual y Bloqueo Automático R11

**User Story:** Como Administrador, quiero que el sistema gestione automáticamente los recordatorios de cobro y bloqueos por falta de pago, para que el proceso de descuento de sueldo sea ordenado y los morosos queden bloqueados.

#### Criterios de Aceptación

1. WHEN llega el día 28 del mes, THE Sistema_Crédito_R11 SHALL ejecutar un cron job que genere un reporte de todos los Beneficiario_R11 con `credito_r11_usado > 0`, incluyendo nombre, monto adeudado y relación R11.
2. WHEN llega el día 28 del mes, THE Sistema_Crédito_R11 SHALL enviar un email recordatorio a cada Beneficiario_R11 con saldo pendiente, indicando el monto a descontar y la fecha de descuento (día 1).
3. WHEN llega el día 28 del mes, THE Sistema_Crédito_R11 SHALL enviar un email resumen al Administrador con la lista completa de deudores R11 y el monto total a cobrar.
4. WHEN llega el día 2 del mes y un Beneficiario_R11 tiene `credito_r11_usado > 0` y `fecha_ultimo_pago_r11` anterior al día 1 del mes actual, THE Sistema_Crédito_R11 SHALL actualizar `credito_r11_bloqueado = 1` para ese Beneficiario_R11.
5. WHEN un Beneficiario_R11 es bloqueado por falta de pago, THE Sistema_Crédito_R11 SHALL enviar un email de aviso de bloqueo al Beneficiario_R11 indicando que su crédito ha sido suspendido hasta que regularice su pago.

### Requerimiento 10: Bloqueo Automático RL6 (Pendiente)

**User Story:** Como Administrador, quiero que el sistema RL6 existente implemente el bloqueo automático por falta de pago el día 22, para que los militares morosos no puedan seguir usando crédito.

#### Criterios de Aceptación

1. WHEN llega el día 22 del mes y un usuario militar tiene `credito_usado > 0` y `fecha_ultimo_pago` anterior al día 21 del mes actual, THE Sistema_App3 SHALL actualizar `credito_bloqueado = 1` para ese usuario.
2. WHEN un usuario militar es bloqueado por falta de pago, THE Sistema_App3 SHALL enviar un email de aviso de bloqueo al usuario indicando que su crédito RL6 ha sido suspendido.
3. WHEN llega el día 18 del mes, THE Sistema_App3 SHALL enviar un email recordatorio de pago a cada usuario militar con `credito_usado > 0`, indicando el monto pendiente y la fecha límite (día 21).
4. WHEN un usuario militar con `credito_bloqueado = 1` intenta usar crédito RL6 en el checkout, THE Sistema_App3 SHALL rechazar la compra con un mensaje indicando que el crédito está bloqueado por falta de pago.

### Requerimiento 11: Frontend Crédito R11 en App3

**User Story:** Como Beneficiario_R11, quiero tener una sección dedicada de Crédito R11 en la app del cliente, para que pueda ver mi crédito, estado de cuenta y realizar pagos.

#### Criterios de Aceptación

1. THE Sistema_App3 SHALL mostrar una pestaña o sección "Crédito R11" visible solo para usuarios con `es_credito_r11 = 1`.
2. WHEN un Beneficiario_R11 accede a la sección Crédito R11, THE Sistema_App3 SHALL mostrar tarjetas con el límite de crédito, crédito usado, Crédito_Disponible y relación R11.
3. WHEN un Beneficiario_R11 tiene `credito_r11_usado > 0`, THE Sistema_App3 SHALL mostrar un botón "Pagar Crédito" que redirija a la página de pago `pagar-credito-r11.astro`.
4. WHEN un Beneficiario_R11 tiene `credito_r11_bloqueado = 1`, THE Sistema_App3 SHALL mostrar un banner de alerta indicando que el crédito está bloqueado por falta de pago.
5. WHEN un Beneficiario_R11 accede al checkout con productos en el carrito, THE Sistema_App3 SHALL mostrar `r11_credit` como opción de método de pago si el usuario tiene crédito R11 aprobado y no bloqueado.

### Requerimiento 12: Frontend Crédito R11 en Caja3

**User Story:** Como Administrador, quiero gestionar los créditos R11 desde la interfaz de caja3, para que pueda administrar beneficiarios, procesar pagos y ver reportes.

#### Criterios de Aceptación

1. THE Sistema_Caja3 SHALL proveer una vista "Créditos R11" en el panel de administración, separada de la vista "Militares RL6".
2. WHEN un Administrador accede a la vista Créditos R11, THE Sistema_Caja3 SHALL mostrar una tabla con todos los Beneficiario_R11 y sus datos de crédito (nombre, relación, límite, usado, disponible, estado).
3. WHEN un Administrador selecciona un Beneficiario_R11, THE Sistema_Caja3 SHALL mostrar opciones para aprobar crédito (con monto de límite), rechazar crédito y procesar pago manual.
4. THE Sistema_Caja3 SHALL integrar los montos de crédito R11 en el ArqueoApp, mostrando el total de ventas pagadas con `r11_credit` como línea separada en el resumen de métodos de pago.
5. WHEN un Administrador visualiza un pedido pagado con crédito R11 en MiniComandas, THE Sistema_Caja3 SHALL mostrar una etiqueta "Crédito R11" y permitir la anulación con reintegro automático del crédito.

### Requerimiento 13: Registro de Beneficiario R11 desde Admin

**User Story:** Como Administrador, quiero registrar nuevos beneficiarios de crédito R11 directamente desde el admin, para que pueda dar de alta trabajadores rápidamente.

#### Criterios de Aceptación

1. WHEN un Administrador registra un nuevo Beneficiario_R11, THE Sistema_Caja3 SHALL actualizar `es_credito_r11 = 1` y asignar la `relacion_r11` ('trabajador', 'familiar' o 'confianza') en la Tabla_Usuarios.
2. WHEN un Administrador registra un nuevo Beneficiario_R11 que no existe en la Tabla_Usuarios, THE Sistema_Caja3 SHALL crear el registro del usuario con los datos básicos (nombre, teléfono, email) y marcarlo como `es_credito_r11 = 1`.
3. WHEN un Administrador registra un Beneficiario_R11, THE Sistema_Caja3 SHALL permitir aprobar el crédito y asignar el límite en el mismo paso de registro.

### Requerimiento 14: Registro Público de Trabajador R11

**User Story:** Como trabajador de La Ruta 11, quiero registrarme como beneficiario R11 desde la app del cliente, para que pueda solicitar crédito de forma rápida y al mismo tiempo quedar registrado como trabajador para la app de RRHH (mi3).

#### Criterios de Aceptación

1. THE Sistema_App3 SHALL proveer la página `app3/src/pages/r11.astro` con un formulario de registro R11 que requiera login previo (cuenta en La Ruta 11).
2. WHEN un usuario no logueado accede a la página de registro R11, THE Sistema_App3 SHALL mostrar un modal indicando que debe registrarse o iniciar sesión primero en La Ruta 11.
3. WHEN un usuario logueado accede al formulario de registro R11, THE Sistema_App3 SHALL pre-llenar el nombre y email del usuario desde su cuenta existente.
4. THE formulario de registro R11 SHALL incluir un lector de código QR que use la cámara del dispositivo para escanear el QR del carnet de identidad chileno, el cual contiene una URL del formato `https://portal.sidiv.registrocivil.cl/docstatus?RUN={rut}&type=CEDULA&serial={serial}&mrz={mrz}`.
5. WHEN el lector QR escanea exitosamente un código, THE Sistema_App3 SHALL extraer el RUT del parámetro `RUN` de la URL escaneada y mostrarlo al usuario como confirmación.
6. WHEN el lector QR escanea una URL que no corresponde al dominio `portal.sidiv.registrocivil.cl` o no contiene el parámetro `RUN`, THE Sistema_App3 SHALL mostrar un error indicando que el QR no es válido y solicitar que escanee el QR de su carnet de identidad.
7. THE formulario de registro R11 SHALL solicitar además del escaneo QR: una selfie (foto de rostro) y un selector de rol (Planchero/a, Cajero/a, Rider, Otro).
8. WHEN el usuario sube la selfie, THE Sistema_App3 SHALL subir la imagen a AWS S3 en la carpeta `carnets-trabajadores/`.
9. WHEN el usuario envía el formulario de registro R11, THE Sistema_App3 SHALL actualizar la Tabla_Usuarios con `es_credito_r11 = 1`, `rut` (extraído del QR), `selfie_url` y `relacion_r11` con el rol seleccionado.
10. WHEN el usuario envía el formulario de registro R11, THE Sistema_App3 SHALL crear o actualizar un registro en la tabla `personal` con los datos del usuario (nombre, teléfono, email, rut, rol) para vincularlo con el sistema de RRHH (mi3).
11. WHEN el usuario envía el formulario de registro R11, THE Sistema_App3 SHALL enviar una notificación por Telegram al Administrador con los datos del solicitante, selfie, RUT y botones de aprobación rápida (similar al flujo RL6).
12. WHEN el Administrador aprueba la solicitud vía Telegram, THE Sistema_App3 SHALL actualizar `credito_r11_aprobado = 1` y asignar el `limite_credito_r11` seleccionado.
13. THE Sistema_App3 SHALL aplicar rate limiting al formulario de registro R11 (máximo 5 intentos por IP en 10 minutos).
