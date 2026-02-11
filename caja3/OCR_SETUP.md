# 🧪 Laboratorio OCR - Instrucciones de Instalación

## 📦 Instalación

Ejecuta en tu terminal:

```bash
npm install tesseract.js
```

## 🚀 Uso

1. Inicia el servidor de desarrollo:
```bash
npm run dev
```

2. Accede a la página de pruebas:
```
http://localhost:4321/test-ocr
```

## 📸 Cómo Probar

1. **Sube una foto** de boleta (Unimarc, Lider, Santa Isabel, etc.)
2. **Espera 3-5 segundos** mientras Tesseract escanea
3. **Revisa los datos extraídos**:
   - Proveedor detectado
   - Monto total
   - Fecha
   - Items (si se detectan)
4. **Copia el texto OCR crudo** para analizar patrones
5. **Ajusta los patrones** en `src/utils/receiptParser.js` según necesites

## 🎯 Calibración de Patrones

### Si el monto no se detecta bien:
Edita en `receiptParser.js`:
```javascript
unimarc: {
  total: /TOTAL\s*\$?\s*([\d.]+)/i,  // ← Ajusta este regex
}
```

### Si la fecha falla:
```javascript
unimarc: {
  date: /(\d{2}\/\d{2}\/\d{4})/,  // ← Ajusta formato
}
```

### Agregar nuevo proveedor:
```javascript
miproveedor: {
  identifier: /MI PROVEEDOR/i,
  total: /TOTAL.*?([\d.]+)/i,
  date: /(\d{2}\/\d{2}\/\d{4})/,
  provider: 'MI PROVEEDOR'
}
```

## 📊 Flujo de Trabajo

1. **Prueba con 3-5 boletas** de cada proveedor
2. **Copia el texto OCR** de las que fallan
3. **Ajusta los regex** en `receiptParser.js`
4. **Vuelve a probar** hasta lograr 80%+ confianza
5. **Una vez calibrado**, integra a `ComprasApp.jsx`

## 🔧 Archivos Creados

- `src/pages/test-ocr.astro` - Página de pruebas
- `src/components/OCRTester.jsx` - UI del tester
- `src/utils/receiptParser.js` - Algoritmos de parseo

## ✅ Próximo Paso

Una vez que los patrones funcionen bien (80%+ confianza), integraremos el código a `ComprasApp.jsx` con un simple botón "📸 Escanear Boleta".
