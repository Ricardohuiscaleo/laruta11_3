# 📱 Documentación del Menú de Categorías - La Ruta 11

## 📍 Ubicación
**Header Superior - Fila 2**
- Posición: Debajo de la fila principal del header (Logo, Status, Búsqueda, etc.)
- Comportamiento: Sticky/Fixed con scroll dinámico

---

## 🎨 Diseño Visual

### Estructura General
```
┌─────────────────────────────────────────────────────────┐
│  [🍔 Hamburguesas] [🍔 Hamburguesas] [🥪 Sandwiches]   │
│     (200g)            (100g)                             │
│  [🌭 Completos] [🍟 Papas] [🍕 Pizzas] [🥤 Bebidas]    │
└─────────────────────────────────────────────────────────┘
```

### Características Visuales
- **Scroll Horizontal**: Deslizable con scrollbar visible
- **Indicador Visual**: Banner amarillo "Desliza para ver más" con flecha animada
- **Estados Visuales**:
  - Activo: Gradiente naranja-rojo con texto blanco
  - Inactivo: Texto gris con hover naranja

---

## 🔧 Componentes Técnicos

### 1. Estados React
```javascript
const [activeCategory, setActiveCategory] = useState('hamburguesas');
const [isCategoriesVisible, setIsCategoriesVisible] = useState(true);
const [isScrolledToEnd, setIsScrolledToEnd] = useState(false);
const categoriesScrollRef = useRef(null);
```

### 2. Configuración de Categorías (CÓDIGO EXACTO)
```javascript
// Línea 56 de MenuApp.jsx
const mainCategories = ['hamburguesas', 'hamburguesas_100g', 'churrascos', 'completos', 'papas', 'pizzas', 'bebidas', 'Combos'];
```

**Explicación:**
- `hamburguesas` → Hamburguesas 200g (Especiales, subcategory_id 6)
- `hamburguesas_100g` → Hamburguesas 100g (Clásicas, subcategory_id 5)
- `churrascos` → Sandwiches
- `completos` → Completos
- `papas` → Papas Fritas (category_id 12, subcategory_ids 9 y 57)
- `pizzas` → Pizzas (category_id 5, subcategory_id 60)
- `bebidas` → Bebidas/Jugos/Té/Café (category_id 5, subcategory_ids 11, 10, 28, 27)
- `Combos` → Combos

### 3. Nombres de Visualización (CÓDIGO EXACTO)
```javascript
// Líneas 45-54 de MenuApp.jsx
const categoryDisplayNames = {
  hamburguesas: "Hamburguesas\n(200g)",
  hamburguesas_100g: "Hamburguesas\n(100g)",
  churrascos: "Sandwiches", 
  completos: "Completos",
  papas: "Papas",
  pizzas: "Pizzas",
  bebidas: "Bebidas",
  Combos: "Combos"
};
```

**Nota:** El `\n` crea un salto de línea en el botón para mostrar "Hamburguesas" en una línea y "(200g)" o "(100g)" en otra.

### 4. Iconos por Categoría (CÓDIGO EXACTO)
```javascript
// Líneas 67-81 de MenuApp.jsx
const categoryIcons = {
  hamburguesas: <GiHamburger style={{width: 'clamp(19.2px, 4.8vw, 24px)', height: 'clamp(19.2px, 4.8vw, 24px)'}} />,
  hamburguesas_100g: <GiHamburger style={{width: 'clamp(13.2px, 3.36vw, 16.8px)', height: 'clamp(13.2px, 3.36vw, 16.8px)'}} />,
  churrascos: <GiSandwich style={{width: 'clamp(19.2px, 4.8vw, 24px)', height: 'clamp(19.2px, 4.8vw, 24px)'}} />,
  completos: <GiHotDog style={{width: 'clamp(19.2px, 4.8vw, 24px)', height: 'clamp(19.2px, 4.8vw, 24px)'}} />,
  papas: <GiFrenchFries style={{width: 'clamp(19.2px, 4.8vw, 24px)', height: 'clamp(19.2px, 4.8vw, 24px)'}} />,
  pizzas: <Pizza style={{width: 'clamp(19.2px, 4.8vw, 24px)', height: 'clamp(19.2px, 4.8vw, 24px)'}} />,
  bebidas: <CupSoda style={{width: 'clamp(19.2px, 4.8vw, 24px)', height: 'clamp(19.2px, 4.8vw, 24px)'}} />,
  Combos: (
    <div style={{display: 'flex', alignItems: 'center', gap: '2px'}}>
      <GiHamburger style={{width: 'clamp(12px, 3vw, 16.8px)', height: 'clamp(12px, 3vw, 16.8px)'}} />
      <CupSoda style={{width: 'clamp(12px, 3vw, 16.8px)', height: 'clamp(12px, 3vw, 16.8px)'}} />
    </div>
  )
};
```

**Características:**
- Usa `clamp()` para tamaños responsivos
- `hamburguesas_100g` tiene icono 30% más pequeño (16.8px vs 24px)
- `Combos` combina dos iconos (hamburguesa + bebida)

---

## 🎯 Lógica de Filtrado

### Filtros por Category ID y Subcategory ID (CÓDIGO EXACTO)
```javascript
// Líneas 57-63 de MenuApp.jsx
const categoryFilters = {
  hamburguesas_100g: { category_id: 3, subcategory_id: 5 },
  hamburguesas: { category_id: 3, subcategory_id: 6 },
  papas: { category_id: 12, subcategory_ids: [9, 57] },
  pizzas: { category_id: 5, subcategory_id: 60 },
  bebidas: { category_id: 5, subcategory_ids: [11, 10, 28, 27] }
};
```

**Mapeo de IDs:**

| Categoría | Category ID | Subcategory ID(s) | Descripción |
|-----------|-------------|-------------------|-------------|
| `hamburguesas_100g` | 3 | 5 | Hamburguesas Clásicas 100g |
| `hamburguesas` | 3 | 6 | Hamburguesas Especiales 200g |
| `papas` | 12 | 9, 57 | Papas Fritas (ambas subcategorías) |
| `pizzas` | 5 | 60 | Pizzas |
| `bebidas` | 5 | 11, 10, 28, 27 | Bebidas (11), Jugos (10), Té (28), Café (27) |

### Lógica de Filtrado en Renderizado (CÓDIGO EXACTO)
```javascript
// Líneas 1300-1400 aprox de MenuApp.jsx (dentro del return principal)

// Filtro para hamburguesas 100g (solo clásicas)
if (activeCategory === 'hamburguesas_100g') {
  categoryData = {};
  Object.entries(menuWithImages.hamburguesas || {}).forEach(([subCat, products]) => {
    const filtered = products.filter(p => p.subcategory_id === 5);
    if (filtered.length > 0) categoryData[subCat] = filtered;
  });
}

// Filtro para hamburguesas 200g (excluir clásicas)
if (activeCategory === 'hamburguesas') {
  categoryData = {};
  Object.entries(menuWithImages.hamburguesas || {}).forEach(([subCat, products]) => {
    const filtered = products.filter(p => p.subcategory_id !== 5);
    if (filtered.length > 0) categoryData[subCat] = filtered;
  });
}

// Filtro para Papas (Cat 12, Subcat 9 y 57)
if (activeCategory === 'papas') {
  categoryData = { papas: [] };
  Object.values(menuWithImages).forEach(category => {
    if (Array.isArray(category)) {
      categoryData.papas.push(...category.filter(p => p.category_id === 12 && [9, 57].includes(p.subcategory_id)));
    } else {
      Object.values(category).forEach(subcat => {
        if (Array.isArray(subcat)) {
          categoryData.papas.push(...subcat.filter(p => p.category_id === 12 && [9, 57].includes(p.subcategory_id)));
        }
      });
    }
  });
}

// Filtro para Pizzas (Cat 5, Subcat 60)
if (activeCategory === 'pizzas') {
  categoryData = { pizzas: [] };
  Object.values(menuWithImages).forEach(category => {
    if (Array.isArray(category)) {
      categoryData.pizzas.push(...category.filter(p => p.category_id === 5 && p.subcategory_id === 60));
    } else {
      Object.values(category).forEach(subcat => {
        if (Array.isArray(subcat)) {
          categoryData.pizzas.push(...subcat.filter(p => p.category_id === 5 && p.subcategory_id === 60));
        }
      });
    }
  });
}

// Filtro para Bebidas (Cat 5, Subcat 11, 10, 28, 27)
if (activeCategory === 'bebidas') {
  categoryData = {};
  const bebidasSubcats = { 11: 'bebidas', 10: 'jugos', 28: 'té', 27: 'café' };
  Object.values(menuWithImages).forEach(category => {
    if (Array.isArray(category)) {
      category.filter(p => p.category_id === 5 && [11, 10, 28, 27].includes(p.subcategory_id)).forEach(p => {
        const subName = bebidasSubcats[p.subcategory_id];
        if (!categoryData[subName]) categoryData[subName] = [];
        categoryData[subName].push(p);
      });
    } else {
      Object.values(category).forEach(subcat => {
        if (Array.isArray(subcat)) {
          subcat.filter(p => p.category_id === 5 && [11, 10, 28, 27].includes(p.subcategory_id)).forEach(p => {
            const subName = bebidasSubcats[p.subcategory_id];
            if (!categoryData[subName]) categoryData[subName] = [];
            categoryData[subName].push(p);
          });
        }
      });
    }
  });
}
```

---

## 🎭 Comportamiento UX/UI

### 1. Scroll Dinámico
```javascript
// Ocultar al hacer scroll hacia abajo
useEffect(() => {
  const handleScroll = () => {
    const currentScrollY = window.scrollY;
    if (currentScrollY > lastScrollY && currentScrollY > 100) {
      setIsCategoriesVisible(false);
    } else {
      setIsCategoriesVisible(true);
    }
    setLastScrollY(currentScrollY);
  };
  window.addEventListener('scroll', handleScroll, { passive: true });
}, [lastScrollY]);
```

### 2. Detección de Scroll Horizontal
```javascript
onScroll={(e) => {
  const { scrollLeft, scrollWidth, clientWidth } = e.target;
  setIsScrolledToEnd(scrollLeft + clientWidth >= scrollWidth - 5);
}}
```

### 3. Banner "Desliza para ver más"
- **Posición**: `top-[115px]` móvil, `top-[100px]` PC
- **Animación**: Flecha rota 180° al llegar al final
- **Comportamiento**: Se oculta junto con el menú al hacer scroll

```javascript
{isCategoriesVisible && (
  <div className="fixed top-[115px] sm:top-[100px] ...">
    <div className="bg-yellow-300 text-black px-3 py-1.5 rounded-full ...">
      <span>Desliza para ver más</span>
      <svg className={`${isScrolledToEnd ? 'rotate-180' : ''} ...`}>
        <path d="M9 5l7 7-7 7"/>
      </svg>
    </div>
  </div>
)}
```

---

## 📐 Responsive Design

### Móvil (< 640px)
```css
- Padding: px-2 (sin padding en scroll)
- Gap: gap-1
- Font: text-[9px]
- Icon: clamp(19.2px, 4.8vw, 24px)
- Scrollbar: visible con estilo personalizado
```

### PC (≥ 640px)
```css
- Padding: px-4
- Gap: gap-2
- Font: text-xs
- Icon: 24px fijo
- Scrollbar: estándar
```

---

## 🎨 Estilos CSS

### Scrollbar Personalizado
```css
.scrollbar-visible::-webkit-scrollbar { 
  height: 4px; 
}
.scrollbar-visible::-webkit-scrollbar-track { 
  background: #fed7aa; 
  border-radius: 2px; 
}
.scrollbar-visible::-webkit-scrollbar-thumb { 
  background: #f97316; 
  border-radius: 2px; 
}
```

### Animación de Flecha
```css
@keyframes bounce-horizontal {
  0%, 100% { transform: translateX(0); }
  50% { transform: translateX(4px); }
}

.animate-bounce-horizontal { 
  animation: bounce-horizontal 1s ease-in-out infinite; 
}
```

---

## 🔄 Flujo de Interacción

### 1. Click en Categoría
```
Usuario hace click → vibrate(30) → setActiveCategory(cat) → 
Filtrado de productos → Renderizado de productos
```

### 2. Scroll Horizontal
```
Usuario desliza → onScroll detecta posición → 
Actualiza isScrolledToEnd → Rota flecha del banner
```

### 3. Scroll Vertical
```
Usuario scrollea página → handleScroll detecta dirección → 
Oculta/muestra menú y banner → Transición suave 300ms
```

---

## 🎯 Casos de Uso

### Caso 1: Hamburguesas 200g vs 100g
- **200g**: Muestra solo "Especiales" (subcategory_id 6)
- **100g**: Muestra solo "Clásicas" (subcategory_id 5)
- **Razón**: Evitar duplicados y confusión de tamaños

### Caso 2: Bebidas Múltiples
- **Categoría**: Bebidas
- **Incluye**: Bebidas (11), Jugos (10), Té (28), Café (27)
- **Renderizado**: Secciones separadas por subcategoría

### Caso 3: Papas Duplicadas
- **Category ID**: 12
- **Subcategories**: 9 y 57
- **Solución**: Filtro por ambos IDs para mostrar todas

---

## ⚠️ Consideraciones Importantes

### 1. Orden de Categorías
El orden en `mainCategories` define el orden visual. Cambiar el orden requiere actualizar el array.

### 2. Filtros Duales
Algunas categorías usan filtros en DOS lugares:
- `productsToShow` (useMemo)
- Bloque de renderizado principal

Ambos deben mantenerse sincronizados.

### 3. Performance
- Scroll listener usa `requestAnimationFrame` para optimización
- `passive: true` en event listeners para mejor scroll
- `useMemo` para evitar re-cálculos innecesarios

### 4. Accesibilidad
- Botones con `aria-label` implícito por texto visible
- Vibración táctil en cada interacción
- Contraste de colores WCAG AA compliant

---

## 🐛 Debugging

### Verificar Categoría Activa
```javascript
console.log('Categoría activa:', activeCategory);
```

### Verificar Productos Filtrados
```javascript
console.log('Productos a mostrar:', productsToShow);
```

### Verificar Scroll
```javascript
console.log('Scroll position:', {
  scrollLeft: categoriesScrollRef.current?.scrollLeft,
  isAtEnd: isScrolledToEnd
});
```

---

## 📝 Notas de Mantenimiento

### Agregar Nueva Categoría
1. Agregar a `mainCategories`
2. Agregar a `categoryDisplayNames`
3. Agregar icono a `categoryIcons`
4. Agregar color a `categoryColors`
5. Agregar filtro a `categoryFilters` (si aplica)
6. Agregar lógica de filtrado en renderizado (si aplica)

### Modificar Filtros
1. Actualizar `categoryFilters`
2. Actualizar lógica en `productsToShow` (useMemo)
3. Actualizar lógica en bloque de renderizado
4. Verificar que no haya duplicados

---

## 🔗 Referencias

- **Archivo**: `src/components/MenuApp.jsx`
- **Líneas**: ~50-100 (configuración), ~1100-1150 (filtrado), ~1300-1400 (renderizado)
- **Documentación relacionada**: `CATEGORIAS_SUBCATEGORIAS.md`

