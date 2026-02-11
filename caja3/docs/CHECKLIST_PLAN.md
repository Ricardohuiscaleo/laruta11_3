# 📋 Sistema de Checklist - Plan de Implementación Completo

## 🎯 Resumen Ejecutivo

Sistema de checklist operacional con 2 horarios fijos (18:00 Apertura y 00:45 Cierre), notificaciones automáticas, tiempo límite de 1 hora, integración con sistema de comandas, y compresión/subida de fotos similar al sistema de compras.

---

## ✅ Base de Datos - COMPLETADO

### Tablas Creadas:
- ✅ `checklists` - Tabla principal de checklists
- ✅ `checklist_items` - Items individuales de cada checklist
- ✅ `checklist_templates` - Plantillas predefinidas (8 items apertura + 10 items cierre)

### Datos Insertados:
- ✅ 8 items de checklist de apertura
- ✅ 10 items de checklist de cierre

---

## 📂 Estructura de Archivos a Crear

### **Backend API** (`/api/`)

```
api/
└── checklist.php                     # API unificada con múltiples actions
```

**Actions disponibles**:
- `get_active` - Obtener checklist activo/pendiente
- `start` - Iniciar checklist
- `update_item` - Actualizar item individual
- `complete` - Completar checklist
- `get_history` - Obtener historial
- `upload_photo` - Subir foto comprimida
- `create_daily` - Crear checklists diarios (cron)

### **Frontend Pages** (`/src/pages/`)

```
src/pages/
└── checklist.astro                   # Página principal con 3 tabs
```

### **Frontend Components** (`/src/components/`)

```
src/components/
├── ChecklistCard.jsx                 # Card de checklist en mini-comandas
└── ChecklistNotification.jsx         # Notificación automática en sistema
```

---

## 🎨 Diseño UX/UI

### **Navegación en Header**

```
┌────────────────────────────────────────────────────────────────┐
│ [Logo] [📋 Checklist] [On/Off] [Perfil] [Config] [Compartir]  │
│                                      [Notificación] [Carrito]  │
└────────────────────────────────────────────────────────────────┘
```

### **Página Principal: /checklist**

```
┌─────────────────────────────────────────────────────────────┐
│                                                             │
│  ┌──────────────┬──────────────┬──────────────┐           │
│  │ Check 18:00  │ Check 00:45  │  Historial   │           │
│  └──────────────┴──────────────┴──────────────┘           │
│                                                             │
│  ⏰ Tiempo restante: 45:23                                 │
│  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━  │
│                                                             │
│  ☀️ Checklist Apertura (18:00)                            │
│  Progreso: 3/8 (37%)                                       │
│  ▓▓▓▓▓▓▓▓▓▓▓▓░░░░░░░░░░░░░░░░░░░░░░                      │
│                                                             │
│  ┌─────────────────────────────────────────────┐          │
│  │ ✅ Subir 3 estados de WSP (etiquetar...)    │          │
│  └─────────────────────────────────────────────┘          │
│                                                             │
│  ┌─────────────────────────────────────────────┐          │
│  │ ✅ Encender PedidosYa                        │          │
│  └─────────────────────────────────────────────┘          │
│                                                             │
│  ┌─────────────────────────────────────────────┐          │
│  │ ✅ Revisar carga de máquinas TUU             │          │
│  └─────────────────────────────────────────────┘          │
│                                                             │
│  ┌─────────────────────────────────────────────┐          │
│  │ ⬜ Sacar aderezos, vitrina y basureros       │          │
│  └─────────────────────────────────────────────┘          │
│                                                             │
│  ┌─────────────────────────────────────────────┐          │
│  │ ⬜ FOTO 1: Interior desde puerta del carro   │          │
│  │ [📸 Subir Foto]                              │          │
│  └─────────────────────────────────────────────┘          │
│                                                             │
│  ┌──────────────────────────────────────────┐             │
│  │         [💾 Guardar Progreso]            │             │
│  │         [✅ Completar Checklist]         │             │
│  └──────────────────────────────────────────┘             │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### **Mini-Comanda en Sistema de Comandas**

```
┌─────────────────────────────────┐
│ 🔔 CHECKLIST APERTURA           │
│ ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ │
│ ⏰ Horario: 18:00 - 19:00       │
│ ⏳ Tiempo restante: 52 min      │
│                                 │
│ Estado: 🟡 Pendiente            │
│                                 │
│ ┌─────────────────────────────┐ │
│ │  ▶️ Iniciar Checklist       │ │
│ └─────────────────────────────┘ │
└─────────────────────────────────┘
```

### **Notificación Automática**

```
┌─────────────────────────────────────┐
│ 🔔 Checklist Apertura Disponible    │
│ ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ │
│ Tienes 1 hora para completar el     │
│ checklist de apertura (18:00-19:00) │
│                                     │
│ [Ver Checklist] [Cerrar]            │
└─────────────────────────────────────┘
```

---

## ⚙️ Lógica de Negocio

### **Horarios y Activación**

| Checklist | Horario Programado | Ventana Activa | Notificación |
|-----------|-------------------|----------------|--------------|
| Apertura  | 18:00             | 18:00 - 19:00  | 18:00        |
| Cierre    | 00:45             | 00:45 - 01:45  | 00:45        |

### **Estados del Checklist**

```javascript
{
  pending: 'Aún no es hora de hacerlo',
  active: 'Dentro de la ventana de 1 hora',
  completed: 'Completado exitosamente',
  missed: 'No se completó a tiempo'
}
```

### **Flujo de Estados**

```
pending → active → completed ✅
   ↓         ↓
   └─────────→ missed ❌ (si pasa 1 hora)
```

### **Cálculo de Progreso**

```javascript
completion_percentage = (completed_items / total_items) * 100
```

### **Validación de Tiempo**

```javascript
// Checklist se activa automáticamente
if (current_time >= scheduled_time && current_time <= scheduled_time + 1 hour) {
  status = 'active'
  show_notification = true
  show_in_comandas = true
}

// Checklist expira
if (current_time > scheduled_time + 1 hour && status !== 'completed') {
  status = 'missed'
}
```

---

## 🔔 Sistema de Notificaciones

### **Notificación Automática (JSON)**

```json
{
  "type": "checklist",
  "title": "🔔 Checklist Apertura Disponible",
  "message": "Tienes 1 hora para completar el checklist de apertura",
  "scheduled_time": "18:00",
  "deadline": "19:00",
  "checklist_id": 123,
  "checklist_type": "apertura",
  "priority": "high",
  "action_url": "/checklist?tab=apertura",
  "created_at": "2025-01-15 18:00:00"
}
```

### **Integración con Sistema de Comandas**

- Aparece como mini-comanda especial
- Color distintivo: 🟡 Amarillo/Naranja
- Icono: 📋 Checklist
- Botón "Iniciar Checklist" redirige a `/checklist?tab={type}`
- Se actualiza en tiempo real con polling cada 5 segundos

---

## 📸 Sistema de Compresión de Fotos

### **Reutilizar Lógica de Compras**

```javascript
// Compresión automática
const compressImage = async (file) => {
  return new Promise((resolve) => {
    const reader = new FileReader();
    reader.onload = (e) => {
      const img = new Image();
      img.onload = () => {
        const canvas = document.createElement('canvas');
        const MAX_WIDTH = 800;
        const scale = MAX_WIDTH / img.width;
        canvas.width = MAX_WIDTH;
        canvas.height = img.height * scale;
        
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        
        canvas.toBlob((blob) => {
          resolve(blob);
        }, 'image/jpeg', 0.8);
      };
      img.src = e.target.result;
    };
    reader.readAsDataURL(file);
  });
};
```

### **Subida a Storage**

- Mismo sistema que compras
- Carpeta: `/uploads/checklist/{year}/{month}/`
- Nombre: `checklist_{id}_item_{item_id}_{timestamp}.jpg`
- URL guardada en `checklist_items.photo_url`

---

## 🗂️ API Unificada: checklist.php

### **Action: get_active**

**Propósito**: Obtener checklist activo o pendiente del día actual

**Request**:
```php
GET /api/checklist.php?action=get_active&type=apertura&date=2025-01-15
```

**Response**:
```json
{
  "success": true,
  "checklist": {
    "id": 123,
    "type": "apertura",
    "scheduled_time": "18:00:00",
    "scheduled_date": "2025-01-15",
    "status": "active",
    "started_at": "2025-01-15 18:05:00",
    "completed_at": null,
    "total_items": 8,
    "completed_items": 3,
    "completion_percentage": 37.5,
    "time_remaining_minutes": 52,
    "items": [
      {
        "id": 1,
        "description": "Subir 3 estados de WSP (etiquetar grupos ventas)",
        "requires_photo": false,
        "is_completed": true,
        "completed_at": "2025-01-15 18:06:00"
      },
      // ... más items
    ]
  }
}
```

### **Action: start**

**Propósito**: Iniciar checklist (cambiar status a active)

**Request**:
```php
POST /api/checklist.php
{
  "action": "start",
  "checklist_id": 123,
  "user_id": 5,
  "user_name": "Ricardo"
}
```

**Response**:
```json
{
  "success": true,
  "message": "Checklist iniciado correctamente",
  "checklist_id": 123,
  "started_at": "2025-01-15 18:05:00"
}
```

### **Action: update_item**

**Propósito**: Actualizar item individual (check/uncheck)

**Request**:
```php
POST /api/checklist.php
{
  "action": "update_item",
  "item_id": 5,
  "is_completed": true,
  "notes": "Todo OK"
}
```

**Response**:
```json
{
  "success": true,
  "message": "Item actualizado",
  "item": {
    "id": 5,
    "is_completed": true,
    "completed_at": "2025-01-15 18:10:00"
  },
  "checklist_progress": {
    "completed_items": 4,
    "total_items": 8,
    "percentage": 50
  }
}
```

### **Action: upload_photo**

**Propósito**: Subir foto comprimida para item

**Request**:
```php
POST /api/checklist.php
FormData: {
  action: 'upload_photo',
  item_id: 7,
  photo: [compressed_blob]
}
```

**Response**:
```json
{
  "success": true,
  "photo_url": "/uploads/checklist/2025/01/checklist_123_item_7_1737000000.jpg",
  "message": "Foto subida correctamente"
}
```

### **Action: complete**

**Propósito**: Completar checklist completo

**Request**:
```php
POST /api/checklist.php
{
  "action": "complete",
  "checklist_id": 123,
  "notes": "Todo completado sin problemas"
}
```

**Response**:
```json
{
  "success": true,
  "message": "Checklist completado exitosamente",
  "completed_at": "2025-01-15 18:45:00",
  "completion_percentage": 100
}
```

### **Action: get_history**

**Propósito**: Obtener historial con filtros

**Request**:
```php
GET /api/checklist.php?action=get_history&type=apertura&from=2025-01-01&to=2025-01-31&status=completed
```

**Response**:
```json
{
  "success": true,
  "checklists": [
    {
      "id": 123,
      "type": "apertura",
      "scheduled_date": "2025-01-15",
      "status": "completed",
      "completion_percentage": 100,
      "completed_at": "2025-01-15 18:45:00"
    },
    // ... más checklists
  ],
  "stats": {
    "total": 30,
    "completed": 28,
    "missed": 2,
    "completion_rate": 93.33
  }
}
```

### **Action: create_daily**

**Propósito**: Crear checklists diarios (ejecutar con cron)

**Cron Job**:
```bash
# Ejecutar todos los días a las 17:00 (1 hora antes de apertura)
0 17 * * * curl https://laruta11.com/api/checklist.php?action=create_daily
```

**Response**:
```json
{
  "success": true,
  "created": [
    {
      "type": "apertura",
      "scheduled_date": "2025-01-15",
      "scheduled_time": "18:00:00",
      "total_items": 8
    },
    {
      "type": "cierre",
      "scheduled_date": "2025-01-15",
      "scheduled_time": "00:45:00",
      "total_items": 10
    }
  ]
}
```

---

## 🚀 Plan de Implementación por Sprints

### **Sprint 1: Backend API (Día 1)**

#### Archivo a Crear:
- [ ] `api/checklist.php` (API unificada con 7 actions)

#### Actions a Implementar:
- [ ] `get_active` - Obtener checklist activo
- [ ] `start` - Iniciar checklist
- [ ] `update_item` - Actualizar item
- [ ] `complete` - Completar checklist
- [ ] `get_history` - Historial
- [ ] `upload_photo` - Subir foto
- [ ] `create_daily` - Crear diarios

#### Testing:
- [ ] Probar cada endpoint con Postman/curl
- [ ] Validar respuestas JSON
- [ ] Verificar manejo de errores

---

### **Sprint 2: Página Principal (Día 3-4)**

#### Archivos a Crear:
- [ ] `src/pages/checklist.astro`

#### Funcionalidades:
- [ ] Sistema de 3 tabs (Apertura, Cierre, Historial)
- [ ] Timer regresivo en tiempo real
- [ ] Lista de items con checkboxes
- [ ] Barra de progreso visual
- [ ] Botón subir foto (con compresión)
- [ ] Guardar progreso automático
- [ ] Botón completar checklist

#### Diseño:
- [ ] Mobile-first responsive
- [ ] Animaciones suaves
- [ ] Estados visuales claros
- [ ] Feedback táctil

---

### **Sprint 3: Integración con Sistema (Día 5-6)**

#### Archivos a Crear/Modificar:
- [ ] `src/components/ChecklistCard.jsx` (mini-comanda)
- [ ] `src/components/ChecklistNotification.jsx`
- [ ] Modificar header para agregar botón Checklist
- [ ] Integrar en sistema de comandas
- [ ] Integrar en sistema de notificaciones

#### Funcionalidades:
- [ ] Botón "Checklist" en header
- [ ] Mini-comanda en comandas activas
- [ ] Notificación automática a las 18:00 y 00:45
- [ ] Polling cada 5 segundos para actualizar estado
- [ ] Redirección desde mini-comanda a página checklist

---

### **Sprint 4: Cron Job y Testing Final (Día 7)**

#### Tareas:
- [ ] Configurar cron job en servidor
- [ ] Testing completo end-to-end
- [ ] Pruebas de tiempo límite (1 hora)
- [ ] Pruebas de notificaciones
- [ ] Pruebas de compresión de fotos
- [ ] Optimización de performance
- [ ] Documentación final

---

## 📱 Responsive Design

### **Breakpoints**

```css
/* Mobile First */
.checklist-container {
  padding: 1rem;
}

/* Tablet */
@media (min-width: 768px) {
  .checklist-container {
    padding: 2rem;
    max-width: 768px;
    margin: 0 auto;
  }
}

/* Desktop */
@media (min-width: 1024px) {
  .checklist-container {
    max-width: 1024px;
  }
}
```

### **Touch Targets**

- Checkboxes: mínimo 44x44px
- Botones: mínimo 48px altura
- Espaciado entre items: 16px
- Área táctil de fotos: 100% del card

---

## 🎨 Paleta de Colores

```css
/* Estados */
--checklist-pending: #FCD34D;    /* Amarillo */
--checklist-active: #F59E0B;     /* Naranja */
--checklist-completed: #10B981;  /* Verde */
--checklist-missed: #EF4444;     /* Rojo */

/* UI */
--checklist-bg: #FFFFFF;
--checklist-border: #E5E7EB;
--checklist-text: #1F2937;
--checklist-text-light: #6B7280;
```

---

## 🔐 Seguridad

### **Validaciones Backend**

```php
// Validar que el checklist pertenece al día actual
if ($checklist['scheduled_date'] !== date('Y-m-d')) {
    return error('Checklist no corresponde al día actual');
}

// Validar que está dentro de la ventana de tiempo
if ($current_time < $scheduled_time || $current_time > $scheduled_time + 3600) {
    return error('Checklist fuera de ventana de tiempo');
}

// Validar que no esté ya completado
if ($checklist['status'] === 'completed') {
    return error('Checklist ya completado');
}
```

### **Sanitización de Inputs**

```php
$notes = htmlspecialchars(trim($_POST['notes']), ENT_QUOTES, 'UTF-8');
$item_id = intval($_POST['item_id']);
```

---

## 📊 Métricas y Analytics

### **KPIs a Trackear**

- Tasa de completación de checklists
- Tiempo promedio de completación
- Items más frecuentemente omitidos
- Checklists perdidos (missed)
- Fotos subidas vs requeridas

### **Dashboard Admin**

```javascript
{
  "completion_rate": 95.5,
  "avg_completion_time_minutes": 35,
  "total_checklists_month": 60,
  "completed": 57,
  "missed": 3,
  "most_skipped_items": [
    "FOTO 2: Amplia exterior",
    "Desconectar conexiones de gas"
  ]
}
```

---

## 🐛 Manejo de Errores

### **Errores Comunes**

```javascript
// Error: Checklist no encontrado
{
  "success": false,
  "error": "CHECKLIST_NOT_FOUND",
  "message": "No se encontró checklist para hoy"
}

// Error: Fuera de tiempo
{
  "success": false,
  "error": "OUT_OF_TIME_WINDOW",
  "message": "El checklist solo está disponible entre 18:00 y 19:00"
}

// Error: Ya completado
{
  "success": false,
  "error": "ALREADY_COMPLETED",
  "message": "Este checklist ya fue completado"
}

// Error: Foto requerida
{
  "success": false,
  "error": "PHOTO_REQUIRED",
  "message": "Este item requiere una foto"
}
```

---

## 📝 Notas de Implementación

### **Consideraciones Importantes**

1. **Zona Horaria**: Usar timezone de Chile (`America/Santiago`)
2. **Cron Job**: Configurar en servidor para crear checklists diarios
3. **Notificaciones**: Integrar con sistema existente de notificaciones
4. **Fotos**: Reutilizar sistema de compresión de compras
5. **Polling**: Actualizar cada 5 segundos cuando checklist está activo
6. **LocalStorage**: Guardar progreso localmente como backup
7. **Offline**: Permitir completar items offline y sincronizar después

### **Optimizaciones**

- Lazy loading de historial
- Caché de templates en frontend
- Compresión de imágenes antes de subir
- Debounce en auto-save (2 segundos)
- Service Worker para funcionalidad offline

---

## 🎯 Criterios de Éxito

- [ ] Checklist se activa automáticamente a las 18:00 y 00:45
- [ ] Notificación aparece en sistema de comandas
- [ ] Timer regresivo funciona correctamente
- [ ] Items se pueden marcar/desmarcar
- [ ] Fotos se comprimen y suben correctamente
- [ ] Progreso se guarda automáticamente
- [ ] Checklist expira después de 1 hora si no se completa
- [ ] Historial muestra todos los checklists pasados
- [ ] Sistema funciona en móvil y desktop
- [ ] Performance < 2 segundos de carga

---

## 📞 Soporte y Mantenimiento

### **Logs a Monitorear**

- Checklists creados diariamente
- Checklists completados vs perdidos
- Errores en subida de fotos
- Tiempo de respuesta de APIs
- Uso de storage para fotos

### **Backup**

- Backup diario de tabla `checklists`
- Backup semanal de fotos
- Retención: 90 días

---

**Fecha de Creación**: Enero 2025  
**Versión**: 1.0  
**Estado**: ✅ Base de Datos Completada - Listo para Sprint 1
