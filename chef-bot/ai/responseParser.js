/**
 * Response parser for AI engine output.
 * Parses the raw text from Amazon Nova Micro into a structured object.
 * Handles valid JSON, markdown-wrapped JSON, and invalid responses.
 */

/**
 * Parse the AI model's raw text response into a structured object.
 *
 * @param {string} raw - Raw text from the model.
 * @returns {{ intent: string, sql: string, params: any[], explanation: string } | null}
 *   Parsed response or null if the response is unparseable.
 */
function parseResponse(raw) {
  if (!raw || typeof raw !== 'string') {
    return null;
  }

  const cleaned = stripMarkdownCodeBlock(raw.trim());

  let parsed;
  try {
    parsed = JSON.parse(cleaned);
  } catch {
    return null;
  }

  if (!isValidResponse(parsed)) {
    return null;
  }

  return {
    intent: parsed.intent,
    sql: parsed.sql,
    params: Array.isArray(parsed.params) ? parsed.params : [],
    explanation: parsed.explanation || '',
  };
}

/**
 * Strip markdown code block wrappers if present.
 * Handles ```json ... ``` and ``` ... ``` patterns.
 *
 * @param {string} text
 * @returns {string}
 */
function stripMarkdownCodeBlock(text) {
  const match = text.match(/^```(?:json)?\s*\n?([\s\S]*?)\n?\s*```$/);
  if (match) {
    return match[1].trim();
  }
  return text;
}

/**
 * Validate that the parsed object has the required shape.
 *
 * @param {any} obj
 * @returns {boolean}
 */
function isValidResponse(obj) {
  if (!obj || typeof obj !== 'object') return false;
  if (obj.intent !== 'query' && obj.intent !== 'modify') return false;
  if (typeof obj.sql !== 'string' || obj.sql.trim() === '') return false;
  return true;
}

module.exports = { parseResponse, stripMarkdownCodeBlock, isValidResponse };
