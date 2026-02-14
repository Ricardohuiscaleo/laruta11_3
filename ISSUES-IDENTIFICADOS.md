# 🐛 Issues Identificados - La Ruta 11

## 1. ❌ Error de Sesión: "SyntaxError: The string did not match the expected pattern"

### Síntomas:
- Usuario se guarda en localStorage correctamente
- `setUser()` se ejecuta exitosamente
- Inmediatamente después dice "No hay usuario logueado"
- El estado `user` no se actualiza

### Causa:
React NO actualiza el estado inmediatamente. `setUser()` es asíncrono.

### Solución:
```javascript
// En lugar de verificar `user` inmediatamente después de setUser()
setUser(userData);
if (!user) { // ❌ ESTO FALLA porque user aún es null
  console.log('No hay usuario');
}

// Usar useEffect para reaccionar a cambios de user
useEffect(() => {
  if (user) {
    loadNotifications();
    loadUserOrders();
  }
}, [user]); // ✅ Se ejecuta cuando user cambia
```

---

## 2. ⚠️ Crédito RL6 no aparece para usuarios sin google_id

### Síntomas:
- Usuario tiene `es_militar_rl6 = 1` y `credito_aprobado = 1` en BD
- Cierra sesión y vuelve a entrar
- El crédito NO aparece en el checkout

### Causa:
El usuario necesita **refrescar manualmente** su perfil después de la aprobación.

### Solución:
✅ Ya implementado: Botón de refresh en ProfileModalModern.jsx

---

## 3. 📱 Header de perfil se desborda en móviles

### Síntomas:
- Nombre largo + email largo causan overflow
- Texto se sale del contenedor

### Solución:
✅ Ya implementado: Reducción de tamaños y `truncate` en textos

---

## 4. 🔒 NO hay límite de intentos fallidos de login

### Estado:
- NO existe protección contra fuerza bruta
- NO hay rate limiting en el login

### Recomendación:
Implementar:
- Límite de 5 intentos por IP cada 15 minutos
- Captcha después de 3 intentos fallidos
- Bloqueo temporal de cuenta después de 10 intentos

---

## 5. 📄 Página RL6 requiere cerrar sesión manualmente

### Síntomas:
- Después de aprobación, usuario debe cerrar sesión y volver a entrar
- NO se actualiza automáticamente

### Causa:
La sesión en localStorage no se sincroniza con la BD automáticamente.

### Solución propuesta:
Agregar polling cada 30 segundos para verificar cambios en `es_militar_rl6` y `credito_aprobado`.

---

## 6. 🔍 Logs excesivos en producción

### Síntomas:
- Muchos `console.log()` en producción
- Información sensible expuesta en consola

### Recomendación:
- Remover logs de debug en build de producción
- Usar `console.error()` solo para errores críticos
- Implementar sistema de logging en servidor

---

## Prioridad de fixes:

1. 🔴 **CRÍTICO**: Error de sesión (Issue #1)
2. 🟠 **ALTO**: Crédito RL6 no aparece (Issue #2)
3. 🟡 **MEDIO**: Límite de intentos de login (Issue #4)
4. 🟢 **BAJO**: Logs en producción (Issue #6)

---

**Fecha**: 2026-02-13
**Responsable**: Ricardo
