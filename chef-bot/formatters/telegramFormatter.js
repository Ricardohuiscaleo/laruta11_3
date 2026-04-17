/**
 * Telegram message formatter and fuzzy name matching for Chef_Bot.
 * Includes unit conversion for accurate cost calculations.
 */

/**
 * Unit conversion factors to normalize to base units.
 * Matches the RecipeService UNIT_CONVERSIONS in Laravel.
 */
const UNIT_CONVERSIONS = {
  kg: { base: 'g', factor: 1000 },
  g:  { base: 'g', factor: 1 },
  L:  { base: 'ml', factor: 1000 },
  ml: { base: 'ml', factor: 1 },
  unidad: { base: 'unidad', factor: 1 },
};

/**
 * Calculate ingredient cost with unit conversion.
 * e.g. Tomate costs $500/kg, recipe needs 150g → 150 * (1/1000) * 500 = $75
 */
function calculateIngredientCost(costPerUnit, ingredientUnit, quantity, recipeUnit) {
  const ingConv = UNIT_CONVERSIONS[ingredientUnit] || { base: ingredientUnit, factor: 1 };
  const recConv = UNIT_CONVERSIONS[recipeUnit] || { base: recipeUnit, factor: 1 };

  if (ingConv.base === recConv.base) {
    const quantityInIngUnit = quantity * (recConv.factor / ingConv.factor);
    return costPerUnit * quantityInIngUnit;
  }
  return costPerUnit * quantity;
}

/**
 * Calculate Levenshtein edit distance between two strings (case-insensitive).
 */
function levenshtein(a, b) {
  a = a.toLowerCase();
  b = b.toLowerCase();
  const m = a.length;
  const n = b.length;
  const prev = new Array(n + 1);
  for (let j = 0; j <= n; j++) prev[j] = j;
  for (let i = 1; i <= m; i++) {
    let corner = prev[0];
    prev[0] = i;
    for (let j = 1; j <= n; j++) {
      const temp = prev[j];
      prev[j] = a[i - 1] === b[j - 1] ? corner : 1 + Math.min(corner, prev[j], prev[j - 1]);
      corner = temp;
    }
  }
  return prev[n];
}

/**
 * Return top 3 fuzzy suggestions sorted by edit distance.
 */
function fuzzyMatch(input, candidates, maxResults = 3) {
  if (!input || !candidates || candidates.length === 0) return [];
  const scored = candidates.map((name) => ({ name, distance: levenshtein(input, name) }));
  scored.sort((a, b) => a.distance - b.distance);
  return scored.slice(0, maxResults);
}

/**
 * Format a CLP monetary value for display. e.g. "$1.234"
 */
function formatCLP(value) {
  if (value == null || isNaN(value)) return '$0';
  return `$${Math.round(Number(value)).toLocaleString('es-CL')}`;
}

/**
 * Format recipe query results for Telegram with unit conversion.
 * Expects rows with: producto, ingrediente, quantity, unit (recipe unit),
 * cost_per_unit, ing_unit (ingredient's native unit).
 */
function formatRecipeResults(rows) {
  if (!rows || rows.length === 0) return '🔍 No se encontraron resultados.';

  const products = new Map();
  for (const row of rows) {
    const productName = row.producto || row.product_name || row.name || 'Sin nombre';
    if (!products.has(productName)) products.set(productName, []);
    products.get(productName).push(row);
  }

  const parts = [];
  for (const [productName, ingredients] of products) {
    let totalCost = 0;
    const lines = [`*🍽 ${productName}*\n`];

    for (const ing of ingredients) {
      const ingName = ing.ingrediente || ing.ingredient_name || '';
      const qty = Number(ing.quantity) || 0;
      const recipeUnit = ing.unit || ing.recipe_unit || '';
      const costPerUnit = Number(ing.cost_per_unit) || 0;
      const ingredientUnit = ing.ing_unit || ing.ingredient_unit || recipeUnit;

      const ingCost = calculateIngredientCost(costPerUnit, ingredientUnit, qty, recipeUnit);
      totalCost += ingCost;

      const qtyDisplay = qty % 1 === 0 ? qty.toString() : qty.toFixed(1);
      lines.push(`  • ${ingName}: \`${qtyDisplay} ${recipeUnit}\` — ${formatCLP(ingCost)}`);
    }

    lines.push(`\n*Costo total:* ${formatCLP(totalCost)}`);
    parts.push(lines.join('\n'));
  }
  return parts.join('\n\n');
}

/**
 * Format stock query results for Telegram.
 */
function formatStockResults(rows) {
  if (!rows || rows.length === 0) return '🔍 No se encontraron resultados.';

  const lines = ['*📦 Estado de Stock*\n'];
  for (const row of rows) {
    const name = row.name || row.nombre || 'Sin nombre';
    const stock = row.current_stock ?? 0;
    const unit = row.unit || '';
    const cost = Number(row.cost_per_unit) || 0;
    const minLevel = Number(row.min_stock_level) || 0;

    let status;
    if (Number(stock) < minLevel) status = '🔴 Crítico';
    else if (Number(stock) < minLevel * 1.5) status = '🟡 Bajo';
    else status = '🟢 OK';

    lines.push(
      `*${name}*` +
      `\n  Stock: \`${stock} ${unit}\`` +
      `\n  Costo: ${formatCLP(cost)}/${unit}` +
      `\n  Estado: ${status}`
    );
  }
  return lines.join('\n');
}

/**
 * Detect result type and format accordingly.
 */
function formatQueryResults(rows) {
  if (!rows || rows.length === 0) return '🔍 No se encontraron resultados.';

  const sample = rows[0];
  if ('ingrediente' in sample || 'ingredient_name' in sample) return formatRecipeResults(rows);
  if ('current_stock' in sample || 'min_stock_level' in sample) return formatStockResults(rows);
  return formatGenericResults(rows);
}

/**
 * Generic fallback formatter for arbitrary query results.
 */
function formatGenericResults(rows) {
  if (!rows || rows.length === 0) return '🔍 No se encontraron resultados.';

  const lines = [];
  for (const row of rows) {
    const entries = Object.entries(row)
      .map(([k, v]) => `  ${k}: \`${v}\``)
      .join('\n');
    lines.push(entries);
  }
  return lines.join('\n---\n');
}

module.exports = {
  levenshtein,
  fuzzyMatch,
  formatCLP,
  calculateIngredientCost,
  formatRecipeResults,
  formatStockResults,
  formatQueryResults,
  formatGenericResults,
  UNIT_CONVERSIONS,
};
