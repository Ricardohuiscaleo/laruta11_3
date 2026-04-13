---
inclusion: auto
---

# Reglas del Proyecto La Ruta 11

Reglas extraídas de 211 lecciones aprendidas en producción. Seguir siempre.

## S3 y Uploads

- NUNCA usar `Storage::disk('s3')->put()` (Flysystem falla silenciosamente). Usar PUT directo con SigV4 (como `ImagenService` y `PhotoAnalysisService`)
- Si la compresión de imagen falla (HEIC en iPhone), subir el archivo original sin bloquear al usuario. S3 acepta cualquier formato
- URL directa `https://{bucket}.s3.amazonaws.com/{key}` > `Storage::url()`

## Deploys

- Siempre verificar estado del deploy con GET `/api/v1/deployments/{uuid}` → `status: finished/failed`. `queued` ≠ `finished`
- Dockerfile `composer require` debe incluir TODOS los paquetes (el `composer.json` local NO se copia al container)
- Nunca guardar tokens/secrets en archivos dentro de Docker — usar BD o env vars

## Frontend

- `apiFetch`: NO setear Content-Type para FormData (el browser debe setear `multipart/form-data; boundary=...`)
- Optimistic UI > re-fetch para acciones frecuentes (marcar/desmarcar checklist)
- Types TypeScript deben reflejar la API real, no lo ideal. Verificar estructura de respuesta antes de deployar
- Siempre null-check funciones de formateo (`formatearPesosCLP`, etc.)
- Parámetros opcionales (`aiScore?`) → coalescer con `?? null` antes de asignar a tipos que no aceptan `undefined`

## IA / Bedrock

- Modelo: `amazon.nova-pro-v1:0` (Nova Lite no sirve para OCR de boletas)
- SigV4 con curl nativo (no Guzzle/Laravel HTTP que double-encoda el `:` en model ID)
- Prompts deben reflejar la realidad del negocio, no suposiciones. El feedback del usuario es la mejor fuente
- Doble mapeo: prompt (best effort) + `mapPersonToSupplier()` server-side (garantizado)
- Formato de imagen para Bedrock: detectar de la extensión URL, no hardcodear `jpeg`
- Si la IA analiza algo, mostrar el resultado al usuario inmediatamente

## Checklists

- 3 condiciones para visibilidad: `personal_id` + turno asignado + rol (`cajero`/`planchero`)
- Cierre solo visible después de 18:00 Chile
- Upload foto = marcar completado inmediato. Análisis IA en background
- Items de foto son transversales a todos los roles
- Contexto de foto debe coincidir con el prompt de IA (plancha/lavaplatos/mesón/exterior/interior)

## Auth

- Remember token = true por defecto (app interna, 5 usuarios)
- Sanctum stateless con tokens en `personal_access_tokens`. Tabla `sessions` vacía es correcto

## Proveedores / Compras

- ARIAKA: normalizar cualquier variante a exactamente "ARIAKA"
- Ricardo Huiscaleo (emisor) → null, no es proveedor
- Mercado Pago → null, no es proveedor real
- Ariztía, Agrosuper, Ideal, agro-lucila, ARIAKA, JumboAPP → siempre `metodo_pago: transfer`
- RUT solo en facturas/boletas de supermercado, no en ferias/agro

## Infraestructura

- SSH: `ssh root@76.13.126.63`
- mi3-backend container: `docker ps --filter "name=ds24j8"`
- app3 container: `docker ps --filter "name=egck4w"`
- Coolify API: `http://76.13.126.63:8000`, Token: `3|S52ZUspC6N5G54apjgnKO6sY3VW5OixHlnY9GsMv8dc72ae8`
- Telegram bot: `@SuperKiro_bot`, Token: `8432728868:AAEf2aVSvZqCT1t7SVgKKuG411XveYGpA7M`, chat_id: `8104543914`
