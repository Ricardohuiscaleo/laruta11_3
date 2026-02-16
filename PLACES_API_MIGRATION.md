# Migración a Google Places API (New)

## Resumen
Migración del sistema de autocompletado de direcciones desde la API legacy de Google Places a la nueva Places API (New).

## Cambios Implementados

### Endpoint Actualizado
- **Antes**: `https://maps.googleapis.com/maps/api/place/autocomplete/json` (GET)
- **Ahora**: `https://places.googleapis.com/v1/places:autocomplete` (POST)

### Archivo Modificado
- `app3/api/location/autocomplete_proxy.php`

### Implementación Técnica

```php
// Endpoint correcto
$url = "https://places.googleapis.com/v1/places:autocomplete";

// Body de la solicitud
$postData = json_encode([
    'input' => $input,
    'includedRegionCodes' => ['CL'],
    'languageCode' => 'es'
]);

// Headers requeridos
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-Goog-Api-Key: ' . $apiKey
]);
```

### Formato de Respuesta

**API Nueva**:
```json
{
  "suggestions": [
    {
      "placePrediction": {
        "text": { "text": "Dirección completa" },
        "placeId": "ChIJ..."
      }
    }
  ]
}
```

**Transformado a formato legacy** (para compatibilidad con frontend):
```json
{
  "predictions": [
    {
      "description": "Dirección completa",
      "place_id": "ChIJ..."
    }
  ]
}
```

## Configuración Requerida

### 1. Habilitar API en Google Cloud Console
1. Ir a: https://console.cloud.google.com/apis/library/places-backend.googleapis.com
2. Clic en "Habilitar"
3. Esperar 1-2 minutos para activación

### 2. API Key
- Ubicación: `app3/config.php`
- Variable: `ruta11_google_maps_api_key`
- Valor actual: `AIzaSyAcK15oZ84Puu5Nc4wDQT_Wyht0xqkbO-A`

## Ventajas de la Nueva API

1. **Más precisa**: Mejores resultados de autocompletado
2. **Más campos**: Acceso a datos adicionales de lugares
3. **Mejor soporte**: API activamente mantenida por Google
4. **Futuro**: La API legacy será deprecada eventualmente

## Costos

### Autocomplete (New)
- **Gratis**: Hasta 10,000 requests/mes
- **Después**: ~$2,421 CLP por 1,000 requests
- **Volumen actual**: ~3,000/mes (dentro del tier gratuito)

## Componentes Afectados

### Frontend
- `app3/src/components/AddressAutocomplete.jsx`
- `app3/src/components/MenuApp.jsx` (checkout modal)
- `app3/src/components/CheckoutApp.jsx` (página checkout)

### Backend
- `app3/api/location/autocomplete_proxy.php` (proxy PHP)

## Testing

### Verificar Funcionamiento
1. Abrir: https://app.laruta11.cl/checkout/
2. Escribir en campo "Dirección de entrega"
3. Verificar que aparezcan sugerencias de direcciones chilenas

### Errores Comunes

**Error 404**: API no habilitada en Google Cloud Console
```json
{
  "error": "API error: 404"
}
```
**Solución**: Habilitar Places API (New) en Google Cloud Console

**Error 403**: API Key inválida o sin permisos
```json
{
  "error": "API error: 403"
}
```
**Solución**: Verificar API Key y permisos en Google Cloud Console

## Rollback (Si es necesario)

Si la nueva API presenta problemas, revertir a API legacy:

```php
// En autocomplete_proxy.php
$url = "https://maps.googleapis.com/maps/api/place/autocomplete/json?" . http_build_query([
    'input' => $input,
    'components' => "country:cl",
    'language' => 'es',
    'key' => $apiKey
]);

$response = file_get_contents($url);
echo $response;
```

## Referencias

- [Places API (New) - Documentación Oficial](https://developers.google.com/maps/documentation/places/web-service/op-overview)
- [Autocomplete (New) - Guía](https://developers.google.com/maps/documentation/places/web-service/autocomplete)
- [Migración desde API Legacy](https://developers.google.com/maps/legacy)

## Commits Relacionados

- `b1db236` - Fix: Usar endpoint correcto places:autocomplete con cURL
- `8cafbac` - Fix: Migrar a Places API (New) para autocomplete de direcciones
