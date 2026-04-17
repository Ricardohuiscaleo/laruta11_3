/**
 * Recipe API client for Chef_Bot.
 * Handles HTTP calls to mi3-backend Recipe_API for write operations,
 * and provides direct DB execution for SQL-based modifications.
 */

const config = require('../config');
const pool = require('../db/mysql');
const { logModification } = require('../logger');

const BASE_URL = config.API_BASE_URL;

/**
 * Make an authenticated request to the Recipe API.
 *
 * @param {string} method - HTTP method (GET, POST, PUT, DELETE).
 * @param {string} path - API path (e.g. '/api/v1/admin/recetas/5').
 * @param {object|null} body - JSON body for POST/PUT requests.
 * @returns {Promise<{ok: boolean, status: number, data: object}>}
 */
async function apiRequest(method, path, body = null) {
  const url = `${BASE_URL}${path}`;
  const options = {
    method,
    headers: { 'Content-Type': 'application/json' },
  };

  if (body && (method === 'POST' || method === 'PUT')) {
    options.body = JSON.stringify(body);
  }

  try {
    const response = await fetch(url, options);
    const data = await response.json().catch(() => ({}));
    return { ok: response.ok, status: response.status, data };
  } catch (err) {
    return { ok: false, status: 0, data: { error: err.message } };
  }
}

/**
 * Create a recipe for a product via the Recipe API.
 *
 * @param {number} productId
 * @param {Array<{ingredient_id: number, quantity: number, unit: string}>} ingredients
 * @returns {Promise<{ok: boolean, status: number, data: object}>}
 */
async function createRecipe(productId, ingredients) {
  return apiRequest('POST', `/api/v1/admin/recetas/${productId}`, { ingredients });
}

/**
 * Update a recipe for a product via the Recipe API.
 *
 * @param {number} productId
 * @param {Array<{ingredient_id: number, quantity: number, unit: string}>} ingredients
 * @returns {Promise<{ok: boolean, status: number, data: object}>}
 */
async function updateRecipe(productId, ingredients) {
  return apiRequest('PUT', `/api/v1/admin/recetas/${productId}`, { ingredients });
}

/**
 * Remove an ingredient from a product's recipe via the Recipe API.
 *
 * @param {number} productId
 * @param {number} ingredientId
 * @returns {Promise<{ok: boolean, status: number, data: object}>}
 */
async function removeIngredient(productId, ingredientId) {
  return apiRequest('DELETE', `/api/v1/admin/recetas/${productId}/${ingredientId}`);
}

/**
 * Execute a validated SQL modification directly on the database pool.
 * Used by the callbackHandler when a user confirms a modification.
 *
 * @param {string} sql - The validated SQL statement.
 * @param {Array} params - Parameterized values for the query.
 * @param {string} [chatId] - Telegram chat_id for audit logging.
 * @returns {Promise<{ok: boolean, result?: object, error?: string}>}
 */
async function executeModification(sql, params = [], chatId = '') {
  try {
    if (chatId) {
      logModification(chatId, sql, params);
    }
    const [result] = await pool.execute(sql, params);
    return { ok: true, result };
  } catch (err) {
    console.error('[RecipeApi] executeModification error:', err.message);
    return { ok: false, error: err.message };
  }
}

module.exports = {
  apiRequest,
  createRecipe,
  updateRecipe,
  removeIngredient,
  executeModification,
};
