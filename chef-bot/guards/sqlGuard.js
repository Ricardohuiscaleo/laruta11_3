/**
 * SQL_Guard — validation pipeline for AI-generated SQL.
 *
 * Receives the parsed response from responseParser.js:
 *   { intent, sql, params, explanation }
 *
 * Returns:
 *   On success: { ok: true, sql: string, params: any[] }
 *   On failure: { ok: false, reason: string }
 */

const ALLOWED_TABLES = new Set(['products', 'ingredients', 'product_recipes']);

const DANGEROUS_STATEMENTS = /\b(DELETE|DROP|ALTER|TRUNCATE|CREATE)\b/i;

const MODIFY_KEYWORDS = /\b(INSERT|UPDATE|DELETE)\b/i;

/**
 * Identify the top-level statement type from a SQL string.
 * @param {string} sql
 * @returns {string} Uppercased statement type (SELECT, INSERT, UPDATE, etc.) or 'UNKNOWN'
 */
function identifyStatementType(sql) {
  const trimmed = sql.trim();
  const match = trimmed.match(/^(\w+)/);
  if (!match) return 'UNKNOWN';
  return match[1].toUpperCase();
}

/**
 * Extract table references from a SQL string using regex.
 * Looks for FROM, JOIN, INTO, UPDATE table patterns.
 * @param {string} sql
 * @returns {string[]} Array of table names found
 */
function extractTableReferences(sql) {
  const tables = new Set();

  // FROM table, FROM table alias, FROM table AS alias
  const fromPattern = /\bFROM\s+`?(\w+)`?/gi;
  let m;
  while ((m = fromPattern.exec(sql)) !== null) {
    tables.add(m[1].toLowerCase());
  }

  // JOIN table
  const joinPattern = /\bJOIN\s+`?(\w+)`?/gi;
  while ((m = joinPattern.exec(sql)) !== null) {
    tables.add(m[1].toLowerCase());
  }

  // INSERT INTO table
  const insertPattern = /\bINTO\s+`?(\w+)`?/gi;
  while ((m = insertPattern.exec(sql)) !== null) {
    tables.add(m[1].toLowerCase());
  }

  // UPDATE table
  const updatePattern = /\bUPDATE\s+`?(\w+)`?/gi;
  while ((m = updatePattern.exec(sql)) !== null) {
    tables.add(m[1].toLowerCase());
  }

  return [...tables];
}

/**
 * Check if a SQL string contains data-modifying subqueries.
 * Looks for INSERT/UPDATE/DELETE inside parenthesized subqueries.
 * @param {string} sql
 * @returns {boolean}
 */
function hasModifyingSubquery(sql) {
  // Find content inside parentheses and check for modifying keywords
  const subqueryPattern = /\(([^()]*(?:\([^()]*\)[^()]*)*)\)/g;
  let m;
  while ((m = subqueryPattern.exec(sql)) !== null) {
    const inner = m[1];
    if (MODIFY_KEYWORDS.test(inner)) {
      return true;
    }
  }
  return false;
}

/**
 * Parameterize literal values in SQL to prevent injection.
 * Extracts string literals ('...') and numeric literals, replaces with ?,
 * and appends extracted values to the params array.
 *
 * @param {string} sql
 * @param {any[]} existingParams
 * @returns {{ sql: string, params: any[] }}
 */
function parameterizeLiterals(sql, existingParams) {
  const params = [...existingParams];

  // Replace string literals (single-quoted) — handle escaped quotes inside
  let result = sql.replace(/'((?:[^'\\]|\\.)*)'/g, (_match, value) => {
    params.push(value.replace(/\\'/g, "'").replace(/\\\\/g, '\\'));
    return '?';
  });

  // Replace standalone numeric literals (integers and decimals).
  // Use a negative lookbehind to skip numbers preceded by a letter or underscore
  // (e.g. table1, col_2) so only true value literals are parameterized.
  result = result.replace(/(?<![a-zA-Z_])(\d+(?:\.\d+)?)(?![a-zA-Z_])/g, (_match, num) => {
    params.push(Number(num));
    return '?';
  });

  return { sql: result, params };
}

/**
 * Validate and sanitize AI-generated SQL.
 *
 * @param {{ intent: string, sql: string, params: any[], explanation: string }} parsed
 *   The parsed AI response.
 * @param {string} [chatId] Optional chat_id for logging rejections.
 * @returns {{ ok: true, sql: string, params: any[] } | { ok: false, reason: string }}
 */
function validateSQL(parsed, chatId) {
  if (!parsed || typeof parsed.sql !== 'string' || !parsed.sql.trim()) {
    return reject('Empty or missing SQL', chatId, '');
  }

  const { intent, sql, params = [] } = parsed;
  const stmtType = identifyStatementType(sql);

  // Step 5: Reject dangerous statement types outright
  if (DANGEROUS_STATEMENTS.test(sql)) {
    // Only reject if the dangerous keyword is a top-level statement or appears as a statement
    const dangerousMatch = sql.match(/\b(DELETE|DROP|ALTER|TRUNCATE|CREATE)\b/i);
    if (dangerousMatch) {
      return reject(`Prohibited statement type: ${dangerousMatch[1].toUpperCase()}`, chatId, sql);
    }
  }

  // Step 1-2: Check statement type against intent
  if (intent === 'query') {
    if (stmtType !== 'SELECT') {
      return reject(`Query intent requires SELECT, got ${stmtType}`, chatId, sql);
    }
  } else if (intent === 'modify') {
    if (stmtType !== 'INSERT' && stmtType !== 'UPDATE') {
      return reject(`Modify intent requires INSERT or UPDATE, got ${stmtType}`, chatId, sql);
    }
    // For modifications, only product_recipes table is allowed
    const tables = extractTableReferences(sql);
    const modifyTables = tables.filter(t => t !== 'product_recipes');
    // Check that the target table of INSERT/UPDATE is product_recipes
    if (stmtType === 'INSERT') {
      const insertTarget = sql.match(/\bINTO\s+`?(\w+)`?/i);
      if (!insertTarget || insertTarget[1].toLowerCase() !== 'product_recipes') {
        return reject('Modifications only allowed on product_recipes table', chatId, sql);
      }
    }
    if (stmtType === 'UPDATE') {
      const updateTarget = sql.match(/\bUPDATE\s+`?(\w+)`?/i);
      if (!updateTarget || updateTarget[1].toLowerCase() !== 'product_recipes') {
        return reject('Modifications only allowed on product_recipes table', chatId, sql);
      }
    }
  } else {
    return reject(`Unknown intent: ${intent}`, chatId, sql);
  }

  // Step 3: Validate all table references against allowlist
  const tables = extractTableReferences(sql);
  for (const table of tables) {
    if (!ALLOWED_TABLES.has(table)) {
      return reject(`Table not allowed: ${table}`, chatId, sql);
    }
  }

  // Step 4: Reject subqueries containing INSERT/UPDATE/DELETE
  if (hasModifyingSubquery(sql)) {
    return reject('Data-modifying subqueries are not allowed', chatId, sql);
  }

  // Step 6: Parameterize literal values
  const parameterized = parameterizeLiterals(sql, params);

  return { ok: true, sql: parameterized.sql, params: parameterized.params };
}

/**
 * Build a rejection result and log it.
 * @param {string} reason
 * @param {string} [chatId]
 * @param {string} [sql]
 * @returns {{ ok: false, reason: string }}
 */
function reject(reason, chatId, sql) {
  // Step 7: Log rejected queries with chat_id and timestamp
  console.warn(
    `[SQL_Guard REJECTED] ${new Date().toISOString()} | chat_id: ${chatId || 'unknown'} | reason: ${reason} | sql: ${sql || 'N/A'}`
  );
  return { ok: false, reason };
}

module.exports = {
  validateSQL,
  identifyStatementType,
  extractTableReferences,
  hasModifyingSubquery,
  parameterizeLiterals,
  ALLOWED_TABLES,
};
