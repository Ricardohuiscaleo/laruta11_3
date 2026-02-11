# La Ruta11 - Food Trucks Landing Page

Página web moderna para La Ruta11 food trucks construida con Astro, React y Tailwind CSS, con backend PHP para gestión de imágenes.

## 🚀 Instalación y Desarrollo

```bash
# Instalar dependencias
npm install

# Ejecutar en modo desarrollo
npm run dev

# Construir para producción
npm run build

# Vista previa de producción
npm run preview
```

## 🎨 Características

- **Diseño moderno** con tonos cálidos de marca
- **Completamente responsive** para todos los dispositivos
- **Componentes React** interactivos con animaciones
- **Astro** para optimización de rendimiento
- **Tailwind CSS** para estilos rápidos y consistentes
- **Header sticky** con efectos de scroll y transparencia
- **Efectos de apilación** en tarjetas de servicios
- **Tooltips interactivos** con hover effects
- **Shimmer effects** en botones CTA
- **API PHP** para gestión de imágenes con AWS S3

## 🎨 Paleta de Colores

- Rojo: #DC2626
- Naranja: #EA580C  
- Café: #92400E
- Café claro: #D97706
- Negro: #1F2937
- Blanco: #FFFFFF
- Amarillo: #FCD34D

## 📱 Secciones

- **Hero** - Llamada a la acción con botones CTA
- **Servicios** - Tarjetas apilables con efectos sticky
- **Ubicación** - Mapa interactivo y horarios
- **Menú** - Especialidades con botón a app
- **Contacto** - Información de contacto con iconos
- **App** - Promoción de aplicación móvil

## 🔧 Estructura del Proyecto

```
/
├── src/
│   ├── components/     # Componentes React
│   ├── layouts/        # Layouts de Astro
│   └── pages/          # Páginas de Astro
├── api/
│   └── s3-manager.php  # API para gestión de imágenes
├── config.php          # Configuración (fuera de public)
├── load-env.php        # Cargador de variables de entorno
└── .env                # Variables de entorno
```

## 🔐 Configuración

Crea un archivo `.env` en la raíz con:

```env
AWS_ACCESS_KEY_ID=tu_access_key
AWS_SECRET_ACCESS_KEY=tu_secret_key
AWS_REGION=us-east-1
S3_BUCKET=laruta11-images
GOOGLE_MAPS_API_KEY=tu_maps_key
```

## 🚀 Despliegue

En producción, asegúrate de:
1. Mover `config.php` y `load-env.php` fuera del directorio público
2. Configurar variables de entorno en el servidor
3. Configurar permisos adecuados para archivos PHP
4. Habilitar HTTPS para seguridad

## 🛠️ APIs

- **POST /api/s3-manager.php** - Gestión de imágenes S3
  - `action=upload` - Subir imagen
  - `action=list` - Listar imágenes
  - `action=delete` - Eliminar imagen