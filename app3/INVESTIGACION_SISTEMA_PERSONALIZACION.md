# INVESTIGACIÓN: SISTEMA DE PERSONALIZACIÓN "COMBINA TU PEDIDO"

**Fecha:** Diciembre 2024  
**Sistema:** La Ruta 11 - Personalización de Productos  
**Estado:** ✅ Implementado y Funcional

---

## 1. RESUMEN EJECUTIVO

El sistema "Combina tu Pedido" permite a los usuarios personalizar productos agregando extras como:
- Cebolla extra ($300)
- Churrasco Extra ($3,000)
- Hamburguesa Ruta 11 Extra ($2,290)
- Lomo Cerdo Extra ($1,500)
- Lomo Vetado Extra ($4,890)
- Merkén Ahumado Extra ($200)
- Palta Extra ($1,290)
- Papas fritas extra ($590)
- Pollo a la Plancha Extra ($1,700)
- Queso Cheddar Extra ($300)
- Queso Gouda Extra ($500)
- Salchicha Extra ($890)
- Sweet Relish ($890)
- Tocino Extra ($1,490)

---

## 2. ARQUITECTURA DEL SISTEMA

### 2.1 Flujo de Datos

```
┌─────────────────────────────────────────────────────────────┐
│                    FLUJO COMPLETO                           │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  1. API: get_menu_products.php                              │
│     └─> SELECT * FROM products WHERE category_id = 6       │
│         (Categoría "Personalizar")                          │
│                                                             │
│  2. Frontend: MenuApp.jsx                                   │
│     ├─> Recibe menuData.personalizar.personalizar[]        │
│     └─> Construye comboItems.personalizar                  │
│                                                             │
│  3. Modal: ProductDetailModal.jsx                           │
│     ├─> Muestra sección "Combina tu Pedido"                │
│     ├─> Acordeón "Personaliza [Producto]" (amarillo)       │
│     ├─> Usuario agrega extras con botones +/-              │
│     └─> Calcula subtotal de personalizaciones              │
│                                                             │
│  4. Carrito: MenuApp.jsx                                    │
│     ├─> Producto principal + customizations[]              │
│     ├─> Cada customization tiene: id, name, price, qty     │
│     └─> Total = precio_base + sum(customizations)          │
│                                                             │
│  5. Checkout: CheckoutApp.jsx                               │
│     └─> Muestra desglose de personalizaciones              │
│                                                             │
│  6. Backend: create_payment_direct.php                      │
│     └─> Guarda en tuu_order_items con item_type            │
│         'personalizar'                                      │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## 3. BASE DE DATOS

### 3.1 Tabla: products

Los extras de personalización están en la tabla `products`:

```sql
SELECT id, name, price, category_id, subcategory_id, description
FROM products
WHERE category_id = 6  -- Categoría "Personalizar"
AND is_active = 1;
```

**Estructura:**
- `category_id = 6` → Categoría "Personalizar"
- `subcategory_id` → Puede variar (no crítico)
- `price` → Precio del extra
- `name` → Nombre del extra (ej: "Cebolla extra")
- `description` → Descripción del extra

### 3.2 Tabla: tuu_order_items

Cuando se guarda un pedido, los extras se almacenan con:

```sql
INSERT INTO tuu_order_items (
    order_id,
    order_reference,
    product_id,
    product_name,
    product_price,
    quantity,
    subtotal,
    item_type  -- ← 'personalizar'
) VALUES (?, ?, ?, ?, ?, ?, ?, 'personalizar');
```

**Valores de item_type:**
- `'product'` → Producto principal
- `'personalizar'` → Extras de personalización
- `'extras'` → Extras de delivery (escándalo, abrazo, etc.)
- `'acompañamiento'` → Bebidas/papas de combos

---

## 4. COMPONENTES FRONTEND

### 4.1 MenuApp.jsx

**Ubicación:** `src/components/MenuApp.jsx`

**Construcción de comboItems:**
```javascript
const comboItems = useMemo(() => ({
    papas_y_snacks: menuWithImages.papas?.papas || [],
    jugos: menuWithImages.papas_y_snacks?.jugos || [],
    bebidas: menuWithImages.papas_y_snacks?.bebidas || [],
    empanadas: menuWithImages.papas_y_snacks?.empanadas || [],
    cafe: menuWithImages.papas_y_snacks?.café || [],
    te: menuWithImages.papas_y_snacks?.té || [],
    salsas: menuWithImages.papas_y_snacks?.salsas || [],
    personalizar: menuWithImages.personalizar?.personalizar || [],  // ← AQUÍ
    extras: (menuWithImages.extras?.extras || [])
        .filter(item => !(item.category_id === 7 && item.subcategory_id === 30)),
    deliveryExtras: (menuWithImages.extras?.extras || [])
        .filter(item => item.category_id === 7 && item.subcategory_id === 30)
}), [menuWithImages]);
```

**Carga de datos:**
```javascript
useEffect(() => {
    const loadMenuData = async () => {
        const response = await fetch('/api/get_menu_products.php');
        const menuData = await response.json();
        
        if (menuData.success && menuData.menuData) {
            setMenuWithImages(menuData.menuData);
            // menuData.menuData.personalizar.personalizar[] contiene los extras
        }
    };
    
    loadMenuData();
}, []);
```

### 4.2 ProductDetailModal.jsx

**Ubicación:** `src/components/modals/ProductDetailModal.jsx`

**Sección de Personalización:**
```javascript
<div className="border-t pt-4">
    <h3 className="text-lg font-bold text-gray-800 mb-4">
        Combina tu Pedido
    </h3>
    
    <ComboSection 
        title={`Personaliza "${product.name}"`} 
        items={comboItems.personalizar}  // ← Extras de personalización
        isExtra={true} 
        titleColor="text-black text-left"
        bgColor="bg-yellow-400"  // ← Fondo amarillo distintivo
        sectionKey="personalizar"
        expandedComboSections={expandedComboSections}
        setExpandedComboSections={setExpandedComboSections}
        onAddToCart={handleTempAdd}
        onRemoveFromCart={handleTempRemove}
        getProductQuantity={getProductQuantity}
        preventClose={preventClose}
        useTempCart={true}
        getTempQuantity={getTempQuantity}
    />
    
    {/* Otras secciones: Jugos, Bebidas, Café, Té, Salsas, Extras */}
</div>
```

**Estado Temporal de Personalizaciones:**
```javascript
const [tempCustomizations, setTempCustomizations] = useState(() => {
    if (isEditing && product.customizations) {
        const initial = {};
        product.customizations.forEach(c => {
            initial[c.id] = c.quantity;
        });
        return initial;
    }
    return {};
});

const handleTempAdd = (item) => {
    setTempCustomizations(prev => ({
        ...prev,
        [item.id]: (prev[item.id] || 0) + 1
    }));
};

const handleTempRemove = (itemId) => {
    setTempCustomizations(prev => {
        const newQty = (prev[itemId] || 0) - 1;
        if (newQty <= 0) {
            const { [itemId]: _, ...rest } = prev;
            return rest;
        }
        return { ...prev, [itemId]: newQty };
    });
};

const getTempQuantity = (itemId) => tempCustomizations[itemId] || 0;
```

**Cálculo de Subtotal:**
```javascript
const comboSubtotal = useMemo(() => {
    const allComboItems = [
        ...comboItems.papas_y_snacks,
        ...comboItems.jugos,
        ...comboItems.bebidas,
        ...comboItems.salsas,
        ...comboItems.personalizar,  // ← Incluye personalizaciones
        ...comboItems.extras,
        ...comboItems.empanadas,
        ...comboItems.cafe,
        ...comboItems.te
    ];
    
    return Object.entries(tempCustomizations).reduce((total, [itemId, qty]) => {
        const item = allComboItems.find(i => i.id === parseInt(itemId));
        if (!item) return total;
        
        let itemPrice = item.price;
        if (item.extraPrice && qty > 1) {
            itemPrice = item.price + (qty - 1) * item.extraPrice;
        } else {
            itemPrice = item.price * qty;
        }
        return total + itemPrice;
    }, 0);
}, [tempCustomizations, comboItems]);

const displayTotal = useMemo(() => 
    product.price + comboSubtotal, 
    [product.price, comboSubtotal]
);
```

**Agregar al Carrito:**
```javascript
<button 
    onClick={() => {
        const allComboItems = [
            ...comboItems.papas_y_snacks,
            ...comboItems.jugos,
            ...comboItems.bebidas,
            ...comboItems.salsas,
            ...comboItems.personalizar,
            ...comboItems.extras,
            ...comboItems.empanadas,
            ...comboItems.cafe,
            ...comboItems.te
        ];
        
        const customizationsArray = Object.entries(tempCustomizations)
            .map(([itemId, qty]) => {
                const item = allComboItems.find(i => i.id === parseInt(itemId));
                if (!item) return null;
                return { ...item, quantity: qty };
            })
            .filter(Boolean);
        
        if (isEditing && onUpdateCartItem) {
            onUpdateCartItem(cartIndex, product, customizationsArray);
        } else {
            const productWithCustomizations = {
                ...product,
                customizations: customizationsArray.length > 0 ? customizationsArray : null,
                quantity: 1,
                cartItemId: Date.now()
            };
            onAddToCart(productWithCustomizations);
        }
        onClose();
    }} 
    className="w-full bg-orange-500 hover:bg-orange-600 text-white py-4 rounded-full"
>
    {isEditing ? 'Personalizar este Producto' : 'Agregar al Carro'}
</button>
```

### 4.3 ComboSection Component

**Acordeón Colapsable:**
```javascript
const ComboSection = ({ 
    title, 
    items, 
    isExtra = false, 
    titleColor = 'text-gray-700', 
    bgColor = 'bg-gray-50',
    sectionKey, 
    expandedComboSections, 
    setExpandedComboSections,
    onAddToCart, 
    onRemoveFromCart, 
    getProductQuantity,
    useTempCart, 
    getTempQuantity 
}) => {
    const isExpanded = expandedComboSections.has(sectionKey);
    
    const toggleSection = () => {
        setExpandedComboSections(prev => {
            const newSet = new Set(prev);
            if (newSet.has(sectionKey)) {
                newSet.delete(sectionKey);
            } else {
                newSet.add(sectionKey);
            }
            return newSet;
        });
    };
    
    return (
        <div className="mb-4 border border-gray-200 rounded-lg overflow-hidden">
            <button
                onClick={toggleSection}
                className={`w-full px-4 py-3 flex items-center justify-between ${bgColor} hover:bg-gray-100 transition-colors`}
            >
                <div className="flex items-center gap-2 text-left">
                    <h4 className={`font-bold ${titleColor}`}>{title}</h4>
                    {items.length > 0 && (
                        <span className="bg-orange-500 text-white text-xs px-2 py-1 rounded-full">
                            {items.length}
                        </span>
                    )}
                </div>
                {isExpanded ? <ChevronUp /> : <ChevronDown />}
            </button>
            
            <div className={`transition-all duration-300 ${
                isExpanded ? 'max-h-80 opacity-100' : 'max-h-0 opacity-0'
            }`}>
                <div className="p-4 space-y-2 max-h-80 overflow-y-auto">
                    {items.map(item => (
                        <ComboItem 
                            key={item.id} 
                            item={item} 
                            isExtra={isExtra}
                            onAddToCart={onAddToCart}
                            onRemoveFromCart={onRemoveFromCart}
                            getProductQuantity={getProductQuantity}
                            useTempCart={useTempCart}
                            getTempQuantity={getTempQuantity}
                        />
                    ))}
                </div>
            </div>
        </div>
    );
};
```

### 4.4 ComboItem Component

**Card de Extra Individual:**
```javascript
const ComboItem = ({ 
    item, 
    isExtra = false, 
    onAddToCart, 
    onRemoveFromCart, 
    getProductQuantity,
    useTempCart, 
    getTempQuantity 
}) => {
    const quantity = useTempCart ? getTempQuantity(item.id) : getProductQuantity(item.id);
    const maxReached = isExtra && item.maxQuantity && quantity >= item.maxQuantity;
    
    const priceText = item.price === 0 ? 'Gratis' : `$${item.price.toLocaleString('es-CL')}`;
    
    return (
        <div className="flex justify-between items-center bg-gray-50 p-2 rounded-lg">
            <div className="flex items-center gap-3">
                {item.image ? (
                    <img 
                        src={item.image} 
                        alt={item.name} 
                        className="w-12 h-12 object-cover rounded-md"
                    />
                ) : (
                    <div className="w-12 h-12 bg-gray-200 rounded-md animate-pulse"></div>
                )}
                <div>
                    <p className="font-semibold text-sm text-gray-800">{item.name}</p>
                    <p className="text-xs font-medium text-orange-500">
                        {priceText}
                        {item.extraPrice && ` / +$${item.extraPrice.toLocaleString('es-CL')}`}
                        {isExtra && item.maxQuantity && ` (máx. ${item.maxQuantity})`}
                    </p>
                    {item.description && (
                        <p className="text-xs text-gray-500 italic">{item.description}</p>
                    )}
                </div>
            </div>
            <div className="flex items-center gap-2">
                {quantity > 0 && (
                    <button 
                        onClick={(e) => { 
                            e.stopPropagation(); 
                            onRemoveFromCart(item.id); 
                        }} 
                        className="text-red-500 hover:text-red-700"
                    >
                        <MinusCircle size={22} />
                    </button>
                )}
                {quantity > 0 && (
                    <span className="font-bold w-5 text-center">{quantity}</span>
                )}
                <button 
                    onClick={(e) => { 
                        e.stopPropagation(); 
                        onAddToCart(item); 
                    }} 
                    disabled={maxReached}
                    className={`${maxReached ? 'text-gray-400 cursor-not-allowed' : 'text-green-500 hover:text-green-700'}`}
                >
                    <PlusCircle size={22} />
                </button>
            </div>
        </div>
    );
};
```

---

## 5. API BACKEND

### 5.1 get_menu_products.php

**Ubicación:** `api/get_menu_products.php`

**Query SQL:**
```php
$stmt = $pdo->query("
    SELECT 
        p.id,
        p.name,
        p.description,
        p.price,
        p.cost_price,
        p.category_id,
        p.subcategory_id,
        p.sku,
        p.image_url,
        p.stock_quantity,
        p.min_stock_level,
        p.preparation_time,
        p.grams,
        p.calories,
        p.allergens,
        p.views,
        p.likes,
        p.is_active,
        p.created_at,
        s.name as subcategory_name,
        s.slug as subcategory_slug,
        COALESCE(AVG(r.rating), 0) as avg_rating,
        COUNT(r.id) as review_count
    FROM products p
    LEFT JOIN subcategories s ON p.subcategory_id = s.id
    LEFT JOIN reviews r ON p.id = r.product_id AND r.is_approved = 1
    WHERE p.is_active = 1 
    GROUP BY p.id
    ORDER BY p.category_id, p.subcategory_id, p.name
");
```

**Mapeo de Categorías:**
```php
$categoryMap = [
    1 => 'la_ruta_11',
    2 => 'churrascos', 
    3 => 'hamburguesas',
    4 => 'completos',
    5 => 'papas_y_snacks',
    6 => 'personalizar',  // ← Categoría de extras
    7 => 'extras',
    8 => 'Combos',
    12 => 'papas'
];
```

**Estructura de Respuesta:**
```json
{
    "success": true,
    "menuData": {
        "personalizar": {
            "personalizar": [
                {
                    "id": 14,
                    "name": "Cebolla extra",
                    "price": 300,
                    "image": "https://...",
                    "description": "Porción extra de cebolla caramelizada",
                    "category_id": 6,
                    "subcategory_id": null,
                    "reviews": {
                        "count": 0,
                        "average": 0
                    }
                },
                {
                    "id": 15,
                    "name": "Churrasco Extra",
                    "price": 3000,
                    "description": "Extra de Churrasco",
                    "category_id": 6
                }
                // ... más extras
            ]
        }
    }
}
```

---

## 6. ESTRUCTURA DEL CARRITO

### 6.1 Producto con Personalizaciones

```javascript
{
    id: 123,
    name: "Cheeseburger (200g)",
    price: 7280,
    image: "https://...",
    quantity: 1,
    cartItemId: 1701234567890,
    customizations: [  // ← Array de personalizaciones
        {
            id: 14,
            name: "Cebolla extra",
            price: 300,
            quantity: 1,
            image: "https://..."
        },
        {
            id: 16,
            name: "Palta Extra",
            price: 1290,
            quantity: 2,  // ← Puede ser múltiple
            image: "https://..."
        }
    ]
}
```

### 6.2 Cálculo de Total

```javascript
// Total del item en carrito
const itemTotal = product.price + 
    (product.customizations?.reduce((sum, c) => 
        sum + (c.price * c.quantity), 0
    ) || 0);

// Ejemplo:
// Cheeseburger: $7,280
// + Cebolla extra (1x): $300
// + Palta Extra (2x): $2,580
// = Total: $10,160
```

---

## 7. VISUALIZACIÓN EN CHECKOUT

### 7.1 CheckoutApp.jsx

**Desglose de Personalizaciones:**
```javascript
{cart.map((item, index) => (
    <div key={index} className="border-b pb-3">
        <div className="flex justify-between">
            <span className="font-semibold">{item.name}</span>
            <span>${item.price.toLocaleString('es-CL')}</span>
        </div>
        
        {item.customizations && item.customizations.length > 0 && (
            <div className="ml-4 mt-2 text-sm text-gray-600">
                {item.customizations.map((custom, idx) => (
                    <div key={idx} className="flex justify-between">
                        <span>+ {custom.quantity}x {custom.name}</span>
                        <span>${(custom.price * custom.quantity).toLocaleString('es-CL')}</span>
                    </div>
                ))}
            </div>
        )}
    </div>
))}
```

**Ejemplo Visual:**
```
Cheeseburger (200g)                    $7,280
  + 1x Cebolla extra                     $300
  + 2x Palta Extra                     $2,580
─────────────────────────────────────────────
Subtotal item:                        $10,160
```

---

## 8. CARACTERÍSTICAS CLAVE

### 8.1 UX/UI

✅ **Acordeón Amarillo Distintivo**
- Sección "Personaliza [Producto]" con fondo amarillo
- Se destaca visualmente del resto de opciones

✅ **Botones +/- Intuitivos**
- Agregar/quitar extras fácilmente
- Contador visible de cantidad

✅ **Scroll Vertical**
- Lista de extras con scroll si son muchos
- Máximo 320px de altura

✅ **Imágenes de Extras**
- Cada extra muestra su imagen
- Placeholder animado si no hay imagen

✅ **Precio Visible**
- Precio en naranja destacado
- Formato chileno con separador de miles

✅ **Descripción Opcional**
- Texto descriptivo en gris claro
- Fuente italic para diferenciación

### 8.2 Funcionalidades

✅ **Cantidad Ilimitada**
- Usuario puede agregar múltiples unidades del mismo extra
- No hay límite por defecto (salvo que se configure maxQuantity)

✅ **Cálculo en Tiempo Real**
- Subtotal se actualiza instantáneamente
- Total del producto incluye personalizaciones

✅ **Edición de Carrito**
- Usuario puede editar personalizaciones después de agregar
- Se mantiene el estado temporal durante la edición

✅ **Persistencia**
- Personalizaciones se guardan con el producto en carrito
- Se envían al backend al procesar el pago

✅ **Múltiples Secciones**
- Personalizar (amarillo)
- Jugos
- Bebidas
- Café
- Té
- Salsas
- Extras (delivery)

---

## 9. DIFERENCIAS CON EXTRAS DE DELIVERY

| Característica | Personalizar | Extras Delivery |
|----------------|--------------|-----------------|
| **Categoría BD** | category_id = 6 | category_id = 7, subcategory_id = 30 |
| **Ubicación** | Modal de producto | Checkout (cinta horizontal) |
| **Visibilidad** | Siempre visible | Solo si deliveryType = 'delivery' |
| **Color UI** | Amarillo | Naranja |
| **Asociación** | Por producto | Por pedido completo |
| **Descuentos** | Aplican descuentos | NO aplican descuentos |
| **Cashback** | Aplica cashback | NO aplica cashback |
| **item_type** | 'personalizar' | 'extras' |

---

## 10. EJEMPLOS DE USO

### 10.1 Caso 1: Hamburguesa con Extras

**Usuario selecciona:**
- Cheeseburger (200g): $7,280
- + Cebolla extra (1x): $300
- + Queso Cheddar Extra (2x): $600
- + Tocino Extra (1x): $1,490

**Total:** $9,670

**En BD (tuu_order_items):**
```
order_id | product_name              | price  | qty | item_type
---------|---------------------------|--------|-----|-------------
1234     | Cheeseburger (200g)       | 7280   | 1   | product
1234     | Cebolla extra             | 300    | 1   | personalizar
1234     | Queso Cheddar Extra       | 300    | 2   | personalizar
1234     | Tocino Extra              | 1490   | 1   | personalizar
```

### 10.2 Caso 2: Completo Personalizado

**Usuario selecciona:**
- Completo Tradicional: $4,500
- + Palta Extra (1x): $1,290
- + Merkén Ahumado Extra (1x): $200

**Total:** $5,990

---

## 11. REGLAS DE NEGOCIO

### 11.1 Precios

✅ Cada extra tiene su precio individual
✅ Precio se multiplica por cantidad
✅ No hay descuentos especiales por cantidad
✅ Precio puede ser $0 (gratis)

### 11.2 Disponibilidad

✅ Solo productos con `is_active = 1`
✅ Solo categoría `category_id = 6`
✅ Disponible para todos los productos (no hay restricciones)

### 11.3 Límites

✅ Sin límite de cantidad por defecto
✅ Puede configurarse `maxQuantity` por extra
✅ Sin límite de extras diferentes por producto

---

## 12. TESTING

### 12.1 Casos de Prueba

**Test 1: Agregar Extra Simple**
```
DADO un producto en modal
CUANDO usuario hace click en "Personaliza [Producto]"
Y hace click en + de "Cebolla extra"
ENTONCES debe ver cantidad 1
Y subtotal debe aumentar en $300
```

**Test 2: Múltiples Cantidades**
```
DADO un extra con cantidad 0
CUANDO usuario hace click 3 veces en +
ENTONCES debe ver cantidad 3
Y subtotal debe aumentar en $900 (3 x $300)
```

**Test 3: Remover Extra**
```
DADO un extra con cantidad 2
CUANDO usuario hace click en -
ENTONCES debe ver cantidad 1
Y subtotal debe disminuir en $300
```

**Test 4: Agregar al Carrito**
```
DADO un producto con 2 extras
CUANDO usuario hace click en "Agregar al Carro"
ENTONCES producto debe tener customizations[]
Y customizations debe tener 2 items
Y cada item debe tener id, name, price, quantity
```

**Test 5: Editar desde Carrito**
```
DADO un producto en carrito con extras
CUANDO usuario hace click en editar
ENTONCES modal debe mostrar extras actuales
Y cantidades deben coincidir
```

---

## 13. MEJORAS FUTURAS

### 13.1 Sugerencias

🔮 **Extras Recomendados**
- Mostrar extras más populares primero
- "Los clientes también agregaron..."

🔮 **Combos de Extras**
- "Agrega palta + tocino por $2,500 (ahorra $280)"
- Descuentos por combinar extras

🔮 **Límites por Producto**
- Configurar qué extras aplican a qué productos
- Ej: "Merkén" solo para completos

🔮 **Imágenes Mejoradas**
- Fotos reales de cada extra
- Vista previa del producto con extras

🔮 **Búsqueda de Extras**
- Buscador dentro del modal
- Filtros por tipo (proteínas, vegetales, salsas)

🔮 **Extras Gratuitos**
- "Agrega cebolla gratis"
- Promociones especiales

---

## 14. CONCLUSIONES

### 14.1 Fortalezas

✅ **Sistema Flexible:** Fácil agregar nuevos extras desde BD
✅ **UX Intuitiva:** Acordeones y botones +/- claros
✅ **Cálculo Preciso:** Totales en tiempo real
✅ **Escalable:** Soporta múltiples categorías de extras
✅ **Persistente:** Datos se guardan correctamente en BD

### 14.2 Arquitectura Sólida

✅ **Separación de Responsabilidades:**
- API maneja datos
- Modal maneja UI
- Carrito maneja estado

✅ **Estado Temporal:**
- No afecta carrito hasta confirmar
- Permite cancelar sin cambios

✅ **Reutilizable:**
- ComboSection y ComboItem son componentes genéricos
- Se usan para personalizar, bebidas, salsas, etc.

---

**Documento generado:** enero 2026 
**Sistema:** La Ruta 11 - Personalización de Productos  
**Estado:** ✅ Completamente Funcional
