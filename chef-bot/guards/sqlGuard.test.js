const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const {
  validateSQL,
  identifyStatementType,
  extractTableReferences,
  hasModifyingSubquery,
  parameterizeLiterals,
  ALLOWED_TABLES,
} = require('./sqlGuard');

describe('sqlGuard', () => {
  describe('identifyStatementType', () => {
    it('identifies SELECT', () => {
      assert.equal(identifyStatementType('SELECT * FROM products'), 'SELECT');
    });

    it('identifies INSERT', () => {
      assert.equal(identifyStatementType('INSERT INTO product_recipes VALUES (1,2,100,\'g\')'), 'INSERT');
    });

    it('identifies UPDATE', () => {
      assert.equal(identifyStatementType('UPDATE product_recipes SET quantity = 200'), 'UPDATE');
    });

    it('identifies DELETE', () => {
      assert.equal(identifyStatementType('DELETE FROM products'), 'DELETE');
    });

    it('identifies DROP', () => {
      assert.equal(identifyStatementType('DROP TABLE products'), 'DROP');
    });

    it('returns UNKNOWN for empty string', () => {
      assert.equal(identifyStatementType(''), 'UNKNOWN');
    });

    it('is case-insensitive', () => {
      assert.equal(identifyStatementType('select * from products'), 'SELECT');
    });
  });

  describe('extractTableReferences', () => {
    it('extracts FROM tables', () => {
      const tables = extractTableReferences('SELECT * FROM products WHERE id = 1');
      assert.deepStrictEqual(tables, ['products']);
    });

    it('extracts JOIN tables', () => {
      const tables = extractTableReferences(
        'SELECT * FROM products JOIN product_recipes ON products.id = product_recipes.product_id'
      );
      assert.ok(tables.includes('products'));
      assert.ok(tables.includes('product_recipes'));
    });

    it('extracts INSERT INTO tables', () => {
      const tables = extractTableReferences("INSERT INTO product_recipes (product_id) VALUES (1)");
      assert.ok(tables.includes('product_recipes'));
    });

    it('extracts UPDATE tables', () => {
      const tables = extractTableReferences('UPDATE product_recipes SET quantity = 100');
      assert.ok(tables.includes('product_recipes'));
    });

    it('extracts backtick-quoted tables', () => {
      const tables = extractTableReferences('SELECT * FROM `products`');
      assert.deepStrictEqual(tables, ['products']);
    });

    it('extracts multiple tables from complex query', () => {
      const sql = 'SELECT p.name, i.name FROM products p JOIN product_recipes pr ON p.id = pr.product_id JOIN ingredients i ON pr.ingredient_id = i.id';
      const tables = extractTableReferences(sql);
      assert.ok(tables.includes('products'));
      assert.ok(tables.includes('product_recipes'));
      assert.ok(tables.includes('ingredients'));
    });
  });

  describe('hasModifyingSubquery', () => {
    it('returns false for simple SELECT', () => {
      assert.equal(hasModifyingSubquery('SELECT * FROM products'), false);
    });

    it('returns false for SELECT with non-modifying subquery', () => {
      assert.equal(
        hasModifyingSubquery('SELECT * FROM products WHERE id IN (SELECT product_id FROM product_recipes)'),
        false
      );
    });

    it('returns true for SELECT with INSERT subquery', () => {
      assert.equal(
        hasModifyingSubquery('SELECT * FROM products WHERE id IN (INSERT INTO product_recipes VALUES (1,2,3,\'g\'))'),
        true
      );
    });

    it('returns true for SELECT with DELETE subquery', () => {
      assert.equal(
        hasModifyingSubquery('SELECT * FROM products WHERE id IN (DELETE FROM product_recipes WHERE id = 1)'),
        true
      );
    });

    it('returns true for SELECT with UPDATE subquery', () => {
      assert.equal(
        hasModifyingSubquery('SELECT * FROM products WHERE id IN (UPDATE product_recipes SET quantity = 0)'),
        true
      );
    });
  });

  describe('parameterizeLiterals', () => {
    it('replaces string literals with placeholders', () => {
      const result = parameterizeLiterals("SELECT * FROM products WHERE name = 'hamburguesa'", []);
      assert.equal(result.sql, 'SELECT * FROM products WHERE name = ?');
      assert.deepStrictEqual(result.params, ['hamburguesa']);
    });

    it('replaces numeric literals with placeholders', () => {
      const result = parameterizeLiterals('SELECT * FROM products WHERE price > 5000', []);
      assert.equal(result.sql, 'SELECT * FROM products WHERE price > ?');
      assert.deepStrictEqual(result.params, [5000]);
    });

    it('replaces multiple literals', () => {
      const result = parameterizeLiterals(
        "SELECT * FROM products WHERE name = 'burger' AND price > 3000",
        []
      );
      assert.equal(result.sql, 'SELECT * FROM products WHERE name = ? AND price > ?');
      assert.deepStrictEqual(result.params, ['burger', 3000]);
    });

    it('preserves existing params', () => {
      const result = parameterizeLiterals("SELECT * FROM products WHERE name = 'test'", ['existing']);
      assert.deepStrictEqual(result.params, ['existing', 'test']);
    });

    it('handles escaped quotes in strings', () => {
      const result = parameterizeLiterals("SELECT * FROM products WHERE name = 'it\\'s'", []);
      assert.equal(result.params[0], "it's");
    });

    it('handles decimal numbers', () => {
      const result = parameterizeLiterals('SELECT * FROM ingredients WHERE cost_per_unit > 2.5', []);
      assert.ok(result.params.includes(2.5));
    });
  });

  describe('validateSQL', () => {
    // --- Acceptance: valid queries ---
    it('accepts SELECT on allowed tables for query intent', () => {
      const result = validateSQL({
        intent: 'query',
        sql: 'SELECT * FROM products',
        params: [],
        explanation: 'test',
      });
      assert.equal(result.ok, true);
    });

    it('accepts SELECT with JOIN on allowed tables', () => {
      const result = validateSQL({
        intent: 'query',
        sql: 'SELECT p.name, i.name FROM products p JOIN product_recipes pr ON p.id = pr.product_id JOIN ingredients i ON pr.ingredient_id = i.id',
        params: [],
        explanation: 'test',
      });
      assert.equal(result.ok, true);
    });

    it('accepts INSERT INTO product_recipes for modify intent', () => {
      const result = validateSQL({
        intent: 'modify',
        sql: "INSERT INTO product_recipes (product_id, ingredient_id, quantity, unit) VALUES (1, 2, 100, 'g')",
        params: [],
        explanation: 'test',
      });
      assert.equal(result.ok, true);
    });

    it('accepts UPDATE product_recipes for modify intent', () => {
      const result = validateSQL({
        intent: 'modify',
        sql: 'UPDATE product_recipes SET quantity = 200 WHERE product_id = 1 AND ingredient_id = 2',
        params: [],
        explanation: 'test',
      });
      assert.equal(result.ok, true);
    });

    // --- Rejection: dangerous statements ---
    it('rejects DELETE statements', () => {
      const result = validateSQL({
        intent: 'modify',
        sql: 'DELETE FROM product_recipes WHERE id = 1',
        params: [],
        explanation: 'test',
      });
      assert.equal(result.ok, false);
      assert.match(result.reason, /DELETE/i);
    });

    it('rejects DROP statements', () => {
      const result = validateSQL({
        intent: 'query',
        sql: 'DROP TABLE products',
        params: [],
        explanation: 'test',
      });
      assert.equal(result.ok, false);
      assert.match(result.reason, /DROP/i);
    });

    it('rejects ALTER statements', () => {
      const result = validateSQL({
        intent: 'query',
        sql: 'ALTER TABLE products ADD COLUMN foo INT',
        params: [],
        explanation: 'test',
      });
      assert.equal(result.ok, false);
      assert.match(result.reason, /ALTER/i);
    });

    it('rejects TRUNCATE statements', () => {
      const result = validateSQL({
        intent: 'query',
        sql: 'TRUNCATE TABLE products',
        params: [],
        explanation: 'test',
      });
      assert.equal(result.ok, false);
      assert.match(result.reason, /TRUNCATE/i);
    });

    it('rejects CREATE statements', () => {
      const result = validateSQL({
        intent: 'query',
        sql: 'CREATE TABLE evil (id INT)',
        params: [],
        explanation: 'test',
      });
      assert.equal(result.ok, false);
      assert.match(result.reason, /CREATE/i);
    });

    // --- Rejection: intent mismatch ---
    it('rejects INSERT for query intent', () => {
      const result = validateSQL({
        intent: 'query',
        sql: "INSERT INTO product_recipes VALUES (1,2,100,'g')",
        params: [],
        explanation: 'test',
      });
      assert.equal(result.ok, false);
    });

    it('rejects SELECT for modify intent', () => {
      const result = validateSQL({
        intent: 'modify',
        sql: 'SELECT * FROM products',
        params: [],
        explanation: 'test',
      });
      assert.equal(result.ok, false);
    });

    // --- Rejection: disallowed tables ---
    it('rejects queries referencing tables outside allowlist', () => {
      const result = validateSQL({
        intent: 'query',
        sql: 'SELECT * FROM users',
        params: [],
        explanation: 'test',
      });
      assert.equal(result.ok, false);
      assert.match(result.reason, /not allowed/i);
    });

    it('rejects modifications on tables other than product_recipes', () => {
      const result = validateSQL({
        intent: 'modify',
        sql: 'UPDATE products SET price = 1000 WHERE id = 1',
        params: [],
        explanation: 'test',
      });
      assert.equal(result.ok, false);
      assert.match(result.reason, /product_recipes/i);
    });

    it('rejects INSERT into tables other than product_recipes', () => {
      const result = validateSQL({
        intent: 'modify',
        sql: "INSERT INTO products (name, price) VALUES ('evil', 0)",
        params: [],
        explanation: 'test',
      });
      assert.equal(result.ok, false);
    });

    // --- Rejection: modifying subqueries ---
    it('rejects SELECT with data-modifying subquery', () => {
      const result = validateSQL({
        intent: 'query',
        sql: "SELECT * FROM products WHERE id IN (INSERT INTO product_recipes VALUES (1,2,3,'g'))",
        params: [],
        explanation: 'test',
      });
      assert.equal(result.ok, false);
      assert.match(result.reason, /subquer/i);
    });

    // --- Parameterization ---
    it('parameterizes string literals in validated SQL', () => {
      const result = validateSQL({
        intent: 'query',
        sql: "SELECT * FROM products WHERE name = 'hamburguesa'",
        params: [],
        explanation: 'test',
      });
      assert.equal(result.ok, true);
      assert.equal(result.sql, 'SELECT * FROM products WHERE name = ?');
      assert.ok(result.params.includes('hamburguesa'));
    });

    it('parameterizes numeric literals in validated SQL', () => {
      const result = validateSQL({
        intent: 'query',
        sql: 'SELECT * FROM products WHERE price > 5000',
        params: [],
        explanation: 'test',
      });
      assert.equal(result.ok, true);
      assert.ok(result.params.includes(5000));
    });

    // --- Edge cases ---
    it('rejects null parsed input', () => {
      const result = validateSQL(null);
      assert.equal(result.ok, false);
    });

    it('rejects empty SQL', () => {
      const result = validateSQL({ intent: 'query', sql: '', params: [] });
      assert.equal(result.ok, false);
    });

    it('rejects unknown intent', () => {
      const result = validateSQL({
        intent: 'delete',
        sql: 'SELECT * FROM products',
        params: [],
        explanation: 'test',
      });
      assert.equal(result.ok, false);
    });

    // --- Logging ---
    it('logs rejected queries with chat_id', () => {
      const warnings = [];
      const origWarn = console.warn;
      console.warn = (...args) => warnings.push(args.join(' '));

      validateSQL(
        { intent: 'query', sql: 'DROP TABLE products', params: [], explanation: 'test' },
        'chat_12345'
      );

      console.warn = origWarn;
      assert.ok(warnings.length > 0);
      assert.ok(warnings[0].includes('chat_12345'));
      assert.ok(warnings[0].includes('SQL_Guard REJECTED'));
    });
  });

  describe('ALLOWED_TABLES', () => {
    it('contains exactly the three allowed tables', () => {
      assert.equal(ALLOWED_TABLES.size, 3);
      assert.ok(ALLOWED_TABLES.has('products'));
      assert.ok(ALLOWED_TABLES.has('ingredients'));
      assert.ok(ALLOWED_TABLES.has('product_recipes'));
    });
  });
});
