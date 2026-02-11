# 🐛 ISSUE: Unidades Incorrectas en Inventory Transactions

## 📋 Descripción del Problema

**Fecha Detección:** 10 Noviembre 2025  
**Detectado en:** Sistema de Caja - Página Ventas Detalle  
**Severidad:** 🔴 ALTA - Afecta reportes de consumo de ingredientes  
**Impacto:** Caja + App Clientes

---

## 🔍 Problema Identificado

### Datos Incorrectos en Base de Datos

**Tabla:** `inventory_transactions`

**Ejemplo del Error:**
```
quantity: -0.180
unit: 'g'  ❌ INCORRECTO
```

**Debería ser:**
```
quantity: -0.180
unit: 'kg'  ✅ CORRECTO
```

### Origen del Error

En `product_recipes`:
- Ingrediente: Filete Pechuga de Pollo
- Cantidad: 180.000
- Unidad: 'g'

Cuando se procesa la venta en `api/process_sale_inventory.php`:
1. ✅ Convierte 180g a 0.180kg correctamente
2. ❌ Guarda en `inventory_transactions` con `unit='g'` (incorrecto)
3. ❌ Resultado: `quantity=-0.180, unit='g'` (sin sentido)

### Impacto Visual

**Frontend mostraba:**
```
Filete Pechuga de Pollo: 0 g  ❌
```

**Debería mostrar:**
```
Filete Pechuga de Pollo: 180 g  ✅
```

---

## ✅ Solución Implementada

### 1. Backend - process_sale_inventory.php

**Archivo:** `api/process_sale_inventory.php`  
**Líneas:** 68-78

**ANTES:**
```php
$ingredient_quantity = $ingredient['quantity'];
if ($ingredient['unit'] === 'g') {
    $ingredient_quantity = $ingredient_quantity / 1000;
}
// ...
$trans_stmt->execute([
    $ingredient['ingredient_id'],
    -$total_needed,
    $ingredient['unit'],  // ❌ Guarda 'g'
    // ...
]);
```

**DESPUÉS:**
```php
$ingredient_quantity = $ingredient['quantity'];
$transaction_unit = $ingredient['unit'];

if ($ingredient['unit'] === 'g') {
    $ingredient_quantity = $ingredient_quantity / 1000;
    $transaction_unit = 'kg';  // ✅ Guarda 'kg'
}
// ...
$trans_stmt->execute([
    $ingredient['ingredient_id'],
    -$total_needed,
    $transaction_unit,  // ✅ Correcto
    // ...
]);
```

### 2. Backend - get_sales_detail.php

**Archivo:** `api/get_sales_detail.php`  
**Líneas:** 73-85

**Conversión para visualización:**
```php
foreach ($transactions as $trans) {
    $qtyUsed = abs(floatval($trans['quantity']));
    $unit = $trans['unit'];
    
    // Convertir kg a g para visualización
    if ($unit === 'kg') {
        $qtyUsed = $qtyUsed * 1000;  // 0.180 kg → 180 g
        $unit = 'g';
    }
    // ...
}
```

### 3. Frontend - ventas-detalle.astro

**Archivo:** `src/pages/ventas-detalle.astro`

**Muestra directamente sin conversión:**
```javascript
const qty = parseFloat(ing.quantity_needed || 0);
const unit = ing.unit || '';
html += `${qty} ${unit}`;  // Muestra tal cual viene del backend
```

---

## 🔧 SQL para Corregir Datos Existentes

### Análisis de Datos Afectados

```sql
-- Ver transacciones con unit='g' y quantity < 1
SELECT 
    id,
    ingredient_id,
    quantity,
    unit,
    order_reference,
    created_at
FROM inventory_transactions
WHERE unit = 'g' 
  AND ABS(quantity) < 1
  AND ingredient_id IS NOT NULL
ORDER BY created_at DESC;
```

### Script de Corrección

```sql
-- BACKUP PRIMERO
CREATE TABLE inventory_transactions_backup_20251110 AS 
SELECT * FROM inventory_transactions;

-- Corregir transacciones con unit='g' y quantity < 1
UPDATE inventory_transactions
SET unit = 'kg'
WHERE unit = 'g' 
  AND ABS(quantity) < 1
  AND ingredient_id IS NOT NULL;

-- Verificar corrección
SELECT 
    COUNT(*) as registros_corregidos
FROM inventory_transactions
WHERE unit = 'kg' 
  AND ABS(quantity) < 1
  AND ingredient_id IS NOT NULL;
```

### Validación Post-Corrección

```sql
-- Verificar que no queden registros incorrectos
SELECT 
    id,
    ingredient_id,
    quantity,
    unit,
    CASE 
        WHEN unit = 'g' AND ABS(quantity) < 1 THEN '❌ INCORRECTO'
        WHEN unit = 'kg' AND ABS(quantity) < 1 THEN '✅ CORRECTO'
        ELSE '✅ OK'
    END as estado
FROM inventory_transactions
WHERE ingredient_id IS NOT NULL
ORDER BY created_at DESC
LIMIT 100;
```

---

## 📊 Impacto del Issue

### Sistemas Afectados
- ✅ **Caja:** Corregido
- ⚠️ **App Clientes:** Pendiente aplicar mismo fix
- ✅ **Admin Pagos-TUU:** Usa mismo API, corregido automáticamente

### Archivos Modificados
1. `api/process_sale_inventory.php` - Guarda unit correcto
2. `api/get_sales_detail.php` - Convierte kg→g para visualización
3. `src/pages/ventas-detalle.astro` - Muestra sin conversión

### Datos Históricos
- ⚠️ Transacciones anteriores tienen datos incorrectos
- ✅ SQL de corrección disponible arriba
- 📅 Ejecutar SQL en horario de bajo tráfico

---

## 🎯 Próximos Pasos

### Inmediato
- [ ] Ejecutar SQL de corrección en producción
- [ ] Aplicar mismo fix en App Clientes
- [ ] Verificar que nuevas transacciones se guarden correctamente

### Seguimiento
- [ ] Monitorear reportes de consumo por 1 semana
- [ ] Validar que no aparezcan más "0 g" en ingredientes
- [ ] Documentar en README principal

### Prevención
- [ ] Agregar validación en process_sale_inventory.php
- [ ] Test unitario para conversión de unidades
- [ ] Alerta si se detecta quantity < 1 con unit='g'

---

## 📝 Notas Técnicas

### Lógica de Conversión

**Stock en BD (ingredients):**
- Siempre en kg (ej: 4.570 kg)

**Recetas (product_recipes):**
- Pueden estar en g o kg (ej: 180 g)

**Transacciones (inventory_transactions):**
- Deben estar en kg si se convirtió (ej: -0.180 kg)
- Deben estar en unidad original si no se convirtió

**Visualización Frontend:**
- Siempre en g para mejor legibilidad (ej: 180 g)
- Convierte a kg solo si > 1000g (ej: 1.5 kg)

### Regla de Oro
> **Si quantity < 1 y unit = 'g' → ERROR DE DATOS**  
> Debería ser quantity en kg con unit = 'kg'

---

## 🔗 Referencias

- Issue detectado en: `/caja/ventas-detalle`
- Código corregido: Commit del 10-Nov-2025
- SQL de corrección: Ver sección arriba
- Aplicar en: App Clientes (mismo código)

---

**Última actualización:** 10 Noviembre 2025  
**Estado:** ✅ Corregido en Caja | ⚠️ Pendiente en App Clientes
