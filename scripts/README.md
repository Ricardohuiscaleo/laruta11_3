# Conversor de Imágenes a WebP

Script para convertir automáticamente todas las imágenes de productos a formato WebP, reduciendo el tamaño en ~30-50%.

## Instalación

```bash
cd scripts
npm install
```

## Configuración

1. Copia `.env.example` a `.env`:
```bash
cp .env.example .env
```

2. Edita `.env` con tus credenciales:
```env
DB_HOST=tu-host-mysql
DB_USER=tu-usuario
DB_PASSWORD=tu-password
DB_NAME=laruta11

AWS_REGION=us-east-1
S3_BUCKET=laruta11-images
AWS_ACCESS_KEY_ID=tu-access-key
AWS_SECRET_ACCESS_KEY=tu-secret-key
```

## Uso

```bash
npm run convert
```

## ¿Qué hace?

1. ✅ Obtiene todas las URLs de imágenes desde la tabla `products`
2. ✅ Descarga cada imagen desde S3
3. ✅ Convierte a WebP (calidad 85%)
4. ✅ Sube la versión WebP a S3
5. ✅ Actualiza la DB con las nuevas URLs `.webp`

## Beneficios

- 🚀 **30-50% menos peso** en imágenes
- ⚡ **Carga más rápida** de la app
- 💰 **Ahorro en ancho de banda** de S3
- 📱 **Mejor experiencia móvil**

## Notas

- Las imágenes originales NO se eliminan
- El proceso es seguro y reversible
- Se procesa una imagen a la vez para no saturar
- Muestra progreso y estadísticas en tiempo real
