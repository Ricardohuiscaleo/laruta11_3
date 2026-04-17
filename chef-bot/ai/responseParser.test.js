const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const { parseResponse, stripMarkdownCodeBlock, isValidResponse } = require('./responseParser');

describe('responseParser', () => {
  describe('parseResponse', () => {
    it('parses valid JSON response', () => {
      const raw = JSON.stringify({
        intent: 'query',
        sql: 'SELECT * FROM products WHERE name LIKE ?',
        params: ['%hamburguesa%'],
        explanation: 'Buscando hamburguesa',
      });
      const result = parseResponse(raw);
      assert.deepStrictEqual(result, {
        intent: 'query',
        sql: 'SELECT * FROM products WHERE name LIKE ?',
        params: ['%hamburguesa%'],
        explanation: 'Buscando hamburguesa',
      });
    });

    it('parses JSON wrapped in ```json code block', () => {
      const raw = '```json\n{"intent":"query","sql":"SELECT 1","params":[],"explanation":"test"}\n```';
      const result = parseResponse(raw);
      assert.equal(result.intent, 'query');
      assert.equal(result.sql, 'SELECT 1');
    });

    it('parses JSON wrapped in ``` code block (no language)', () => {
      const raw = '```\n{"intent":"modify","sql":"INSERT INTO product_recipes VALUES (1,2,100,\'g\')","params":[],"explanation":"agregando"}\n```';
      const result = parseResponse(raw);
      assert.equal(result.intent, 'modify');
    });

    it('returns null for empty input', () => {
      assert.equal(parseResponse(''), null);
      assert.equal(parseResponse(null), null);
      assert.equal(parseResponse(undefined), null);
    });

    it('returns null for non-string input', () => {
      assert.equal(parseResponse(123), null);
      assert.equal(parseResponse({}), null);
    });

    it('returns null for invalid JSON', () => {
      assert.equal(parseResponse('this is not json'), null);
      assert.equal(parseResponse('{broken'), null);
    });

    it('returns null when intent is missing or invalid', () => {
      assert.equal(parseResponse('{"sql":"SELECT 1","params":[]}'), null);
      assert.equal(
        parseResponse('{"intent":"delete","sql":"SELECT 1","params":[]}'),
        null
      );
    });

    it('returns null when sql is missing or empty', () => {
      assert.equal(parseResponse('{"intent":"query","params":[]}'), null);
      assert.equal(
        parseResponse('{"intent":"query","sql":"","params":[]}'),
        null
      );
    });

    it('defaults params to empty array if not an array', () => {
      const raw = '{"intent":"query","sql":"SELECT 1","params":"bad","explanation":"x"}';
      const result = parseResponse(raw);
      assert.deepStrictEqual(result.params, []);
    });

    it('defaults explanation to empty string if missing', () => {
      const raw = '{"intent":"query","sql":"SELECT 1","params":[]}';
      const result = parseResponse(raw);
      assert.equal(result.explanation, '');
    });
  });

  describe('stripMarkdownCodeBlock', () => {
    it('strips ```json wrapper', () => {
      assert.equal(stripMarkdownCodeBlock('```json\n{}\n```'), '{}');
    });

    it('strips ``` wrapper without language', () => {
      assert.equal(stripMarkdownCodeBlock('```\n{}\n```'), '{}');
    });

    it('returns text unchanged if no code block', () => {
      assert.equal(stripMarkdownCodeBlock('{"a":1}'), '{"a":1}');
    });
  });

  describe('isValidResponse', () => {
    it('accepts valid query response', () => {
      assert.equal(isValidResponse({ intent: 'query', sql: 'SELECT 1' }), true);
    });

    it('accepts valid modify response', () => {
      assert.equal(isValidResponse({ intent: 'modify', sql: 'INSERT INTO x' }), true);
    });

    it('rejects null', () => {
      assert.equal(isValidResponse(null), false);
    });

    it('rejects unknown intent', () => {
      assert.equal(isValidResponse({ intent: 'drop', sql: 'DROP TABLE' }), false);
    });

    it('rejects empty sql', () => {
      assert.equal(isValidResponse({ intent: 'query', sql: '   ' }), false);
    });
  });
});
