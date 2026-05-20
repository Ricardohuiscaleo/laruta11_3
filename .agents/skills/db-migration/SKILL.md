# Skill: Migraciones de Base de Datos

## Descripción
Especialista en migraciones y modificaciones de esquema MySQL para el proyecto La Ruta 11.

## Cuándo usar
- Crear nuevas tablas
- Modificar estructura existente
- Agregar índices
- Migrar datos
- Crear vistas
- Optimizar queries lentas

## Stack
- MySQL 8
- Beekeeper Studio (ejecución en producción)
- PHP PDO (para scripts de migración)

## Reglas

### Preferencias
1. **SQL scripts sobre PHP**: Preferir archivos `.sql` sobre scripts PHP para migraciones
2. **Beekeeper Studio**: Ejecutar en producción via Beekeeper, no local PHP
3. **Minimal changes**: Cambios mínimos posibles
4. **No breaking changes**: Nunca romper funcionalidad existente
5. **Backup siempre**: Hacer backup antes de migraciones en producción

### Patrones

#### Crear Tabla
```sql
CREATE TABLE IF NOT EXISTS nueva_tabla (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Agregar Columna
```sql
ALTER TABLE tabla_existente
ADD COLUMN nueva_columna VARCHAR(100) NULL AFTER columna_existente;
```

#### Crear Índice
```sql
CREATE INDEX idx_nombre ON tabla_existente(columna);
-- o compuesto
CREATE INDEX idx_compuesto ON tabla_existente(col1, col2);
```

#### Migrar Datos
```sql
-- Siempre en transacción
START TRANSACTION;

-- Insertar datos migrados
INSERT INTO nueva_tabla (col1, col2)
SELECT col1, col2 FROM tabla_vieja WHERE condicion;

-- Verificar
SELECT COUNT(*) FROM nueva_tabla;

COMMIT;
-- ROLLBACK; -- si algo falla
```

### Conexión
```php
// Patrón correcto (5 niveles de búsqueda)
$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php',
    __DIR__ . '/../../../../../config.php'
];
foreach ($config_paths as $path) {
    if (file_exists($path)) { $config = require_once $path; break; }
}
$pdo = new PDO("mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4", 
               $config['app_db_user'], $config['app_db_pass']);
```

## Tablas Principales (65+)

### Core
- `productos`, `categorias`, `subcategorias`
- `ingredientes`, `recetas`
- `combos`, `combo_items`, `combo_groups`

### Ventas
- `ventas`, `ventas_items`, `ventas_customizations`
- `tuu_orders`, `tuu_pagos_online`

### Usuarios
- `usuarios` (clientes), `cashiers` (cajeros), `admin_users`
- `personal` (mi3 RRHH)

### Compras
- `compras`, `compra_items`
- `proveedores`
- `extraction_feedback`

### Checklists
- `checklist_templates`, `checklist_items`
- `checklist_photos`, `photo_analysis`

### Inventario
- `inventory_transactions`
- `mermas`

### Finanzas
- `caja_movimientos`, `caja_historial`
- `adelantos`, `pagos_nomina`

## Errores Comunes
1. **Usar `ruta11_db_*`**: Siempre usar `app_db_*`
2. **No verificar existencia**: Usar `IF NOT EXISTS`
3. **Charset incorrecto**: Siempre `utf8mb4`
4. **Foreign keys sin índice**: Crear índice antes de FK
5. **Migrar sin backup**: Siempre backup primero

## Testing
- Probar en local primero
- Verificar con datos de prueba
- Revisar performance con EXPLAIN
- Validar constraints e integridad referencial
