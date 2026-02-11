# La Ruta 11 - Monorepo

Monorepo para el sistema completo de La Ruta 11, incluyendo landing page, aplicación web y sistema de caja.

## 📁 Estructura del Proyecto

```
laruta11_3/
├── landing3/    # Landing page principal (dominio principal)
├── app3/        # Aplicación web (subdominio app)
└── caja3/       # Sistema de caja (subdominio caja)
```

## 🌐 Dominios

- **Landing**: `laruta11.cl` (dominio principal)
- **App**: `app.laruta11.cl` (subdominio)
- **Caja**: `caja.laruta11.cl` (subdominio)

## 🚀 Despliegue en EasyPanel

### Configuración de Variables de Entorno

Cada proyecto tiene su archivo `.env` con las credenciales necesarias. Asegúrate de configurar las variables de entorno en EasyPanel para cada aplicación.

### Landing (landing3)
- Framework: Astro
- Puerto: 4321
- Build: `npm run build`
- Start: `npm run preview`

### App (app3)
- Framework: Astro + React
- Puerto: 4322
- Build: `npm run build`
- Start: `npm run preview`

### Caja (caja3)
- Framework: Astro + React
- Puerto: 4323
- Build: `npm run build`
- Start: `npm run preview`

## 📦 Instalación Local

```bash
# Instalar dependencias para cada proyecto
cd landing3 && npm install
cd ../app3 && npm install
cd ../caja3 && npm install
```

## 🔐 Seguridad

Los archivos `config.php` y `.env` están excluidos del repositorio por seguridad. Las credenciales están respaldadas en los archivos `.env` de cada proyecto.

## 🛠️ Tecnologías

- **Frontend**: Astro, React, TailwindCSS
- **Backend**: PHP
- **Base de Datos**: MySQL
- **Almacenamiento**: AWS S3
- **Pagos**: TUU.cl
- **Auth**: Google OAuth

## 📝 Notas

- Este es un monorepo que contiene 3 aplicaciones independientes
- Cada aplicación tiene su propia configuración y dependencias
- Los archivos sensibles están protegidos en `.gitignore`
