# متطلبات السيرفر لمشروع httpdocs (Laravel)

## نظام التشغيل
- Linux (Ubuntu 22.04+ او ما شابه)

## الحزم الاساسية
- PHP 8.2+ (مطابق لـ composer.json)
- Composer 2.x
- Node.js 18+ و npm (لبناء الواجهة عبر Vite)
- خادم ويب: Nginx او Apache
- قاعدة بيانات: MySQL 8 / MariaDB 10.6+
- اختياري: Redis (للكاش/الصفوف اذا سيتم تفعيله)

## اضافات PHP المطلوبة
- bcmath
- ctype
- curl
- dom
- fileinfo
- filter
- hash
- json
- mbstring
- openssl
- pcre
- pdo
- pdo_mysql
- session
- tokenizer
- xml

## اوامر التثبيت بعد رفع الكود
```bash
cd /var/www/httpdocs
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
php artisan migrate --force
npm install
npm run build
```

## ملاحظات تشغيل
- تأكد من صلاحيات `storage/` و `bootstrap/cache/` للكتابة.
- اضف كرون لارافيل:
  `* * * * * php /var/www/httpdocs/artisan schedule:run >> /dev/null 2>&1`
