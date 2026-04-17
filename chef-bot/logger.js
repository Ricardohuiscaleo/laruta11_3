/**
 * Audit logger for Chef_Bot modifications.
 * Logs all recipe modification operations with chat_id, timestamp, and executed SQL.
 */

/**
 * Log a recipe modification for audit purposes.
 * Outputs a structured log line to console.
 *
 * @param {string} chatId - Telegram chat_id of the user who triggered the modification.
 * @param {string} sql - The executed SQL statement.
 * @param {Array} [params] - Parameterized values used in the query.
 */
function logModification(chatId, sql, params = []) {
  const timestamp = new Date().toISOString();
  const paramStr = params.length > 0 ? ` | params: ${JSON.stringify(params)}` : '';
  console.log(`[AUDIT] ${timestamp} | chat_id: ${chatId} | sql: ${sql}${paramStr}`);
}

module.exports = { logModification };
