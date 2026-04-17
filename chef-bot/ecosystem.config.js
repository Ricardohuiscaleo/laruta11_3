/**
 * pm2 ecosystem config for Chef_Bot deployment.
 */
module.exports = {
  apps: [
    {
      name: 'chef-bot',
      script: 'index.js',
      cwd: '/root/laruta11_3/chef-bot',
      env: {
        NODE_ENV: 'production',
        TELEGRAM_TOKEN: '',
        AUTHORIZED_CHAT_IDS: '',
        DB_HOST: 'localhost',
        DB_PORT: 3306,
        DB_USER: '',
        DB_PASSWORD: '',
        DB_NAME: 'mi3',
        AWS_REGION: 'us-east-1',
        API_BASE_URL: 'https://api-mi3.laruta11.cl',
      },
      autorestart: true,
      max_restarts: 10,
      watch: false,
      restart_delay: 5000,
    },
  ],
};
