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
| Edge OCPP (WebSocket شارژرها) | 9000 |
| Edge2 OCPP | 9001 |

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

## اتصال شارژرهای واقعی (OCPP 1.6J)

هر Edge یک **سرور مرکزی OCPP 1.6J داخلی** دارد (`php artisan ocpp:serve`) که در Docker به‌صورت سرویس `edge-ocpp` اجرا می‌شود.

| مورد | مقدار |
|------|-------|
| آدرس شارژر (Central System URL) | `ws://<IP-ایستگاه>:9000/ocpp/<ChargePointId>` یا از طریق nginx: `ws://<IP-ایستگاه>:8182/ocpp/<ChargePointId>` |
| زیرپروتکل | `ocpp1.6` |
| ChargePointId | باید دقیقاً با «شناسه OCPP» شارژر در پنل ادمین ابر یکی باشد (پس از سینک به Edge می‌رسد) |
| رمز (اختیاری) | `meta.ocpp_password` شارژر → Basic Auth (Security Profile 1) |
| TLS (wss) | پشت nginx/reverse proxy با گواهی |

جریان کامل:

1. شارژر وصل می‌شود ← `BootNotification` پذیرفته می‌شود ← وضعیت کانکتورها با `StatusNotification` زنده می‌شود (و به ابر سینک می‌شود).
2. کاربر از اپ (محلی یا آنلاین) شروع می‌کند ← Edge دستور `RemoteStartTransaction` می‌فرستد ← شارژر `StartTransaction` می‌دهد.
3. انرژی و هزینه از `MeterValues` خود شارژر محاسبه می‌شود؛ اگر موجودی تمام شود Edge خودکار `RemoteStopTransaction` می‌فرستد.
4. توقف ← `StopTransaction` با مقدار نهایی کنتور ← برداشت یک‌باره از کیف پول ← سینک به ابر.
5. کارت RFID روی خود شارژر (حتی بدون اینترنت) با `Authorize`/`StartTransaction` از کش محلی تأیید می‌شود.

اپ آنلاین (ابر) هرگز مستقیم به شارژر وصل نمی‌شود: دستور `remote_start`/`remote_stop` برای Edge صف می‌شود (Edge هر ۵ ثانیه می‌خواند). اگر Edge ظرف ۳ دقیقه پاسخ ندهد، درخواست بدون هزینه بسته می‌شود.

متغیرهای مهم Edge (`edge/.env.example`): `OCPP_DRIVER` (`builtin` | `gateway` | `simulator`)، `OCPP_LISTEN_PORT`، `OCPP_ALLOW_UNKNOWN_CHARGERS`، `OCPP_START_TIMEOUT_MINUTES`، `SC_MIN_WALLET_BALANCE`.

### تست بدون سخت‌افزار

```powershell
# شبیه‌ساز شارژر واقعی OCPP (از همان پروتکل WebSocket استفاده می‌کند)
docker compose exec edge-app php artisan ocpp:simulate-charger CP-1001 --url=ws://sc_edge_ocpp:9000/ocpp --connectors=2 --power=50
# کارت RFID روی دستگاه:
docker compose exec edge-app php artisan ocpp:simulate-charger CP-1002 --url=ws://sc_edge_ocpp:9000/ocpp --rfid=RFID-DEMO-002
```

### تست‌های خودکار

```powershell
docker compose exec edge-app php artisan test
docker compose exec cloud-app php artisan test
```

لاگ خام پیام‌های OCPP برای عیب‌یابی در جدول `ocpp_message_logs` ذخیره می‌شود.

> نکته امنیتی: در `APP_ENV=production` کدهای OTP تستی غیرفعال‌اند و کد OTP هرگز در پاسخ API برنمی‌گردد. ارسال واقعی پیامک باید با درایور GSM/SMS واقعی تنظیم شود.

## ایستگاه بدون کنترلر محلی (اتصال مستقیم به سرور اصلی)

اگر ایستگاهی Edge نداشته باشد، شارژرهایش مستقیم به سرور اصلی وصل می‌شوند (سرویس `cloud-ocpp`):

`ws://<سرور>:9100/ocpp/<ChargePointId>` یا پشت nginx: `ws://<سرور>:8180/ocpp/<ChargePointId>` — برای wss نمونهٔ `docker/nginx/tls/ocpp-tls.conf.example`.

نحوهٔ اتصال هر شارژر در فرم «افزودن شارژر» ادمین قابل انتخاب است: خودکار (پیش‌فرض: اگر ایستگاه Edge دارد از طریق Edge، وگرنه مستقیم) / مستقیم / از طریق Edge. همهٔ امکانات (شروع/توقف از اپ، RFID روی دستگاه، صورتحساب از کنتور، دستورهای راه دور ادمین، رزرو، Smart Charging) در هر دو حالت کار می‌کنند.

```powershell
# شبیه‌ساز شارژر متصل مستقیم به ابر
docker compose exec cloud-app php artisan ocpp:simulate-charger <CP-ID> --url=ws://sc_cloud_ocpp:9100/ocpp
```

## امکانات عملیاتی

| امکان | محل تنظیم / استفاده |
|-------|---------------------|
| Smart Charging (تقسیم سقف توان انشعاب) | ویرایش ایستگاه ← «سقف توان انشعاب (kW)» |
| رزرو واقعی روی شارژر (ReserveNow) | خودکار ۲ دقیقه قبل از شروع رزرو (`reservations:dispatch`) |
| تعرفهٔ اوج/کم‌باری، هزینهٔ اشغال، تخفیف سازمانی | فرم تعرفه در ادمین |
| هشدارهای عملیاتی (قطع شارژر، خطای کانکتور، قطع Edge) | ادمین ← هشدارهای عملیاتی |
| تحلیل، پیش‌بینی شلوغی، کاندید سرویس پیشگیرانه، خروجی CSV (جلسات، درآمد، مالیات، تسویه) | ادمین ← تحلیل، پیش‌بینی و خروجی |
| تسویهٔ خودکار ماهانهٔ شرکا | `settlements:generate` (روز اول هر ماه) |
| رومینگ OCPI 2.2.1 (Locations/Tariffs/Sessions) | `/api/ocpi/versions` با هدر `Authorization: Token …` (`OCPI_TOKENS`) |
| نصب وب‌اپ روی گوشی (PWA) | «افزودن به صفحهٔ اصلی» در مرورگر (نیازمند HTTPS) |
| پشتیبان روزانهٔ دیتابیس Edge | `edge:backup` — در `edge/storage/app/backups` |

## اپ موبایل مشتری (Flutter — اندروید و iOS)

پوشهٔ [`mobile/`](mobile/README.md) نسخهٔ بومی همان وب‌اپ مشتری است (همهٔ صفحات، همان طراحی RTL و وزیرمتن).
اپ با توکن Sanctum به API ‏`/api/v1/mobile/*` سرور ابری وصل می‌شود؛ اکشن‌ها به همان کنترلرهای وب‌اپ می‌روند
تا منطق شارژ، کیف پول، رزرو و … بین وب و اپ یکسان باشد.

```bash
cd mobile && flutter pub get
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8180   # شبیه‌ساز اندروید + Docker محلی
flutter build apk --release --dart-define=API_BASE_URL=https://<سرور>
```

## پروژه‌های مرجع خارجی

- شارژر (OCPP): `C:\Project\Charger` — اختیاری؛ با `OCPP_DRIVER=gateway`. پیش‌فرض، سرور OCPP داخلی Edge است.
- پلاک‌خوان: `C:\Project\Pelak\...\lite`
