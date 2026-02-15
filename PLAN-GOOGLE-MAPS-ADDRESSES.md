# 🗺️ PLAN: Integración Google Maps API - Validación de Direcciones

## 🎯 Problema Actual
- URLs de mapas fallan en el botón "Rider" 
- Clientes ingresan direcciones inválidas o incompletas
- Riders no pueden encontrar las ubicaciones correctamente
- Pérdida de tiempo y dinero en deliveries fallidos

---

## 🔍 Investigación: APIs de Google Maps

### 1. **Places API (Autocomplete)**
**Propósito**: Autocompletar direcciones mientras el usuario escribe

**Características**:
- ✅ Sugerencias en tiempo real
- ✅ Validación automática de direcciones
- ✅ Incluye coordenadas lat/lng
- ✅ Información detallada (calle, número, comuna, región)
- ✅ Restricción por país/región (Chile)

**Costo**: $2.83 USD por 1000 requests (después de 1000 gratis/mes)

### 2. **Geocoding API**
**Propósito**: Convertir direcciones en coordenadas y viceversa

**Características**:
- ✅ Validar si una dirección existe
- ✅ Obtener coordenadas exactas
- ✅ Normalizar formato de direcciones
- ✅ Detectar direcciones ambiguas

**Costo**: $5 USD por 1000 requests (después de 200 gratis/mes)

### 3. **Maps JavaScript API**
**Propósito**: Mostrar mapas interactivos y selección visual

**Características**:
- ✅ Mapa interactivo para confirmar ubicación
- ✅ Marcador arrastrable
- ✅ Vista satelital/calles
- ✅ Zoom automático a la dirección

**Costo**: $7 USD por 1000 cargas de mapa

---

## 🏗️ Arquitectura Propuesta

### **Fase 1: Validación Básica (INMEDIATA)**
```
Cliente escribe dirección → Geocoding API → Validar → Guardar coordenadas
```

### **Fase 2: Autocompletado (MEJORADA)**
```
Cliente escribe → Places Autocomplete → Selecciona → Confirma en mapa → Guardar
```

### **Fase 3: Selección Visual (AVANZADA)**
```
Mapa interactivo → Cliente arrastra pin → Confirma ubicación → Guardar coordenadas
```

---

## 💻 Implementación Técnica

### **1. Configuración API Key**
```javascript
// En app3 - Variables de entorno
GOOGLE_MAPS_API_KEY=AIzaSyAcK15oZ84Puu5Nc4wDQT_Wyht0xqkbO-A
```

### **2. Validación de Direcciones (Backend PHP)**
```php
// /api/validate_address.php
function validateAddress($address) {
    $apiKey = $_ENV['GOOGLE_MAPS_API_KEY'];
    $url = "https://maps.googleapis.com/maps/api/geocode/json?address=" . 
           urlencode($address . ", Arica, Chile") . "&key=" . $apiKey;
    
    $response = file_get_contents($url);
    $data = json_decode($response, true);
    
    if ($data['status'] === 'OK' && count($data['results']) > 0) {
        $result = $data['results'][0];
        return [
            'valid' => true,
            'formatted_address' => $result['formatted_address'],
            'lat' => $result['geometry']['location']['lat'],
            'lng' => $result['geometry']['location']['lng'],
            'place_id' => $result['place_id']
        ];
    }
    
    return ['valid' => false, 'error' => 'Dirección no encontrada'];
}
```

### **3. Autocompletado Frontend (React)**
```javascript
// Componente AddressAutocomplete
import { useLoadScript, Autocomplete } from '@react-google-maps/api';

const AddressInput = ({ onAddressSelect }) => {
    const { isLoaded } = useLoadScript({
        googleMapsApiKey: process.env.GOOGLE_MAPS_API_KEY,
        libraries: ['places']
    });

    const [autocomplete, setAutocomplete] = useState(null);

    const onPlaceChanged = () => {
        if (autocomplete !== null) {
            const place = autocomplete.getPlace();
            if (place.geometry) {
                onAddressSelect({
                    address: place.formatted_address,
                    lat: place.geometry.location.lat(),
                    lng: place.geometry.location.lng(),
                    placeId: place.place_id
                });
            }
        }
    };

    if (!isLoaded) return <div>Cargando...</div>;

    return (
        <Autocomplete
            onLoad={setAutocomplete}
            onPlaceChanged={onPlaceChanged}
            options={{
                componentRestrictions: { country: 'cl' },
                bounds: new google.maps.LatLngBounds(
                    new google.maps.LatLng(-18.5, -70.4), // SW Arica
                    new google.maps.LatLng(-18.4, -70.2)  // NE Arica
                )
            }}
        >
            <input
                type="text"
                placeholder="Ingresa tu dirección..."
                className="w-full p-3 border rounded-lg"
            />
        </Autocomplete>
    );
};
```

### **4. Mapa de Confirmación**
```javascript
// Componente MapConfirmation
const MapConfirmation = ({ address, lat, lng, onConfirm }) => {
    const [markerPosition, setMarkerPosition] = useState({ lat, lng });

    return (
        <GoogleMap
            zoom={16}
            center={markerPosition}
            mapContainerStyle={{ width: '100%', height: '300px' }}
        >
            <Marker
                position={markerPosition}
                draggable={true}
                onDragEnd={(e) => {
                    setMarkerPosition({
                        lat: e.latLng.lat(),
                        lng: e.latLng.lng()
                    });
                }}
            />
            <button 
                onClick={() => onConfirm(markerPosition)}
                className="absolute bottom-4 left-1/2 transform -translate-x-1/2 bg-green-500 text-white px-4 py-2 rounded-lg"
            >
                ✓ Confirmar Ubicación
            </button>
        </GoogleMap>
    );
};
```

---

## 🗄️ Cambios en Base de Datos

### **Nuevas Columnas en `tuu_orders`**
```sql
ALTER TABLE tuu_orders ADD COLUMN delivery_lat DECIMAL(10, 8) NULL;
ALTER TABLE tuu_orders ADD COLUMN delivery_lng DECIMAL(11, 8) NULL;
ALTER TABLE tuu_orders ADD COLUMN delivery_place_id VARCHAR(255) NULL;
ALTER TABLE tuu_orders ADD COLUMN delivery_formatted_address TEXT NULL;
```

---

## 🚀 Plan de Implementación

### **FASE 1: Fix Inmediato (1-2 días)**
1. ✅ Crear API de validación de direcciones
2. ✅ Integrar validación en checkout de app3
3. ✅ Guardar coordenadas en base de datos
4. ✅ Mejorar URLs de mapas en botón Rider

### **FASE 2: Autocompletado (3-5 días)**
1. ⏳ Instalar Google Maps React library
2. ⏳ Implementar componente de autocompletado
3. ⏳ Integrar en formulario de checkout
4. ⏳ Testing y ajustes

### **FASE 3: Mapa Visual (1 semana)**
1. ⏳ Componente de mapa interactivo
2. ⏳ Confirmación visual de ubicación
3. ⏳ Integración completa
4. ⏳ Testing exhaustivo

---

## 💰 Análisis de Costos

### **Estimación Mensual**
- **Pedidos delivery**: ~300/mes
- **Validaciones**: ~600 requests/mes (2 por pedido)
- **Costo estimado**: $1-3 USD/mes

### **ROI Esperado**
- ✅ Reducir deliveries fallidos (ahorro $50.000+ CLP/mes)
- ✅ Mejor experiencia cliente
- ✅ Menos tiempo perdido riders
- ✅ Menos reclamos y reembolsos

---

## 🔧 Configuración Requerida

### **1. Habilitar APIs en Google Cloud**
```bash
# APIs necesarias:
- Maps JavaScript API
- Places API  
- Geocoding API
```

### **2. Restricciones de API Key**
```
- Restricción por dominio: *.laruta11.cl
- Restricción por IP: [IP del servidor]
- Límites de uso: 1000 requests/día
```

### **3. Variables de Entorno**
```env
# app3/.env
GOOGLE_MAPS_API_KEY=AIzaSyAcK15oZ84Puu5Nc4wDQT_Wyht0xqkbO-A

# caja3/.env  
GOOGLE_MAPS_API_KEY=AIzaSyAcK15oZ84Puu5Nc4wDQT_Wyht0xqkbO-A
```

---

## 🧪 Testing Plan

### **Casos de Prueba**
1. ✅ Dirección válida completa
2. ✅ Dirección incompleta (sin número)
3. ✅ Dirección inexistente
4. ✅ Dirección fuera de Arica
5. ✅ Caracteres especiales
6. ✅ Direcciones de cuarteles (pre-configuradas)

### **Validación Manual**
- Probar con direcciones reales de Arica
- Verificar coordenadas en Google Maps
- Confirmar URLs generadas funcionan
- Testing en móviles

---

## 🚨 Consideraciones Importantes

### **Limitaciones**
- Requiere conexión a internet
- Dependencia de Google Services
- Costo por uso (aunque mínimo)

### **Fallbacks**
- Si API falla → permitir ingreso manual
- Guardar direcciones válidas en caché
- Direcciones pre-configuradas para cuarteles

### **Seguridad**
- API Key con restricciones
- Validación server-side
- Rate limiting

---

## 📋 Checklist de Implementación

### **Preparación**
- [ ] Verificar API Key actual de Google Maps
- [ ] Habilitar APIs necesarias en Google Cloud
- [ ] Configurar restricciones de seguridad
- [ ] Backup de base de datos

### **Desarrollo**
- [ ] Crear API de validación PHP
- [ ] Instalar dependencias React Google Maps
- [ ] Implementar componente autocompletado
- [ ] Integrar en checkout app3
- [ ] Actualizar botón Rider en caja3

### **Testing**
- [ ] Probar validación con direcciones reales
- [ ] Verificar URLs de mapas generadas
- [ ] Testing en móviles
- [ ] Validar costos de API

### **Deploy**
- [ ] Subir cambios a producción
- [ ] Monitorear uso de API
- [ ] Documentar para el equipo
- [ ] Capacitar usuarios

---

**Fecha de creación**: 2026-02-12  
**Responsable**: Ricardo  
**Prioridad**: 🔥 ALTA  
**Estado**: 📋 Planificación completa