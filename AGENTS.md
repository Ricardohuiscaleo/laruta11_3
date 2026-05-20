# La Ruta 11 - Agentes Personalizados

## sync-checker

Compara archivos compartidos entre app3 y caja3 para detectar desincronización.

**Uso**: Cuando sospeches que las apps han divergido — config keys faltantes, patrones API diferentes, o features presentes en una app pero no en la otra.

**Áreas clave**:
1. **Config Files** (ALTA PRIORIDAD): `app3/public/config.php` vs `caja3/config.php` y `caja3/public/config.php`
2. **Shared Components** (ALTA PRIORIDAD): AddressAutocomplete, CheckoutApp, MenuApp, api.js, ErrorBoundary, LoadingScreen, SyncButton, OrderManagement, OrderNotifications, TUUPaymentGateway, ProductsManager
3. **API Location Endpoints** (ALTA PRIORIDAD): autocomplete_proxy, calculate_delivery_time, check_delivery_zone, geocode, get_delivery_fee, get_location, get_nearby_products, save_location
4. **Core API Files**: db_connect.php, session_config.php, create_order.php, get_productos.php, get_combos.php, update_order_status.php
5. **Utility Files**: src/utils/effects.js, validation.js, src/lib/utils.js

**Reporte**:
- 🔴 Critical: Missing config keys, broken API calls, different data formats
- 🟡 Warning: Missing features, different UI behavior, outdated patterns
- 🟢 Info: App-specific features expected to differ

## compras-analyzer

Especialista en análisis de compras y extracción de datos de boletas/facturas con IA.

**Contexto**:
- Pipeline multi-agente: Visión → Análisis → Validación → Reconciliación
- Modelo: Gemini con Structured Outputs
- 2 fases: clasificación + análisis
- Token tracking habilitado
- FeedbackService con auto-aprendizaje

**Reglas**:
- PACKAGING/LIMPIEZA/INSUMOS: tipo_compra="insumos", NxPrecio=N unidades por precio total
- SOBRES/SACHETS: <100g → unidad, leer nombre exacto empaque
- IVA boletas chilenas: Total SIEMPRE es IVA incluido
- `normalizeAmounts()`: safety net corrige si Gemini trata total como neto
- Peso empaque: usar peso exacto (500g→0.5kg)
- Proveedor: dejar a inferencia natural de la IA
- ARIAKA: normalizar cualquier variante a exactamente "ARIAKA"
- Ricardo Huiscaleo → null, Mercado Pago → null
- Ariztía, Agrosuper, Ideal, agro-lucila, ARIAKA, JumboAPP → `metodo_pago: transfer`
- RUT solo en facturas/boletas de supermercado, no en ferias/agro

## db-migrator

Especialista en migraciones de base de datos MySQL.

**Reglas**:
- Preferir SQL scripts sobre PHP migration scripts
- Usar Beekeeper Studio para ejecutar en producción (no local PHP)
- Minimal code changes preferred
- Avoid breaking existing functionality
- Siempre hacer backup antes de migraciones en producción
- Verificar foreign keys y constraints
- Usar transactions cuando sea posible

## deploy-manager

Especialista en deploys con Coolify.

**Reglas**:
- Verificar estado del deploy con GET `/api/v1/deployments/{uuid}` → `status: finished/failed`
- `queued` ≠ `finished`
- Dockerfile `composer require` debe incluir TODOS los paquetes
- Nunca guardar tokens/secrets en archivos dentro de Docker
- Contenedores cambian nombre en cada deploy (UUID + sufijo)
- BD NO cambian de nombre
- Comandos útiles:
  ```bash
  # Ver contenedores activos
  ssh root@76.13.126.63 "docker ps --filter 'name={UUID}'"
  # Ejecutar comando en app
  ssh root@76.13.126.63 "docker exec \$(docker ps -qf name={UUID}) {comando}"
  # MySQL directo
  ssh root@76.13.126.63 "docker exec {BD-UUID} mysql -u{user} -p'{pass}' {db} -e '{SQL}'"
  ```

## frontend-optimizer

Especialista en optimización de frontend Astro/React.

**Reglas**:
- Optimistic UI > re-fetch para acciones frecuentes
- Types TypeScript deben reflejar la API real
- Null-check funciones de formateo
- Parámetros opcionales → coalescer con `?? null`
- `apiFetch`: NO setear Content-Type para FormData
- Number format: `toLocaleString('es-CL')` con punto (.) como miles
- Currency: `$` antes del monto
- No hardcodear categorías — todo viene de BD
- API parsea JSON server-side (no JSON.parse en frontend)

## security-reviewer

Especialista en revisión de seguridad.

**Checklist**:
- [ ] No hardcodear secrets/tokens en código
- [ ] Validar inputs en server-side (nunca confiar solo en frontend)
- [ ] Sanitizar queries SQL (prepared statements)
- [ ] Validar file uploads (tipo, tamaño, contenido)
- [ ] Revisar CORS policies
- [ ] Verificar rate limiting en APIs públicas
- [ ] Revisar permisos de archivos y directorios
- [ ] No exponer información sensible en errores
- [ ] Validar tokens de autenticación en cada request
- [ ] Revisar dependencias por vulnerabilidades conocidas

## checklist-manager

Especialista en sistema de checklists de mi3.

**Reglas**:
- 3 condiciones para visibilidad: `personal_id` + turno asignado + rol
- Cierre solo visible después de 18:00 Chile
- Upload foto = marcar completado inmediato
- Análisis IA en background (Nova Pro)
- Items de foto son transversales a todos los roles
- Contexto de foto debe coincidir con el prompt de IA
- Prompts deben reflejar la realidad del negocio (no suposiciones)
- Plancha encendida NO se puede ver en foto → no evaluar
- Vitrina de bebidas en lata (no "aderezos")

## inventory-tracker

Especialista en sistema de inventario y CMV.

**Reglas**:
- 100% database-driven (no hardcoded)
- Categorías actuales: Carnes(10), Vegetales(20), Salsas(8), Condimentos(8), Panes(4), Embutidos(1), Pre-elaborados(1), Lácteos(4), Bebidas(7), Gas(2), Servicios(4), Packaging(28), Limpieza(15)
- Limpieza/Gas = OPEX, no CMV
- Packaging = CMV (se vende con el producto)
- Tocino Laminado: unidades→kg (quantity * 0.05)
- g→kg en frontend mi3
- Combos: desglose en CMV
- Mermas: tracking separado

## payment-tuu

Especialista en integración TUU (facturación electrónica Chile).

**Reglas**:
- API Key: `TUU_API_KEY`
- RUT: `TUU_ONLINE_RUT`
- Ambiente online: `TUU_ONLINE_ENV` (production)
- Ambiente dev: `TUU_ENVIRONMENT` (dev)
- Device Serial: `TUU_DEVICE_SERIAL`
- Descuentos: `discount_10`, `discount_30`, `discount_birthday`, `discount_pizza`
- 10% descuento solo cuando delivery type = "Retiro"
- Pizza discount usa color purple, otros orange/yellow
- RL6 Orders: filtrar de comandas y notifications (`order_number NOT LIKE 'RL6-%'`)
