# caja3 - Fixes y Mejoras Recientes

## 🔧 Configuración y Setup

### Database Configuration (config.php)
- **Problema**: Faltaban credenciales `ruta11_db_*` para base de datos principal
- **Solución**: Agregadas variables `ruta11_db_host`, `ruta11_db_name`, `ruta11_db_user`, `ruta11_db_pass`
- **Uso**: APIs que necesitan acceder a la BD principal de La Ruta 11

## 🚫 Track Usage System

### Eliminación Completa
- **Problema**: Llamadas a `track_usage.php` causaban 404 errors
- **Solución**: Removido sistema completo de tracking de caja3
- **Archivos modificados**: `MenuApp.jsx` (líneas ~1990-2040)
- **Nota**: Sistema aún activo en app3 para clientes

## 👤 User Session Management

### Logs Silenciados
- **Problema**: Console logs "No hay usuario logueado" en funciones que no requieren usuario
- **Funciones afectadas**: `loadUserOrders()`, `loadNotifications()`
- **Solución**: Removidos console.log innecesarios
- **Razón**: caja3 usa `cajaUser` (cashier), no `user` (customer)

## 🍔 Combo Modal Fix

### Category Name Case-Insensitive
- **Problema**: Modal de combos no abría si `category_name` era 'Combos' (mayúscula)
- **Solución**: Aceptar tanto 'combos' como 'Combos' en `handleAddToCart()`
- **Código**: `if (product.category_name?.toLowerCase() === 'combos')`
- **Consistencia**: app3 ya manejaba ambos casos

## ✏️ Checkout Fields

### Campos Editables
- **Problema**: Nombre y teléfono no editables cuando había usuario
- **Solución**: Removido `readOnly={!!user}` de inputs
- **Razón**: Cajeros deben poder editar datos del cliente manualmente
- **Diferencia con app3**: app3 pre-llena desde perfil de usuario

## 💰 Sistema de Descuentos

### Columnas en Base de Datos
- **Tabla**: `tuu_orders`
- **Columnas**: `discount_10`, `discount_30`, `discount_birthday`, `discount_pizza`
- **Tipo**: Valores numéricos (porcentaje o monto)

### Display en MiniComandas
- **Formato**: Texto descriptivo ("10% descuento") en lugar de solo porcentaje
- **Colores**:
  - `discount_10`: Naranja (#f97316)
  - `discount_30`: Amarillo (#eab308)
  - `discount_birthday`: Rosa (#ec4899)
  - `discount_pizza`: Morado (#a855f7) - cambiado de naranja
- **Badges**: Mostrar solo si descuento > 0

### API get_comandas_v2.php
- **Fix**: Agregadas columnas de descuento al SELECT query
- **Líneas**: 49-54
- **Campos**: `discount_10, discount_30, discount_birthday, discount_pizza`

### Descuento 10% (Retiro)
- **Condición**: Solo aparece cuando delivery_type = 'Retiro'
- **Checkbox**: Permite activar/desactivar descuento
- **Cálculo**: Aplicado al subtotal antes de total final

## 📧 Sistema de Emails

### Sender Name
- **Antes**: Solo email "saboresdelaruta11@gmail.com"
- **Ahora**: "La Ruta 11 <saboresdelaruta11@gmail.com>"
- **Archivos**: `send_email.php`, `send_credit_statement.php`
- **Header**: `From: La Ruta 11 <$from>`

### Email Design - Estado de Cuenta RL6

#### Mobile-First Approach
- **Outer Padding**: 5px (antes 20px) para maximizar espacio en móvil
- **Internal Padding**: 20px (antes 30px) para consistencia
- **Width**: 600px máximo, responsive en móvil

#### Header Redesign
- **Layout**: Logo y texto en fila horizontal (antes vertical)
- **Estructura**: Table con logo inline + div inline
- **Elementos**: Logo 50x50px + título + subtítulo

#### Payment Instructions
- **Sección Nueva**: "Cómo Pagar tu Crédito"
- **Formato**: Pasos numerados con círculos azules
- **Contenido**: Instrucciones paso a paso para pagar con TUU

#### Button Styling
- **Color**: Azul gradient (#3b82f6 → #2563eb)
- **Antes**: Naranja gradient
- **Razón**: Mejor contraste y profesionalismo

#### Card Structure
- **Problema**: "Resumen de Cuenta" más angosto que otras tarjetas
- **Solución**: Cambiar de nested table a div-based structure
- **Width**: `width: 100%` inline en tabla interna
- **Consistencia**: Todas las tarjetas usan mismo padding (20px)

#### Technical Details
- **Inline Styles**: Todos los estilos inline para compatibilidad email
- **Table-Based**: Layout con tables para clientes de email antiguos
- **Escaping**: Comillas escapadas con `&quot;` en HTML
- **Deprecated**: Removido `curl_close()` (no necesario en PHP moderno)

## 🔍 Filtros RL6

### Órdenes RL6 Ocultas
- **Condición**: `order_number NOT LIKE 'RL6-%'`
- **Aplicado en**:
  - Comandas (get_comandas_v2.php)
  - Notificaciones (get_notifications.php)
  - MiniComandas (filtro frontend)
- **Razón**: Órdenes RL6 son pagos de crédito, no pedidos de comida

## 📊 Formato Chileno

### Números
- **Función**: `toLocaleString('es-CL')`
- **Separador Miles**: Punto (.)
- **Separador Decimales**: Coma (,)
- **Ejemplo**: 15990 → "15.990"

### Moneda
- **Símbolo**: $ antes del monto
- **Formato**: "$15.990"
- **Sin Decimales**: Pesos chilenos no usan centavos

### Fechas
- **Formato**: "21 de enero, 2024"
- **Meses**: Array en español (enero, febrero, marzo...)
- **Uso**: Estados de cuenta, vencimientos

## 🤖 Telegram Webhook - Email RL6 Aprobado/Rechazado

### Bug: Email nunca se enviaba al aprobar/rechazar desde Telegram
- **Causa**: El webhook de caja3 hacía `require_once __DIR__ . '/../../app3/api/rl6/send_email.php'` — path que no existe en producción porque caja3 y app3 son **contenedores Docker separados** (no comparten filesystem)
- **Síntoma**: Telegram mostraba "Email enviado a: ejemplo@gmail.com" pero el email nunca llegaba, porque `file_exists()` retornaba `false` y el bloque de envío nunca se ejecutaba
- **Solución**: Agregar función `sendRL6Email()` directamente en `telegram_webhook.php` usando `caja3/api/gmail/get_token_db.php` — la misma infraestructura que ya usa `/admin/emails/` y que funciona correctamente
- **Archivo**: `caja3/api/telegram_webhook.php`
- **Commit**: `6976a49`

### Cómo funciona ahora
- Botones Aprobar ($50k/$30k/$20k) → actualiza BD + llama `sendRL6Email(..., 'aprobado', $limite)`
- Botón Rechazar → actualiza BD + llama `sendRL6Email(..., 'rechazado')`
- `sendRL6Email()` usa `getValidGmailToken()` de `caja3/api/gmail/get_token_db.php` (tokens en MySQL, auto-refresh)
- Reply en Telegram ahora muestra ✅ o ⚠️ según si el email realmente se envió

### Regla clave: contenedores separados
- **NUNCA** usar paths relativos cross-container (`../../app3/...`) en caja3
- Cada contenedor solo puede acceder a sus propios archivos
- Si caja3 necesita funcionalidad de app3, debe replicarla localmente o hacer llamada HTTP
- La infraestructura Gmail (get_token_db.php, tokens en BD) está disponible en **ambos** contenedores

## 🔄 SSE - Tab Crédito RL6 en Tiempo Real

### Problema
- Tab "Crédito" en ProfileModalModern solo aparecía si `credito_aprobado = 1` en el objeto `user` en memoria (localStorage)
- Al aprobar desde Telegram, el usuario no veía el tab hasta hacer re-login

### Solución: Server-Sent Events
- **Endpoint**: `app3/api/auth/credit_status_sse.php` — consulta `credito_aprobado`, `es_militar_rl6`, `credito_bloqueado` cada 10s, emite evento solo cuando cambia
- **Frontend**: `ProfileModalModern.jsx` abre `EventSource` al abrir el modal, actualiza `user` y localStorage al recibir cambio
- **Resultado**: Tab aparece automáticamente ~10s después de que admin aprueba, sin re-login

## 🐛 Bugs Resueltos

1. ✅ **404 en track_usage.php**: Sistema removido completamente
2. ✅ **Logs innecesarios**: Silenciados en funciones sin usuario
3. ✅ **Modal combos no abre**: Acepta 'combos' y 'Combos'
4. ✅ **Campos no editables**: Removido readOnly en checkout
5. ✅ **Descuentos no aparecen**: Agregadas columnas en get_comandas_v2.php
6. ✅ **Descuentos sin texto**: Agregado "descuento" a badges
7. ✅ **Email sin nombre**: Agregado "La Ruta 11" en From header
8. ✅ **Email no mobile-friendly**: Rediseño completo mobile-first
9. ✅ **Tarjetas desiguales**: Estructura consistente en todas las cards
10. ✅ **curl_close deprecado**: Removido de send_credit_statement.php
11. ✅ **Email aprobación RL6 no se enviaba**: Webhook usaba path cross-container inexistente en producción
12. ✅ **Tab Crédito no aparecía sin re-login**: SSE actualiza estado en tiempo real

## 📝 Notas Importantes

### caja3 vs app3
- **Usuario**: caja3 usa `cajaUser`, app3 usa `user`
- **Checkout**: caja3 siempre editable, app3 pre-llena
- **Track Usage**: Solo en app3, removido de caja3
- **Combos**: Ambos aceptan case-insensitive

### Email Best Practices
- Inline styles obligatorios
- Table-based layout para compatibilidad
- Mobile-first con padding mínimo
- Escapar comillas con HTML entities
- Evitar funciones deprecated de PHP

### Descuentos
- Cada tipo tiene su columna en BD
- Display descriptivo en frontend
- Colores diferenciados por tipo
- Solo mostrar si > 0

## 🚀 Próximas Mejoras

1. [ ] Validar límite de crédito RL6 en checkout
2. [ ] Sistema de bloqueos automáticos RL6
3. [ ] Email recordatorio de pago (día 18-19)
4. [ ] Cron job para bloqueos (día 22)
5. [ ] Panel admin para gestionar créditos
6. [ ] Exportar estados de cuenta a PDF
