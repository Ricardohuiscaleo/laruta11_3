# La Ruta 11 - Key Insights

## Database Structure
- **Production Database**: MySQL with different schema than local development
- **Category IDs (Production)**:
  - Hamburguesas: category_id=3, subcategory_id=6
  - Hamburguesas 100g: category_id=3, subcategory_id=5
  - Churrascos: category_id=2
  - Completos: category_id=4
  - Papas: category_id=12, subcategory_id=57
  - Pizzas: category_id=5, subcategory_id=60
  - Bebidas/Snacks: category_id=5 (categoría padre "Snacks"), subcategory_ids=[10,11,27,28] (Jugos, Bebidas, Café, Té)
  - **IMPORTANTE**: El filtro de bebidas en ComprasApp usa subcategory_id, NO category_id
  - Combos: category_id=8

## Development Workflow
- User executes SQL scripts in Beekeeper Studio (not local PHP)
- Prefer SQL scripts over PHP migration scripts
- Minimal code changes preferred
- Avoid breaking existing functionality

## System Architecture
- 100% database-driven menu system
- No hardcoded category definitions in frontend
- API parses JSON on server side (no JSON.parse in frontend)
- Dynamic category filtering based on `is_active` flag

## caja3 vs app3 Differences
- **Session Storage**: caja3 uses `cajaUser` (cashier) in localStorage, app3 uses `user` (customer)
- **Category Names**: Both accept 'combos' and 'Combos' (case-insensitive) for combo modal
- **Checkout Fields**: caja3 fields always editable (cashiers enter customer data), app3 pre-fills from user profile
- **Track Usage**: Removed from caja3 (was causing 404 errors), still active in app3
- **User Logging**: caja3 silences "No hay usuario logueado" logs (expected behavior for cashiers)

## Discount System
- **Database Columns**: `discount_10`, `discount_30`, `discount_birthday`, `discount_pizza` in `tuu_orders` table
- **Display Format**: Show descriptive text ("10% descuento") not just percentage
- **10% Discount**: Only appears when "Retiro" (pickup) delivery type is selected
- **Color Coding**: Pizza discount uses purple, others use orange/yellow
- **RL6 Orders**: Filtered out from comandas and notifications (order_number NOT LIKE 'RL6-%')

## caja3 API - Database Credentials
- **SIEMPRE usar `app_db_*`** para conectar a la BD principal en APIs de caja3
- `ruta11_db_*` existe en config.php pero sus variables de entorno (`RUTA11_DB_*`) NO están configuradas en el servidor → retorna vacío → error mysqli
- `app_db_*` usa variables `APP_DB_*` que SÍ están configuradas en el servidor
- **Config path**: caja3 busca config.php con foreach de 5 niveles (`__DIR__.'/../config.php'` hasta `__DIR__.'/../../../../../config.php'`)
- El config.php real en servidor está en la raíz del dist (`/var/www/html/config.php`), encontrado con `../../config.php` desde `api/subcarpeta/`
- **Patrón correcto** (igual que `get_compras.php` y otros APIs que funcionan):
  ```php
  $config_paths = [
      __DIR__ . '/../config.php',
      __DIR__ . '/../../config.php',
      __DIR__ . '/../../../config.php',
      __DIR__ . '/../../../../config.php',
      __DIR__ . '/../../../../../config.php'
  ];
  $config = null;
  foreach ($config_paths as $path) {
      if (file_exists($path)) { $config = require_once $path; break; }
  }
  // Conexión:
  $pdo = new PDO("mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4", $config['app_db_user'], $config['app_db_pass']);
  ```

## Chilean Localization
- **Number Format**: Use `toLocaleString('es-CL')` with dot (.) as thousands separator
- **Currency**: Always show $ symbol before amount
- **Date Format**: "21 de enero, 2024" format for Spanish dates
