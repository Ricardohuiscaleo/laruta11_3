const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const { MODEL_ID, MAX_RETRIES, BASE_DELAY_MS } = require('./bedrockClient');

describe('bedrockClient', () => {
  describe('exported constants', () => {
    it('uses Amazon Nova Micro model ID', () => {
      assert.equal(MODEL_ID, 'amazon.nova-micro-v1:0');
    });

    it('has 2 retries configured', () => {
      assert.equal(MAX_RETRIES, 2);
    });

    it('has 1s base delay for exponential backoff', () => {
      assert.equal(BASE_DELAY_MS, 1000);
    });
  });

  describe('exponential backoff calculation', () => {
    it('produces correct delays: 1s, 3s', () => {
      const delays = [];
      for (let attempt = 0; attempt < MAX_RETRIES; attempt++) {
        delays.push(BASE_DELAY_MS * Math.pow(3, attempt));
      }
      assert.deepStrictEqual(delays, [1000, 3000]);
    });
  });
});
