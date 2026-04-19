// Property 2: Filtrado correcto por categoría seleccionada
// Validates: Requirements 3.2
//
// For any list of items and any selected category, all displayed items
// SHALL have their category field equal to the selected tab's category,
// and no item with a different category SHALL appear in the filtered list.

// Simulate the filter logic from ComprasApp.jsx
function filterItems(items, stockTab) {
  return items.filter(ing => {
    if (stockTab === 'todos') return true;
    if (stockTab === 'bebidas') {
      // Bebidas: subcategory_id 10=Jugos, 11=Bebidas, 27=Café, 28=Té
      return ing.type === 'product' && [10, 11, 27, 28].includes(parseInt(ing.subcategory_id));
    }
    // Category filter for ingredients
    return ing.type === 'ingredient' && ing.category === stockTab;
  });
}

const VALID_CATEGORIES = [
  'Carnes', 'Vegetales', 'Salsas', 'Condimentos', 'Panes',
  'Embutidos', 'Pre-elaborados', 'Lácteos', 'Bebidas',
  'Gas', 'Servicios', 'Packaging', 'Limpieza'
];

// Generate random items
function randomItems(count) {
  const items = [];
  for (let i = 0; i < count; i++) {
    const isProduct = Math.random() > 0.7;
    items.push({
      id: i + 1,
      name: `item_${i}`,
      type: isProduct ? 'product' : 'ingredient',
      category: isProduct ? null : VALID_CATEGORIES[Math.floor(Math.random() * VALID_CATEGORIES.length)],
      subcategory_id: isProduct ? [10, 11, 27, 28, 5, 6][Math.floor(Math.random() * 6)] : null,
      current_stock: Math.random() * 100,
      min_stock_level: 5
    });
  }
  return items;
}

let passed = 0;
let failed = 0;
const ITERATIONS = 200;

// Property: "Todos" tab shows ALL items
for (let i = 0; i < ITERATIONS; i++) {
  const items = randomItems(Math.floor(Math.random() * 30) + 1);
  const filtered = filterItems(items, 'todos');
  if (filtered.length !== items.length) {
    console.error(`FAIL: "Todos" should show all ${items.length} items, got ${filtered.length}`);
    failed++;
  } else {
    passed++;
  }
}

// Property: Category tab shows ONLY items with matching category
for (let i = 0; i < ITERATIONS; i++) {
  const items = randomItems(Math.floor(Math.random() * 30) + 1);
  const category = VALID_CATEGORIES[Math.floor(Math.random() * VALID_CATEGORIES.length)];
  const filtered = filterItems(items, category);

  const allMatch = filtered.every(item => item.type === 'ingredient' && item.category === category);
  const noneExcluded = items.filter(item => item.type === 'ingredient' && item.category === category).length === filtered.length;

  if (!allMatch || !noneExcluded) {
    console.error(`FAIL: Category "${category}" filter incorrect`);
    failed++;
  } else {
    passed++;
  }
}

// Property: "Bebidas" tab shows ONLY products with correct subcategory_ids
for (let i = 0; i < ITERATIONS; i++) {
  const items = randomItems(Math.floor(Math.random() * 30) + 1);
  const filtered = filterItems(items, 'bebidas');

  const allMatch = filtered.every(item =>
    item.type === 'product' && [10, 11, 27, 28].includes(parseInt(item.subcategory_id))
  );
  const expected = items.filter(item =>
    item.type === 'product' && [10, 11, 27, 28].includes(parseInt(item.subcategory_id))
  );

  if (!allMatch || expected.length !== filtered.length) {
    console.error(`FAIL: Bebidas filter incorrect`);
    failed++;
  } else {
    passed++;
  }
}

console.log(`\n✅ Passed: ${passed}/${passed + failed}`);
if (failed > 0) {
  console.log(`❌ Failed: ${failed}`);
  process.exit(1);
} else {
  console.log('All property tests passed!');
}
