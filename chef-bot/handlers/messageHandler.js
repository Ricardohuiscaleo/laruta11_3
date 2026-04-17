/**
 * Message handler for Chef_Bot.
 * Routes incoming Telegram messages through the AI engine, SQL_Guard,
 * then to query execution or modification confirmation flow.
 */

const { buildPrompt } = require('../ai/promptBuilder');
const { invoke } = require('../ai/bedrockClient');
const { parseResponse } = require('../ai/responseParser');
const { validateSQL } = require('../guards/sqlGuard');
const { formatQueryResults } = require('../formatters/telegramFormatter');
const pool = require('../db/mysql');
const config = require('../config');

/**
 * In-memory store for pending modifications awaiting user confirmation.
 * Keyed by chatId → { sql, params, explanation, timestamp }
 */
const pendingModifications = new Map();

/**
 * Help message shown when the AI cannot interpret the user's input.
 */
const HELP_MESSAGE = `🤖 *Chef Bot — Ayuda*

No pude entender tu mensaje. Aquí tienes algunos ejemplos de lo que puedo hacer:

📋 *Consultas de recetas:*
• "¿Qué ingredientes tiene la hamburguesa?"
• "¿Cuánto cuesta la pizza?"
• "Muéstrame los productos con margen menor a 50%"

📦 *Consultas de stock:*
• "¿Cuánto stock queda de queso?"
• "¿Qué ingredientes están bajos de stock?"

✏️ *Modificaciones de recetas:*
• "Agrega 200g de tomate a la hamburguesa"
• "Cambia la cantidad de queso en la pizza a 150g"

Escribe tu pregunta en lenguaje natural y haré lo posible por ayudarte.`;

/**
 * Handle an incoming Telegram message.
 *
 * @param {import('node-telegram-bot-api')} bot - Telegram bot instance.
 * @param {object} msg - Telegram message object.
 */
async function handleMessage(bot, msg) {
  const chatId = msg.chat.id.toString();
  const text = msg.text;

  if (!text || text.startsWith('/')) return;

  try {
    // Step 1: Build prompt and invoke AI
    const { systemPrompt, userMessage } = buildPrompt(text);
    const raw = await invoke(systemPrompt, userMessage);

    // Step 2: Parse AI response
    const parsed = parseResponse(raw);

    if (!parsed) {
      // AI could not interpret — send help message
      await bot.sendMessage(chatId, HELP_MESSAGE, { parse_mode: 'Markdown' });
      return;
    }

    // Step 3: Route by intent
    if (parsed.intent === 'query') {
      await handleQuery(bot, chatId, parsed);
    } else if (parsed.intent === 'modify') {
      await handleModify(bot, chatId, parsed);
    }
  } catch (err) {
    console.error(`[MessageHandler] Error processing message from ${chatId}:`, err);

    if (err.name === 'TimeoutError' || err.message?.includes('Bedrock') || err.message?.includes('modelo')) {
      await bot.sendMessage(chatId, '⚠️ El servicio de IA no está disponible. Intenta de nuevo.');
    } else {
      await bot.sendMessage(chatId, '⚠️ Error de conexión. Intenta de nuevo en unos minutos.');
    }
  }
}

/**
 * Handle a query intent — validate SQL, execute SELECT, format and send results.
 *
 * @param {import('node-telegram-bot-api')} bot
 * @param {string} chatId
 * @param {{ intent: string, sql: string, params: any[], explanation: string }} parsed
 */
async function handleQuery(bot, chatId, parsed) {
  // Validate through SQL_Guard
  const validation = validateSQL(parsed, chatId);

  if (!validation.ok) {
    await bot.sendMessage(chatId, `⚠️ Operación no permitida: ${validation.reason}`);
    return;
  }

  try {
    const [rows] = await pool.execute(validation.sql, validation.params);

    if (!rows || rows.length === 0) {
      await bot.sendMessage(chatId, '🔍 No se encontraron resultados.');
      return;
    }

    const explanation = parsed.explanation ? `💡 ${parsed.explanation}\n\n` : '';
    const formatted = `${explanation}${formatQueryResults(rows)}`;

    await bot.sendMessage(chatId, formatted, { parse_mode: 'Markdown' });
  } catch (err) {
    console.error(`[MessageHandler] Query execution error for chat ${chatId}:`, err);
    await bot.sendMessage(chatId, '⚠️ Error de conexión. Intenta de nuevo en unos minutos.');
  }
}

/**
 * Handle a modify intent — check authorization, validate SQL, show confirmation preview.
 *
 * @param {import('node-telegram-bot-api')} bot
 * @param {string} chatId
 * @param {{ intent: string, sql: string, params: any[], explanation: string }} parsed
 */
async function handleModify(bot, chatId, parsed) {
  // Authorization check — Requirement 10.5: empty list rejects all with logged warning
  if (config.AUTHORIZED_CHAT_IDS.length === 0) {
    console.warn('[Auth] AUTHORIZED_CHAT_IDS is empty — all modification requests are rejected.');
    await bot.sendMessage(chatId, '🔒 No tienes permisos para modificar recetas.');
    return;
  }

  if (!config.AUTHORIZED_CHAT_IDS.includes(chatId)) {
    await bot.sendMessage(chatId, '🔒 No tienes permisos para modificar recetas.');
    return;
  }

  // Validate through SQL_Guard
  const validation = validateSQL(parsed, chatId);

  if (!validation.ok) {
    await bot.sendMessage(chatId, `⚠️ Operación no permitida: ${validation.reason}`);
    return;
  }

  // Store pending modification
  pendingModifications.set(chatId, {
    sql: validation.sql,
    params: validation.params,
    explanation: parsed.explanation,
    timestamp: Date.now(),
  });

  // Show confirmation preview with inline keyboard
  const preview = `✏️ *Modificación propuesta:*\n\n${parsed.explanation || 'Sin descripción'}\n\n¿Confirmar esta operación?`;

  await bot.sendMessage(chatId, preview, {
    parse_mode: 'Markdown',
    reply_markup: {
      inline_keyboard: [
        [
          { text: '✅ Confirmar', callback_data: 'confirm_modify' },
          { text: '❌ Cancelar', callback_data: 'cancel_modify' },
        ],
      ],
    },
  });
}

/**
 * Get the pending modifications map (used by callbackHandler).
 * @returns {Map}
 */
function getPendingModifications() {
  return pendingModifications;
}

module.exports = { handleMessage, getPendingModifications, HELP_MESSAGE };
