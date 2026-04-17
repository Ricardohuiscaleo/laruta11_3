/**
 * Chef_Bot entry point — Telegram long-polling bot for recipe management.
 * Connects handlers for messages and callback queries, manages graceful shutdown.
 */

const TelegramBot = require('node-telegram-bot-api');
const config = require('./config');
const { handleMessage } = require('./handlers/messageHandler');
const { handleCallback } = require('./handlers/callbackHandler');
const pool = require('./db/mysql');

// Validate required config
if (!config.TELEGRAM_TOKEN) {
  console.error('TELEGRAM_TOKEN is required. Set it in your environment.');
  process.exit(1);
}

// Create bot with long-polling
const bot = new TelegramBot(config.TELEGRAM_TOKEN, { polling: true });

// Register message handler
bot.on('message', (msg) => handleMessage(bot, msg));

// Register callback query handler (inline keyboard buttons)
bot.on('callback_query', (query) => handleCallback(bot, query));

// Startup info
console.log('Chef_Bot started.');
console.log(`  Authorized chat IDs: ${config.AUTHORIZED_CHAT_IDS.length ? config.AUTHORIZED_CHAT_IDS.join(', ') : '(none — modifications disabled)'}`);
console.log(`  API base URL: ${config.API_BASE_URL}`);
console.log(`  AWS region: ${config.AWS_REGION}`);

// Graceful shutdown
function shutdown(signal) {
  console.log(`\n${signal} received — shutting down Chef_Bot…`);
  bot.stopPolling();
  pool.end()
    .then(() => {
      console.log('DB pool closed. Goodbye.');
      process.exit(0);
    })
    .catch((err) => {
      console.error('Error closing DB pool:', err);
      process.exit(1);
    });
}

process.on('SIGINT', () => shutdown('SIGINT'));
process.on('SIGTERM', () => shutdown('SIGTERM'));
