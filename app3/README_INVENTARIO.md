# 📱 Sistema de Control de Inventarios Móvil - Ruta 11

## 🎯 Descripción General

Aplicación web móvil independiente para el control de inventarios de productos e ingredientes del restaurante La Ruta 11. Diseñada específicamente para uso en dispositivos móviles con interfaz táctil optimizada.

## 🔗 Acceso

**URL:** `https://app.laruta11.cl/inventario/`

**Credenciales:**
- **Usuario:** `inventario`
- **Contraseña:** `Inv3nt4r10R11@2025`

## 🏗️ Arquitectura del Sistema

### **Frontend**
- **Framework:** React 18 (ESM modules)
- **Estilos:** Tailwind CSS
- **Ubicación:** `/src/pages/inventario/index.astro`
- **Tipo:** Single Page Application (SPA)

### **Backend APIs**
- **Lenguaje:** PHP
- **Base de datos:** MySQL (`u958525313_app`)
- **Autenticación:** Token-based con localStorage
- **Ubicación:** `/api/`

## 📊 Base de Datos

### **Configuración**
```php
'app_db_host' => 'localhost',
'app_db_name' => 'u958525313_app',
'app_db_user' => 'u958525313_app',
'app_db_pass' => 'wEzho0-hujzoz-cevzin'
```

### **Tablas Utilizadas**

#### **Tabla: `products`**
```sql
- id (int, PRIMARY KEY, AUTO_INCREMENT)
- name (varchar(150))
- price (decimal(10,2))
- stock_quantity (int) -- Campo principal para inventario
- min_stock_level (int, default: 5)
- image_url (text)
- is_active (tinyint(1), default: 1)
```

#### **Tabla: `ingredients`**
```sql
- id (int, PRIMARY KEY, AUTO_INCREMENT)
- name (varchar(100))
- unit (varchar(20)) -- Unidad de medida
- cost_per_unit (decimal(10,2))
- current_stock (decimal(10,2)) -- Campo principal para inventario
- min_stock_level (decimal(10,2), default: 1.00)
- is_active (tinyint(1), default: 1)
```

## 🔐 Sistema de Autenticación

### **Credenciales de Configuración**
Las credenciales se almacenan en `config.php`:
```php
'inventario_user' => 'inventario',
'inventario_password' => 'Inv3nt4r10R11@2025'
```

### **APIs de Autenticación**
- `inventario_login.php` - Login con generación de token
- `verify_inventario_token.php` - Verificación de sesión
- Tokens válidos por 24 horas
- Almacenamiento en `localStorage`

## 🛠️ APIs del Sistema

### **Productos**
```php
GET  /api/get_productos.php        # Obtener todos los productos
POST /api/update_producto_stock.php # Actualizar stock de producto
```

### **Ingredientes**
```php
GET  /api/get_ingredients.php       # Obtener todos los ingredientes
POST /api/update_ingredient_stock.php # Actualizar stock de ingrediente
```

### **Autenticación**
```php
POST /api/inventario_login.php      # Login de usuario
POST /api/verify_inventario_token.php # Verificar token
```

## 📱 Funcionalidades Móviles

### **Interfaz de Usuario**
- ✅ **Login seguro** con validación de credenciales
- ✅ **Tabs navegables** (Productos/Ingredientes)
- ✅ **Búsqueda en tiempo real** por nombre
- ✅ **Botones táctiles grandes** (+/-) para modificar stock
- ✅ **Alertas visuales** para stock bajo
- ✅ **Logout** con limpieza de sesión

### **Gestión de Stock**
- ✅ **Productos:** Conteo en números enteros
- ✅ **Ingredientes:** Conteo en números enteros (convertido desde decimales)
- ✅ **Actualización inmediata** en base de datos
- ✅ **Recarga automática** de datos tras cambios
- ✅ **Indicadores de stock bajo** con animaciones

### **Alertas de Stock Bajo**
- **Productos:** Stock ≤ `min_stock_level` (default: 5)
- **Ingredientes:** Stock ≤ `min_stock_level` (default: 1)
- **Visualización:** Borde rojo pulsante + texto de alerta

## 🎨 Diseño UX/UI

### **Colores Principales**
- **Primario:** Gradiente naranja (`#f97316` → `#ea580c`)
- **Fondo:** Gris claro (`#f9fafb`)
- **Alertas:** Rojo (`#ef4444`)
- **Éxito:** Verde (`#22c55e`)

### **Componentes Clave**
- **LoginForm:** Pantalla de acceso con logo oficial
- **InventarioApp:** Aplicación principal con tabs
- **ProductoCard:** Tarjeta de producto con imagen y controles
- **IngredienteCard:** Tarjeta de ingrediente con información detallada

### **Responsive Design**
- **Mobile-first:** Optimizado para pantallas pequeñas
- **Touch-friendly:** Botones de 32px mínimo
- **Gestos:** Tap para incrementar/decrementar
- **Feedback visual:** Animaciones y estados activos

## 🔧 Configuración Técnica

### **Búsqueda de Configuración**
Todas las APIs buscan `config.php` hasta 5 niveles desde la raíz:
```php
function findConfig() {
    $levels = ['', '../', '../../', '../../../', '../../../../', '../../../../../'];
    foreach ($levels as $level) {
        $configPath = __DIR__ . '/' . $level . 'config.php';
        if (file_exists($configPath)) return $configPath;
    }
    return null;
}
```

### **Conexión PDO**
```php
$pdo = new PDO(
    "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
    $config['app_db_user'],
    $config['app_db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
```

## 📁 Estructura de Archivos

```
ruta11app/
├── src/pages/inventario/
│   └── index.astro                 # Aplicación principal
├── api/
│   ├── inventario_login.php        # Login de inventario
│   ├── verify_inventario_token.php # Verificación de token
│   ├── get_productos.php           # Obtener productos
│   ├── get_ingredients.php         # Obtener ingredientes
│   ├── update_producto_stock.php   # Actualizar stock productos
│   └── update_ingredient_stock.php # Actualizar stock ingredientes
└── config.php                     # Configuración principal
```

## 🚀 Instalación y Despliegue

### **Requisitos**
- PHP 7.4+
- MySQL 5.7+
- Servidor web (Apache/Nginx)
- Astro.js para el frontend

### **Configuración**
1. Asegurar que `config.php` esté en la raíz del proyecto
2. Verificar credenciales de base de datos en `config.php`
3. Confirmar que las tablas `products` e `ingredients` existan
4. Probar acceso a `/inventario/` desde el navegador

## 🔒 Seguridad

### **Autenticación**
- Tokens únicos de 64 caracteres hexadecimales
- Expiración automática de sesiones (24h)
- Validación de credenciales en cada request
- Limpieza automática de tokens expirados

### **Validación de Datos**
- Sanitización de inputs en todas las APIs
- Validación de tipos de datos (enteros para stock)
- Protección contra SQL injection con PDO prepared statements
- Headers CORS configurados correctamente

## 📊 Monitoreo y Logs

### **Errores Comunes**
- **500 Error:** Verificar conexión a base de datos
- **Token inválido:** Limpiar localStorage y volver a loguearse
- **Stock no actualiza:** Verificar permisos de escritura en BD

### **Debug**
```javascript
// En consola del navegador
localStorage.getItem('inventario_token') // Ver token actual
localStorage.removeItem('inventario_token') // Limpiar sesión
```

## 🎯 Casos de Uso

### **Personal de Cocina**
1. Acceder con credenciales de inventario
2. Revisar stock de ingredientes
3. Actualizar cantidades según uso real
4. Identificar ingredientes con stock bajo

### **Administración**
1. Monitorear stock de productos terminados
2. Ajustar inventario tras recepción de mercadería
3. Revisar alertas de stock bajo
4. Planificar compras basado en niveles mínimos

## 📞 Soporte

Para problemas técnicos:
1. Verificar conexión a internet
2. Limpiar caché del navegador
3. Revisar credenciales de acceso
4. Contactar al administrador del sistema

---

**Versión:** 1.0  
**Última actualización:** Enero 2025  
**Desarrollado para:** La Ruta 11 Restaurant