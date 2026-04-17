/**
 * Callback handler for Chef_Bot.
 * Handles inline keyboard button callbacks for modification confirmations.
 */

const { getPendingModifications } = require('./messageHandler');
const config = require('../config');

/**
 * Handle an incoming Telegram callback query (inline keyboard button press).
 *
 * @param {import('node-telegram-bot-api')} bot - Telegram bot instance.
 * @param {object} callbackQuery - Telegram callback query object.
 */
async function handleCallback(bot, callbackQuery) {
  const chatId = callbackQuery.message.chat.id.toString();
  const data = callbackQuery.data;
  const messageId = callbackQuery.message.message_id;

  // Acknowledge the callback to remove the loading indicator
  await bot.answerCallbackQuery(callbackQuery.id);

  const pendingModifications = getPendingModifications();

  if (data === 'confirm_modify') {
    await handleConfirm(bot, chatId, messageId, pendingModifications);
  } else if (data === 'cancel_modify') {
    await handleCancel(bot, chatId, messageId, pendingModifications);
  }
}

/**
 * Execute a confirmed modification via the Recipe API.
 *
 * @param {import('node-telegram-bot-api')} bot
 * @param {string} chatId
 * @param {number} messageId
 * @param {Map} pendingModifications
 */
async function handleConfirm(bot, chatId, messageId, pendingModifications) {
  const pending = pendingModifications.get(chatId);

  if (!pending) {
    await bot.editMessageText('⚠️ No hay modificación pendiente o ya expiró.', {
      chat_id: chatId,
      message_id: messageId,
    });
    return;
  }

  // Remove from pending before execution
  pendingModifications.delete(chatId);

  try {
    // Execute modification via Recipe API (recipeApi.js — task 9.4)
    // For now, attempt direct execution as a fallback
    let recipeApi;
    try {
      recipeApi = require('../api/recipeApi');
    } catch {
      recipeApi = null;
    }

    if (recipeApi && typeof recipeApi.executeModification === 'function') {
      const result = await recipeApi.executeModification(pending.sql, pending.params, chatId);
      if (!result.ok) {
        throw new Error(result.error || 'Error al ejecutar la modificación');
      }
    } else {
      // Direct DB execution fallback until recipeApi is implemented
      const pool = require('../db/mysql');
      await pool.execute(pending.sql, pending.params);
    }

    await bot.editMessageText(
      `✅ *Modificación aplicada*\n\n${pending.explanation || 'Operación completada.'}`,
      {
        chat_id: chatId,
        message_id: messageId,
        parse_mode: 'Markdown',
      }
    );
  } catch (err) {
    console.error(`[CallbackHandler] Modification execution error for chat ${chatId}:`, err);
    await bot.editMessageText(
      '❌ Error al ejecutar la modificación. Intenta de nuevo.',
      {
        chat_id: chatId,
        message_id: messageId,
      }
    );
  }
}

/**
 * Cancel a pending modification.
 *
 * @param {import('node-telegram-bot-api')} bot
 * @param {string} chatId
 * @param {number} messageId
 * @param {Map} pendingModifications
 */
async function handleCancel(bot, chatId, messageId, pendingModifications) {
  pendingModifications.delete(chatId);

  await bot.editMessageText('🚫 Modificación cancelada.', {
    chat_id: chatId,
    message_id: messageId,
  });
}

module.exports = { handleCallback };
