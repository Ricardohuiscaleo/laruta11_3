# 📋 Estructura de Categorías y Subcategorías - La Ruta 11

## Resumen General

| Cat ID | Categoría | Subcategorías | Total |
|--------|-----------|---------------|-------|
| 1 | La Ruta 11 | 1 | 1 |
| 2 | Sandwiches | 6 | 6 |
| 3 | Hamburguesas | 2 | 2 |
| 4 | Completos | 3 | 3 |
| 5 | Snacks | 11 | 11 |
| 6 | Personalizar | 1 | 1 |
| 7 | Extras | 1 | 1 |
| 8 | Combos | 4 | 4 |
| 12 | Papas | 2 | 2 |

---

## Detalle por Categoría

### 1️⃣ Categoría 1: La Ruta 11
**Descripción**: Cortes premium y especialidades de la casa

| Subcat ID | Nombre | Slug |
|-----------|--------|------|
| 50 | Tomahawks | tomahawk |

---

### 2️⃣ Categoría 2: Sandwiches
**Descripción**: Churrascos de carne y pollo

| Subcat ID | Nombre | Slug |
|-----------|--------|------|
| 1 | Tomahawks | tomahawks |
| 3 | Pollo | pollo |
| 48 | Salchichas | salchichas |
| 49 | Lomito (Cerdo) | lomito (Cerdo) |
| 51 | Lomo Vetado | lomo-vetado |
| 52 | Churrasco | churrasco |

---

### 3️⃣ Categoría 3: Hamburguesas
**Descripción**: Hamburguesas clásicas y especiales

| Subcat ID | Nombre | Slug | Tamaño |
|-----------|--------|------|--------|
| 5 | Clásicas | clasicas | 100g |
| 6 | Especiales | especiales | 200g |

**Filtros en MenuApp.jsx**:
```javascript
hamburguesas_100g: { category_id: 3, subcategory_id: 5 }  // Solo clásicas
hamburguesas: { category_id: 3, subcategory_id: 6 }       // Solo especiales
```

---

### 4️⃣ Categoría 4: Completos
**Descripción**: Completos tradicionales y al vapor

| Subcat ID | Nombre | Slug |
|-----------|--------|------|
| 6 | Especiales | Especiales |
| 7 | Tradicionales | tradicionales |
| 47 | Especiales | Especiales |

---

### 5️⃣ Categoría 5: Snacks
**Descripción**: Papas, jugos, bebidas y salsas

| Subcat ID | Nombre | Slug |
|-----------|--------|------|
| 2 | Carne | carne |
| 9 | Papas | papas |
| 10 | Jugos | jugos |
| 11 | Bebidas | bebidas |
| 12 | Salsas | salsas |
| 26 | Empanadas | empanadas |
| 27 | Café | cafe |
| 28 | Té | té |
| 30 | Extras | extras |
| 59 | Hipocalóricos | hipocaloricos |
| 60 | Pizzas | pizzas |

---

### 6️⃣ Categoría 6: Personalizar
**Descripción**: Opciones para personalizar tu pedido

| Subcat ID | Nombre | Slug |
|-----------|--------|------|
| 29 | Personalizar | personalizar |

---

### 7️⃣ Categoría 7: Extras
**Descripción**: Servicios especiales y divertidos

| Subcat ID | Nombre | Slug |
|-----------|--------|------|
| 30 | Extras | extras |

---

### 8️⃣ Categoría 8: Combos
**Descripción**: Combos especiales y promociones

| Subcat ID | Nombre | Slug |
|-----------|--------|------|
| 31 | Hamburguesas | hamburguesas |
| 46 | Completos | completos |
| 48 | Sándwiches | Sándwiches |
| 57 | Papas | papas |

---

### 1️⃣2️⃣ Categoría 12: Papas
**Descripción**: Papas Fritas Rústicas

| Subcat ID | Nombre | Slug |
|-----------|--------|------|
| 9 | Papas | papas |
| 57 | Papas | papas |

---

## Notas Importantes

⚠️ **Subcategorías duplicadas**:
- Subcat 30 (Extras) aparece en Categoría 5 y 7
- Subcat 9 (Papas) aparece en Categoría 5 y 12
- Subcat 57 (Papas) aparece en Categoría 8 y 12

✅ **Filtros por ID**: Todos los filtros en MenuApp.jsx usan `category_id` y `subcategory_id` para máxima eficiencia

🔧 **Para agregar nuevos filtros**:
```javascript
const categoryFilters = {
  nombreFiltro: { category_id: X, subcategory_id: Y }
};
```
