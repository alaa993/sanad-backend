# موقع سند (Laravel)

## الصفحات العامة

| المسار | الغرض |
|--------|--------|
| `/` | الصفحة الرئيسية |
| `/privacy` | سياسة الخصوصية (App Store) |
| `/terms` | شروط الاستخدام |
| `/contact` | تواصل معنا |
| `/delete-account` | حذف الحساب (App Store) |
| `/admin/login` | دخول المشرف |
| `/admin` | لوحة التحكم |

## الإعداد (.env)

```env
APP_URL=https://dashboard.sanadhub.cloud
SANAD_SUPPORT_EMAIL=support@sanad.app
SANAD_APP_STORE_URL=https://apps.apple.com/...
SANAD_PLAY_STORE_URL=https://play.google.com/...
```

## البذور

```bash
php artisan db:seed --class=SiteSettingsSeeder
php artisan db:seed --class=AdminSeeder
```

حساب الإدارة الافتراضي: `admin@sanad.local` / `Sanad@123`

## API مفيد للتطبيق

- `GET /api/bootstrap` — روابط الموقع والمتاجر
- `GET /api/v1/settings` — نصوص الخصوصية + روابط
- `DELETE /api/auth/account` — حذف الحساب (يتطلب توكن + كلمة مرور)

## App Store Connect

- Privacy Policy URL: `{APP_URL}/privacy`
- Account Deletion URL: `{APP_URL}/delete-account`
