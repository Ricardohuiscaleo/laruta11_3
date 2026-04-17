const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const {
  levenshtein,
  fuzzyMatch,
  formatCLP,
  formatRecipeResults,
  formatStockResults,
  formatQueryResults,
  formatGenericResults,
} = require('./telegramFormatter');

// --- Levenshtein ---

describe('levenshtein', () => {
  it('returns 0 for identical strings', () => {
    assert.equal(levenshtein('queso', 'queso'), 0);
  });

  it('is case-insensitive', () => {
    assert.equal(levenshtein('Queso', 'queso'), 0);
  });

  it('returns correct distance for single substitution', () => {
    assert.equal(levenshtein('gato', 'pato'), 1);
  });

  it('returns correct distance for insertion', () => {
    assert.equal(levenshtein('pan', 'pana'), 1);
  });

  it('returns correct distance for deletion', () => {
    assert.equal(levenshtein('pana', 'pan'), 1);
  });

  it('returns length of other string when one is empty', () => {
    assert.equal(levenshtein('', 'abc'), 3);
    assert.equal(levenshtein('abc', ''), 3);
  });

  it('returns 0 for two empty strings', () => {
    assert.equal(levenshtein('', ''), 0);
  });
});


// --- fuzzyMatch ---

describe('fuzzyMatch', () => {
  const candidates = ['Queso', 'Carne', 'Lechuga', 'Tomate', 'Pan'];

  it('returns top 3 suggestions sorted by distance', () => {
    const results = fuzzyMatch('qeso', candidates);
    assert.equal(results.length, 3);
    assert.equal(results[0].name, 'Queso');
    // Sorted ascending by distance
    assert.ok(results[0].distance <= results[1].distance);
    assert.ok(results[1].distance <= results[2].distance);
  });

  it('returns exact match with distance 0 first', () => {
    const results = fuzzyMatch('Pan', candidates);
    assert.equal(results[0].name, 'Pan');
    assert.equal(results[0].distance, 0);
  });

  it('is case-insensitive', () => {
    const results = fuzzyMatch('QUESO', candidates);
    assert.equal(results[0].name, 'Queso');
    assert.equal(results[0].distance, 0);
  });

  it('returns empty array for empty input', () => {
    assert.deepStrictEqual(fuzzyMatch('', candidates), []);
  });

  it('returns empty array for empty candidates', () => {
    assert.deepStrictEqual(fuzzyMatch('queso', []), []);
  });

  it('returns fewer results if candidates are fewer than maxResults', () => {
    const results = fuzzyMatch('test', ['a', 'b']);
    assert.equal(results.length, 2);
  });

  it('respects custom maxResults', () => {
    const results = fuzzyMatch('test', candidates, 1);
    assert.equal(results.length, 1);
  });
});

// --- formatCLP ---

describe('formatCLP', () => {
  it('formats integer values with $ prefix', () => {
    const result = formatCLP(1500);
    assert.ok(result.startsWith('$'));
    assert.ok(result.includes('1'));
  });

  it('returns $0 for null', () => {
    assert.equal(formatCLP(null), '$0');
  });

  it('returns $0 for NaN', () => {
    assert.equal(formatCLP(NaN), '$0');
  });

  it('returns $0 for zero', () => {
    assert.equal(formatCLP(0), '$0');
  });
});

// --- formatRecipeResults ---

describe('formatRecipeResults', () => {
  it('formats recipe rows with product name, ingredients, and total cost', () => {
    const rows = [
      { producto: 'Hamburguesa', ingrediente: 'Pan', quantity: 1, unit: 'unidad', cost_per_unit: 300 },
      { producto: 'Hamburguesa', ingrediente: 'Carne', quantity: 200, unit: 'g', cost_per_unit: 8 },
    ];
    const result = formatRecipeResults(rows);
    assert.ok(result.includes('Hamburguesa'));
    assert.ok(result.includes('Pan'));
    assert.ok(result.includes('Carne'));
    assert.ok(result.includes('Costo total'));
  });

  it('groups ingredients by product', () => {
    const rows = [
      { producto: 'Pizza', ingrediente: 'Queso', quantity: 100, unit: 'g', cost_per_unit: 5 },
      { producto: 'Burger', ingrediente: 'Pan', quantity: 1, unit: 'unidad', cost_per_unit: 200 },
    ];
    const result = formatRecipeResults(rows);
    assert.ok(result.includes('Pizza'));
    assert.ok(result.includes('Burger'));
  });

  it('returns no-results message for empty array', () => {
    assert.ok(formatRecipeResults([]).includes('No se encontraron'));
  });

  it('returns no-results message for null', () => {
    assert.ok(formatRecipeResults(null).includes('No se encontraron'));
  });

  it('handles alternative column names (product_name, ingredient_name)', () => {
    const rows = [
      { product_name: 'Ensalada', ingredient_name: 'Lechuga', quantity: 50, unit: 'g', cost_per_unit: 3 },
    ];
    const result = formatRecipeResults(rows);
    assert.ok(result.includes('Ensalada'));
    assert.ok(result.includes('Lechuga'));
  });
});

// --- formatStockResults ---

describe('formatStockResults', () => {
  it('formats stock rows with name, stock, unit, cost, and status', () => {
    const rows = [
      { name: 'Queso', current_stock: 5000, unit: 'g', cost_per_unit: 5, min_stock_level: 1000 },
    ];
    const result = formatStockResults(rows);
    assert.ok(result.includes('Queso'));
    assert.ok(result.includes('5000'));
    assert.ok(result.includes('OK'));
  });

  it('marks critical stock correctly', () => {
    const rows = [
      { name: 'Lechuga', current_stock: 50, unit: 'g', cost_per_unit: 3, min_stock_level: 200 },
    ];
    const result = formatStockResults(rows);
    assert.ok(result.includes('Crítico'));
  });

  it('marks low stock correctly', () => {
    const rows = [
      { name: 'Tomate', current_stock: 250, unit: 'g', cost_per_unit: 2, min_stock_level: 200 },
    ];
    const result = formatStockResults(rows);
    assert.ok(result.includes('Bajo'));
  });

  it('returns no-results message for empty array', () => {
    assert.ok(formatStockResults([]).includes('No se encontraron'));
  });
});

// --- formatQueryResults (auto-detection) ---

describe('formatQueryResults', () => {
  it('detects recipe results by ingrediente column', () => {
    const rows = [
      { producto: 'Pizza', ingrediente: 'Queso', quantity: 100, unit: 'g', cost_per_unit: 5 },
    ];
    const result = formatQueryResults(rows);
    assert.ok(result.includes('Pizza'));
    assert.ok(result.includes('Queso'));
  });

  it('detects stock results by current_stock column', () => {
    const rows = [
      { name: 'Queso', current_stock: 5000, unit: 'g', cost_per_unit: 5, min_stock_level: 1000 },
    ];
    const result = formatQueryResults(rows);
    assert.ok(result.includes('Stock'));
  });

  it('falls back to generic format for unknown columns', () => {
    const rows = [{ foo: 'bar', baz: 42 }];
    const result = formatQueryResults(rows);
    assert.ok(result.includes('foo'));
    assert.ok(result.includes('bar'));
  });

  it('returns no-results for empty array', () => {
    assert.ok(formatQueryResults([]).includes('No se encontraron'));
  });
});

// --- formatGenericResults ---

describe('formatGenericResults', () => {
  it('formats arbitrary rows as key/value pairs', () => {
    const rows = [{ id: 1, name: 'Test' }];
    const result = formatGenericResults(rows);
    assert.ok(result.includes('id'));
    assert.ok(result.includes('name'));
    assert.ok(result.includes('Test'));
  });

  it('returns no-results for null', () => {
    assert.ok(formatGenericResults(null).includes('No se encontraron'));
  });
});
