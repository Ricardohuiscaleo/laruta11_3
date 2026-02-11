# 🔧 Troubleshooting - Sesión en Móvil

## ❌ **Problema: "No funciona en móvil"**

### 🔍 **Diagnóstico Rápido**

Abre la consola del navegador móvil y busca estos mensajes:

```
✅ Usuario cargado desde localStorage: Juan
✅ Sesión guardada en localStorage
✅ Login Google exitoso, sesión guardada
⚠️ localStorage no disponible (modo privado?)
⚠️ No se pudo guardar en localStorage
```

---

## 🚨 **Causas Comunes**

### **1. Modo Privado/Incógnito Activo**
**Síntoma:** Sesión se pierde al cerrar app

**Solución:**
- Safari iOS: Desactivar "Navegación Privada"
- Chrome Android: Salir del modo incógnito
- Firefox: Desactivar "Navegación privada"

**Verificar:**
```javascript
// En consola del navegador
localStorage.setItem('test', '123');
console.log(localStorage.getItem('test')); // Debe mostrar '123'
```

---

### **2. Configuración de Privacidad Estricta**

#### **Safari iOS:**
1. Ajustes → Safari
2. Desactivar "Prevenir rastreo entre sitios"
3. Desactivar "Bloquear todas las cookies"

#### **Chrome Android:**
1. Configuración → Privacidad y seguridad
2. Cookies → Permitir cookies
3. Desactivar "Borrar cookies al salir"

#### **Firefox Android:**
1. Configuración → Privacidad
2. Cookies → Permitir todas
3. Desactivar "Eliminar datos al salir"

---

### **3. PWA Instalada con Restricciones**

**Síntoma:** Funciona en navegador pero no en PWA instalada

**Solución:**
1. Desinstalar PWA
2. Limpiar caché del navegador
3. Reinstalar PWA desde navegador

**iOS:**
- Ajustes → Safari → Avanzado → Datos de sitios web → Eliminar todo

**Android:**
- Ajustes → Apps → Chrome → Almacenamiento → Borrar datos

---

### **4. Almacenamiento Lleno**

**Síntoma:** Error al guardar en localStorage

**Verificar:**
```javascript
// En consola
try {
  localStorage.setItem('test_large', 'x'.repeat(5000000));
  console.log('✅ Espacio disponible');
} catch (e) {
  console.log('❌ Almacenamiento lleno:', e);
}
```

**Solución:**
- Limpiar datos de sitios web
- Desinstalar apps innecesarias
- Liberar espacio en dispositivo

---

## 🧪 **Tests de Diagnóstico**

### **Test 1: localStorage Disponible**
```javascript
// Copiar en consola del navegador móvil
try {
  localStorage.setItem('ruta11_test', 'OK');
  const result = localStorage.getItem('ruta11_test');
  localStorage.removeItem('ruta11_test');
  console.log(result === 'OK' ? '✅ localStorage funciona' : '❌ localStorage no funciona');
} catch (e) {
  console.log('❌ localStorage bloqueado:', e.message);
}
```

### **Test 2: Sesión Guardada**
```javascript
// Después de hacer login
const user = localStorage.getItem('ruta11_user');
if (user) {
  console.log('✅ Sesión guardada:', JSON.parse(user).nombre);
} else {
  console.log('❌ No hay sesión guardada');
}
```

### **Test 3: Cookies Funcionando**
```javascript
// En consola
document.cookie = "test=123; path=/";
console.log(document.cookie.includes('test=123') ? '✅ Cookies funcionan' : '❌ Cookies bloqueadas');
```

---

## 🔄 **Flujo de Sesión Correcto**

### **Login Manual:**
```
1. Usuario ingresa email/password
2. AuthModal envía a /api/auth/login_manual.php
3. ✅ Servidor crea sesión PHP
4. ✅ Frontend guarda en localStorage
5. ✅ Usuario ve su perfil
```

### **Login Google:**
```
1. Usuario hace click en "Google"
2. Redirige a Google OAuth
3. Google redirige a /api/auth/google/callback.php
4. ✅ Servidor crea sesión PHP
5. Redirige a /?login=success
6. ✅ Frontend detecta parámetro
7. ✅ Carga sesión desde servidor
8. ✅ Guarda en localStorage
9. ✅ Usuario ve su perfil
```

### **Recarga de App:**
```
1. Usuario abre app
2. ✅ Lee localStorage (instantáneo)
3. ✅ Muestra usuario
4. ✅ Verifica con servidor (background)
5. ✅ Actualiza datos si cambió algo
```

---

## 📱 **Problemas Específicos por Navegador**

### **Safari iOS**
**Problema:** localStorage se borra al cerrar app
**Causa:** Intelligent Tracking Prevention (ITP)
**Solución:** 
- Agregar app a pantalla de inicio (PWA)
- Usar app desde PWA, no desde Safari

### **Chrome Android**
**Problema:** Sesión se pierde en modo ahorro de datos
**Causa:** Chrome limpia caché agresivamente
**Solución:**
- Desactivar "Modo Lite" en Chrome
- Configuración → Modo Lite → Desactivar

### **Firefox Android**
**Problema:** localStorage no persiste
**Causa:** Protección contra rastreo estricta
**Solución:**
- Configuración → Protección contra rastreo → Estándar

### **Samsung Internet**
**Problema:** Cookies bloqueadas por defecto
**Solución:**
- Menú → Configuración → Sitios y descargas
- Cookies → Permitir todas

---

## 🛠️ **Solución Definitiva**

Si nada funciona, usar **solo cookies PHP** (fallback):

1. El sistema ya funciona con cookies PHP
2. localStorage es un **extra** para mejor UX
3. Si localStorage falla, cookies PHP mantienen sesión
4. Usuario puede seguir usando la app normalmente

**Verificar cookies:**
```javascript
// En consola después de login
fetch('/api/auth/check_session.php')
  .then(r => r.json())
  .then(d => console.log(d.authenticated ? '✅ Sesión activa' : '❌ Sin sesión'));
```

---

## 📊 **Estadísticas de Compatibilidad**

| Navegador | localStorage | Cookies | PWA |
|-----------|-------------|---------|-----|
| Safari iOS | ⚠️ Limitado | ✅ Sí | ✅ Sí |
| Chrome Android | ✅ Sí | ✅ Sí | ✅ Sí |
| Firefox Android | ✅ Sí | ✅ Sí | ✅ Sí |
| Samsung Internet | ✅ Sí | ⚠️ Config | ✅ Sí |
| Opera Mobile | ✅ Sí | ✅ Sí | ✅ Sí |

---

## 🎯 **Recomendación Final**

**Para mejor experiencia:**
1. Instalar como PWA (agregar a pantalla de inicio)
2. Permitir cookies en configuración del navegador
3. No usar modo privado/incógnito
4. Mantener espacio disponible en dispositivo

**El sistema funciona con:**
- ✅ localStorage (mejor UX)
- ✅ Cookies PHP (fallback)
- ✅ Verificación servidor (seguridad)

**Si localStorage falla, la app sigue funcionando con cookies PHP.**

---

## 📞 **Soporte**

Si el problema persiste:
1. Abrir consola del navegador (F12 en PC, Remote Debug en móvil)
2. Buscar mensajes con ✅ ❌ ⚠️
3. Tomar screenshot de la consola
4. Reportar con detalles del dispositivo y navegador

---

**Última actualización:** 2024  
**Sistema:** La Ruta 11 - Sesión Híbrida v1.0
