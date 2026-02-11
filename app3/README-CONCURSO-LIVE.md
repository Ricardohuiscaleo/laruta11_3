# 🏆 SISTEMA DE MONITOR EN VIVO - CONCURSO LA RUTA 11

## 📋 Archivos Creados

### 🎮 **Páginas Frontend**
1. **`/src/pages/concurso/admin.astro`** - Panel de administración móvil
2. **`/src/pages/concurso/live.astro`** - Monitor en vivo para TV/pantalla grande

### 🔧 **APIs Backend**
3. **`/api/update_concurso_state.php`** - Actualizar estado del torneo
4. **`/api/get_concurso_live.php`** - Obtener estado actual del torneo

### 📊 **Base de Datos**
5. **`/api/setup_concurso_live_table.sql`** - Script para crear tabla `concurso_state`

## 🎯 URLs del Sistema

### **Admin (Móvil)**
```
https://app.laruta11.cl/concurso/admin
```
- Control del torneo desde celular
- 8 participantes dummy para testing
- Selección de ganadores por tap
- Control manual de progresión de etapas

### **Monitor (TV/Pantalla)**
```
https://app.laruta11.cl/concurso/live
```
- Visualización en tiempo real
- Actualización en tiempo real cada 1 segundo
- Diseño optimizado para pantallas grandes
- Animaciones y efectos visuales

## 🗄️ Base de Datos

### **Tabla Nueva**: `concurso_state`
```sql
CREATE TABLE IF NOT EXISTS concurso_state (
    id INT PRIMARY KEY DEFAULT 1,
    tournament_data JSON NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### **Configuración**
- **Base de datos**: `u958525313_app` (misma del sistema principal)
- **Búsqueda config**: Multinivel hasta 5 niveles
- **Conexión**: PDO con manejo de errores

## 🚀 Flujo de Uso

### **1. Preparación**
```
1. Ejecutar SQL: setup_concurso_live_table.sql
2. Abrir admin en móvil: /concurso/admin
3. Abrir monitor en TV: /concurso/live
4. Presionar "Iniciar Torneo" en admin
```

### **2. Durante el Evento**
```
Admin (móvil):
- Tap en ganador de cada match
- Click "➡️ Avanzar a Siguiente Etapa" para continuar
- Control manual total sobre progresión
- Sincroniza con backend en tiempo real

Monitor (TV):
- Muestra progreso actualizado
- Animaciones para matches en vivo
- Declara campeón al final
```

## 🎨 Características

### **Admin Panel**
- ✅ **Touch-friendly**: Botones grandes para móvil
- ✅ **Estados visuales**: Verde (ganador), Rojo (eliminado)
- ✅ **Control manual**: Botón para avanzar etapas manualmente
- ✅ **Participantes dummy**: 8 participantes para testing
- ✅ **Sync automático**: Guarda estado en MySQL

### **Monitor Live**
- ✅ **Diseño TV**: Tipografía grande, colores contrastantes
- ✅ **Bracket completo**: Visualización de todas las rondas
- ✅ **Animaciones**: Winner glow, live pulse, crown bounce
- ✅ **Polling**: Actualización cada 1 segundo (tiempo real)
- ✅ **Formato piramidal**: Campeón → Final → Semifinales → Cuartos
- ✅ **Tags visuales**: "GANADOR" en participantes que ganan
- ✅ **Nombres de pila**: Solo primer nombre para mejor legibilidad

## 🔧 APIs Técnicas

### **`update_concurso_state.php`**
```php
POST /api/update_concurso_state.php
Content-Type: application/json

{
  "participants": {...},
  "currentRound": "cuartos",
  "matches": [...],
  "status": "active"
}
```

### **`get_concurso_live.php`**
```php
GET /api/get_concurso_live.php

Response:
{
  "participants": {...},
  "rounds": [...],
  "champion": "p5",
  "last_updated": "2025-01-15 14:30:00"
}
```

## 📱 Participantes Dummy

```javascript
const DUMMY_PARTICIPANTS = [
  { id: 'p1', name: 'Javier Pérez', seed: 1 },
  { id: 'p2', name: 'Sofía Reyes', seed: 8 },
  { id: 'p3', name: 'Miguel Soto', seed: 4 },
  { id: 'p4', name: 'Laura Gómez', seed: 5 },
  { id: 'p5', name: 'Ricardo Vidal', seed: 2 },
  { id: 'p6', name: 'Andrea Díaz', seed: 7 },
  { id: 'p7', name: 'Carlos Leal', seed: 3 },
  { id: 'p8', name: 'Elena Rojas', seed: 6 }
];
```

## 🎯 Para el Evento (11 Oct 2025)

### **Setup Día del Evento**
1. **Ejecutar SQL** en phpMyAdmin
2. **Conectar TV** a `/concurso/live`
3. **Admin en móvil** `/concurso/admin`
4. **Iniciar torneo** cuando lleguen participantes reales

### **Integración con Participantes Reales**
- El sistema detecta automáticamente participantes de `concurso_registros`
- Fallback a dummy participants si no hay registros reales
- Transición suave entre modo testing y modo real

## ✅ Estado Actual

- ✅ **Sistema completo** funcionando
- ✅ **Testing con dummies** listo
- ✅ **Base de datos** configurada
- ✅ **APIs** integradas con sistema principal
- ✅ **Responsive design** móvil y TV
- ✅ **Animaciones** y efectos visuales

¡El sistema está 100% listo para el concurso del 11 de octubre! 🏆