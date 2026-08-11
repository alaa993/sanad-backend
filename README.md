# Sanad Backend

Laravel API (`httpdocs`) and Socket.IO realtime gateway (`realtime-server`) for the Sanad apps.

## Setup

### API
```bash
cd httpdocs
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

### Realtime
```bash
cd realtime-server
npm install
node server.js
```
