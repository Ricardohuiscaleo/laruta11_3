/**
 * Response parser for AI engine output.
 * Parses the raw text from Amazon Nova Micro into a structured object.
 * Supports intents: chat, query, modify, api_action, bulk_action.
 */

const VALID_INTENTS = new Set(['chat', 'query', 'modify', 'api_action', 'bulk_action']);

/**
 * Parse the AI model's raw text response into a structured object.
 *
 * @param {string} raw - Raw text from the model.
 * @returns {object|null} Parsed response or null if unparseable.
 */
function parseResponse(raw) {
  if (!raw || typeof raw !== 'string') return null;

  const cleaned = stripMarkdownCodeBlock(raw.trim());

  let parsed;
  try {
    parsed = JSON.parse(cleaned);
  } catch {
    // If JSON parse fails, treat the raw text as a chat message
    return { intent: 'chat', message: raw.trim() };
  }

  if (!parsed || typeof parsed !== 'object') return null;

  if (!VALID_INTENTS.has(parsed.intent)) {
    // Unknown intent — if it has a message field, treat as chat
    if (parsed.message) return { intent: 'chat', message: parsed.message };
    return null;
  }

  // Route by intent type
  switch (parsed.intent) {
    case 'chat':
      return {
        intent: 'chat',
        message: parsed.message || '',
      };

    case 'query':
    case 'modify':
      if (typeof parsed.sql !== 'string' || !parsed.sql.trim()) return null;
      return {
        intent: parsed.intent,
        sql: parsed.sql,
        params: Array.isArray(parsed.params) ? parsed.params : [],
        explanation: parsed.explanation || '',
      };

    case 'api_action':
      return {
        intent: 'api_action',
        action: parsed.action || '',
        data: parsed.data || {},
        explanation: parsed.explanation || '',
        steps: Array.isArray(parsed.steps) ? parsed.steps : [],
      };

    case 'bulk_action':
      return {
        intent: 'bulk_action',
        action: parsed.action || '',
        search: parsed.search || '',
        replace: parsed.replace || '',
        scope: parsed.scope || 'all',
        type: parsed.type || '',
        value: parsed.value ?? null,
        explanation: parsed.explanation || '',
        steps: Array.isArray(parsed.steps) ? parsed.steps : [],
      };

    default:
      return null;
  }
}

/**
 * Strip markdown code block wrappers if present.
 * @param {string} text
 * @returns {string}
 */
function stripMarkdownCodeBlock(text) {
  const match = text.match(/^```(?:json)?\s*\n?([\s\S]*?)\n?\s*```$/);
  return match ? match[1].trim() : text;
}

/**
 * Validate that the parsed object has a valid intent.
 * @param {any} obj
 * @returns {boolean}
 */
function isValidResponse(obj) {
  if (!obj || typeof obj !== 'object') return false;
  return VALID_INTENTS.has(obj.intent);
}

module.exports = { parseResponse, stripMarkdownCodeBlock, isValidResponse, VALID_INTENTS };
