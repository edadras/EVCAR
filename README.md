# SafirCharge

سامانهٔ مدیریت شارژ خودرو برقی — دو پروژهٔ Laravel مستقل:

| پوشه | نقش | URL پیش‌فرض |
|------|-----|-------------|
| `cloud/` | پلتفرم مرکزی (ادمین، وب‌اپ، شرکا) | http://localhost:8180 |
| `edge/` | کنترلر ایستگاه (OCPP، ANPR، آفلاین) | http://localhost:8182 |

## پیش‌نیاز

- Docker Desktop (یا Docker Engine + Compose)

PHP و Composer روی سیستم **لازم نیست** — همه‌چیز داخل کانتینر اجرا می‌شود.

## اجرای سریع

```powershell
cd C:\Project\SafirCharge
docker compose up -d --build
```

اولین build ممکن است چند دقیقه طول بکشد.

## بررسی سلامت

```powershell
curl http://localhost:8180/health
curl http://localhost:8182/health
```

## سرویس‌ها

| سرویس | پورت |
|--------|------|
| Cloud HTTP | 8180 |
| Edge HTTP | 8182 |
| Cloud MySQL | 3317 |
| Edge MySQL | 3318 |
| Cloud Redis | 6380 |
| Edge Redis | 6381 |

## دستورات مفید

```powershell
# لاگ همه سرویس‌ها
docker compose logs -f

# فقط Cloud
docker compose logs -f cloud-app cloud-nginx

# migrate دستی
docker compose exec cloud-app php artisan migrate
docker compose exec edge-app php artisan migrate

# توقف
docker compose down

# توقف + حذف volume دیتابیس
docker compose down -v
```

## مستندات

- `Detail.txt` — معماری کسب‌وکار
- `بررسی-پروژه.md` — مرجع فنی و یکپارچه‌سازی
- `docs/DATABASE.md` — ساختار کامل جداول Cloud و Edge
- `Template/` — قالب UI استاتیک

## پایگاه داده

```powershell
docker compose exec cloud-app php artisan migrate
docker compose exec edge-app php artisan migrate
docker compose exec cloud-app php artisan db:seed
docker compose exec edge-app php artisan db:seed
```

## همگام‌سازی UI با قالب `Template/`

پس از تغییر HTML قالب:

```powershell
docker run --rm -v ${PWD}:/work -w /work/cloud php:8.4-cli php scripts/convert-template.php
docker run --rm -v ${PWD}:/work -w /work/cloud php:8.4-cli php scripts/patch-admin-dynamic.php
Copy-Item -Force Template/assets/app.css cloud/public/assets/admin/app.css
Copy-Item -Force Template/assets/app.js cloud/public/assets/admin/app.js
docker compose exec cloud-app php artisan view:clear
```

## پنل‌ها (فاز ۱)

صفحهٔ انتخاب: http://localhost:8180/

| پنل | URL | کاربر نمونه |
|-----|-----|-------------|
| ادمین | http://localhost:8180/admin/login | `admin@safircharge.ir` / `Admin@123456` |
| شرکا | http://localhost:8180/partner/login | `partner@safircharge.ir` / `Partner@123456` |
| وب‌اپ موبایل | http://localhost:8180/app/login | `user1@demo.ir` / `Demo@123456` |

## API (فاز ۱)

| Endpoint | توضیح |
|----------|--------|
| `GET /api/v1/health` | سلامت Cloud |
| `GET /api/v1/stations` | لیست ایستگاه‌ها (موبایل) |
| `POST /api/v1/auth/login` | ورود + توکن Sanctum |
| `GET http://localhost:8182/api/v1/health` | سلامت Edge |
| `GET /api/v1/station/config` | تنظیمات ایستگاه Edge |

فازبندی کامل: `docs/فازبندی.md`

## خطای `tempnam()` در Docker (ویندوز)

اگر پنل خطای مربوط به `tempnam()` داد، دسترسی `storage` را درست کنید:

```powershell
docker compose exec -u root cloud-app sh -c "chown -R www-data:www-data storage bootstrap/cache && chmod -R ug+rwx storage bootstrap/cache"
docker compose restart cloud-app
```

(همین کار را برای `edge-app` در صورت نیاز تکرار کنید.)

## پروژه‌های مرجع خارجی

- شارژر (OCPP): `C:\Project\Charger`
- پلاک‌خوان: `C:\Project\Pelak\...\lite`
