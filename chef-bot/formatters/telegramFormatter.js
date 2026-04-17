/**
 * Telegram message formatter and fuzzy name matching for Chef_Bot.
 *
 * Formats recipe query results and stock query results for Telegram,
 * and provides fuzzy name matching via Levenshtein edit distance.
 */

/**
 * Calculate Levenshtein edit distance between two strings (case-insensitive).
 *
 * @param {string} a
 * @param {string} b
 * @returns {number}
 */
function levenshtein(a, b) {
  a = a.toLowerCase();
  b = b.toLowerCase();

  const m = a.length;
  const n = b.length;

  // Use single-row DP for space efficiency
  const prev = new Array(n + 1);
  for (let j = 0; j <= n; j++) prev[j] = j;

  for (let i = 1; i <= m; i++) {
    let corner = prev[0];
    prev[0] = i;
    for (let j = 1; j <= n; j++) {
      const temp = prev[j];
      if (a[i - 1] === b[j - 1]) {
        prev[j] = corner;
      } else {
        prev[j] = 1 + Math.min(corner, prev[j], prev[j - 1]);
      }
      corner = temp;
    }
  }

  return prev[n];
}

/**
 * Return top 3 fuzzy suggestions from candidates, sorted by edit distance (closest first).
 * Case-insensitive comparison.
 *
 * @param {string} input - The user's input string.
 * @param {string[]} candidates - Array of known names to match against.
 * @param {number} [maxResults=3] - Maximum number of suggestions to return.
 * @returns {{ name: string, distance: number }[]}
 */
function fuzzyMatch(input, candidates, maxResults = 3) {
  if (!input || !candidates || candidates.length === 0) return [];

  const scored = candidates.map((name) => ({
    name,
    distance: levenshtein(input, name),
  }));

  scored.sort((a, b) => a.distance - b.distance);

  return scored.slice(0, maxResults);
}


/**
 * Format a CLP monetary value for display.
 *
 * @param {number} value
 * @returns {string} e.g. "$1.234"
 */
function formatCLP(value) {
  if (value == null || isNaN(value)) return '$0';
  return `$${Number(value).toLocaleString('es-CL')}`;
}

/**
 * Format recipe query results for Telegram.
 * Expects rows with columns like: producto, ingrediente, quantity, unit, cost_per_unit
 *
 * Groups ingredients by product and shows total cost per product.
 *
 * @param {object[]} rows - MySQL result rows.
 * @returns {string} Telegram Markdown formatted message.
 */
function formatRecipeResults(rows) {
  if (!rows || rows.length === 0) return '🔍 No se encontraron resultados.';

  // Group by product name
  const products = new Map();

  for (const row of rows) {
    const productName = row.producto || row.product_name || row.name || 'Sin nombre';
    if (!products.has(productName)) {
      products.set(productName, []);
    }
    products.get(productName).push(row);
  }

  const parts = [];

  for (const [productName, ingredients] of products) {
    let totalCost = 0;
    const lines = [`*🍽 ${productName}*\n`];

    for (const ing of ingredients) {
      const ingName = ing.ingrediente || ing.ingredient_name || '';
      const qty = ing.quantity ?? '';
      const unit = ing.unit || '';
      const costPerUnit = Number(ing.cost_per_unit) || 0;
      const ingCost = costPerUnit * (Number(qty) || 0);
      totalCost += ingCost;

      lines.push(`  • ${ingName}: \`${qty} ${unit}\` — ${formatCLP(ingCost)}`);
    }

    lines.push(`\n*Costo total:* ${formatCLP(totalCost)}`);
    parts.push(lines.join('\n'));
  }

  return parts.join('\n\n');
}

/**
 * Format stock query results for Telegram.
 * Expects rows with columns: name, current_stock, unit, cost_per_unit, min_stock_level
 *
 * @param {object[]} rows - MySQL result rows.
 * @returns {string} Telegram Markdown formatted message.
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
    if (Number(stock) < minLevel) {
      status = '🔴 Crítico';
    } else if (Number(stock) < minLevel * 1.5) {
      status = '🟡 Bajo';
    } else {
      status = '🟢 OK';
    }

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
 * Heuristic: if rows have 'ingrediente' or 'ingredient_name' columns → recipe result.
 * If rows have 'current_stock' or 'min_stock_level' → stock result.
 * Otherwise, fall back to a generic table format.
 *
 * @param {object[]} rows - MySQL result rows.
 * @returns {string} Formatted Telegram message.
 */
function formatQueryResults(rows) {
  if (!rows || rows.length === 0) return '🔍 No se encontraron resultados.';

  const sample = rows[0];

  if ('ingrediente' in sample || 'ingredient_name' in sample) {
    return formatRecipeResults(rows);
  }

  if ('current_stock' in sample || 'min_stock_level' in sample) {
    return formatStockResults(rows);
  }

  // Generic fallback — simple key/value listing
  return formatGenericResults(rows);
}

/**
 * Generic fallback formatter for arbitrary query results.
 *
 * @param {object[]} rows
 * @returns {string}
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
  formatRecipeResults,
  formatStockResults,
  formatQueryResults,
  formatGenericResults,
};
