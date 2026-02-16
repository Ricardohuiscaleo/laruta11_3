# 🐛 Debug: Subida de Fotos en Móvil

## Problema
Error "No se recibió un archivo válido" al subir fotos desde iPhone en `/compras/`

## Cambios Realizados

### 1. Backend (`api/compras/upload_respaldo.php`)
- ✅ Mejor manejo de errores con mensajes específicos
- ✅ Logging de `$_FILES` y `$_POST` para debugging
- ✅ Validación de errores de PHP upload

### 2. Frontend (`ComprasApp.jsx`)
- ✅ Validación de tipo de archivo (solo imágenes)
- ✅ Validación de tamaño (máx 10MB)
- ✅ Atributo `capture="environment"` para cámara trasera
- ✅ Mejor manejo de errores con mensajes descriptivos

### 3. Configuración (`.htaccess`)
- ✅ `upload_max_filesize = 10M`
- ✅ `post_max_size = 10M`
- ✅ `max_execution_time = 300`

## Cómo Debuggear en iPhone

### Opción 1: Safari Web Inspector (Recomendado)
1. **En iPhone**: Settings → Safari → Advanced → Enable "Web Inspector"
2. **En Mac**: 
   - Conecta iPhone por cable
   - Abre Safari
   - Develop → [Tu iPhone] → [caja.laruta11.cl]
3. **Prueba subir foto** y revisa:
   - Console: errores JavaScript
   - Network: respuesta del servidor
   - Storage: archivos en memoria

### Opción 2: Console.app
1. Conecta iPhone al Mac
2. Abre Console.app
3. Selecciona tu iPhone en sidebar
4. Filtra por: `Safari` o `WebKit`
5. Prueba subir foto y observa logs

### Opción 3: Ver Logs del Servidor
Los logs de PHP se guardan en el servidor. Pide al admin que revise:
```bash
tail -f /var/log/php_errors.log
# o
tail -f /path/to/caja3/api/error.log
```

## Posibles Causas del Error

### 1. Tamaño de Archivo
- **Síntoma**: Fotos de iPhone son muy grandes (3-5MB+)
- **Solución**: Ya implementada - límite de 10MB

### 2. Formato de Archivo
- **Síntoma**: iPhone usa HEIC en vez de JPG
- **Solución**: Ya implementada - validación de tipo MIME

### 3. Timeout de Red
- **Síntoma**: Conexión lenta en móvil
- **Solución**: Ya implementada - `max_execution_time = 300`

### 4. Permisos del Servidor
- **Síntoma**: PHP no puede escribir archivos
- **Solución**: Verificar permisos de carpeta uploads

### 5. Configuración PHP del Servidor
- **Síntoma**: `.htaccess` no se aplica
- **Solución**: Verificar `php.ini` del servidor

## Pruebas a Realizar

### Test 1: Foto Pequeña
1. Toma una foto en iPhone
2. Reduce calidad/tamaño en app de Fotos
3. Intenta subir
4. **Resultado esperado**: ✅ Sube correctamente

### Test 2: Foto desde Galería
1. Selecciona foto existente (no tomar nueva)
2. Intenta subir
3. **Resultado esperado**: ✅ Sube correctamente

### Test 3: Captura Directa
1. Click en "Subir" → "Tomar foto"
2. Toma foto con cámara
3. Intenta subir
4. **Resultado esperado**: ✅ Sube correctamente

## Mensajes de Error Mejorados

Ahora verás mensajes específicos:
- ❌ "El archivo es demasiado grande" → Reduce tamaño
- ❌ "Por favor selecciona una imagen" → Archivo no es imagen
- ❌ "El archivo se subió parcialmente" → Problema de red
- ❌ "No se seleccionó ningún archivo" → Bug del navegador

## Próximos Pasos

Si el problema persiste:

1. **Revisa logs del servidor** (ver Opción 3)
2. **Prueba en Safari Web Inspector** (ver Opción 1)
3. **Comprime la imagen antes de subir**:
   ```javascript
   // Agregar compresión de imagen en frontend
   // Usar canvas para reducir tamaño
   ```

## Workaround Temporal

Si nada funciona, puedes:
1. Enviar foto por WhatsApp/Email
2. Subir desde computador
3. Usar app de terceros para comprimir imagen

## Contacto

Si necesitas ayuda adicional, comparte:
- Screenshot del error
- Logs de Safari Web Inspector
- Tamaño de la foto que intentas subir
