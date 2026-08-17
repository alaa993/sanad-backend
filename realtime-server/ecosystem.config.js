module.exports = {
  apps: [
    {
      name: 'sanad-realtime',
      script: 'server.js',
      cwd: __dirname,
      instances: 1,
      exec_mode: 'fork',
      autorestart: true,
      max_memory_restart: '256M',
      env: {
        NODE_ENV: 'production',
        PORT: 3000,
        SOCKET_PATH: '/socket/',
        SOCKET_ALLOWED_ORIGINS: 'https://dashboard.sanadhub.cloud',
        AUTH_VERIFY_URL: process.env.AUTH_VERIFY_URL || 'https://dashboard.sanadhub.cloud/api/auth/me',
        REALTIME_SOCKET_TOKEN: process.env.REALTIME_SOCKET_TOKEN || '',
        REDIS_HOST: '127.0.0.1',
        REDIS_PORT: 6379,
        COMMUNITY_GLOBAL_EVENTS: 'true',
      },
    },
  ],
};
