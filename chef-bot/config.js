/**
 * Chef_Bot configuration — reads from environment variables with sensible defaults.
 */

const config = {
  // Telegram
  TELEGRAM_TOKEN: process.env.TELEGRAM_TOKEN || '',

  // Authorized chat IDs for modification commands (comma-separated in env)
  AUTHORIZED_CHAT_IDS: (process.env.AUTHORIZED_CHAT_IDS || '')
    .split(',')
    .map(id => id.trim())
    .filter(Boolean),

  // MySQL
  DB_HOST: process.env.DB_HOST || 'localhost',
  DB_PORT: parseInt(process.env.DB_PORT, 10) || 3306,
  DB_USER: process.env.DB_USER || 'root',
  DB_PASSWORD: process.env.DB_PASSWORD || '',
  DB_NAME: process.env.DB_NAME || 'mi3',

  // AWS Bedrock
  AWS_REGION: process.env.AWS_REGION || 'us-east-1',

  // Recipe API base URL (mi3-backend)
  API_BASE_URL: process.env.API_BASE_URL || 'https://api-mi3.laruta11.cl',
};

module.exports = config;
