# Instalación de Dompdf para Generación de PDF

## Opción 1: Instalar con Composer (Recomendado)

1. Navegar a la carpeta del proyecto:
```bash
cd /Users/ricardohuiscaleollafquen/ruta11app
```

2. Instalar Dompdf:
```bash
composer require dompdf/dompdf
```

## Opción 2: Descarga Manual

1. Descargar Dompdf desde: https://github.com/dompdf/dompdf/releases
2. Extraer en la carpeta `api/vendor/`
3. Incluir el autoloader en el archivo PHP

## Verificar Instalación

El archivo `generate_pdf_dompdf.php` detectará automáticamente si Dompdf está disponible:

- ✅ **Con Dompdf**: Genera PDF real
- ⚠️ **Sin Dompdf**: Usa fallback (HTML optimizado para impresión)

## Estructura Esperada

```
ruta11app/
├── api/
│   ├── vendor/
│   │   └── autoload.php (generado por Composer)
│   └── tracker/
│       └── generate_pdf_dompdf.php
```

## Alternativas sin Dompdf

Si no se puede instalar Dompdf, el sistema tiene fallbacks:

1. **HTML optimizado** para imprimir como PDF desde el navegador
2. **Archivo de texto** descargable directamente
3. **API externa** para conversión HTML→PDF (requiere internet)

## Comando de Instalación Rápida

```bash
cd /Users/ricardohuiscaleollafquen/ruta11app && composer require dompdf/dompdf
```