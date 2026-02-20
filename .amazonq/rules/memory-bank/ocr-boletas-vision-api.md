# OCR Automático de Boletas/Facturas - Google Cloud Vision API

## 📋 Resumen
Sistema de extracción automática de datos de boletas y facturas usando Google Cloud Vision API integrado al flujo de registro de compras de caja3.

## 💰 Costos Actualizados (Precios Oficiales Google)

### Tabla de Precios por Función (por 1,000 unidades)

| Función | 0-1,000/mes | 1,001-5,000,000/mes | 5,000,001+/mes |
|---------|-------------|---------------------|----------------|
| Detección de texto | **GRATIS** | $1.50 USD | $0.60 USD |
| Detección de texto en documentos | **GRATIS** | $1.50 USD | $0.60 USD |
| Detección de etiquetas | **GRATIS** | $1.50 USD | $1.00 USD |
| Detección de logotipos | **GRATIS** | $1.50 USD | $0.60 USD |
| Propiedades de imágenes | **GRATIS** | $1.50 USD | $0.60 USD |
| Ubicación de objetos | **GRATIS** | $2.25 USD | $1.50 USD |

> **Nota de facturación**: El último bloque de 1,000 unidades se prorratea. Ej: 4,300 solicitudes = (4 × $1.50) + (300/1,000 × $1.50) = $6.45

### Estimación La Ruta 11
- **Compras promedio**: ~30/día = ~900/mes
- **Función usada**: `TEXT_DETECTION` ($1.50/1,000 sobre el tier gratuito)
- **Costo mensual normal**: $0 USD (dentro del tier gratuito de 1,000)
- **Si excede 1,000**: ~$1.50 USD/mes para 2,000 boletas
- **Función recomendada**: `DOCUMENT_TEXT_DETECTION` — mejor para boletas con tablas y texto denso, mismo precio

## 🔗 Integración con Flujo de Registro de Compras

### Flujo Actual (ComprasApp.jsx)
```
Cajero abre tab "Registro"
→ Ingresa proveedor manualmente
→ Selecciona fecha
→ Busca ingrediente por nombre (fuzzy search)
→ Ingresa cantidad + precio unitario
→ Agrega item
→ Repite por cada item
→ Adjunta foto de boleta (solo como respaldo visual)
→ Registra compra
```

### Flujo Mejorado con OCR
```
Cajero abre tab "Registro"
→ Toca "🤖 Escanear Boleta"
→ Saca foto o sube imagen
→ OCR extrae automáticamente:
   - Proveedor → auto-llena campo proveedor
   - Fecha → auto-llena fecha_compra
   - Items con cantidades y precios → auto-agrega a formData.items
   - Total → validación cruzada
→ Cajero revisa/corrige datos extraídos
→ Registra compra (1 click)
```

### Mejoras Concretas al Flujo Actual

#### 1. Auto-llenado de Proveedor
- **Antes**: Cajero escribe nombre manualmente, busca en historial
- **Después**: OCR detecta nombre en primeras líneas de boleta → auto-llena + sugiere de proveedores existentes

#### 2. Auto-llenado de Items
- **Antes**: Por cada item → buscar ingrediente → ingresar cantidad → ingresar precio → click agregar (4 pasos × N items)
- **Después**: OCR parsea tabla de items → todos los items se agregan automáticamente con cantidad y precio
- **Impacto**: Compra de 10 items pasa de ~40 acciones a ~2 acciones

#### 3. Validación de Total
- **Antes**: Sin validación del monto total vs items ingresados
- **Después**: OCR extrae total de boleta → comparar con suma de items → alertar si hay diferencia

#### 4. Respaldo Integrado
- **Antes**: Foto de boleta se sube por separado después de registrar
- **Después**: La misma imagen usada para OCR se guarda como respaldo automáticamente (un solo upload)

#### 5. Detección de IVA
- **Antes**: Checkbox manual "c/IVA" por cada item
- **Después**: OCR detecta si boleta incluye IVA desglosado → aplica automáticamente

## 🔧 Implementación

### 1. Setup Google Cloud

```bash
# 1. Ir a https://console.cloud.google.com/
# 2. Crear proyecto "laruta11-ocr"
# 3. Habilitar Vision API
# 4. APIs & Services > Credentials > Service Account
# 5. Descargar JSON key
cd caja3
composer require google/cloud-vision
```

### 2. Backend PHP

#### Archivo: `caja3/api/compras/ocr_boleta.php`
```php
<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Google\Cloud\Vision\V1\ImageAnnotatorClient;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

putenv('GOOGLE_APPLICATION_CREDENTIALS=/var/www/caja3/credentials/vision-api-key.json');

function extractBoletaData($imagePath) {
    try {
        $imageAnnotator = new ImageAnnotatorClient();
        $imageContent = file_get_contents($imagePath);
        
        // DOCUMENT_TEXT_DETECTION es mejor para boletas con tablas
        $response = $imageAnnotator->documentTextDetection($imageContent);
        $annotation = $response->getFullTextAnnotation();
        
        if (!$annotation) {
            return ['success' => false, 'error' => 'No se detectó texto'];
        }
        
        $fullText = $annotation->getText();
        $imageAnnotator->close();
        
        return ['success' => true, 'data' => [
            'proveedor' => extractProveedor($fullText),
            'fecha'     => extractFecha($fullText),
            'total'     => extractTotal($fullText),
            'items'     => extractItems($fullText),
            'rut'       => extractRUT($fullText),
            'tiene_iva' => detectaIVA($fullText),
            'raw_text'  => $fullText
        ]];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function extractProveedor($text) {
    $lines = explode("\n", $text);
    foreach ($lines as $i => $line) {
        if ($i > 5) break;
        if (preg_match('/\d{1,2}\.\d{3}\.\d{3}-[\dkK]/', $line)) {
            return trim($lines[$i - 1] ?? '');
        }
    }
    return trim($lines[0] ?? '');
}

function extractFecha($text) {
    if (preg_match('/(\d{2})[\/\-\.](\d{2})[\/\-\.](\d{4})/', $text, $m)) {
        // Convertir a formato YYYY-MM-DD para input date
        return "{$m[3]}-{$m[2]}-{$m[1]}";
    }
    return null;
}

function extractTotal($text) {
    if (preg_match('/TOTAL[:\s]+\$?\s*(\d{1,3}(?:\.\d{3})*)/i', $text, $m)) {
        return floatval(str_replace('.', '', $m[1]));
    }
    preg_match_all('/\$?\s*(\d{1,3}(?:\.\d{3})+)/', $text, $matches);
    if (!empty($matches[1])) {
        return max(array_map(fn($m) => floatval(str_replace('.', '', $m)), $matches[1]));
    }
    return null;
}

function extractItems($text) {
    $items = [];
    foreach (explode("\n", $text) as $line) {
        if (preg_match('/^(.+?)\s+(\d+(?:[,.]\d+)?)\s+\$?\s*(\d{1,3}(?:\.\d{3})*)$/i', trim($line), $m)) {
            $items[] = [
                'nombre'   => trim($m[1]),
                'cantidad' => floatval(str_replace(',', '.', $m[2])),
                'precio'   => floatval(str_replace('.', '', $m[3]))
            ];
        }
    }
    return $items;
}

function extractRUT($text) {
    if (preg_match('/(\d{1,2}\.\d{3}\.\d{3}-[\dkK])/', $text, $m)) return $m[1];
    return null;
}

function detectaIVA($text) {
    return (bool) preg_match('/IVA|I\.V\.A|19%/i', $text);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['image'])) {
        echo json_encode(['success' => false, 'error' => 'No se recibió imagen']);
        exit;
    }
    echo json_encode(extractBoletaData($_FILES['image']['tmp_name']));
}
?>
```

### 3. Frontend - ComprasApp.jsx

#### Estados adicionales
```javascript
const [ocrLoading, setOcrLoading] = useState(false);
const [ocrData, setOcrData] = useState(null);
```

#### Handler OCR con auto-llenado completo
```javascript
const handleOCRScan = async (file) => {
  setOcrLoading(true);
  const fd = new FormData();
  fd.append('image', file);
  
  try {
    const res = await fetch('/api/compras/ocr_boleta.php', { method: 'POST', body: fd });
    const data = await res.json();
    
    if (data.success) {
      const d = data.data;
      
      // Auto-llenar proveedor y fecha
      setFormData(prev => ({
        ...prev,
        proveedor: d.proveedor || prev.proveedor,
        fecha_compra: d.fecha || prev.fecha_compra,
      }));
      
      // Auto-agregar items si se detectaron
      if (d.items?.length > 0) {
        const newItems = d.items.map(item => ({
          ingrediente_id: '',
          item_type: 'ingredient',
          nombre_item: item.nombre,
          cantidad: item.cantidad,
          unidad: 'kg',
          precio_unitario: item.precio.toFixed(2),
          con_iva: d.tiene_iva,
          subtotal: item.cantidad * item.precio
        }));
        setFormData(prev => ({ ...prev, items: [...prev.items, ...newItems] }));
      }
      
      setOcrData(d);
    } else {
      alert('❌ OCR Error: ' + data.error);
    }
  } catch (e) {
    alert('❌ Error al escanear boleta');
  } finally {
    setOcrLoading(false);
  }
};
```

#### Botón en UI (reemplaza el label de adjuntar foto)
```jsx
<div style={{display: 'flex', gap: '8px'}}>
  <label style={{flex: 1, /* estilos adjuntar */}}>
    <Image size={18} /> Adjuntar
    <input type="file" accept="image/*" style={{display: 'none'}}
      onChange={(e) => { const f = e.target.files[0]; if(f){ setRespaldoFile(f); setRespaldoPreview(URL.createObjectURL(f)); }}} />
  </label>
  
  <label style={{flex: 2, background: ocrLoading ? '#6b7280' : 'linear-gradient(135deg, #3b82f6, #2563eb)', color: 'white', /* ... */}}>
    {ocrLoading ? '⏳ Escaneando...' : '🤖 Escanear y Auto-llenar'}
    <input type="file" accept="image/*" style={{display: 'none'}} disabled={ocrLoading}
      onChange={(e) => { const f = e.target.files[0]; if(f){ setRespaldoFile(f); setRespaldoPreview(URL.createObjectURL(f)); handleOCRScan(f); }}} />
  </label>
</div>

{ocrData && (
  <div style={{marginTop: '8px', padding: '10px', background: '#f0fdf4', borderRadius: '6px', fontSize: '12px', border: '1px solid #10b981'}}>
    <strong>✅ OCR completado:</strong>
    {ocrData.proveedor && <div>🏪 Proveedor: {ocrData.proveedor}</div>}
    {ocrData.total && <div>💰 Total boleta: ${ocrData.total.toLocaleString('es-CL')}</div>}
    {ocrData.items?.length > 0 && <div>📦 Items detectados: {ocrData.items.length}</div>}
    {ocrData.tiene_iva && <div>🧾 IVA detectado: aplicado automáticamente</div>}
  </div>
)}
```

## 🔐 Seguridad

```bash
# .env (caja3)
GOOGLE_CLOUD_PROJECT_ID=laruta11-ocr
GOOGLE_APPLICATION_CREDENTIALS=/var/www/caja3/credentials/vision-api-key.json
```

- Service Account con solo permiso `Cloud Vision API User`
- Credenciales fuera del webroot
- Validar tipo/tamaño de imagen antes de enviar a API

## 📊 Monitoreo

```
https://console.cloud.google.com/apis/api/vision.googleapis.com/metrics
```

Métricas clave:
- Uso mensual vs límite gratuito (1,000)
- Tasa de éxito del parsing (campos extraídos / total scans)
- Tiempo promedio de respuesta

## 🆚 Alternativas

| Opción | Costo | Precisión | Estado |
|--------|-------|-----------|--------|
| **Cloud Vision** (recomendado) | Gratis hasta 1,000/mes | Alta | Pendiente implementar |
| **Tesseract.js** | Gratis 100% | Media | Ya instalado en caja3 |
| **Document AI** | $1.50/1,000 páginas | Muy alta (tablas) | Overkill para boletas simples |
| **AWS Textract** | $1.50/1,000 páginas | Alta | Sin tier gratuito permanente |

## 📝 Notas Importantes

1. **Tier gratuito permanente**: 1,000 imágenes/mes siempre gratis
2. **`DOCUMENT_TEXT_DETECTION` > `TEXT_DETECTION`**: Mejor para boletas con tablas, mismo precio
3. **Latencia**: ~1-2 segundos por imagen
4. **Tamaño máximo**: 20MB por imagen
5. **Formatos**: JPG, PNG, GIF, BMP, WEBP, PDF, TIFF
6. **La misma imagen sirve de respaldo**: No hay doble upload

## 🚀 Próximos Pasos

1. [ ] Crear proyecto en Google Cloud
2. [ ] Habilitar Vision API
3. [ ] Crear Service Account y descargar JSON key
4. [ ] Instalar `google/cloud-vision` en caja3 (`composer require google/cloud-vision`)
5. [ ] Implementar `caja3/api/compras/ocr_boleta.php`
6. [ ] Agregar estados `ocrLoading` y `ocrData` en ComprasApp.jsx
7. [ ] Reemplazar botón "Adjuntar" por botones "Adjuntar" + "Escanear y Auto-llenar"
8. [ ] Testing con boletas reales chilenas (Jumbo, Santa Isabel, proveedores locales)
9. [ ] Ajustar regex según resultados reales
10. [ ] Deploy a producción

## 📞 Recursos

- **Documentación**: https://cloud.google.com/vision/docs
- **Pricing**: https://cloud.google.com/vision/pricing
- **PHP Client**: https://github.com/googleapis/google-cloud-php-vision
- **Calculadora precios**: https://cloud.google.com/products/calculator
