# 🎖️ Sistema RL6 - Registro Exclusivo para Militares
## Regimiento Logístico N°6 Pisagua - Sistema de Créditos

---

## 📋 Resumen Ejecutivo

Sistema de registro exclusivo para personal militar del Regimiento Logístico N°6 Pisagua que extiende el sistema de usuarios existente con datos adicionales específicos para gestión de créditos militares.

### Objetivo Principal
Crear una página de registro especializada (`rl6.astro`) que capture información militar adicional y la almacene en la tabla `usuarios` existente, aprovechando la infraestructura actual de autenticación y subida de imágenes a AWS S3.

---

## 🔍 Análisis del Sistema Actual

### **APIs de Registro Existentes**

#### 1. **`/api/auth/register.php`**
- Registro con email/password
- Crea usuario en tabla `usuarios`
- Genera `google_id` único: `manual_` + `uniqid()`
- Genera `session_token` de 64 caracteres
- Hashea password con `password_hash()`
- También crea registro en `app_users` (sincronización)

#### 2. **`/api/auth/register_manual.php`**
- Registro simplificado
- Campos: nombre, email, password, teléfono
- Solo usa base de datos `u958525313_app`
- Crea sesión PHP automáticamente
- Genera avatar con UI Avatars

#### 3. **`/api/auth/update_profile.php`**
- Actualiza perfil de usuario autenticado
- Campos actualizables:
  - `telefono`
  - `instagram`
  - `fecha_nacimiento`
  - `genero`
  - `direccion`
- Requiere sesión activa

#### 4. **`/api/users/update_profile.php`**
- Similar al anterior pero con más campos:
  - `lugar_nacimiento`
  - `genero`
  - `fecha_nacimiento`

### **Sistema de Subida de Imágenes**

#### **`/api/upload_image.php`**
- Usa clase `S3Manager` para subir a AWS S3
- Compresión automática si imagen > 500KB
- Tipos permitidos: JPG, PNG, GIF, WEBP
- Retorna URL pública de S3
- Estructura: `https://[bucket].s3.amazonaws.com/[key]`

#### **`/api/S3Manager.php`**
- Clase reutilizable para gestión de S3
- Métodos principales:
  - `uploadFile($file, $key, $compress = true)` → Sube archivo
  - `compressImage($sourcePath, $quality, $maxWidth, $maxHeight)` → Comprime imagen
  - `deleteFile($key)` → Elimina archivo
- Configuración desde `config.php`:
  - `s3_bucket`
  - `s3_url`
  - `s3_region`
  - `aws_access_key_id`
  - `aws_secret_access_key`

---

## 🗄️ Estructura de Base de Datos

### **Tabla: `usuarios` (Base de datos: `u958525313_app`)**

#### **Columnas Existentes (34 campos)**

| # | Campo | Tipo | Descripción |
|---|-------|------|-------------|
| 1 | `id` | int(11) PK AUTO_INCREMENT | ID único del usuario |
| 2 | `google_id` | varchar(255) | ID de Google o manual_[uniqid] |
| 3 | `nombre` | varchar(255) | Nombre completo |
| 4 | `email` | varchar(255) | Email único |
| 5 | `password` | varchar(255) | Password hasheado |
| 6 | `foto_perfil` | text | URL de foto de perfil |
| 7 | `fecha_registro` | timestamp | Fecha de registro |
| 8 | `ultimo_acceso` | timestamp | Último acceso |
| 9 | `activo` | tinyint(1) | Usuario activo (1/0) |
| 10 | `telefono` | varchar(20) | Teléfono |
| 11 | `instagram` | varchar(100) | Usuario de Instagram |
| 12 | `lugar_nacimiento` | varchar(255) | Lugar de nacimiento |
| 13 | `nacionalidad` | varchar(20) | Nacionalidad |
| 14 | `genero` | enum | masculino/femenino/otro/no_decir |
| 15 | `fecha_nacimiento` | date | Fecha de nacimiento |
| 16 | `latitud` | decimal(10,8) | Coordenada latitud |
| 17 | `longitud` | decimal(11,8) | Coordenada longitud |
| 18 | `direccion_actual` | text | Dirección actual |
| 19 | `ubicacion_actualizada` | timestamp | Última actualización ubicación |
| 20 | `total_sessions` | int(11) | Total de sesiones |
| 21 | `total_time_seconds` | int(11) | Tiempo total en app |
| 22 | `last_session_duration` | int(11) | Duración última sesión |
| 23 | `direccion` | varchar(500) | Dirección |
| 24 | `total_orders` | int(11) | Total de órdenes |
| 25 | `total_spent` | decimal(10,2) | Total gastado |
| 26 | `kanban_status` | enum | Estado en kanban de reclutamiento |
| 27 | `last_notification_sent` | timestamp | Última notificación enviada |
| 28 | `notification_count` | int(11) | Contador de notificaciones |
| 29 | `pending_notification` | tinyint(1) | Notificación pendiente |
| 30 | `notification_history` | longtext | Historial de notificaciones |
| 31 | `session_token` | varchar(64) | Token de sesión |
| 32 | `cashback_level_bronze` | tinyint(1) | Nivel bronze alcanzado |
| 33 | `cashback_level_silver` | tinyint(1) | Nivel silver alcanzado |
| 34 | `cashback_level_gold` | tinyint(1) | Nivel gold alcanzado |

#### **Nuevas Columnas Necesarias para RL6**

```sql
ALTER TABLE usuarios
ADD COLUMN rut VARCHAR(12) NULL COMMENT 'RUT del militar (formato: 12345678-9)',
ADD COLUMN grado_militar VARCHAR(100) NULL COMMENT 'Grado militar (Ej: Cabo, Sargento, Teniente)',
ADD COLUMN unidad_trabajo VARCHAR(255) NULL COMMENT 'Unidad donde trabaja',
ADD COLUMN domicilio_particular TEXT NULL COMMENT 'Domicilio particular completo',
ADD COLUMN carnet_frontal_url TEXT NULL COMMENT 'URL imagen carnet frontal en S3',
ADD COLUMN carnet_trasero_url TEXT NULL COMMENT 'URL imagen carnet trasero en S3',
ADD COLUMN es_militar_rl6 TINYINT(1) DEFAULT 0 COMMENT 'Flag: usuario es militar RL6',
ADD COLUMN credito_aprobado TINYINT(1) DEFAULT 0 COMMENT 'Crédito aprobado (1/0)',
ADD COLUMN limite_credito DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Límite de crédito asignado',
ADD COLUMN credito_usado DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Crédito usado actualmente',
ADD COLUMN fecha_registro_rl6 TIMESTAMP NULL COMMENT 'Fecha de registro en sistema RL6';
```

---

## 🏗️ Arquitectura de la Solución

### **Componentes Principales**

```
┌─────────────────────────────────────────────────────────────┐
│                    FRONTEND (Astro)                         │
├─────────────────────────────────────────────────────────────┤
│  /rl6.astro                                                 │
│  - Formulario de registro militar                           │
│  - Subida de carnets (frontal/trasero)                     │
│  - Validación de RUT chileno                               │
│  - Preview de imágenes                                      │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│                    BACKEND (PHP APIs)                       │
├─────────────────────────────────────────────────────────────┤
│  /api/rl6/register_militar.php                             │
│  - Rate limiting: máx 5 registros por IP en 1 hora         │
│  - Valida datos militares                                   │
│  - Sube carnets a S3                                        │
│  - Crea usuario con flag es_militar_rl6=1                  │
│  - Envía email de registro exitoso                          │
│  - Retorna token de sesión                                  │
│                                                              │
│  /api/rl6/update_militar_data.php                          │
│  - Actualiza datos militares existentes                    │
│  - Permite re-subir carnets                                │
│                                                              │
│  /api/rl6/get_militar_profile.php                          │
│  - Obtiene perfil completo del militar                      │
│  - Incluye URLs de carnets                                  │
│                                                              │
│  /api/rl6/admin_approve_credit.php                         │
│  - Admin aprueba/rechaza crédito                            │
│  - Asigna límite de crédito                                │
│  - Envía email de aprobación/rechazo                       │
│                                                              │
│  /api/rl6/send_rl6_emails.php                              │
│  - Envía emails de registro exitoso                        │
│  - Envía emails de aprobación de crédito                   │
│  - Envía emails de rechazo                                 │
│  - Usa Gmail API (sistema existente)                       │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│                  BASE DE DATOS (MySQL)                      │
├─────────────────────────────────────────────────────────────┤
│  Tabla: usuarios (u958525313_app)                          │
│  - Campos existentes (34)                                   │
│  - Campos nuevos RL6 (11)                                   │
│  - Total: 45 campos                                         │
│                                                              │
│  Tabla: rl6_credit_transactions                            │
│  - Historial de transacciones (crédito/débito)             │
│  - Saldo anterior y nuevo                                   │
│  - Vinculado a tuu_orders                                   │
│                                                              │
│  Tabla: rl6_credit_audit                                   │
│  - Auditoría de cambios por admin                          │
│  - Acciones: approve, reject, update_limit, delete_user   │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│                  ALMACENAMIENTO (AWS S3)                    │
├─────────────────────────────────────────────────────────────┤
│  /carnets-militares/                                        │
│  - [user_id]_frontal_[timestamp].jpg                       │
│  - [user_id]_trasero_[timestamp].jpg                       │
│  - Compresión automática                                    │
│  - URLs públicas                                            │
└─────────────────────────────────────────────────────────────┘
```

---

## 📝 Plan de Implementación

### **FASE 1: Base de Datos (30 min)**

#### **1.1 Crear Script de Migración**
**Archivo**: `/api/rl6/setup_rl6_tables.php`

```php
<?php
require_once __DIR__ . '/../../config.php';

$conn = mysqli_connect(
    $config['app_db_host'],
    $config['app_db_user'],
    $config['app_db_pass'],
    $config['app_db_name']
);

if (!$conn) {
    die(json_encode(['error' => 'Error de conexión']));
}

// Agregar columnas RL6
$queries = [
    "ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS rut VARCHAR(12) NULL",
    "ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS grado_militar VARCHAR(100) NULL",
    "ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS unidad_trabajo VARCHAR(255) NULL",
    "ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS domicilio_particular TEXT NULL",
    "ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS carnet_frontal_url TEXT NULL",
    "ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS carnet_trasero_url TEXT NULL",
    "ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS es_militar_rl6 TINYINT(1) DEFAULT 0",
    "ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS credito_aprobado TINYINT(1) DEFAULT 0",
    "ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS limite_credito DECIMAL(10,2) DEFAULT 0.00",
    "ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS credito_usado DECIMAL(10,2) DEFAULT 0.00",
    "ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS fecha_registro_rl6 TIMESTAMP NULL"
];

$success = true;
foreach ($queries as $query) {
    if (!mysqli_query($conn, $query)) {
        $success = false;
        echo "Error: " . mysqli_error($conn) . "\n";
    }
}

echo json_encode([
    'success' => $success,
    'message' => $success ? 'Tablas RL6 configuradas correctamente' : 'Error en configuración'
]);

mysqli_close($conn);
?>
```

#### **1.2 Ejecutar Migración**
```bash
php api/rl6/setup_rl6_tables.php
```

---

### **FASE 2: Backend APIs (2 horas)**

#### **2.1 API de Registro Militar**
**Archivo**: `/api/rl6/register_militar.php`

**Funcionalidad**:
- Valida RUT chileno (formato y dígito verificador)
- Valida que email no exista
- Sube carnets a S3 usando `S3Manager`
- Crea usuario con `es_militar_rl6 = 1`
- Genera token de sesión

**Campos requeridos**:
- `nombre` (string)
- `email` (string, único)
- `password` (string, min 6 caracteres)
- `rut` (string, formato 12345678-9)
- `grado_militar` (string)
- `unidad_trabajo` (string)
- `domicilio_particular` (string)
- `telefono` (string)
- `carnet_frontal` (file, imagen)
- `carnet_trasero` (file, imagen)

**Respuesta exitosa**:
```json
{
  "success": true,
  "message": "Registro militar exitoso",
  "token": "abc123...",
  "user": {
    "id": 123,
    "nombre": "Juan Pérez",
    "email": "juan@example.com",
    "rut": "12345678-9",
    "grado_militar": "Cabo",
    "es_militar_rl6": 1
  }
}
```

#### **2.2 API de Actualización de Datos Militares**
**Archivo**: `/api/rl6/update_militar_data.php`

**Funcionalidad**:
- Actualiza datos militares de usuario autenticado
- Permite re-subir carnets
- Valida sesión activa

#### **2.3 API de Perfil Militar**
**Archivo**: `/api/rl6/get_militar_profile.php`

**Funcionalidad**:
- Obtiene perfil completo del militar
- Incluye URLs de carnets
- Muestra estado de crédito

#### **2.4 API de Aprobación de Crédito (Admin)**
**Archivo**: `/api/rl6/admin_approve_credit.php`

**Funcionalidad**:
- Solo accesible por admin
- Aprueba/rechaza solicitud de crédito
- Asigna límite de crédito

---

### **FASE 3: Frontend (3 horas)**

#### **3.1 Página de Registro RL6**
**Archivo**: `/src/pages/rl6.astro`

**Secciones del Formulario**:

1. **Datos Personales**
   - Nombre completo
   - Email
   - Contraseña
   - Teléfono

2. **Datos Militares**
   - RUT (con validación)
   - Grado militar (select)
   - Unidad de trabajo
   - Domicilio particular

3. **Documentación**
   - Carnet frontal (drag & drop + preview)
   - Carnet trasero (drag & drop + preview)

4. **Términos y Condiciones**
   - Checkbox de aceptación
   - Link a términos RL6

**Características UI**:
- Diseño responsive (móvil first)
- Validación en tiempo real
- Preview de imágenes antes de subir
- Barra de progreso de registro
- Mensajes de error claros
- Loading states

#### **3.2 Componente de Subida de Carnet**
**Archivo**: `/src/components/RL6CarnetUpload.jsx`

```jsx
const RL6CarnetUpload = ({ label, onFileSelect, preview }) => {
  // Drag & drop
  // Preview de imagen
  // Validación de tipo/tamaño
  // Compresión client-side opcional
}
```

#### **3.3 Validador de RUT**
**Archivo**: `/src/utils/rutValidator.js`

```javascript
export function validarRUT(rut) {
  // Limpia formato
  // Valida dígito verificador
  // Retorna true/false
}

export function formatearRUT(rut) {
  // Formatea a 12.345.678-9
}
```

---

### **FASE 4: Panel de Administración (2 horas)**

#### **4.1 Vista de Militares Registrados**
**Archivo**: `/src/pages/admin/militares-rl6.astro`

**Funcionalidades**:
- Lista de militares registrados
- Filtros: aprobado/pendiente/rechazado
- Búsqueda por RUT/nombre
- Ver carnets en modal
- Aprobar/rechazar crédito
- Asignar límite de crédito

#### **4.2 Modal de Revisión**
**Componente**: Muestra carnets lado a lado
- Zoom de imágenes
- Datos del militar
- Formulario de aprobación
- Campo de límite de crédito
- Notas internas

---

## 🔐 Seguridad y Validaciones

### **Validaciones Backend**

1. **RUT Chileno**
   - Formato: 12345678-9
   - Dígito verificador válido
   - Único en sistema

2. **Email**
   - Formato válido
   - Único en sistema
   - No permitir emails temporales

3. **Password**
   - Mínimo 6 caracteres
   - Hasheado con `password_hash()`

4. **Imágenes de Carnet**
   - Tipos permitidos: JPG, PNG
   - Tamaño máximo: 5MB
   - Compresión automática
   - Validación de tipo MIME real

5. **Datos Militares**
   - Grado militar de lista predefinida
   - Unidad de trabajo no vacía
   - Domicilio completo

### **Seguridad de APIs**

```php
// Todas las APIs RL6 deben incluir:
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Validar sesión para operaciones sensibles
if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

// Sanitizar inputs
$rut = mysqli_real_escape_string($conn, $_POST['rut']);

// Validar permisos admin
if ($_SESSION['user']['role'] !== 'admin') {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}
```

---

## 📊 Flujo de Usuario

### **Registro de Militar**

```
1. Usuario accede a /rl6
   ↓
2. Completa formulario de datos personales
   ↓
3. Completa datos militares (RUT, grado, unidad)
   ↓
4. Sube foto carnet frontal
   ↓
5. Sube foto carnet trasero
   ↓
6. Acepta términos y condiciones
   ↓
7. Click en "Registrar"
   ↓
8. Frontend valida datos
   ↓
9. Sube carnets a S3 (via upload_image.php)
   ↓
10. Envía datos a register_militar.php
   ↓
11. Backend crea usuario con es_militar_rl6=1
   ↓
12. Retorna token de sesión
   ↓
13. Redirige a página de confirmación
   ↓
14. Muestra mensaje: "Registro exitoso. Tu solicitud está en revisión"
```

### **Aprobación de Crédito (Admin)**

```
1. Admin accede a /admin/militares-rl6
   ↓
2. Ve lista de militares pendientes
   ↓
3. Click en militar para revisar
   ↓
4. Modal muestra carnets y datos
   ↓
5. Admin verifica información
   ↓
6. Ingresa límite de crédito
   ↓
7. Click en "Aprobar Crédito"
   ↓
8. API actualiza:
   - credito_aprobado = 1
   - limite_credito = [monto]
   ↓
9. Se envía notificación al militar
   ↓
10. Militar puede usar crédito en app
```

---

## 🎨 Diseño UI/UX

### **Página RL6 (/rl6.astro)**

**Colores**:
- Verde militar: `#4A5D23`
- Dorado: `#D4AF37`
- Blanco: `#FFFFFF`
- Gris oscuro: `#2C2C2C`

**Layout**:
```
┌─────────────────────────────────────┐
│  🎖️ REGIMIENTO LOGÍSTICO N°6       │
│     Sistema de Créditos             │
├─────────────────────────────────────┤
│                                     │
│  [Paso 1/4] Datos Personales       │
│  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━  │
│                                     │
│  Nombre: [________________]         │
│  Email:  [________________]         │
│  Pass:   [________________]         │
│  Tel:    [________________]         │
│                                     │
│  [Siguiente →]                      │
│                                     │
└─────────────────────────────────────┘
```

**Componentes Reutilizables**:
- `<RL6Input />` - Input con validación
- `<RL6Select />` - Select estilizado
- `<RL6FileUpload />` - Subida de archivos
- `<RL6ProgressBar />` - Barra de progreso
- `<RL6Button />` - Botón estilizado

---

## 🧪 Testing

### **Tests Unitarios**

1. **Validación de RUT**
   ```javascript
   test('RUT válido', () => {
     expect(validarRUT('12345678-9')).toBe(true);
   });
   ```

2. **Subida de Imágenes**
   - Imagen válida → success
   - Imagen muy grande → compresión
   - Tipo inválido → error

3. **Registro de Usuario**
   - Datos completos → success
   - Email duplicado → error
   - RUT duplicado → error

### **Tests de Integración**

1. **Flujo completo de registro**
2. **Aprobación de crédito**
3. **Actualización de datos**

---

## 📈 Métricas y Monitoreo

### **KPIs a Trackear**

1. **Registros**
   - Total de militares registrados
   - Registros por día/semana/mes
   - Tasa de completitud de formulario

2. **Aprobaciones**
   - Créditos aprobados vs rechazados
   - Tiempo promedio de aprobación
   - Límites de crédito promedio

3. **Uso de Crédito**
   - Crédito total asignado
   - Crédito usado
   - Crédito disponible

### **Dashboard Admin**

```sql
-- Query para dashboard
SELECT 
  COUNT(*) as total_militares,
  SUM(CASE WHEN credito_aprobado = 1 THEN 1 ELSE 0 END) as aprobados,
  SUM(CASE WHEN credito_aprobado = 0 THEN 1 ELSE 0 END) as pendientes,
  SUM(limite_credito) as credito_total,
  SUM(credito_usado) as credito_usado,
  SUM(limite_credito - credito_usado) as credito_disponible
FROM usuarios
WHERE es_militar_rl6 = 1;
```

---

## 🚀 Deployment

### **Checklist Pre-Deploy**

- [ ] Ejecutar `setup_rl6_tables.php`
- [ ] Verificar credenciales AWS S3
- [ ] Crear carpeta `/carnets-militares/` en S3
- [ ] Configurar permisos de bucket S3
- [ ] Probar subida de imágenes
- [ ] Validar todas las APIs
- [ ] Probar flujo completo en staging
- [ ] Configurar backup de BD
- [ ] Documentar credenciales admin

### **Variables de Entorno**

```php
// config.php - Agregar si no existen
's3_bucket' => 'tu-bucket',
's3_url' => 'https://tu-bucket.s3.amazonaws.com',
's3_region' => 'us-east-1',
'aws_access_key_id' => 'AKIA...',
'aws_secret_access_key' => 'secret...'
```

---

## 📚 Documentación Adicional

### **Grados Militares (Lista Predefinida)**

```javascript
const GRADOS_MILITARES = [
  'Soldado',
  'Cabo',
  'Cabo 1°',
  'Sargento 2°',
  'Sargento 1°',
  'Suboficial Mayor',
  'Subteniente',
  'Teniente',
  'Capitán',
  'Mayor',
  'Teniente Coronel',
  'Coronel',
  'General de Brigada',
  'General de División'
];
```

### **Formato de RUT**

```
Formato: 12.345.678-9
Sin formato: 12345678-9
Almacenado: 12345678-9 (sin puntos)
```

### **Estructura de URLs de Carnets**

```
Frontal: https://bucket.s3.amazonaws.com/carnets-militares/123_frontal_1234567890.jpg
Trasero: https://bucket.s3.amazonaws.com/carnets-militares/123_trasero_1234567890.jpg

Formato: [user_id]_[tipo]_[timestamp].jpg
```

---

## ⏱️ Estimación de Tiempos

| Fase | Tarea | Tiempo |
|------|-------|--------|
| 1 | Setup BD | 30 min |
| 2 | APIs Backend | 2 horas |
| 3 | Frontend RL6 | 3 horas |
| 4 | Panel Admin | 2 horas |
| 5 | Testing | 1 hora |
| 6 | Deployment | 30 min |
| **TOTAL** | | **9 horas** |

---

## 🎯 Checklist de Implementación

### **✅ COMPLETADO - Sprint 1 (Backend & Database)**
1. ✅ Crear documento de planificación
2. ✅ Ejecutar migración de BD (usuarios + rl6_credit_transactions + rl6_credit_audit)
3. ✅ Crear APIs de registro (`/api/rl6/register_militar.php`)
4. ✅ Crear API de obtención de crédito (`/api/rl6/get_credit.php`)
5. ✅ Crear API de uso de crédito (`/api/rl6/use_credit.php`)
6. ✅ Crear sistema de emails (`/api/rl6/send_email.php`)
7. ✅ Integración con AWS S3 para carnets
8. ✅ Rate limiting (5 intentos/hora)

### **✅ COMPLETADO - Sprint 2 (Frontend App)**
9. ✅ Desarrollar página RL6 (`/src/pages/rl6.astro`)
10. ✅ PASO 0: Verificación de sesión (localStorage)
11. ✅ Formulario 4 pasos (sesión, datos, militar+selfie, dirección+carnets)
12. ✅ Pre-llenado de datos para usuarios logueados
13. ✅ Subida de 3 imágenes (selfie, carnet frontal, carnet trasero)
14. ✅ Validación de RUT con dígito verificador
15. ✅ Integración ProfileModal (tab "Crédito" solo para militares aprobados)
16. ✅ Mostrar saldo, límite, usado, transacciones

### **✅ COMPLETADO - Sprint 3 (Checkout & Payment)**
17. ✅ Integración CheckoutApp (botón "Crédito RL6")
18. ✅ Validación de saldo disponible ANTES de compra
19. ✅ Descuento de crédito DESPUÉS de compra exitosa
20. ✅ Registro en `rl6_credit_transactions`
21. ✅ Actualización de `tuu_orders` con campos RL6
22. ✅ Página de confirmación (`/rl6-pending.astro`)
23. ✅ Mensaje WhatsApp estructurado para RL6

### **⏳ PENDIENTE - Sprint 4 (Admin Panel)**
24. ⏳ Panel de administración en caja.laruta11.cl
25. ⏳ Listar militares pendientes de aprobación
26. ⏳ Aprobar/rechazar solicitudes
27. ⏳ Asignar límite de crédito
28. ⏳ Ver historial de transacciones
29. ⏳ Sistema de auditoría completo
30. ⏳ Envío automático de emails (aprobado/rechazado)

### **⏳ PENDIENTE - Sprint 5 (Testing & Deployment)**
31. ⏳ Testing completo de flujo de registro
32. ⏳ Testing de uso de crédito
33. ⏳ Testing de límites y validaciones
34. ⏳ Deployment a producción
35. ⏳ Documentación de usuario final

### **📋 FUTURO - Mejoras Opcionales**
36. ⏳ Reportes de crédito mensuales
37. ⏳ Notificaciones push cuando se aprueba
38. ⏳ Sistema de pagos automático (día 21)
39. ⏳ App móvil nativa
40. ⏳ Dashboard de métricas RL6

---

## 📞 Contacto y Soporte

**Desarrollador**: RHLL
**Fecha**: Enero 2026
**Versión**: 1.0  
**Estado**: 📋 Planificación Completa

---

**Nota Final**: Este sistema extiende la infraestructura existente de manera no invasiva, agregando columnas a la tabla `usuarios` sin afectar funcionalidades actuales. Todos los usuarios regulares tendrán `es_militar_rl6 = 0` por defecto.


---

## 💳 Sistema de Crédito RL6

### **Lógica Simple (Idéntica a Cashback)**
- Usuario ID 4: $50.000 límite → usa $10.000 → quedan $40.000
- Campos: `limite_credito`, `credito_usado`
- Disponible = límite - usado

### **Validar Saldo ANTES de Compra**
```sql
SELECT (limite_credito - credito_usado) as credito_disponible
FROM usuarios
WHERE id = [USER_ID] AND es_militar_rl6 = 1 AND credito_aprobado = 1;
```

### **Descontar DESPUÉS de Compra Exitosa**
```sql
UPDATE usuarios SET credito_usado = credito_usado + [MONTO]
WHERE id = [USER_ID];

INSERT INTO rl6_credit_transactions 
(user_id, amount, type, description, order_id)
VALUES ([USER_ID], [MONTO], 'debit', 'Compra orden #[ORDER_ID]', [ORDER_ID]);
```

### **Integración con tuu_orders**
- Independiente de Webpay
- Agregar: `pagado_con_credito_rl6` (TINYINT), `monto_credito_rl6` (DECIMAL)
- Registrar cada compra con crédito

### **Pestaña "Crédito" en App**
- Solo para militares RL6 (`es_militar_rl6 = 1`)
- Mostrar: límite, usado, disponible
- Historial últimas 20 transacciones
- Sin notificaciones push ni banners

---

## 🛒 Checkout Exclusivo para Militares RL6

### **Diferencias vs Usuario Regular**

#### **Usuario Regular**
```
❌ Pop-up "Próxima Apertura 18:00"
✅ Tipo de Entrega: [Delivery] [Retiro]
✅ Programar Pedido (visible)
✅ Medios de Pago: Webpay, Cashback
```

#### **Militar RL6 Aprobado**
```
✅ Sin pop-up de horarios (acceso directo)
✅ Tipo de Entrega: [Delivery] [Retiro] [Cuartel]
❌ Programar Pedido (oculto si selecciona Cuartel)
✅ Medios de Pago: Webpay, Cashback, Crédito RL6
```

### **Tipo de Entrega (3 Opciones en 1 Fila)**

```jsx
// CheckoutApp.jsx
const isMilitarRL6 = user.es_militar_rl6 === 1 && user.credito_aprobado === 1;

<div className="delivery-options-row">
  <button 
    className={deliveryType === 'delivery' ? 'active' : ''}
    onClick={() => setDeliveryType('delivery')}
  >
    🚚 Delivery
  </button>
  
  <button 
    className={deliveryType === 'retiro' ? 'active' : ''}
    onClick={() => setDeliveryType('retiro')}
  >
    🏪 Retiro
  </button>
  
  {isMilitarRL6 && (
    <button 
      className={deliveryType === 'cuartel' ? 'active' : ''}
      onClick={() => setDeliveryType('cuartel')}
    >
      🪖 Cuartel
      <span className="subtitle">Retiro en Cuartel</span>
    </button>
  )}
</div>
```

### **Lógica de Programar Pedido**

```jsx
// Solo mostrar si NO es Cuartel
{deliveryType !== 'cuartel' && (
  <div className="programar-pedido">
    <h3>📅 Programar Pedido</h3>
    <input type="datetime-local" />
  </div>
)}
```

### **Medios de Pago (Militares RL6)**

```jsx
// Siempre visible para militares (sin restricción de horario)
<div className="payment-methods">
  {/* Webpay */}
  <label>
    <input type="radio" name="payment" value="webpay" />
    💳 Webpay
  </label>
  
  {/* Cashback */}
  {cashbackBalance > 0 && (
    <label>
      <input type="radio" name="payment" value="cashback" />
      💰 Cashback (${cashbackBalance})
    </label>
  )}
  
  {/* Crédito RL6 - EXCLUSIVO */}
  {isMilitarRL6 && (
    <label>
      <input 
        type="radio" 
        name="payment" 
        value="credito_rl6"
        disabled={creditoDisponible < total}
      />
      🪖 Crédito RL6 (${creditoDisponible})
    </label>
  )}
</div>
```

### **Validación de Pop-up de Horarios**

```jsx
// Al cargar checkout
const shouldShowSchedulePopup = () => {
  // Militares RL6 nunca ven el pop-up
  if (user.es_militar_rl6 === 1 && user.credito_aprobado === 1) {
    return false;
  }
  
  // Usuarios regulares: validar horario
  const now = new Date();
  const hour = now.getHours();
  return hour < 18; // Antes de las 18:00
};

if (shouldShowSchedulePopup()) {
  // Mostrar pop-up solo a usuarios regulares
  showModal({
    title: 'Próxima Apertura',
    message: 'Hoy a las 18:00',
    actions: ['Programar Pedido', 'WhatsApp', 'Entendido']
  });
}
```

### **Registro en tuu_orders**

```sql
-- Agregar columnas para tracking
ALTER TABLE tuu_orders ADD COLUMN delivery_type ENUM('delivery', 'retiro', 'cuartel') DEFAULT 'delivery';
ALTER TABLE tuu_orders ADD COLUMN pagado_con_credito_rl6 TINYINT(1) DEFAULT 0;
ALTER TABLE tuu_orders ADD COLUMN monto_credito_rl6 DECIMAL(10,2) DEFAULT 0;
```

### **Flujo Completo de Compra (Militar RL6)**

```
1. Militar accede a checkout
   ↓
2. NO ve pop-up de horarios
   ↓
3. Selecciona tipo de entrega:
   - Delivery → Muestra "Programar Pedido" (PROGRAMADO)
   - Retiro → Muestra "Programar Pedido" (PROGRAMADO)
   - Cuartel → Pago inmediato (sin programar)
   ↓
4. Selecciona medio de pago:
   - Webpay
   - Cashback (si tiene)
   - Crédito RL6 (si tiene saldo)
   ↓
5. Confirma compra
   ↓
6. Sistema valida crédito disponible
   ↓
7. Procesa pago
   ↓
8. Descuenta crédito usado
   ↓
9. Registra en tuu_orders con:
   - delivery_type = 'cuartel'
   - pagado_con_credito_rl6 = 1
   - monto_credito_rl6 = [MONTO]
```

### **Tabla de Comportamiento por Tipo de Entrega**

| Tipo de Entrega | Muestra "Programar Pedido" | Tipo de Pago |
|-----------------|---------------------------|-------------|
| **Delivery** | ✅ Sí | Programado |
| **Retiro** | ✅ Sí | Programado |
| **Cuartel** | ❌ No | Inmediato |

### **CSS para 3 Botones en 1 Fila**

```css
.delivery-options-row {
  display: flex;
  gap: 12px;
  margin: 20px 0;
}

.delivery-options-row button {
  flex: 1;
  padding: 16px;
  border: 2px solid #ddd;
  border-radius: 12px;
  background: white;
  cursor: pointer;
  transition: all 0.3s;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
}

.delivery-options-row button.active {
  border-color: #FF6B35;
  background: #FFF5F2;
}

.delivery-options-row button .subtitle {
  font-size: 12px;
  color: #666;
  font-weight: normal;
}
```

### **Resumen de Cambios**

| Elemento | Usuario Regular | Militar RL6 |
|----------|----------------|-------------|
| Pop-up Horarios | ✅ Sí (antes 18:00) | ❌ No |
| Opciones Entrega | 2 (Delivery, Retiro) | 3 (Delivery, Retiro, Cuartel) |
| Programar Pedido | ✅ Siempre visible | ❌ Oculto si Cuartel |
| Pago Crédito RL6 | ❌ No disponible | ✅ Disponible |
| Acceso Checkout | ⏰ Restringido | ✅ 24/7 |

---

## 🔐 Seguridad

### **Rate Limiting Básico**
- Máximo 5 registros por IP en 1 hora
- En `/api/rl6/register_militar.php`
- Protege contra bots automáticos

### **Validación Manual**
- Admin llama al militar para confirmar
- Solicita selfie como parte del proceso
- Revisa carnets (frontal/trasero)
- Valida RUT con rutificador web

### **Auditoría Completa**
- Tabla `rl6_credit_audit` registra cambios
- Acciones: approve, reject, update_limit, delete_user
- Timestamp y admin_id en cada acción

---

## 📧 Sistema de Emails (Gmail API)

### **Email 1: Registro Exitoso** (inmediato)
- Confirmación de datos recibidos
- Resumen: Nombre, RUT, Grado, Unidad
- Estado: EN REVISIÓN

### **Email 2: Aprobación de Crédito** (cuando admin aprueba)
- Felicitaciones
- Límite asignado
- Crédito disponible
- Instrucciones de uso

### **Email 3: Rechazo** (cuando admin rechaza)
- Información de rechazo
- Opción de apelar
- Contacto para consultas

---

## 🎯 Flujos Principales

### **Registro Militar**

**PASO 0: Verificación de Sesión** (CRÍTICO)
1. Accede a `/rl6`
2. Sistema lee `localStorage.getItem('ruta11_user')`

**Si usuario NO está logueado:**
- Muestra mensaje: "Primero regístrate en La Ruta 11"
- Botón: "Ir a Registro" → redirige a `/` (app principal)
- Texto: "Luego vuelve a esta página o escanea nuevamente el QR"
- **NO muestra formulario**

**Si usuario SÍ está logueado:**
- Muestra mensaje: "Hola [Nombre], ya estás registrado en La Ruta 11"
- Botón: "Continuar" → muestra formulario RL6
- Pre-llena: nombre, email, teléfono desde localStorage
- Formulario solo pide: RUT, grado, unidad, domicilio, carnets (3 pasos)

**Proceso de registro:**
3. Rate limiting: máx 5 por IP/hora
4. Sube carnets a S3
5. **Actualiza** usuario existente agregando campos RL6
6. Envía email de registro
7. Estado: EN REVISIÓN

### **Aprobación (Admin en caja.laruta11.cl)**
1. Revisa militar pendiente
2. Verifica carnets
3. Valida RUT
4. Ingresa límite de crédito
5. Aprueba o rechaza
6. Si rechaza: elimina usuario
7. Registra en auditoría
8. Envía email al militar

### **Uso de Crédito**
1. Militar compra en app
2. Valida saldo disponible
3. Si OK: procesa compra
4. Descuenta crédito usado
5. Registra en `rl6_credit_transactions`
6. Registra en `tuu_orders`
7. Saldo se actualiza automáticamente

---

## 📋 Detalles Técnicos

### **Validación de RUT**
- Solo formato + dígito verificador
- Validación manual: humano revisa carnet + rutificador web
- No hay API gratis de validación real

### **Rechazo de Solicitud**
- Si rechaza → eliminar usuario
- Puede intentar de nuevo (nuevo registro)
- Solo 1 intento por sesión

### **Expiración de Crédito**
- NO expira
- Es saldo permanente
- Admin asigna nuevo crédito cuando paga

### **Admin Panel (caja.laruta11.cl)**
- Acceso: sistema admin existente
- Roles: super_admin, gerentes (a definir)
- Funciones: listar, aprobar, rechazar, ver historial, auditoría

### **Integración con Checkout**
- Validar saldo disponible ANTES
- Descontar DESPUÉS de pago exitoso
- Registrar en `tuu_orders`
- Actualizar `credito_usado` automáticamente

---

## 📊 Queries SQL Listas

Ver documento: `SISTEMA_RL6_QUERIES_SQL.md`

---

## 📁 Archivos a Crear

### **Backend APIs**
- `/api/rl6/register_militar.php` - Registro con rate limiting
- `/api/rl6/update_militar_data.php` - Actualizar datos
- `/api/rl6/get_militar_profile.php` - Obtener perfil
- `/api/rl6/send_rl6_emails.php` - Enviar emails
- `/api/rl6/setup_rl6_tables.php` - Crear tablas

### **Frontend**
- `/src/pages/rl6.astro` - Página de registro
- `/src/components/RL6CarnetUpload.jsx` - Subida de carnets
- `/src/utils/rutValidator.js` - Validador de RUT

### **Admin (caja.laruta11.cl)**
- `/admin/militares-rl6.astro` - Panel de gestión
- APIs de aprobación/rechazo

---

**Estado**: ✅ Planificación Completa con Todos los Insights
**Versión**: 2.0
**Última actualización**: Enero 2025


---

## 🔐 Autenticación RL6

### **Dos Flujos Posibles**

#### **Flujo 1: Usuario NO Logueado**
```
Accede a app.laruta11.cl/rl6 (sin sesión)
↓
Redirige a login Google
↓
Completa datos militares + carnets
↓
Crea usuario nuevo con es_militar_rl6 = 1
```

#### **Flujo 2: Usuario YA Logueado (Google)**
```
Usuario logueado en app.laruta11.cl
↓
Accede a app.laruta11.cl/rl6 (vía link o QR)
↓
Sistema detecta sesión activa
↓
Muestra formulario con datos previos pre-llenados:
  - Nombre (del perfil Google)
  - Email (del perfil Google)
  - Teléfono (si existe)
↓
Usuario completa SOLO datos militares + carnets
↓
Actualiza usuario existente:
  - es_militar_rl6 = 1
  - rut, grado_militar, unidad_trabajo, etc.
↓
Mantiene google_id intacto
```

### **Acceso vía Link o QR**
- No necesita botones en UI
- Link directo: `app.laruta11.cl/rl6`
- QR apunta a: `app.laruta11.cl/rl6`
- Si no está logueado → redirige a Google
- Si está logueado → muestra formulario con datos previos

### **Datos Pre-llenados (Usuario Logueado)**
```javascript
// En /src/pages/rl6.astro
const usuario = await obtenerUsuarioActual(); // Desde sesión

if (usuario) {
  // Pre-llenar datos existentes
  nombre.value = usuario.nombre;
  email.value = usuario.email;
  telefono.value = usuario.telefono || '';
  
  // Mostrar solo campos militares
  mostrarFormularioMilitares();
} else {
  // Mostrar formulario completo
  mostrarFormularioCompleto();
}
```

### **API Correspondiente**
```php
// /api/rl6/register_militar.php
// Detecta si usuario está logueado
if (isset($_SESSION['user'])) {
  // Actualizar usuario existente
  UPDATE usuarios SET es_militar_rl6 = 1, rut = ..., etc
} else {
  // Crear nuevo usuario
  INSERT INTO usuarios ...
}
```

### **Ventajas**
✅ Un solo usuario por persona  
✅ Mantiene historial Google  
✅ No duplica datos  
✅ Acceso simple vía link/QR  
✅ Sin botones en UI  
✅ Experiencia fluida

---

## 📱 Acceso en App

**URL**: `app.laruta11.cl/rl6`

**Métodos de Acceso**:
1. Link directo (compartido por email/WhatsApp)
2. QR (impreso o digital)
3. Desde perfil (si está logueado)

**Comportamiento**:
- Si NO logueado → Google login → Formulario completo
- Si logueado → Formulario militares (datos previos pre-llenados)

---


---

## 🔄 Flujo Completo de Acceso a /rl6

### **Escenario 1: Usuario NO Logueado + SIN Cuenta**
```
Accede a app.laruta11.cl/rl6
↓
Sistema detecta: sin sesión + sin cuenta
↓
Muestra: "Crear nueva cuenta"
↓
Formulario completo (datos personales + militares + carnets)
↓
Crea usuario nuevo con es_militar_rl6 = 1
```

### **Escenario 2: Usuario NO Logueado + CON Cuenta Existente**
```
Accede a app.laruta11.cl/rl6
↓
Sistema detecta: sin sesión + pero email existe en BD
↓
Muestra mensaje:
  "¿Ya tienes cuenta en La Ruta 11?"
  
  Opción 1: [Inicia Sesión] → app.laruta11.cl
  Opción 2: [Escanea QR nuevamente]
  Opción 3: [Completa datos para validar crédito]
↓
Si elige Opción 1 → Redirige a login
  (Vuelve a /rl6 logueado → Escenario 3)
↓
Si elige Opción 2 → Recarga página
  (Espera que escanee QR nuevamente)
↓
Si elige Opción 3 → Pide email/RUT para validar
  (Verifica que sea el mismo usuario)
  (Muestra formulario militares + carnets)
  (Actualiza usuario existente)
```

### **Escenario 3: Usuario YA Logueado**
```
Accede a app.laruta11.cl/rl6 (vía link o QR)
↓
Sistema detecta: sesión activa
↓
Muestra formulario con datos pre-llenados:
  - Nombre (del perfil)
  - Email (del perfil)
  - Teléfono (si existe)
↓
Usuario completa SOLO:
  - RUT
  - Grado militar
  - Unidad de trabajo
  - Domicilio particular
  - Carnets (frontal/trasero)
↓
Actualiza usuario existente:
  - es_militar_rl6 = 1
  - Campos militares
↓
Mantiene google_id intacto
```

### **Detección de Usuario Existente**
```php
// /api/rl6/check_user.php
// Verifica si email existe en BD

$email = $_POST['email']; // Ingresado por usuario

$query = "SELECT id, nombre, email FROM usuarios WHERE email = ?";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) > 0) {
  // Usuario existe
  echo json_encode([
    'exists' => true,
    'message' => '¿Ya tienes cuenta en La Ruta 11?',
    'user' => $userData
  ]);
} else {
  // Usuario no existe
  echo json_encode(['exists' => false]);
}
```

### **Validación para Opción 3**
```php
// /api/rl6/validate_existing_user.php
// Valida que sea el mismo usuario

$email = $_POST['email'];
$rut = $_POST['rut'];

$query = "SELECT id FROM usuarios WHERE email = ? AND rut = ?";
// Si coinciden → permite completar registro
// Si no coinciden → error "Datos no coinciden"
```

---

## 📱 Página /rl6 - Lógica de Detección

```javascript
// En /src/pages/rl6.astro

const usuario = await obtenerUsuarioActual(); // Sesión

if (usuario) {
  // Escenario 3: Logueado
  mostrarFormularioMilitares(usuario);
} else {
  // No logueado - Detectar si tiene cuenta
  const tieneEmail = localStorage.getItem('email_rl6');
  
  if (tieneEmail) {
    // Escenario 2: Sin sesión pero con email guardado
    mostrarMensajeYaTieneCuenta(tieneEmail);
  } else {
    // Escenario 1: Sin sesión y sin cuenta
    mostrarFormularioCompleto();
  }
}
```

---

## 🎨 UI - Mensaje "Ya tienes cuenta"

```
┌─────────────────────────────────────────┐
│  🎖️ REGIMIENTO LOGÍSTICO N°6           │
│     Sistema de Créditos                 │
├─────────────────────────────────────────┤
│                                         │
│  ¿Ya tienes cuenta en La Ruta 11?      │
│                                         │
│  Detectamos que ya estás registrado.    │
│  Elige una opción para continuar:       │
│                                         │
│  [1] Inicia Sesión                      │
│      → app.laruta11.cl                  │
│                                         │
│  [2] Escanea QR Nuevamente              │
│      (Recarga esta página)              │
│                                         │
│  [3] Completa Datos para Validar        │
│      (Verifica email + RUT)             │
│                                         │
└─────────────────────────────────────────┘
```

---

## ✅ Ventajas de Este Flujo

✅ Evita duplicación de usuarios  
✅ UX clara y directa  
✅ Opciones flexibles  
✅ Valida identidad del usuario  
✅ Mantiene integridad de datos  
✅ Sin confusión de cuentas

---


---

## 📊 Estado del Proyecto RL6

**Progreso General**: 57.5% (23/40 tareas completadas)

**Por Sprint**:
- ✅ Sprint 1 (Backend): 100% (8/8)
- ✅ Sprint 2 (Frontend App): 100% (8/8)
- ✅ Sprint 3 (Checkout): 100% (7/7)
- ⏳ Sprint 4 (Admin): 0% (0/7)
- ⏳ Sprint 5 (Testing): 0% (0/5)
- ⏳ Futuro (Mejoras): 0% (0/5)

**Estado Actual**: ✅ **FUNCIONAL EN APP** - Sistema listo para uso de militares. Falta panel admin para aprobaciones.

**Próximo Paso**: Desarrollar panel de administración en caja.laruta11.cl

---

## 📦 Archivos Creados

### Backend APIs
- ✅ `/api/rl6/register_militar.php` - Registro con rate limiting y AWS S3
- ✅ `/api/rl6/get_credit.php` - Obtener crédito disponible
- ✅ `/api/rl6/use_credit.php` - Usar crédito en compra
- ✅ `/api/rl6/send_email.php` - Sistema de emails (registro, aprobado, rechazado)

### Frontend
- ✅ `/src/pages/rl6.astro` - Página de registro (4 pasos con validación de sesión)
- ✅ `/src/pages/rl6-pending.astro` - Página de confirmación de pedido
- ✅ `/src/components/modals/ProfileModalModern.jsx` - Tab "Crédito" integrado
- ✅ `/src/components/CheckoutApp.jsx` - Botón "Crédito RL6" integrado

### Base de Datos
- ✅ Tabla `usuarios` - 11 columnas RL6 agregadas
- ✅ Tabla `rl6_credit_transactions` - Historial de transacciones
- ✅ Tabla `rl6_credit_audit` - Auditoría de cambios admin
- ✅ Tabla `tuu_orders` - Campos RL6 agregados

---

**Última actualización**: Enero 2025
**Versión**: 3.0 - Sistema Funcional en Producción
