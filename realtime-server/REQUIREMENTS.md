# متطلبات السيرفر لمشروع realtime-server (Node.js + Socket.IO)

## نظام التشغيل
- Linux (Ubuntu 22.04+ او ما شابه)

## الحزم الاساسية
- Node.js 18+ و npm (مطابق لـ README)
- مدير عمليات (مستحسن): PM2
- اختياري: Redis (للتوسّع الافقي) مع ضبط `REDIS_URL` او `REDIS_HOST`
- عاكس (Reverse Proxy): Nginx لتوجيه `/socket/` الى المنفذ الداخلي

## اوامر التثبيت بعد رفع الكود
```bash
cd /var/www/realtime-server
npm install --production
```

## تشغيل الانتاج (مثال)
```bash
PORT=3000 SOCKET_PATH=/socket/ SOCKET_ALLOWED_ORIGINS=https://domain.com pm2 start server.js --name sanad-realtime
```

## ملاحظات
- تأكد من فتح المنفذ الداخلي للـ Nginx فقط (ليس عام).
- اذا فعّلت Redis وتريد تعدد السيرفرات، ثبّت Redis واضبط المتغيرات البيئية.
