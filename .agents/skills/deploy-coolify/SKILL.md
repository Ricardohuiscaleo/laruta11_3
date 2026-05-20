# Skill: Deploy con Coolify

## Descripción
Especialista en deploys y gestión de infraestructura con Coolify (Docker containers en VPS).

## Cuándo usar
- Realizar un deploy de cualquier app
- Verificar estado de deploys
- Reiniciar servicios
- Diagnosticar problemas de producción
- Gestionar variables de entorno
- Ejecutar comandos en contenedores

## Infraestructura

### Servidor
- IP: `76.13.126.63`
- Usuario: `root`
- Proxy: Traefik 3.6.9
- SSL: Let's Encrypt via Traefik

### Apps Desplegadas

| App | UUID | Dominio | Puerto |
|-----|------|---------|--------|
| app3 | `egck4wwcg0ccc4osck4sw8ow` | app.laruta11.cl | 80 |
| caja3 | `xockcgsc8k000o8osw8o88ko` | caja.laruta11.cl | 80 |
| landing3 | `dks4cg8s0wsswk08ocwggk0g` | laruta11.cl | 80 |
| mi3-backend | `ds24j8jlaf9ov4flk1nq4jek` | api-mi3.laruta11.cl | 8080 |
| mi3-frontend | `sxdw43i9nt3cofrzxj28hx1e` | mi.laruta11.cl | 3000 |

### Bases de Datos
- laruta11-db: `zs00occ8kcks40w4c88ogo08` (MySQL 8, puerto 3306)
- saas-db: `eocws4gsgkk4ck800w8g8000` (MySQL 8, puerto 3307)

## Comandos Útiles

### Verificar Deploy
```bash
# Verificar estado del deploy
curl -s -H "Authorization: Bearer $COOLIFY_TOKEN" \
  -H "Accept: application/json" \
  http://76.13.126.63:8000/api/v1/deployments/{uuid}
# Esperar status: finished (queued ≠ finished)
```

### Reiniciar App
```bash
curl -s -X POST \
  -H "Authorization: Bearer $COOLIFY_TOKEN" \
  -H "Accept: application/json" \
  http://76.13.126.63:8000/api/v1/applications/{uuid}/restart
```

### Ejecutar en Contenedor
```bash
# Encontrar contenedor activo
ssh root@76.13.126.63 "docker ps -qf name={UUID}"

# Ejecutar comando
ssh root@76.13.126.63 "docker exec \$(docker ps -qf name={UUID}) {comando}"

# Ejemplo: ver logs
ssh root@76.13.126.63 "docker exec \$(docker ps -qf name=egck4wwcg0ccc4osck4sw8ow) tail -f /var/log/apache2/error.log"
```

### MySQL Directo
```bash
# laruta11 (principal)
ssh root@76.13.126.63 "docker exec zs00occ8kcks40w4c88ogo08 mysql -ularuta11_user -p'<PASS>' laruta11 -e '{SQL}'"

# saas_backend
ssh root@76.13.126.63 "docker exec eocws4gsgkk4ck800w8g8000 mysql -usaas_user -p'<PASS>' saas_backend -e '{SQL}'"
```

## Reglas Críticas

1. **Verificar estado**: `queued` ≠ `finished`. Siempre esperar `status: finished`
2. **Dockerfile completo**: `composer require` debe incluir TODOS los paquetes
3. **No secrets en Docker**: Nunca guardar tokens/secrets en archivos dentro de containers
4. **Contenedores cambian**: Nombre cambia en cada deploy (UUID + sufijo), BD NO cambian
5. **Cross-container**: app3 y caja3 son independientes — no comparten filesystem

## Variables de Entorno
- Obtener via: `GET /applications/{uuid}/envs`
- Keys compartidas app3/caja3: APP_DB_*, AWS_*, GEMINI_API_KEY, etc.
- mi3-backend tiene variables adicionales de Laravel

## Troubleshooting

### Deploy fallido
1. Verificar logs del build: `GET /deployments/{uuid}/logs`
2. Verificar Dockerfile (composer require completo)
3. Verificar variables de entorno faltantes
4. Reintentar deploy

### App no responde
1. Verificar contenedor activo: `docker ps -qf name={UUID}`
2. Verificar logs: `docker logs {container}`
3. Reiniciar app via API
4. Verificar Traefik (proxy)

### BD no conecta
1. Verificar BD está running: `docker ps | grep zs00occ8kcks40w4c88ogo08`
2. Verificar credenciales en env vars
3. Probar conexión directa con mysql client
4. Verificar firewall/red

## API Coolify
- Base URL: `http://76.13.126.63:8000/api/v1`
- Auth: `Authorization: Bearer <COOLIFY_API_TOKEN>`
- Token name: `kiro-Ruta11-Coolify`
