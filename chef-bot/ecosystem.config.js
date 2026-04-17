/**
 * pm2 ecosystem config for Chef_Bot deployment.
 *
 * SETUP: On the VPS, copy this file and fill in the real values:
 *   cp ecosystem.config.js ecosystem.local.js
 *   # Edit ecosystem.local.js with real TELEGRAM_TOKEN and DB_PASSWORD
 *   pm2 start ecosystem.local.js
 *
 * Or set env vars directly:
 *   export TELEGRAM_TOKEN="your-token-here"
 *   export DB_PASSWORD="your-password-here"
 *   pm2 start ecosystem.config.js
 */
module.exports = {
  apps: [
    {
      name: 'chef-bot',
      script: 'index.js',
      cwd: '/root/laruta11_3/chef-bot',
      env: {
        NODE_ENV: 'production',
        TELEGRAM_TOKEN: process.env.TELEGRAM_TOKEN || '',
        AUTHORIZED_CHAT_IDS: process.env.AUTHORIZED_CHAT_IDS || '8104543914',
        DB_HOST: process.env.DB_HOST || 'localhost',
        DB_PORT: process.env.DB_PORT || 3306,
        DB_USER: process.env.DB_USER || 'laruta11',
        DB_PASSWORD: process.env.DB_PASSWORD || '',
        DB_NAME: process.env.DB_NAME || 'mi3',
        AWS_REGION: process.env.AWS_REGION || 'us-east-1',
        API_BASE_URL: process.env.API_BASE_URL || 'https://api-mi3.laruta11.cl',
      },
      autorestart: true,
      max_restarts: 10,
      watch: false,
      restart_delay: 5000,
    },
  ],
};
