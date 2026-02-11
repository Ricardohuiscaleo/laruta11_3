# Mejoras Pendientes Dashboard

## 1. CRÍTICO: Usar `amount` (valor orden) en lugar de valor productos

**Problema actual:**
- Los gráficos están usando el valor de productos sin considerar descuentos
- Si una hamburguesa tiene 10% descuento, el gráfico muestra el precio original

**Solución:**
- Usar `t.amount` que es el valor REAL pagado (con descuentos aplicados)
- Ya se está usando correctamente en `processSalesDataByPayment()`
- ✅ VERIFICADO: El código actual YA usa `parseFloat(t.amount || 0)`

## 2. Considerar TURNOS en cálculos

**Regla de turnos:**
- Horario 00:00 - 03:59 → Pertenece al DÍA ANTERIOR
- Horario 04:00 - 23:59 → Pertenece al día actual

**Archivos a modificar:**
- `get_smart_projection.php` - ✅ YA implementado
- `processSalesDataByPayment()` - Pendiente agregar lógica de turnos

## 3. Labels mejorados con día de semana

**Formato deseado desde 1S en adelante:**
```
1L  2M  3Mi  4J  5V  6S  7D  8L  9M  10Mi  11J  12V  13S  14D
                              ↑ ROJO (Domingo)                ↑ ROJO
```

**Implementación:**
```javascript
const dayNames = ['D', 'L', 'M', 'Mi', 'J', 'V', 'S'];
const date = new Date(key);
const dayNum = date.getDate();
const dayName = dayNames[date.getDay()];
const label = `${dayNum}${dayName}`;
const isWeekend = date.getDay() === 0 || date.getDay() === 6;
```

## 4. Separadores de mes para vistas 1M+

**Representación visual deseada:**

```
Eje X del gráfico:
1L 2M 3Mi ... 28V 29S 30D | 1L 2M 3Mi ... 30J 31V | 1S 2D 3L ...
|______ Septiembre ______| |______ Octubre ______| |__ Noviembre

```

**Implementación con Chart.js:**
```javascript
scales: {
  x: {
    ticks: {
      callback: function(value, index) {
        return labels[index]; // "1L", "2M", etc.
      },
      color: function(context) {
        const date = dates[context.index];
        return date.getDay() === 0 ? '#dc2626' : '#666'; // Rojo domingos
      }
    },
    afterFit: function(scale) {
      // Agregar labels de mes debajo
    }
  }
}
```

## 5. Código a implementar

### A. Función para calcular día de turno
```javascript
function getShiftDate(date) {
  const d = new Date(date);
  const hour = d.getHours();
  if (hour >= 0 && hour < 4) {
    d.setDate(d.getDate() - 1);
  }
  return d;
}
```

### B. Actualizar processSalesDataByPayment()
```javascript
// En lugar de usar directamente tDate, usar:
const shiftDate = getShiftDate(tDate);
const key = shiftDate.toISOString().split('T')[0];
```

### C. Labels con día de semana
```javascript
const dayNames = ['D', 'L', 'M', 'Mi', 'J', 'V', 'S'];
labels.push(`${date.getDate()}${dayNames[date.getDay()]}`);
```

### D. Configuración Chart.js para colores
```javascript
scales: {
  x: {
    ticks: {
      color: function(context) {
        const index = context.index;
        const date = dateObjects[index];
        return date.getDay() === 0 ? '#dc2626' : '#666';
      },
      font: function(context) {
        const index = context.index;
        const date = dateObjects[index];
        return {
          weight: date.getDay() === 0 ? 'bold' : 'normal'
        };
      }
    }
  }
}
```

## 6. Prioridad de implementación

1. ✅ **VERIFICADO**: Ya se usa `amount` correctamente
2. ✅ **COMPLETADO**: Implementar lógica de turnos en `processSalesDataByPayment()`
3. ✅ **COMPLETADO**: Labels con día de semana (1L, 2M, etc.)
4. ✅ **COMPLETADO**: Domingos en rojo y negrita
5. 🟢 **BAJA**: Separadores de mes (nice to have)

## 7. Testing

Verificar que:
- [x] Venta a las 02:00 del 12 Nov → Cuenta para el 11 Nov ✅
- [x] Venta a las 04:00 del 12 Nov → Cuenta para el 12 Nov ✅
- [x] Descuentos se reflejan correctamente (usar `amount`) ✅
- [x] Domingos aparecen en rojo y negrita ✅
- [x] Labels muestran "1L", "2M", "3Mi", etc. ✅
- [x] Gráfico de proyección muestra todos los días del mes (1-30) ✅
- [x] Gráfico "Ventas por Período" con labels mejorados ✅

## 8. Implementación Completada

### ✅ Cambios Realizados:

**Frontend (`index.astro`):**
- Función `getShiftDate()` para lógica de turnos (00:00-03:59 = día anterior)
- Labels con formato `1L`, `2M`, `3Mi`, etc. en ambos gráficos
- Domingos en rojo (#dc2626) y negrita
- Array `dateObjects` para tracking de fechas y colores
- Aplicado en "Ventas por Período" y "Proyección de Ventas"

**Backend (`get_smart_projection.php`):**
- Ya tenía lógica de turnos implementada
- Agregados campos `year` y `month` a cada item de proyección
- Genera proyección completa del día 1 al último día del mes
