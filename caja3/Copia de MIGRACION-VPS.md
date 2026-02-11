# 🚀 Guía de Migración: Hosting Compartido → VPS con Easypanel

Guía completa para migrar proyectos desde hosting compartido de Hostinger a VPS con deploy automático.

## 📋 Requisitos Previos

- VPS con Easypanel instalado
- Acceso SSH al VPS
- Repositorio GitHub
- Dominio configurado en Hostinger

## 🔧 Paso 1: Preparar el Proyecto

### 1.1 Crear Dockerfile

```dockerfile
FROM node:18-alpine AS base
WORKDIR /app
COPY package*.json ./

FROM base AS deps
RUN npm ci

FROM base AS build
COPY --from=deps /app/node_modules ./node_modules
COPY . .
RUN npm run build

FROM nginx:alpine AS runtime
COPY --from=build /app/dist /usr/share/nginx/html
EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
```

### 1.2 Crear .dockerignore

```
node_modules
npm-debug.log
.env
.env.local
dist
.astro
.git
*.log
```

### 1.3 Actualizar .gitignore

```
node_modules/
dist/
.env
.env.local
.vercel/
.netlify/
```

## 📦 Paso 2: Subir a GitHub

```bash
git init
git add .
git commit -m "Initial commit - Ready for VPS"
git branch -M main
git remote add origin https://github.com/tu-usuario/tu-repo.git
git push -u origin main
```

## 🗄️ Paso 3: Migrar Bases de Datos

### 3.1 Instalar MySQL en VPS

```bash
ssh root@TU_VPS_IP
apt update
apt install mysql-server php8.3-fpm php8.3-mysql -y
mysql_secure_installation
```

### 3.2 Crear Usuario MySQL

```bash
mysql -u root -p
```

```sql
CREATE USER 'usuario'@'%' IDENTIFIED BY 'contraseña_segura';
GRANT ALL PRIVILEGES ON *.* TO 'usuario'@'%' WITH GRANT OPTION;
FLUSH PRIVILEGES;
EXIT;
```

### 3.3 Habilitar Acceso Remoto en Hostinger

1. Ve a: Bases de datos → MySQL remoto
2. IP: Tu VPS IP o "Cualquier host"
3. Selecciona las bases de datos
4. Click "Crear"

### 3.4 Migración de Bases de Datos

#### Opción A: Bases de datos pequeñas (<2MB) - phpMyAdmin

1. **Exportar desde Hostinger:**
   - phpMyAdmin Hostinger → Selecciona base de datos
   - Exportar → Formato SQL → Estructura y datos
   - Descargar archivo

2. **Importar en VPS:**
   - phpMyAdmin VPS (https://phpmyadmin.tu-dominio.com)
   - Nueva → Crear base de datos
   - Seleccionar base de datos → Importar
   - Seleccionar archivo SQL → Continuar

#### Opción B: Bases de datos grandes (>2MB) - Terminal

**1. Configurar SSH Key (solo primera vez):**

```bash
# En tu Mac, genera clave SSH
ssh-keygen -t ed25519 -C "tu-email@example.com"
# Presiona Enter 3 veces (sin password)

# Copia la clave pública
cat ~/.ssh/id_ed25519.pub | pbcopy

# Pega en Easypanel → SSH Keys → Agregar clave SSH
```

**2. Transferir archivo SQL grande:**

```bash
# Desde tu Mac
scp ~/Downloads/nombre_db.sql root@TU_VPS_IP:/tmp/
```

**3. Crear base de datos e importar:**

```bash
# En el VPS
mysql -u agenterag -p'TU_PASSWORD' -e "CREATE DATABASE nombre_db;"
mysql -u agenterag -p'TU_PASSWORD' nombre_db < /tmp/nombre_db.sql
```

**4. Verificar importación:**

```bash
mysql -u agenterag -p'TU_PASSWORD' nombre_db -e "SHOW TABLES;"
```

#### Opción C: Script Automático (múltiples DBs)

Crea `migrate-to-vps.sh` en el VPS:

```bash
#!/bin/bash
HOSTINGER_HOST="srv1438.hstgr.io"
HOSTINGER_USER="usuario_db"
HOSTINGER_PASS="password"
VPS_USER="agenterag"
VPS_PASS="TU_PASSWORD"

mkdir -p /tmp/db_migration

echo "Exportando desde Hostinger..."
mysqldump -h "$HOSTINGER_HOST" \
  -u "$HOSTINGER_USER" \
  -p"$HOSTINGER_PASS" \
  --skip-column-statistics \
  --no-tablespaces \
  nombre_db > /tmp/db_migration/db.sql

echo "Creando base de datos en VPS..."
mysql -u "$VPS_USER" -p"$VPS_PASS" -e "CREATE DATABASE IF NOT EXISTS nombre_db;"

echo "Importando datos..."
mysql -u "$VPS_USER" -p"$VPS_PASS" nombre_db < /tmp/db_migration/db.sql

rm -rf /tmp/db_migration
echo "✅ Migración completada"
```

Ejecutar:
```bash
chmod +x migrate-to-vps.sh
./migrate-to-vps.sh
```

### 3.5 Solución de Problemas Comunes

#### Error: Unknown collation 'utf8mb4_uca1400_ai_ci'

Esto ocurre cuando el SQL viene de MariaDB 10.10+ y el VPS tiene MySQL 8.0:

```bash
# Reemplazar collation incompatible
sed -i 's/utf8mb4_uca1400_ai_ci/utf8mb4_unicode_ci/g' /tmp/nombre_db.sql
mysql -u agenterag -p'TU_PASSWORD' nombre_db < /tmp/nombre_db.sql
```

#### Error: Table already exists

Limpia y vuelve a importar:

```bash
mysql -u agenterag -p'TU_PASSWORD' -e "DROP DATABASE nombre_db; CREATE DATABASE nombre_db;"
mysql -u agenterag -p'TU_PASSWORD' nombre_db < /tmp/nombre_db.sql
```

#### Archivo SQL muy grande (>100MB)

Usa compresión:

```bash
# Comprimir antes de transferir
gzip nombre_db.sql
scp nombre_db.sql.gz root@TU_VPS_IP:/tmp/

# En el VPS, descomprimir e importar
gunzip /tmp/nombre_db.sql.gz
mysql -u agenterag -p'TU_PASSWORD' nombre_db < /tmp/nombre_db.sql
```

## 🔗 Paso 4: Configurar Deploy Automático

### 4.1 Conectar Repositorio en Easypanel

1. Easypanel → Create Service → From GitHub
2. Selecciona tu repositorio
3. Easypanel detectará el Dockerfile automáticamente

### 4.2 Configurar Webhook de GitHub

```bash
curl -X POST \
  -H "Authorization: token TU_GITHUB_TOKEN" \
  -H "Accept: application/vnd.github.v3+json" \
  https://api.github.com/repos/tu-usuario/tu-repo/hooks \
  -d '{
    "config": {
      "url": "http://TU_VPS_IP:3000/api/deploy/DEPLOYMENT_TRIGGER_ID",
      "content_type": "json"
    },
    "events": ["push"],
    "active": true
  }'
```

O manualmente:
1. GitHub → Settings → Webhooks → Add webhook
2. Payload URL: URL de Easypanel Deployment Trigger
3. Content type: application/json
4. Events: Just the push event

## 🌐 Paso 5: Configurar Dominio

### 5.1 Desactivar CDN en Hostinger

1. Sitios web → tu-dominio.com → Rendimiento → CDN
2. Desactivar CDN

### 5.2 Actualizar DNS

1. Hostinger → Dominios → Administrar DNS
2. Borrar registro ALIAS `@` (si existe)
3. Agregar registro A:
   - Tipo: A
   - Nombre: @
   - Apunta a: TU_VPS_IP
   - TTL: 14400

4. Agregar registro A para www:
   - Tipo: A
   - Nombre: www
   - Apunta a: TU_VPS_IP
   - TTL: 14400

### 5.3 Configurar Dominio en Easypanel

1. Easypanel → Tu servicio → Domains → Create Domain
2. Host: tu-dominio.com
3. Path: /
4. Service: tu-servicio
5. Protocol: http
6. Port: 80
7. SSL → Enable HTTPS → Generate Let's Encrypt

## ✅ Paso 6: Verificar Migración

```bash
# Verificar que apunta al VPS
curl -I https://tu-dominio.com

# Debe mostrar:
# HTTP/2 200
# server: nginx
```

## 🔄 Workflow de Deploy Automático

Ahora cada vez que hagas:

```bash
git add .
git commit -m "cambios"
git push
```

El sitio se desplegará automáticamente en el VPS.

## 📝 Variables de Entorno

Configura en Easypanel → Settings → Environment:

```env
PUBLIC_SUPABASE_URL=tu-url
PUBLIC_SUPABASE_ANON_KEY=tu-key
GOOGLE_GEMINI_API_KEY=tu-key
NODE_ENV=production
```

## 🎯 Resultado Final

✅ Frontend en VPS  
✅ Bases de datos en VPS  
✅ Deploy automático desde GitHub  
✅ Dominio apuntando al VPS  
✅ SSL/HTTPS configurado  
✅ Ya NO dependes del hosting compartido  

## 🗄️ Bonus: Instalar phpMyAdmin (Una sola vez para todos los proyectos)

### En Easypanel:

1. **Create Service → Docker Image**
2. **Image:** `phpmyadmin/phpmyadmin:latest`
3. **Environment Variables:**
   ```
   PMA_HOST=host.docker.internal
   PMA_PORT=3306
   PMA_USER=tu_usuario_mysql
   PMA_PASSWORD=tu_password_mysql
   ```
4. **Port:** 80
5. **Deploy**

### Configurar Dominio:

1. **DNS en Hostinger:**
   ```
   Tipo: A
   Nombre: phpmyadmin
   Apunta a: TU_VPS_IP
   TTL: 14400
   ```

2. **Easypanel → Domains:**
   - Host: `phpmyadmin.tu-dominio.com`
   - Enable SSL

3. **Acceder:** `https://phpmyadmin.tu-dominio.com`

**Nota:** Este phpMyAdmin sirve para gestionar TODAS las bases de datos de TODOS tus proyectos en el VPS.

### Bases de Datos Migradas (Ejemplo Real)

**Proyecto agenterag.com:**
- `u958525313_booking` (3 tablas: bookings, booking_sessions, temp_bookings)
- `u958525313_rag_database` (9 tablas: gaby_contacts, gaby_email_log, gaby_meetings, etc.)
- `u958525313_app` (538,586 líneas SQL - 75MB)

**Ventajas de MySQL centralizado:**
- ✅ Un solo phpMyAdmin para todos los proyectos
- ✅ Un solo servidor MySQL (172.17.0.1:3306)
- ✅ Un solo usuario con acceso a todo
- ✅ Backups centralizados
- ✅ Mejor rendimiento que hosting compartido  

## 💰 Ahorro

- Hosting compartido: ~$10-20/mes
- VPS: ~$5-10/mes (más control y recursos)

## 🔧 Troubleshooting

### Error 404
- Verifica que el puerto en Easypanel sea 80
- Revisa que el path esté vacío o sea `/`

### Error 502
- El contenedor está crasheando
- Revisa logs en Easypanel
- Verifica el Dockerfile

### DNS no actualiza
- Espera 5-10 minutos para propagación DNS
- Limpia caché DNS: `sudo dscacheutil -flushcache` (Mac)

### Deploy no automático
- Verifica el webhook en GitHub → Settings → Webhooks
- Debe mostrar entregas exitosas (checkmark verde)

## 📚 Recursos

- [Easypanel Docs](https://easypanel.io/docs)
- [Docker Docs](https://docs.docker.com/)
- [GitHub Webhooks](https://docs.github.com/en/webhooks)

---

**Creado:** 2026-02-04  
**Proyecto:** agenterag.com  
**Stack:** Astro + MySQL + Easypanel + GitHub Actions
