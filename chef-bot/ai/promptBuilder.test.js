const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const { buildPrompt, SYSTEM_PROMPT } = require('./promptBuilder');

describe('promptBuilder', () => {
  describe('SYSTEM_PROMPT', () => {
    it('contains DB schema for products table', () => {
      assert.ok(SYSTEM_PROMPT.includes('Tabla: products'));
      assert.ok(SYSTEM_PROMPT.includes('cost_price'));
      assert.ok(SYSTEM_PROMPT.includes('category_id'));
      assert.ok(SYSTEM_PROMPT.includes('is_active'));
    });

    it('contains DB schema for ingredients table', () => {
      assert.ok(SYSTEM_PROMPT.includes('Tabla: ingredients'));
      assert.ok(SYSTEM_PROMPT.includes('cost_per_unit'));
      assert.ok(SYSTEM_PROMPT.includes('current_stock'));
      assert.ok(SYSTEM_PROMPT.includes('min_stock_level'));
    });

    it('contains DB schema for product_recipes table', () => {
      assert.ok(SYSTEM_PROMPT.includes('Tabla: product_recipes'));
      assert.ok(SYSTEM_PROMPT.includes('product_id'));
      assert.ok(SYSTEM_PROMPT.includes('ingredient_id'));
    });

    it('contains example NL→SQL mappings in Spanish', () => {
      assert.ok(SYSTEM_PROMPT.includes('¿cuánto cuesta la hamburguesa?'));
      assert.ok(SYSTEM_PROMPT.includes('¿qué ingredientes tiene la pizza?'));
      assert.ok(SYSTEM_PROMPT.includes('¿cuánto stock queda de queso?'));
    });

    it('contains JSON output format instructions', () => {
      assert.ok(SYSTEM_PROMPT.includes('"intent"'));
      assert.ok(SYSTEM_PROMPT.includes('"sql"'));
      assert.ok(SYSTEM_PROMPT.includes('"params"'));
      assert.ok(SYSTEM_PROMPT.includes('"explanation"'));
    });

    it('instructs to respond only in JSON', () => {
      assert.ok(SYSTEM_PROMPT.includes('Responde SOLO con el JSON'));
    });

    it('forbids destructive SQL operations', () => {
      assert.ok(SYSTEM_PROMPT.includes('NUNCA uses DELETE'));
      assert.ok(SYSTEM_PROMPT.includes('DROP'));
      assert.ok(SYSTEM_PROMPT.includes('TRUNCATE'));
    });
  });

  describe('buildPrompt', () => {
    it('returns systemPrompt and userMessage', () => {
      const result = buildPrompt('¿cuánto cuesta la hamburguesa?');
      assert.equal(result.systemPrompt, SYSTEM_PROMPT);
      assert.equal(result.userMessage, '¿cuánto cuesta la hamburguesa?');
    });

    it('passes through arbitrary user messages', () => {
      const msg = 'muéstrame los ingredientes con stock bajo';
      const result = buildPrompt(msg);
      assert.equal(result.userMessage, msg);
    });
  });
});
