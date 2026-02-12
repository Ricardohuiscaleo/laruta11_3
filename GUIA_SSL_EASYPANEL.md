# 🔒 Guía Definitiva: SSL/HTTPS en EasyPanel con Let's Encrypt

## 📋 Requisitos Previos
- VPS con EasyPanel instalado
- Dominio/subdominio configurado
- Acceso SSH al VPS
- Puertos 80 y 443 abiertos

---

## 🎯 Paso a Paso Infalible

### 1️⃣ Configurar DNS (5-10 minutos)

**En tu proveedor de DNS:**

1. Crear registro **A**:
   - Host: `app` (o `@` para dominio raíz)
   - Tipo: `A`
   - Valor: `76.13.126.63` (IP de tu VPS)
   - TTL: `3600` (o automático)

2. **IMPORTANTE**: Eliminar registros **AAAA** (IPv6):
   - Si existe AAAA apuntando a IPv6 que no usas, Let's Encrypt puede fallar
   - Elimina AAAA del subdominio que estés certificando

3. Esperar propagación (5-10 min, máximo 24h)

**Verificar desde VPS:**
```bash
# Debe devolver la IP de tu VPS
dig +short app.tudominio.com A

# Debe estar vacío (si eliminaste AAAA)
dig +short app.tudominio.com AAAA
```

---

### 2️⃣ Verificar Puerto 80 (Let's Encrypt lo necesita)

**Desde el VPS:**
```bash
# Debe responder 404 o 200 (no importa, solo que conecte)
curl -4I http://app.tudominio.com/.well-known/acme-challenge/test
```

**Verificar puertos abiertos:**
```bash
# Verificar firewall (debe estar inactivo o permitir 80/443)
ufw status verbose

# Verificar que Docker/Traefik escucha en 80 y 443
ss -lntp | grep -E ":80|:443"
```

**Resultado esperado:**
```
LISTEN 0 4096 0.0.0.0:80 0.0.0.0:* users:(("docker-proxy",...))
LISTEN 0 4096 0.0.0.0:443 0.0.0.0:* users:(("docker-proxy",...))
```

---

### 3️⃣ Configurar Dominio en EasyPanel

**En EasyPanel:**

1. Ve a tu **Service** (ej. app3)
2. Busca sección **"Domains"**
3. Click en **"Add Domain"** o edita existente
4. Configurar:
   - **Domain**: `app.tudominio.com`
   - **HTTPS**: **OFF** ❌
   - **Middlewares**: Dejar vacío
   - **SSL**: Dejar vacío por ahora
5. Click **"Save"**
6. Esperar 30 segundos

---

### 4️⃣ Activar HTTPS con Let's Encrypt

**Método 1 - Activación directa:**
1. Editar el dominio
2. **HTTPS**: **ON** ✅
3. **SSL** (si aparece): Escribir `letsencrypt`
4. **Save**
5. Esperar 2-5 minutos

**Método 2 - Reset (si Método 1 falla):**
1. **HTTPS**: **OFF** → **Save**
2. Esperar 30 segundos
3. **HTTPS**: **ON** → **Save**
4. Esperar 2-5 minutos

---

### 5️⃣ Verificar Certificado SSL

**Desde el VPS:**

```bash
# Verificar que el certificado sea de Let's Encrypt
curl -vk https://app.tudominio.com/ 2>&1 | grep -E "subject:|issuer:" | head -n 5
```

**Resultado esperado:**
```
subject: CN=app.tudominio.com
issuer: C=US; O=Let's Encrypt; CN=R12
```

❌ **Si dice `issuer: CN=Easypanel`** → Certificado self-signed, repetir paso 4 (Método 2)

---

### 6️⃣ Confirmar Funcionamiento

**Verificar respuesta HTTPS:**
```bash
curl -4I https://app.tudominio.com
```

**Resultado esperado:**
```
HTTP/2 200
server: Apache/2.4.66 (Debian)
...
```

**Probar en navegador:**
- Abrir: `https://app.tudominio.com`
- Debe mostrar candado 🔒 verde
- Si muestra certificado viejo: Modo incógnito o limpiar caché

---

## 🔧 Troubleshooting

### Problema: Certificado self-signed persiste

**Solución:**
1. Verificar que DNS apunte correctamente (paso 1)
2. Eliminar registros AAAA (IPv6)
3. Hacer reset: HTTPS OFF → Save → HTTPS ON → Save
4. Esperar 5 minutos completos

### Problema: Error "DNS not pointing"

**Solución:**
```bash
# Verificar propagación DNS
dig +short app.tudominio.com A

# Debe devolver IP del VPS
# Si no, esperar más tiempo o revisar configuración DNS
```

### Problema: Puerto 80 no responde

**Solución:**
```bash
# Verificar firewall
ufw status

# Si está activo, permitir puertos
ufw allow 80/tcp
ufw allow 443/tcp
```

### Problema: Let's Encrypt no emite certificado

**Solución:**
1. Ver logs de Traefik en EasyPanel:
   - Settings → Proxy → Logs
   - Buscar: `acme`, `challenge`, `error`
2. Verificar que no haya rate limit de Let's Encrypt (5 intentos/hora)
3. Esperar 1 hora y reintentar

---

## ✅ Checklist Final

- [ ] DNS A apunta a IP del VPS
- [ ] DNS AAAA eliminado (si no usas IPv6)
- [ ] Puerto 80 responde (404 es OK)
- [ ] Puerto 443 abierto
- [ ] Dominio agregado en EasyPanel
- [ ] HTTPS activado con Let's Encrypt
- [ ] Certificado muestra `issuer: Let's Encrypt`
- [ ] `curl -4I https://...` devuelve HTTP/2 200
- [ ] Navegador muestra candado 🔒

---

## 📝 Comandos de Referencia Rápida

```bash
# Verificar DNS
dig +short app.tudominio.com A
dig +short app.tudominio.com AAAA

# Verificar HTTP
curl -4I http://app.tudominio.com/.well-known/acme-challenge/test

# Verificar puertos
ss -lntp | grep -E ":80|:443"

# Verificar certificado
curl -vk https://app.tudominio.com/ 2>&1 | grep -E "subject:|issuer:"

# Verificar HTTPS
curl -4I https://app.tudominio.com

# Ping al dominio
ping app.tudominio.com
```

---

## 🎉 Resultado Final

Si todo está correcto:
- ✅ `https://app.tudominio.com` carga con candado verde
- ✅ Certificado válido de Let's Encrypt
- ✅ HTTP/2 habilitado
- ✅ Redirección automática HTTP → HTTPS

---

**Fecha de creación**: 12 Feb 2026  
**Probado en**: EasyPanel + Docker + Traefik  
**VPS**: 76.13.126.63
