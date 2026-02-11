# 🎖️ Sistema RL6 - Queries SQL Listas para Ejecutar

## 1️⃣ TABLA PRINCIPAL: Agregar Columnas a `usuarios`

```sql
ALTER TABLE usuarios
ADD COLUMN IF NOT EXISTS rut VARCHAR(12) NULL COMMENT 'RUT del militar (formato: 12345678-9)',
ADD COLUMN IF NOT EXISTS grado_militar VARCHAR(100) NULL COMMENT 'Grado militar (Ej: Cabo, Sargento, Teniente)',
ADD COLUMN IF NOT EXISTS unidad_trabajo VARCHAR(255) NULL COMMENT 'Unidad donde trabaja',
ADD COLUMN IF NOT EXISTS domicilio_particular TEXT NULL COMMENT 'Domicilio particular completo',
ADD COLUMN IF NOT EXISTS carnet_frontal_url TEXT NULL COMMENT 'URL imagen carnet frontal en S3',
ADD COLUMN IF NOT EXISTS carnet_trasero_url TEXT NULL COMMENT 'URL imagen carnet trasero en S3',
ADD COLUMN IF NOT EXISTS es_militar_rl6 TINYINT(1) DEFAULT 0 COMMENT 'Flag: usuario es militar RL6',
ADD COLUMN IF NOT EXISTS credito_aprobado TINYINT(1) DEFAULT 0 COMMENT 'Crédito aprobado (1/0)',
ADD COLUMN IF NOT EXISTS limite_credito DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Límite de crédito asignado',
ADD COLUMN IF NOT EXISTS credito_usado DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Crédito usado actualmente',
ADD COLUMN IF NOT EXISTS fecha_registro_rl6 TIMESTAMP NULL COMMENT 'Fecha de registro en sistema RL6';
```

---

## 2️⃣ TABLA DE HISTORIAL DE TRANSACCIONES: `rl6_credit_transactions`

Similar a `wallet_transactions` del sistema cashback.

```sql
CREATE TABLE IF NOT EXISTS rl6_credit_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL COMMENT 'Monto de la transacción',
    type ENUM('credit', 'debit') NOT NULL COMMENT 'credit=aprobación, debit=uso',
    description VARCHAR(255) COMMENT 'Descripción (ej: Compra en app, Aprobación admin)',
    order_id VARCHAR(50) NULL COMMENT 'ID de orden si aplica',
    saldo_anterior DECIMAL(10,2) COMMENT 'Saldo antes de la transacción',
    saldo_nuevo DECIMAL(10,2) COMMENT 'Saldo después de la transacción',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES usuarios(id),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
);
```

---

## 3️⃣ TABLA DE AUDITORÍA: `rl6_credit_audit`

Para trackear cambios realizados por admin.

```sql
CREATE TABLE IF NOT EXISTS rl6_credit_audit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT 'Usuario militar',
    admin_id INT NULL COMMENT 'Admin que realizó la acción',
    action VARCHAR(50) NOT NULL COMMENT 'approve, reject, update_limit, delete_user',
    limite_credito_anterior DECIMAL(10,2),
    limite_credito_nuevo DECIMAL(10,2),
    motivo_rechazo VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES usuarios(id),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
);
```

---

## 4️⃣ LÓGICA DE DESCUENTO DE CRÉDITO AL COMPRAR

### Validar saldo disponible ANTES de permitir compra:

```sql
-- Verificar si usuario tiene crédito disponible
SELECT 
    u.id,
    u.nombre,
    u.limite_credito,
    u.credito_usado,
    (u.limite_credito - u.credito_usado) as credito_disponible,
    CASE 
        WHEN (u.limite_credito - u.credito_usado) >= [MONTO_COMPRA] THEN 'OK'
        ELSE 'INSUFICIENTE'
    END as estado
FROM usuarios u
WHERE u.id = [USER_ID] 
AND u.es_militar_rl6 = 1 
AND u.credito_aprobado = 1;
```

### Descontar crédito DESPUÉS de compra exitosa:

```sql
-- Actualizar crédito usado
UPDATE usuarios 
SET credito_usado = credito_usado + [MONTO_COMPRA]
WHERE id = [USER_ID] 
AND es_militar_rl6 = 1;

-- Registrar transacción en historial
INSERT INTO rl6_credit_transactions (
    user_id, 
    amount, 
    type, 
    description, 
    order_id,
    saldo_anterior,
    saldo_nuevo
) VALUES (
    [USER_ID],
    [MONTO_COMPRA],
    'debit',
    'Compra en app - Orden #[ORDER_NUMBER]',
    [ORDER_ID],
    [SALDO_ANTERIOR],
    [SALDO_ANTERIOR] - [MONTO_COMPRA]
);
```

### Obtener saldo disponible actual:

```sql
SELECT 
    u.id,
    u.nombre,
    u.limite_credito,
    u.credito_usado,
    (u.limite_credito - u.credito_usado) as credito_disponible
FROM usuarios u
WHERE u.id = [USER_ID] 
AND u.es_militar_rl6 = 1;
```

---

## 5️⃣ LÓGICA DE APROBACIÓN DE CRÉDITO (Admin)

### Aprobar crédito:

```sql
-- Actualizar estado de aprobación
UPDATE usuarios 
SET 
    credito_aprobado = 1,
    limite_credito = [MONTO_ASIGNADO],
    credito_usado = 0
WHERE id = [USER_ID] 
AND es_militar_rl6 = 1;

-- Registrar en auditoría
INSERT INTO rl6_credit_audit (
    user_id,
    admin_id,
    action,
    limite_credito_anterior,
    limite_credito_nuevo
) VALUES (
    [USER_ID],
    [ADMIN_ID],
    'approve',
    0,
    [MONTO_ASIGNADO]
);

-- Registrar en historial de transacciones
INSERT INTO rl6_credit_transactions (
    user_id,
    amount,
    type,
    description,
    saldo_anterior,
    saldo_nuevo
) VALUES (
    [USER_ID],
    [MONTO_ASIGNADO],
    'credit',
    'Crédito aprobado por admin',
    0,
    [MONTO_ASIGNADO]
);
```

### Rechazar y eliminar usuario:

```sql
-- Registrar rechazo en auditoría
INSERT INTO rl6_credit_audit (
    user_id,
    admin_id,
    action,
    motivo_rechazo
) VALUES (
    [USER_ID],
    [ADMIN_ID],
    'reject',
    '[MOTIVO_RECHAZO]'
);

-- Eliminar usuario
DELETE FROM usuarios 
WHERE id = [USER_ID] 
AND es_militar_rl6 = 1 
AND credito_aprobado = 0;
```

---

## 6️⃣ INTEGRACIÓN CON TABLA `tuu_orders`

Cuando se registra una compra con crédito RL6:

```sql
-- Agregar columna a tuu_orders si no existe
ALTER TABLE tuu_orders 
ADD COLUMN IF NOT EXISTS pagado_con_credito_rl6 TINYINT(1) DEFAULT 0 COMMENT 'Pagado con crédito RL6',
ADD COLUMN IF NOT EXISTS monto_credito_rl6 DECIMAL(10,2) DEFAULT 0 COMMENT 'Monto pagado con crédito RL6';

-- Registrar orden con crédito RL6
INSERT INTO tuu_orders (
    user_id,
    order_number,
    amount,
    pagado_con_credito_rl6,
    monto_credito_rl6,
    status,
    created_at
) VALUES (
    [USER_ID],
    [ORDER_NUMBER],
    [TOTAL_AMOUNT],
    1,
    [MONTO_CREDITO_USADO],
    'completed',
    NOW()
);
```

---

## 7️⃣ QUERIES ÚTILES PARA ADMIN

### Ver todos los militares registrados:

```sql
SELECT 
    u.id,
    u.nombre,
    u.email,
    u.rut,
    u.grado_militar,
    u.unidad_trabajo,
    u.credito_aprobado,
    u.limite_credito,
    u.credito_usado,
    (u.limite_credito - u.credito_usado) as credito_disponible,
    u.fecha_registro_rl6
FROM usuarios u
WHERE u.es_militar_rl6 = 1
ORDER BY u.fecha_registro_rl6 DESC;
```

### Ver militares pendientes de aprobación:

```sql
SELECT 
    u.id,
    u.nombre,
    u.email,
    u.rut,
    u.grado_militar,
    u.unidad_trabajo,
    u.carnet_frontal_url,
    u.carnet_trasero_url,
    u.fecha_registro_rl6
FROM usuarios u
WHERE u.es_militar_rl6 = 1 
AND u.credito_aprobado = 0
ORDER BY u.fecha_registro_rl6 ASC;
```

### Ver historial de transacciones de un militar:

```sql
SELECT 
    t.id,
    t.amount,
    t.type,
    t.description,
    t.order_id,
    t.saldo_anterior,
    t.saldo_nuevo,
    t.created_at
FROM rl6_credit_transactions t
WHERE t.user_id = [USER_ID]
ORDER BY t.created_at DESC;
```

### Ver auditoría de cambios:

```sql
SELECT 
    a.id,
    a.user_id,
    a.admin_id,
    a.action,
    a.limite_credito_anterior,
    a.limite_credito_nuevo,
    a.motivo_rechazo,
    a.created_at
FROM rl6_credit_audit a
WHERE a.user_id = [USER_ID]
ORDER BY a.created_at DESC;
```

### Dashboard: Resumen de créditos RL6:

```sql
SELECT 
    COUNT(*) as total_militares,
    SUM(CASE WHEN credito_aprobado = 1 THEN 1 ELSE 0 END) as aprobados,
    SUM(CASE WHEN credito_aprobado = 0 THEN 1 ELSE 0 END) as pendientes,
    SUM(CASE WHEN credito_aprobado = 1 THEN limite_credito ELSE 0 END) as credito_total_asignado,
    SUM(CASE WHEN credito_aprobado = 1 THEN credito_usado ELSE 0 END) as credito_total_usado,
    SUM(CASE WHEN credito_aprobado = 1 THEN (limite_credito - credito_usado) ELSE 0 END) as credito_total_disponible
FROM usuarios
WHERE es_militar_rl6 = 1;
```

---

## 8️⃣ PLAN PARA ADMIN (caja.laruta11.cl)

**Esto se desarrollará en el proyecto hermano `caja.laruta11.cl`**

### Tablas necesarias en admin:
- ✅ `rl6_credit_transactions` - Historial de transacciones
- ✅ `rl6_credit_audit` - Auditoría de cambios

### Funcionalidades a implementar en `/admin/militares-rl6`:
1. Listar militares pendientes de aprobación
2. Ver carnets (frontal/trasero)
3. Aprobar/rechazar con límite de crédito
4. Ver historial de transacciones
5. Ver auditoría de cambios
6. Buscar por RUT/nombre
7. Filtrar por estado (aprobado/pendiente/rechazado)

### Autenticación:
- Usar sistema admin existente de `caja.laruta11.cl`
- Roles: super_admin, gerente (a definir)

---

## 9️⃣ PESTAÑA "CRÉDITO" EN APP (app.laruta11.cl)

**Ubicación**: Perfil → Pestaña "Crédito" (solo para militares RL6)

### Mostrar:
```sql
SELECT 
    u.limite_credito,
    u.credito_usado,
    (u.limite_credito - u.credito_usado) as credito_disponible,
    u.credito_aprobado
FROM usuarios u
WHERE u.id = [USER_ID] 
AND u.es_militar_rl6 = 1;
```

### Historial de movimientos:
```sql
SELECT 
    t.amount,
    t.type,
    t.description,
    t.saldo_nuevo,
    t.created_at
FROM rl6_credit_transactions t
WHERE t.user_id = [USER_ID]
ORDER BY t.created_at DESC
LIMIT 20;
```

---

## 🔟 RESUMEN DE EJECUCIÓN

### Paso 1: Ejecutar en BD
```bash
# Copiar y pegar en phpMyAdmin o MySQL CLI
# 1. Agregar columnas a usuarios
# 2. Crear tabla rl6_credit_transactions
# 3. Crear tabla rl6_credit_audit
```

### Paso 2: Desarrollo en app.laruta11.cl
- Página `/rl6.astro` - Registro
- APIs de registro y perfil
- Pestaña "Crédito" en perfil

### Paso 3: Desarrollo en caja.laruta11.cl
- Panel `/admin/militares-rl6`
- Aprobación de créditos
- Gestión de límites

### Paso 4: Integración en checkout
- Validar saldo disponible
- Descontar crédito al comprar
- Registrar en `tuu_orders`

---

**Estado**: ✅ Queries listas para copiar/pegar
