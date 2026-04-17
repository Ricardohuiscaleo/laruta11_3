/**
 * Message handler for Chef_Bot — conversational RAG agent.
 * Routes messages through AI engine, then handles by intent type:
 * chat, query, modify, api_action, bulk_action.
 */

const { buildPrompt } = require('../ai/promptBuilder');
const { invoke } = require('../ai/bedrockClient');
const { parseResponse } = require('../ai/responseParser');
const { validateSQL } = require('../guards/sqlGuard');
const { formatQueryResults } = require('../formatters/telegramFormatter');
const pool = require('../db/mysql');
const config = require('../config');

const pendingModifications = new Map();

async function handleMessage(bot, msg) {
  const chatId = msg.chat.id.toString();
  const text = msg.text;

  if (!text || text.startsWith('/')) return;

  try {
    const { systemPrompt, userMessage } = buildPrompt(text);
    const raw = await invoke(systemPrompt, userMessage);
    const parsed = parseResponse(raw);

    if (!parsed) {
      await bot.sendMessage(chatId, '🤔 No entendí tu mensaje. Intenta de otra forma o escribe "ayuda".');
      return;
    }

    switch (parsed.intent) {
      case 'chat':
        await handleChat(bot, chatId, parsed);
        break;
      case 'query':
        await handleQuery(bot, chatId, parsed);
        break;
      case 'modify':
        await handleModify(bot, chatId, parsed);
        break;
      case 'api_action':
        await handleApiAction(bot, chatId, parsed);
        break;
      case 'bulk_action':
        await handleApiAction(bot, chatId, parsed);
        break;
      default:
        await bot.sendMessage(chatId, '🤔 No entendí tu mensaje. Intenta de otra forma.');
    }
  } catch (err) {
    console.error(`[MessageHandler] Error for ${chatId}:`, err);
    if (err.message?.includes('credentials') || err.message?.includes('Bedrock')) {
      await bot.sendMessage(chatId, '⚠️ El servicio de IA no está disponible. Intenta de nuevo.');
    } else {
      await bot.sendMessage(chatId, '⚠️ Error de conexión. Intenta de nuevo en unos minutos.');
    }
  }
}

/**
 * Handle chat intent — send conversational message directly.
 */
async function handleChat(bot, chatId, parsed) {
  const message = parsed.message || '🤔 No tengo una respuesta para eso.';
  try {
    await bot.sendMessage(chatId, message, { parse_mode: 'Markdown' });
  } catch {
    // Markdown parse error — retry without formatting
    await bot.sendMessage(chatId, message.replace(/[*_`\[\]]/g, ''));
  }
}

/**
 * Handle query intent — validate SQL, execute, format results.
 */
async function handleQuery(bot, chatId, parsed) {
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

    // Telegram has a 4096 char limit — split if needed
    if (formatted.length > 4000) {
      const chunks = splitMessage(formatted, 4000);
      for (const chunk of chunks) {
        try {
          await bot.sendMessage(chatId, chunk, { parse_mode: 'Markdown' });
        } catch {
          await bot.sendMessage(chatId, chunk.replace(/[*_`\[\]]/g, ''));
        }
      }
    } else {
      try {
        await bot.sendMessage(chatId, formatted, { parse_mode: 'Markdown' });
      } catch {
        await bot.sendMessage(chatId, formatted.replace(/[*_`\[\]]/g, ''));
      }
    }
  } catch (err) {
    console.error(`[MessageHandler] Query error for ${chatId}:`, err);
    await bot.sendMessage(chatId, '⚠️ Error ejecutando la consulta. Intenta de nuevo.');
  }
}

/**
 * Handle modify intent — auth check, validate SQL, confirm before executing.
 */
async function handleModify(bot, chatId, parsed) {
  if (!isAuthorized(chatId)) {
    await bot.sendMessage(chatId, '🔒 No tienes permisos para modificar recetas.');
    return;
  }

  const validation = validateSQL(parsed, chatId);
  if (!validation.ok) {
    await bot.sendMessage(chatId, `⚠️ Operación no permitida: ${validation.reason}`);
    return;
  }

  pendingModifications.set(chatId, {
    sql: validation.sql,
    params: validation.params,
    explanation: parsed.explanation,
    timestamp: Date.now(),
  });

  const preview = `✏️ *Modificación propuesta:*\n\n${parsed.explanation || 'Sin descripción'}\n\n¿Confirmar?`;
  try {
    await bot.sendMessage(chatId, preview, {
      parse_mode: 'Markdown',
      reply_markup: {
        inline_keyboard: [[
          { text: '✅ Confirmar', callback_data: 'confirm_modify' },
          { text: '❌ Cancelar', callback_data: 'cancel_modify' },
        ]],
      },
    });
  } catch {
    await bot.sendMessage(chatId, preview.replace(/[*_`\[\]]/g, ''), {
      reply_markup: {
        inline_keyboard: [[
          { text: '✅ Confirmar', callback_data: 'confirm_modify' },
          { text: '❌ Cancelar', callback_data: 'cancel_modify' },
        ]],
      },
    });
  }
}

/**
 * Handle api_action / bulk_action — show plan and confirm.
 */
async function handleApiAction(bot, chatId, parsed) {
  if (!isAuthorized(chatId)) {
    await bot.sendMessage(chatId, '🔒 No tienes permisos para esta operación.');
    return;
  }

  const steps = parsed.steps || [];
  const stepsText = steps.length > 0
    ? steps.map((s, i) => `${i + 1}️⃣ ${s}`).join('\n')
    : '';

  const message = [
    `🔧 *${parsed.explanation || 'Acción propuesta'}*`,
    '',
    stepsText,
    '',
    '⚠️ Esta funcionalidad está en desarrollo. Por ahora puedo ayudarte con consultas y modificaciones de recetas directas.',
    '',
    '¿Quieres que haga algo más?',
  ].filter(Boolean).join('\n');

  try {
    await bot.sendMessage(chatId, message, { parse_mode: 'Markdown' });
  } catch {
    await bot.sendMessage(chatId, message.replace(/[*_`\[\]]/g, ''));
  }
}

/**
 * Check if a chat_id is authorized for modifications.
 */
function isAuthorized(chatId) {
  if (config.AUTHORIZED_CHAT_IDS.length === 0) {
    console.warn('[Auth] AUTHORIZED_CHAT_IDS empty — all modifications rejected.');
    return false;
  }
  return config.AUTHORIZED_CHAT_IDS.includes(chatId);
}

/**
 * Split a long message into chunks at line boundaries.
 */
function splitMessage(text, maxLen) {
  const chunks = [];
  let current = '';
  for (const line of text.split('\n')) {
    if ((current + '\n' + line).length > maxLen) {
      if (current) chunks.push(current);
      current = line;
    } else {
      current = current ? current + '\n' + line : line;
    }
  }
  if (current) chunks.push(current);
  return chunks;
}

function getPendingModifications() {
  return pendingModifications;
}

module.exports = { handleMessage, getPendingModifications };
