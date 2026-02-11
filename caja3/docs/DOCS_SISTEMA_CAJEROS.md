# Sistema de Gestión de Cajeros - Documentación Técnica

**Fecha:** Enero 2025  
**Versión:** 1.0  
**Base de Datos:** `u958525313_app`

---

## 📋 Resumen Ejecutivo

Implementación de sistema completo de gestión de cajeros para La Ruta 11, permitiendo:
- Autenticación de cajeros con credenciales únicas
- Registro de quién procesa cada venta (auditoría)
- Gestión de perfiles de cajeros
- Trazabilidad completa de operaciones

---

## 🗄️ Estructura de Base de Datos

### Tabla: `cashiers`

Almacena información de todos los cajeros del sistema.

```sql
CREATE TABLE IF NOT EXISTS cashiers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NULL,
    email VARCHAR(100) NULL,
    role ENUM('cajero', 'admin') DEFAULT 'cajero',
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Campos:**
- `id`: Identificador único del cajero
- `username`: Usuario para login (único)
- `password`: Contraseña en texto plano (migrar a hash en futuro)
- `full_name`: Nombre completo del cajero
- `phone`: Teléfono de contacto
- `email`: Email de contacto
- `role`: Rol del usuario (cajero/admin)
- `active`: Estado del cajero (1=activo, 0=inactivo)
- `created_at`: Fecha de creación
- `updated_at`: Última actualización

### Modificaciones a `tuu_orders`

Se agregan columnas para registrar el cajero que procesó cada venta.

```sql
ALTER TABLE tuu_orders 
ADD COLUMN cashier_id INT NULL AFTER customer_notes,
ADD COLUMN cashier_name VARCHAR(100) NULL AFTER cashier_id,
ADD INDEX idx_cashier_id (cashier_id);
```

**Nuevas Columnas:**
- `cashier_id`: ID del cajero (FK a `cashiers.id`)
- `cashier_name`: Nombre del cajero (redundante para reportes)
- `idx_cashier_id`: Índice para optimizar consultas

---

## 🔐 Datos Iniciales

### Cajeros Predefinidos

```sql
INSERT INTO cashiers (username, password, full_name, role) VALUES
('cajera', 'ruta11caja', 'Tami', 'cajero'),
('admin', 'admin123', 'Administrador', 'admin')
ON DUPLICATE KEY UPDATE full_name=VALUES(full_name);
```

**Usuarios:**
1. **Tami** (Cajera Principal)
   - Username: `cajera`
   - Password: `ruta11caja`
   - Role: `cajero`

2. **Administrador**
   - Username: `admin`
   - Password: `admin123`
   - Role: `admin`

---

## 🔌 APIs Implementadas

### 1. Setup de Tabla
**Archivo:** `api/setup_cashiers_table.php`

Crea la tabla `cashiers` e inserta datos iniciales.

**Uso:**
```bash
GET /api/setup_cashiers_table.php
```

**Respuesta:**
```json
{
  "success": true,
  "message": "Tabla de cajeros creada exitosamente"
}
```

---

### 2. Login v2
**Archivo:** `api/auth/login_v2.php`

Autentica cajeros y devuelve datos completos del perfil.

**Request:**
```json
POST /api/auth/login_v2.php
{
  "username": "cajera",
  "password": "ruta11caja"
}
```

**Response (Éxito):**
```json
{
  "success": true,
  "user": "cajera",
  "userId": 1,
  "fullName": "Tami",
  "phone": null,
  "email": null,
  "role": "cajero",
  "token": "abc123...",
  "message": "Login exitoso"
}
```

**Response (Error):**
```json
{
  "success": false,
  "message": "Usuario o contraseña incorrectos"
}
```

---

### 3. Actualizar Perfil
**Archivo:** `api/update_cashier_profile.php`

Actualiza datos personales del cajero.

**Request:**
```json
POST /api/update_cashier_profile.php
{
  "userId": 1,
  "fullName": "Tami González",
  "phone": "+56912345678",
  "email": "tami@ruta11.cl"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Perfil actualizado exitosamente"
}
```

---

## 🖥️ Frontend

### Flujo de Login

**Archivo:** `src/pages/login.astro`

1. Usuario ingresa credenciales
2. Se llama a `login_v2.php`
3. Si éxito, se guarda en `localStorage`:
```javascript
localStorage.setItem('caja_session', JSON.stringify({
  user: "cajera",
  userId: 1,
  fullName: "Tami",
  phone: "+56912345678",
  email: "tami@ruta11.cl",
  role: "cajero",
  token: "abc123...",
  timestamp: 1234567890
}));
```
4. Redirección a `/`

---

### Modal de Perfil

**Archivo:** `src/components/MenuApp.jsx`

**Ubicación:** Header → Botón con icono User + nombre

**Funcionalidad:**
- Muestra datos actuales del cajero
- Permite editar: nombre completo, teléfono, email
- Username es read-only
- Al guardar, llama a `update_cashier_profile.php`

**Código Relevante:**
```jsx
{cajaUser && (
  <button onClick={() => setIsProfileOpen(true)}>
    <User size={22} />
    <span>{cajaUser.user}</span>
  </button>
)}
```

---

## 📊 Registro de Ventas

### Modificación en `create_order.php`

**Antes:**
```php
INSERT INTO tuu_orders (order_number, customer_name, ...) 
VALUES (?, ?, ...)
```

**Después:**
```php
// Obtener cajero desde sesión
$cashier_id = $_POST['cashier_id'] ?? null;
$cashier_name = $_POST['cashier_name'] ?? 'Sistema';

INSERT INTO tuu_orders (
  order_number, customer_name, ..., 
  cashier_id, cashier_name
) VALUES (?, ?, ..., ?, ?)
```

### Envío desde Frontend

**MenuApp.jsx:**
```javascript
const orderData = {
  ...orderData,
  cashier_id: cajaUser?.userId || null,
  cashier_name: cajaUser?.fullName || cajaUser?.user || 'Sistema'
};

fetch('/api/create_order.php', {
  method: 'POST',
  body: JSON.stringify(orderData)
});
```

---

## 📈 Reportes y Auditoría

### Consultas Útiles

**Ventas por Cajero (Hoy):**
```sql
SELECT 
  c.full_name,
  COUNT(*) as total_ventas,
  SUM(o.installment_amount) as total_monto
FROM tuu_orders o
LEFT JOIN cashiers c ON o.cashier_id = c.id
WHERE DATE(o.created_at) = CURDATE()
  AND o.payment_status = 'paid'
GROUP BY c.id, c.full_name;
```

**Ventas sin Cajero Asignado:**
```sql
SELECT COUNT(*) as ventas_sin_cajero
FROM tuu_orders
WHERE cashier_id IS NULL
  AND created_at >= '2025-01-01';
```

**Historial de un Cajero:**
```sql
SELECT 
  o.order_number,
  o.customer_name,
  o.installment_amount,
  o.created_at
FROM tuu_orders o
WHERE o.cashier_id = 1
ORDER BY o.created_at DESC
LIMIT 50;
```

---

## 🔒 Seguridad

### Consideraciones Actuales

⚠️ **IMPORTANTE:** El sistema actual tiene las siguientes limitaciones de seguridad:

1. **Contraseñas en texto plano**
   - Las contraseñas se guardan sin encriptar
   - **Recomendación:** Migrar a `password_hash()` de PHP

2. **Sin expiración de sesión**
   - Las sesiones en localStorage no expiran
   - **Recomendación:** Implementar timeout de 8 horas

3. **Sin validación de token**
   - El token no se valida en el servidor
   - **Recomendación:** Implementar JWT o sesiones PHP

### Mejoras Futuras

```php
// Ejemplo de hash de contraseña
$hashed = password_hash($password, PASSWORD_BCRYPT);

// Verificación
if (password_verify($input_password, $hashed)) {
  // Login exitoso
}
```

---

## 🧪 Testing

### Pruebas Manuales

1. **Login Exitoso:**
   - Ir a `/login`
   - Ingresar: `cajera` / `ruta11caja`
   - Verificar redirección a `/`
   - Verificar nombre en header

2. **Actualizar Perfil:**
   - Click en botón de perfil
   - Modificar nombre/teléfono/email
   - Guardar
   - Verificar actualización en BD

3. **Registro de Venta:**
   - Procesar una venta
   - Verificar en BD que `cashier_id` y `cashier_name` estén llenos

### Queries de Verificación

```sql
-- Verificar tabla creada
SHOW TABLES LIKE 'cashiers';

-- Verificar cajeros
SELECT * FROM cashiers;

-- Verificar columnas en tuu_orders
SHOW COLUMNS FROM tuu_orders LIKE 'cashier%';

-- Verificar última venta con cajero
SELECT 
  order_number, 
  cashier_id, 
  cashier_name, 
  created_at 
FROM tuu_orders 
ORDER BY created_at DESC 
LIMIT 1;
```

---

## 🚀 Instalación

### Paso 1: Ejecutar SQL
```bash
# Conectar a MySQL
mysql -u u958525313_app -p u958525313_app

# Ejecutar queries
source /path/to/setup_queries.sql
```

### Paso 2: Verificar APIs
```bash
# Setup tabla
curl https://app.laruta11.cl/api/setup_cashiers_table.php

# Test login
curl -X POST https://app.laruta11.cl/api/auth/login_v2.php \
  -H "Content-Type: application/json" \
  -d '{"username":"cajera","password":"ruta11caja"}'
```

### Paso 3: Deploy Frontend
```bash
npm run build
# Subir carpeta dist/ a servidor
```

---

## 📝 Changelog

### v1.0 (Enero 2025)
- ✅ Tabla `cashiers` creada
- ✅ Login v2 con datos completos
- ✅ Modal de perfil funcional
- ✅ Registro de cajero en ventas
- ✅ APIs de gestión implementadas

---

## 🔗 Referencias

**Archivos Clave:**
- `api/setup_cashiers_table.php` - Setup inicial
- `api/auth/login_v2.php` - Login
- `api/update_cashier_profile.php` - Actualizar perfil
- `src/pages/login.astro` - Página de login
- `src/components/MenuApp.jsx` - Modal de perfil

**Base de Datos:**
- Host: `localhost`
- Database: `u958525313_app`
- User: `u958525313_app`
- Password: `wEzho0-hujzoz-cevzin`

---

## 📞 Soporte

Para dudas o problemas:
1. Revisar logs en `/api/setup_cashiers_table.php`
2. Verificar estructura de BD con queries de testing
3. Revisar console del navegador para errores de frontend

---

**Documento generado:** Enero 2025  
**Autor:** Sistema La Ruta 11  
**Versión:** 1.0
