# OCR Automático de Boletas/Facturas - Google Cloud Vision API

## 📋 Resumen
Sistema de extracción automática de datos de boletas y facturas usando Google Cloud Vision API con nivel gratuito.

## 💰 Costos

### Vision API - TEXT_DETECTION
| Volumen | Precio |
|---------|--------|
| 0 - 1,000 unidades/mes | **GRATIS** |
| 1,001 - 5,000,000 unidades/mes | $1.50 USD / 1,000 imágenes |
| 5,000,001+ unidades/mes | $0.60 USD / 1,000 imágenes |

### Estimación La Ruta 11
- **Compras promedio**: 30/día = ~900/mes
- **Costo mensual**: $0 USD (dentro del tier gratuito)
- **Si excede 1,000**: ~$1.50 USD/mes para 2,000 boletas

## 🎯 Casos de Uso

### Datos a Extraer
1. **Proveedor**: Nombre del negocio/empresa
2. **Fecha**: Fecha de emisión de la boleta
3. **Total**: Monto total de la compra
4. **Items**: Lista de productos con cantidades y precios
5. **RUT**: RUT del proveedor (opcional)
6. **Número de boleta**: Folio/número de documento

## 🔧 Implementación

### 1. Setup Google Cloud

#### Crear Proyecto
```bash
# 1. Ir a https://console.cloud.google.com/
# 2. Crear nuevo proyecto "laruta11-ocr"
# 3. Habilitar Vision API
# 4. Ir a "APIs & Services" > "Credentials"
# 5. Crear Service Account
# 6. Descargar JSON key
```

#### Instalar Librería PHP
```bash
cd caja3
composer require google/cloud-vision
```

### 2. Backend PHP

#### Archivo: `caja3/api/compras/ocr_boleta.php`
```php
<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\Image;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Configurar credenciales
putenv('GOOGLE_APPLICATION_CREDENTIALS=/path/to/service-account.json');

function extractBoletaData($imagePath) {
    try {
        $imageAnnotator = new ImageAnnotatorClient();
        
        // Leer imagen
        $imageContent = file_get_contents($imagePath);
        $image = (new Image())->setContent($imageContent);
        
        // Detectar texto
        $response = $imageAnnotator->textDetection($image);
        $texts = $response->getTextAnnotations();
        
        if (empty($texts)) {
            return ['success' => false, 'error' => 'No se detectó texto'];
        }
        
        // Texto completo
        $fullText = $texts[0]->getDescription();
        
        // Parsear datos
        $data = [
            'proveedor' => extractProveedor($fullText),
            'fecha' => extractFecha($fullText),
            'total' => extractTotal($fullText),
            'items' => extractItems($fullText),
            'rut' => extractRUT($fullText),
            'numero_boleta' => extractNumeroBoleta($fullText),
            'raw_text' => $fullText // Para debugging
        ];
        
        $imageAnnotator->close();
        
        return ['success' => true, 'data' => $data];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function extractProveedor($text) {
    // Buscar nombre de empresa (primeras líneas, antes de RUT)
    $lines = explode("\n", $text);
    $proveedor = '';
    
    foreach ($lines as $i => $line) {
        if ($i > 5) break; // Solo primeras 5 líneas
        
        // Si encuentra RUT, el proveedor es la línea anterior
        if (preg_match('/\d{1,2}\.\d{3}\.\d{3}-[\dkK]/', $line)) {
            $proveedor = trim($lines[$i - 1] ?? '');
            break;
        }
    }
    
    return $proveedor ?: trim($lines[0] ?? '');
}

function extractFecha($text) {
    // Formatos: DD/MM/YYYY, DD-MM-YYYY, DD.MM.YYYY
    if (preg_match('/(\d{2})[\/\-\.](\d{2})[\/\-\.](\d{4})/', $text, $matches)) {
        return $matches[0];
    }
    return null;
}

function extractTotal($text) {
    // Buscar "TOTAL" seguido de monto
    if (preg_match('/TOTAL[:\s]+\$?\s*(\d{1,3}(?:\.\d{3})*(?:,\d{2})?)/i', $text, $matches)) {
        // Convertir formato chileno a número
        $total = str_replace(['.', ','], ['', '.'], $matches[1]);
        return floatval($total);
    }
    
    // Buscar último monto grande en el texto
    preg_match_all('/\$?\s*(\d{1,3}(?:\.\d{3})+)/', $text, $matches);
    if (!empty($matches[1])) {
        $montos = array_map(function($m) {
            return floatval(str_replace('.', '', $m));
        }, $matches[1]);
        return max($montos);
    }
    
    return null;
}

function extractItems($text) {
    $items = [];
    $lines = explode("\n", $text);
    
    foreach ($lines as $line) {
        // Buscar líneas con formato: PRODUCTO CANTIDAD $PRECIO
        if (preg_match('/^(.+?)\s+(\d+(?:,\d+)?)\s+\$?\s*(\d{1,3}(?:\.\d{3})*)$/i', trim($line), $matches)) {
            $items[] = [
                'nombre' => trim($matches[1]),
                'cantidad' => floatval(str_replace(',', '.', $matches[2])),
                'precio' => floatval(str_replace('.', '', $matches[3]))
            ];
        }
    }
    
    return $items;
}

function extractRUT($text) {
    // Formato: XX.XXX.XXX-X
    if (preg_match('/(\d{1,2}\.\d{3}\.\d{3}-[\dkK])/', $text, $matches)) {
        return $matches[1];
    }
    return null;
}

function extractNumeroBoleta($text) {
    // Buscar "N°", "Nro", "Folio" seguido de número
    if (preg_match('/(?:N[°º]|Nro\.?|Folio)[:\s]*(\d+)/i', $text, $matches)) {
        return $matches[1];
    }
    return null;
}

// Endpoint principal
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['image'])) {
        echo json_encode(['success' => false, 'error' => 'No se recibió imagen']);
        exit;
    }
    
    $tmpPath = $_FILES['image']['tmp_name'];
    $result = extractBoletaData($tmpPath);
    
    echo json_encode($result);
}
?>
```

### 3. Frontend Integration

#### Modificar ComprasApp.jsx
```javascript
const [ocrLoading, setOcrLoading] = useState(false);
const [ocrData, setOcrData] = useState(null);

const handleOCRScan = async (file) => {
  setOcrLoading(true);
  
  const formData = new FormData();
  formData.append('image', file);
  
  try {
    const response = await fetch('/api/compras/ocr_boleta.php', {
      method: 'POST',
      body: formData
    });
    
    const data = await response.json();
    
    if (data.success) {
      // Auto-llenar formulario
      setFormData({
        ...formData,
        proveedor: data.data.proveedor || '',
        fecha_compra: data.data.fecha || formData.fecha_compra,
        // Agregar items automáticamente
      });
      
      setOcrData(data.data);
      alert('✅ Boleta escaneada correctamente');
    } else {
      alert('❌ Error: ' + data.error);
    }
  } catch (error) {
    alert('❌ Error al escanear boleta');
  } finally {
    setOcrLoading(false);
  }
};
```

#### UI Component
```jsx
<div className="form-group">
  <label><Paperclip size={16} /> Respaldo (Boleta/Factura)</label>
  
  {!respaldoPreview ? (
    <div style={{display: 'flex', gap: '8px'}}>
      <label style={{flex: 1, ...uploadButtonStyle}}>
        <Image size={18} /> Adjuntar Foto
        <input
          type="file"
          accept="image/*"
          style={{display: 'none'}}
          onChange={(e) => {
            const file = e.target.files[0];
            if (file) {
              setRespaldoFile(file);
              setRespaldoPreview(URL.createObjectURL(file));
            }
          }}
        />
      </label>
      
      <label style={{flex: 1, ...scanButtonStyle}}>
        {ocrLoading ? '⏳ Escaneando...' : '🤖 Escanear Boleta'}
        <input
          type="file"
          accept="image/*"
          style={{display: 'none'}}
          disabled={ocrLoading}
          onChange={(e) => {
            const file = e.target.files[0];
            if (file) {
              setRespaldoFile(file);
              setRespaldoPreview(URL.createObjectURL(file));
              handleOCRScan(file);
            }
          }}
        />
      </label>
    </div>
  ) : (
    // Preview existente...
  )}
  
  {ocrData && (
    <div style={{marginTop: '8px', padding: '10px', background: '#f0fdf4', borderRadius: '6px', fontSize: '12px'}}>
      <strong>✅ Datos extraídos:</strong>
      <div>Proveedor: {ocrData.proveedor}</div>
      <div>Total: ${ocrData.total?.toLocaleString('es-CL')}</div>
      <div>Items: {ocrData.items?.length || 0}</div>
    </div>
  )}
</div>
```

## 🔐 Seguridad

### Variables de Entorno
```bash
# .env
GOOGLE_CLOUD_PROJECT_ID=laruta11-ocr
GOOGLE_APPLICATION_CREDENTIALS=/var/www/caja3/credentials/vision-api-key.json
```

### Permisos Service Account
- **Cloud Vision API User**: Permiso mínimo necesario
- **NO dar permisos de admin**: Solo lectura de Vision API

## 📊 Monitoreo

### Métricas a Trackear
1. **Uso mensual**: Cuántas imágenes procesadas
2. **Tasa de éxito**: % de boletas correctamente parseadas
3. **Campos extraídos**: Qué campos se detectan mejor
4. **Tiempo de procesamiento**: Latencia promedio

### Dashboard Google Cloud
```
https://console.cloud.google.com/apis/api/vision.googleapis.com/metrics
```

## 🎯 Mejoras Futuras

### Fase 1: OCR Básico (Actual)
- ✅ Extraer texto completo
- ✅ Parsear proveedor, fecha, total
- ✅ Auto-llenar formulario

### Fase 2: Machine Learning
- 🔄 Entrenar modelo con boletas chilenas
- 🔄 Mejorar detección de items
- 🔄 Reconocer formatos específicos de proveedores

### Fase 3: Validación Inteligente
- 🔄 Validar RUT con API SII
- 🔄 Verificar coherencia de montos
- 🔄 Sugerir correcciones

## 🆚 Alternativas

### Tesseract.js (Client-Side)
- ✅ **Gratis 100%**
- ✅ Ya instalado en caja3
- ⚠️ Menos preciso
- ⚠️ Más lento (procesa en navegador)

### Document AI (Google)
- ⚠️ Más caro ($1.50 por 1,000 páginas)
- ✅ Mejor para facturas estructuradas
- ✅ Extrae tablas automáticamente

### AWS Textract
- ⚠️ Sin tier gratuito permanente
- ⚠️ $1.50 por 1,000 páginas
- ✅ Buena precisión

## 📝 Notas Importantes

1. **Tier gratuito es permanente**: 1,000 imágenes/mes SIEMPRE gratis
2. **No hay cargos ocultos**: Solo pagas por lo que usas
3. **Latencia**: ~1-2 segundos por imagen
4. **Tamaño máximo**: 20MB por imagen
5. **Formatos soportados**: JPG, PNG, GIF, BMP, WEBP, RAW, ICO, PDF, TIFF

## 🚀 Próximos Pasos

1. [ ] Crear proyecto en Google Cloud
2. [ ] Habilitar Vision API
3. [ ] Crear Service Account y descargar JSON
4. [ ] Instalar `google/cloud-vision` en caja3
5. [ ] Implementar `ocr_boleta.php`
6. [ ] Agregar botón "Escanear Boleta" en UI
7. [ ] Testing con boletas reales chilenas
8. [ ] Ajustar regex según resultados
9. [ ] Deploy a producción
10. [ ] Monitorear uso mensual

## 📞 Recursos

- **Documentación**: https://cloud.google.com/vision/docs
- **Pricing**: https://cloud.google.com/vision/pricing
- **PHP Client**: https://github.com/googleapis/google-cloud-php-vision
- **Ejemplos**: https://cloud.google.com/vision/docs/ocr
