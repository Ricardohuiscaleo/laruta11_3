# Plan: Sistema Dinámico de Combos - Eliminar Mapeo Manual

## 🎯 Problema Identificado

**Situación Actual**: El sistema usa mapeo manual en ComboModal.jsx que requiere actualización manual cada vez que se crea un combo nuevo.

```javascript
// PROBLEMA: Mapeo manual que requiere mantenimiento
const comboNameMapping = {
  'Combo Doble Mixta': 1,
  'Combo Completo': 2, 
  'Combo Gorda': 3,
  'Combo Dupla': 4,
  'Combo Salchipapas x2': 234  // ← Tuvimos que agregar esto manualmente
};
```

## 🚀 Solución Propuesta: Sistema Dinámico

### **Detección Automática por Categoría**

Todos los combos tienen `category_id = 8`, podemos usar esto para detección automática:

```javascript
// SOLUCIÓN: Detección dinámica
if (combo.category_id === 8) {
  // Buscar combo por nombre en tabla combos
  const response = await fetch(`/api/get_combos.php?name=${encodeURIComponent(combo.name)}`);
  const data = await response.json();
  if (data.success && data.combos.length > 0) {
    realComboId = data.combos[0].id;
  }
}
```

## 📋 Plan de Implementación (Mañana)

### **Paso 1: Modificar API get_combos.php**
```php
// Agregar soporte para búsqueda por nombre
$combo_name = isset($_GET['name']) ? trim($_GET['name']) : null;

if ($combo_name) {
    $stmt = $pdo->prepare("
        SELECT c.*, cat.name as category_name 
        FROM combos c 
        LEFT JOIN categories cat ON c.category_id = cat.id 
        WHERE c.active = 1 AND c.name = ?
        ORDER BY c.name
    ");
    $stmt->execute([$combo_name]);
}
```

### **Paso 2: Modificar ComboModal.jsx**
```javascript
// Reemplazar mapeo manual con detección dinámica
const loadComboData = async () => {
  let realComboId = combo.id;
  
  // Si es categoría Combos (8), buscar por nombre
  if (combo.category_id === 8) {
    try {
      const nameResponse = await fetch(`/api/get_combos.php?name=${encodeURIComponent(combo.name)}`);
      const nameData = await nameResponse.json();
      if (nameData.success && nameData.combos.length > 0) {
        realComboId = nameData.combos[0].id;
      }
    } catch (error) {
      console.log('Error finding combo by name, using product ID:', error);
    }
  }
  
  // Continuar con carga normal usando realComboId
  const response = await fetch(`/api/get_combos.php?combo_id=${realComboId}`);
  // ... resto del código
};
```

### **Paso 3: Eliminar Mapeo Manual**
```javascript
// ELIMINAR COMPLETAMENTE:
const comboNameMapping = {
  'Combo Doble Mixta': 1,
  'Combo Completo': 2, 
  'Combo Gorda': 3,
  'Combo Dupla': 4,
  'Combo Salchipapas x2': 234
};
```

## ✅ Beneficios del Sistema Dinámico

### **Antes (Manual)**
- ❌ Cada combo nuevo requiere modificar código
- ❌ Propenso a errores de mantenimiento
- ❌ Desarrollador debe recordar actualizar mapeo
- ❌ Riesgo de combos que no funcionen

### **Después (Dinámico)**
- ✅ Combos nuevos funcionan automáticamente
- ✅ Cero mantenimiento de código
- ✅ Detección automática por categoría
- ✅ Sistema robusto y escalable

## 🔧 Archivos a Modificar

### **1. api/get_combos.php**
- Agregar parámetro `?name=` para búsqueda por nombre
- Mantener compatibilidad con `?combo_id=`

### **2. src/components/modals/ComboModal.jsx**
- Eliminar `comboNameMapping` completamente
- Implementar detección por `category_id === 8`
- Agregar fallback robusto

## 🧪 Testing Plan

### **Casos de Prueba**
1. **Combo Existente**: "Combo Gorda" → Debe encontrar ID 3
2. **Combo Nuevo**: "Combo Salchipapas x2" → Debe encontrar ID 234
3. **Producto Regular**: Cualquier producto no-combo → Usar ID original
4. **Error Handling**: Si falla búsqueda → Fallback gracioso

### **Validación**
- ✅ Todos los combos actuales siguen funcionando
- ✅ Combos futuros funcionan sin modificar código
- ✅ Productos regulares no se ven afectados
- ✅ Performance no se degrada

## ⏰ Cronograma de Implementación

### **Horario Sugerido: Madrugada (3-5 AM)**
- **3:00 AM**: Backup de archivos actuales
- **3:15 AM**: Modificar get_combos.php
- **3:30 AM**: Modificar ComboModal.jsx
- **4:00 AM**: Testing completo
- **4:30 AM**: Deploy y monitoreo
- **5:00 AM**: Validación final

### **Rollback Plan**
- Mantener backup de archivos originales
- Si hay problemas → Restaurar inmediatamente
- Monitorear logs por 30 minutos post-deploy

## 🎯 Resultado Esperado

**Antes del cambio**:
```
Nuevo combo → No funciona → Desarrollador debe agregar mapeo → Deploy
```

**Después del cambio**:
```
Nuevo combo → Funciona automáticamente ✅
```

## 📊 Impacto en Producción

### **Riesgo**: BAJO
- Cambio no afecta funcionalidad existente
- Solo mejora la detección de combos
- Fallback robusto mantiene compatibilidad

### **Beneficio**: ALTO
- Elimina mantenimiento manual permanentemente
- Sistema más robusto y escalable
- Menos errores de configuración

---

**Nota**: Este plan se ejecutará en horario de baja actividad para minimizar impacto en clientes.