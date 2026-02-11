# Sistema de Food Trucks - Guía de Implementación

## 📋 Resumen
Sistema completo de gestión de food trucks con:
- Cálculo automático de horarios con zona horaria de Chile
- Soporte para horarios que cruzan medianoche
- UI/UX mejorada con iconos lucide-react
- Integración con Google Maps

---

## 🗄️ Base de Datos

### Tabla: `food_trucks`

```sql
CREATE TABLE food_trucks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    descripcion TEXT,
    direccion VARCHAR(500) NOT NULL,
    latitud DECIMAL(10, 8) NOT NULL,
    longitud DECIMAL(11, 8) NOT NULL,
    horario_inicio TIME DEFAULT '10:00:00',
    horario_fin TIME DEFAULT '22:00:00',
    activo BOOLEAN DEFAULT TRUE,
    tarifa_delivery INT DEFAULT 2000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

**Campos importantes:**
- `horario_inicio` y `horario_fin`: Formato `TIME` (HH:MM:SS)
- `activo`: Boolean para activar/desactivar truck
- `tarifa_delivery`: Costo de delivery en CLP

---

## 🔧 Backend APIs

### Estructura de carpetas
```
api/
└── food_trucks/
    ├── setup_table.php      # Crear tabla e insertar datos ejemplo
    ├── get_all.php          # Obtener todos los trucks
    ├── get_by_id.php        # Obtener truck por ID
    ├── save.php             # Crear/actualizar truck
    ├── delete.php           # Eliminar truck
    ├── get_nearby.php       # Obtener trucks cercanos
    └── get_exact_coordinates.php
```

### API Principal: `get_all.php`

```php
<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Buscar config.php en múltiples niveles
$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

if (!$config) {
    die(json_encode(['success' => false, 'error' => 'Configuración no encontrada']));
}

$conn = new mysqli(
    $config['app_db_host'], 
    $config['app_db_user'], 
    $config['app_db_pass'], 
    $config['app_db_name']
);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Error de conexión']));
}

$result = $conn->query("SELECT * FROM food_trucks ORDER BY nombre");
$trucks = [];

while ($row = $result->fetch_assoc()) {
    $trucks[] = $row;
}

echo json_encode(['success' => true, 'trucks' => $trucks]);
$conn->close();
?>
```

---

## 🎨 Frontend - MenuApp.jsx

### 1. Imports de Iconos Lucide

```javascript
import { 
    PlusCircle, X, Star, ShoppingCart, MinusCircle, User, ZoomIn,
    Award, ChefHat, GlassWater, CupSoda, Droplets,
    Eye, Heart, MessageSquare, Calendar, Search, Bike, Caravan, 
    ChevronDown, ChevronUp, Package,
    // Iconos para Food Trucks
    Truck, TruckIcon, Navigation, MapPin, Clock, CheckCircle2, XCircle
} from 'lucide-react';
```

### 2. Componente FoodTrucksModal

#### Header con Degradado

```javascript
<div className="bg-gradient-to-r from-orange-500 to-orange-600 text-white flex justify-between items-center" style={{padding: 'clamp(12px, 3vw, 16px)'}}>
    <h2 className="font-bold flex items-center gap-2" style={{fontSize: 'clamp(16px, 4vw, 20px)'}}>
        <Truck size={22} />
        Food Trucks Cercanos
        {deliveryZone && (
            <span className={`ml-2 text-xs px-2 py-1 rounded-full ${
                deliveryZone.in_delivery_zone 
                    ? 'bg-white/20 text-white' 
                    : 'bg-red-500 text-white'
            }`}>
                {deliveryZone.in_delivery_zone ? (
                    <span className="flex items-center gap-1">
                        <TruckIcon size={12} />
                        {deliveryZone.zones[0]?.tiempo_estimado}min
                    </span>
                ) : (
                    <span className="flex items-center gap-1">
                        <XCircle size={12} />
                        Sin delivery
                    </span>
                )}
            </span>
        )}
    </h2>
    <button onClick={onClose} className="p-1 hover:bg-white/20 rounded-full transition-colors">
        <X size={20} />
    </button>
</div>
```

### 3. Cálculo de Horarios (Zona Horaria Chile)

```javascript
// Obtener hora actual en zona horaria de Chile (America/Santiago)
const now = new Date();
const chileTime = new Date(now.toLocaleString('en-US', { timeZone: 'America/Santiago' }));
const hours = chileTime.getHours().toString().padStart(2, '0');
const minutes = chileTime.getMinutes().toString().padStart(2, '0');
const seconds = chileTime.getSeconds().toString().padStart(2, '0');
const currentTime = `${hours}:${minutes}:${seconds}`;

// Manejar horarios que cruzan medianoche (ej: 18:00 - 00:30)
let isOpen;
if (truck.horario_inicio > truck.horario_fin) {
    // Cruza medianoche: abierto si hora >= inicio O hora <= fin
    isOpen = truck.activo && (currentTime >= truck.horario_inicio || currentTime <= truck.horario_fin);
} else {
    // Normal: abierto si hora >= inicio Y hora <= fin
    isOpen = truck.activo && currentTime >= truck.horario_inicio && currentTime <= truck.horario_fin;
}
```

**Lógica de horarios:**
- **Horario normal** (10:00 - 22:00): `currentTime >= inicio && currentTime <= fin`
- **Cruza medianoche** (18:00 - 00:30): `currentTime >= inicio || currentTime <= fin`

### 4. Card de Food Truck Mejorada

```javascript
<div key={truck.id} className="border border-gray-200 rounded-xl p-4 hover:shadow-md transition-shadow bg-white">
    {/* Header con icono y distancia */}
    <div className="flex justify-between items-start mb-3">
        <div className="flex items-start gap-2">
            <div className="bg-orange-100 p-2 rounded-lg">
                <Truck size={18} className="text-orange-600" />
            </div>
            <div>
                <h3 className="font-bold text-gray-800 text-sm">{truck.nombre}</h3>
                <p className="text-xs text-gray-500 mt-0.5">{truck.descripcion}</p>
            </div>
        </div>
        <div className="flex items-center gap-1 text-orange-600 font-semibold text-sm bg-orange-50 px-2 py-1 rounded-lg">
            <Navigation size={12} />
            {truck.distance ? `${truck.distance.toFixed(1)} km` : '...'}
        </div>
    </div>
    
    {/* Dirección */}
    <div className="flex items-center gap-1.5 text-xs text-gray-600 mb-3">
        <MapPin size={12} className="text-gray-400" />
        <p className="line-clamp-1">{truck.direccion}</p>
    </div>
    
    {/* Badges de información */}
    <div className="flex flex-wrap items-center gap-2 mb-3">
        {/* Horario */}
        <div className="flex items-center gap-1 text-xs bg-gray-50 px-2 py-1.5 rounded-lg">
            <Clock size={12} className="text-gray-500" />
            <span className="text-gray-700 font-medium">
                {truck.horario_inicio.slice(0,5)} - {truck.horario_fin.slice(0,5)}
            </span>
        </div>
        
        {/* Estado Abierto/Cerrado */}
        <span className={`px-2.5 py-1.5 rounded-lg text-xs font-medium flex items-center gap-1 ${
            isOpen ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
        }`}>
            {isOpen ? (
                <><CheckCircle2 size={12} /> Abierto</>
            ) : (
                <><XCircle size={12} /> Cerrado</>
            )}
        </span>
        
        {/* Tarifa Delivery */}
        {truck.tarifa_delivery && (
            <div className="flex items-center gap-1 text-xs bg-blue-50 px-2 py-1.5 rounded-lg">
                <TruckIcon size={12} className="text-blue-600" />
                <span className="text-blue-700 font-medium">
                    ${parseInt(truck.tarifa_delivery).toLocaleString('es-CL')}
                </span>
            </div>
        )}
    </div>
    
    {/* Botón Cómo llegar */}
    <button 
        onClick={() => openDirections(truck)}
        className="w-full bg-gradient-to-r from-blue-500 to-blue-600 text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:from-blue-600 hover:to-blue-700 transition-all flex items-center justify-center gap-2 shadow-sm"
    >
        <Navigation size={16} />
        Cómo llegar
    </button>
</div>
```

### 5. Estado Vacío

```javascript
<div className="text-center py-12 p-4">
    <div className="bg-gray-100 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4">
        <Truck size={40} className="text-gray-400" />
    </div>
    <p className="text-gray-700 font-medium text-lg">No hay food trucks cerca</p>
    <p className="text-sm text-gray-500 mt-2 flex items-center justify-center gap-1">
        {!userLocation ? (
            <><MapPin size={14} /> Activa tu ubicación para encontrar trucks cercanos</>
        ) : (
            <><Navigation size={14} /> No hay trucks en un radio de 10km</>
        )}
    </p>
</div>
```

---

## 🎯 Casos de Uso

### Caso 1: Horario Normal
```
Horario: 10:00:00 - 22:00:00
Hora actual: 15:30:00
Resultado: ABIERTO ✅
Lógica: 15:30:00 >= 10:00:00 && 15:30:00 <= 22:00:00
```

### Caso 2: Horario que Cruza Medianoche
```
Horario: 18:00:00 - 00:30:00
Hora actual: 20:00:00
Resultado: ABIERTO ✅
Lógica: 20:00:00 >= 18:00:00 (cumple primera condición)

Hora actual: 00:15:00
Resultado: ABIERTO ✅
Lógica: 00:15:00 <= 00:30:00 (cumple segunda condición)

Hora actual: 02:00:00
Resultado: CERRADO ❌
Lógica: No cumple ninguna condición
```

---

## 📱 Admin Panel

### Página: `/admin/food-trucks`

**Características:**
- Formulario para crear/editar trucks
- Búsqueda de direcciones con Google Geocoding API
- Mapa interactivo con marcadores
- Inputs tipo `time` para horarios (HH:MM)
- Lista de trucks con acciones (Editar/Eliminar)

**Inputs importantes:**
```html
<input type="time" id="horario_inicio" value="10:00">
<input type="time" id="horario_fin" value="22:00">
```

Los valores se guardan en BD como `TIME` (HH:MM:SS), agregando `:00` automáticamente.

---

## 🚀 Pasos para Replicar

### 1. Base de Datos
```bash
# Ejecutar setup
php api/food_trucks/setup_table.php
```

### 2. Backend
- Copiar carpeta `api/food_trucks/` completa
- Verificar que `config.php` esté accesible

### 3. Frontend

**Modificar imports en MenuApp.jsx:**
```javascript
import { 
    // ... otros iconos
    Truck, TruckIcon, Navigation, MapPin, Clock, CheckCircle2, XCircle
} from 'lucide-react';
```

**Reemplazar componente FoodTrucksModal completo** con el código proporcionado en esta guía.

### 4. Testing

**Probar horarios normales:**
- Crear truck con horario 10:00 - 22:00
- Verificar que muestre "Abierto" entre esas horas

**Probar horarios que cruzan medianoche:**
- Crear truck con horario 18:00 - 00:30
- Verificar que muestre "Abierto" desde 18:00 hasta 00:30

**Probar zona horaria:**
- Verificar que use hora de Chile (UTC-3)
- Comparar con hora del servidor

---

## 🎨 Iconos Lucide Usados

| Icono | Uso |
|-------|-----|
| `Truck` | Icono principal de food truck |
| `TruckIcon` | Badge de delivery |
| `Navigation` | Distancia y botón "Cómo llegar" |
| `MapPin` | Ubicación/dirección |
| `Clock` | Horarios |
| `CheckCircle2` | Estado "Abierto" |
| `XCircle` | Estado "Cerrado" / Sin delivery |

---

## ⚠️ Puntos Críticos

1. **Zona Horaria:** Siempre usar `America/Santiago` para Chile
2. **Formato de Hora:** Construir string `HH:MM:SS` manualmente con `padStart(2, '0')`
3. **Comparación:** Usar comparación de strings directa (funciona con formato TIME)
4. **Medianoche:** Detectar con `horario_inicio > horario_fin` y usar lógica OR
5. **Cache:** Agregar headers `no-cache` en APIs para datos en tiempo real

---

## 📊 Estructura de Respuesta API

```json
{
  "success": true,
  "trucks": [
    {
      "id": 1,
      "nombre": "La Ruta 11 - Plaza Maipú",
      "descripcion": "Food truck principal",
      "direccion": "Plaza de Maipú, Chile",
      "latitud": "-33.51100000",
      "longitud": "-70.75800000",
      "horario_inicio": "10:00:00",
      "horario_fin": "22:00:00",
      "activo": "1",
      "tarifa_delivery": "2000",
      "created_at": "2024-01-01 00:00:00",
      "updated_at": "2024-01-01 00:00:00"
    }
  ]
}
```

---

## 🔗 Integración con Google Maps

**Abrir direcciones:**
```javascript
const openDirections = (truck) => {
    const url = `https://www.google.com/maps/dir/${userLocation?.latitude},${userLocation?.longitude}/${truck.latitud},${truck.longitud}`;
    window.open(url, '_blank');
};
```

**Embed en iframe:**
```javascript
<iframe
    src={`https://www.google.com/maps/embed/v1/directions?key=YOUR_API_KEY&origin=${userLocation.latitude},${userLocation.longitude}&destination=${trucks[0]?.latitud},${trucks[0]?.longitud}&mode=driving&zoom=14`}
/>
```

---

## ✅ Checklist de Implementación

- [ ] Crear tabla `food_trucks` en BD
- [ ] Copiar APIs en `api/food_trucks/`
- [ ] Agregar imports de iconos lucide en MenuApp.jsx
- [ ] Reemplazar componente FoodTrucksModal
- [ ] Implementar lógica de horarios con zona horaria
- [ ] Agregar lógica para horarios que cruzan medianoche
- [ ] Probar con diferentes horarios
- [ ] Verificar UI/UX en móvil y desktop
- [ ] Configurar Google Maps API key
- [ ] Probar integración con delivery zones

---

**Última actualización:** Diciembre 2024
**Versión:** 1.0
