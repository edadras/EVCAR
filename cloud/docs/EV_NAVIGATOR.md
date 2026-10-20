# مسیریاب برقی SafirCharge — طرح فنی، ارتفاع و ذخیره‌سازی داده

این سند معماری **حالت ابری (lite)** مسیریاب EV را توضیح می‌دهد: مسیر، ارتفاع، مصرف انرژی، و اینکه چه داده‌ای کجا ذخیره می‌شود.

---

## ۱. نمای کلی

```
┌─────────────┐     POST /app/api/navigator/analyze      ┌──────────────────────┐
│  اپ موبایل  │ ───────────────────────────────────────► │ NavigatorController  │
│ ev-navigator│     POST /app/api/navigator/preview      │                      │
└─────────────┘                                          └──────────┬───────────┘
                                                                    │
                                                                    ▼
                                                         ┌──────────────────────┐
                                                         │  EvNavigatorService  │
                                                         └──────────┬───────────┘
                    ┌───────────────────────────────────────────────┼────────────────────────────┐
                    ▼                    ▼                          ▼                            ▼
            ┌──────────────┐    ┌──────────────┐           ┌──────────────┐            ┌──────────────┐
            │ OSRM (مسیر)  │    │ OpenTopoData │           │ Open-Meteo   │            │  MySQL       │
            │ زنده / API   │    │ (ارتفاع)     │           │ (آب‌وهوا)    │            │  کاتالوگ +   │
            └──────────────┘    └──────────────┘           └──────────────┘           │  ایستگاه‌ها  │
                                                                                        └──────────────┘
```

**اصل طراحی:** مسیر، ارتفاع و آب‌وهوا **در لحظه درخواست** محاسبه می‌شوند و **در دیتابیس ذخیره نمی‌شوند**. فقط داده‌های مرجع (خودرو، ایستگاه) از DB خوانده می‌شوند.

---

## ۲. جریان تحلیل مسیر (`analyzeLite`)

### فاز ۱ — مسیر مستقیم و برنامه شارژ اولیه

1. دریافت ورودی: مبدا، مقصد، SOC شروع، SOC هدف، خودرو
2. `fetchOsrmRoute(origin → destination)` — هندسه جاده از **OSRM**
3. `smartSampleRoute()` — کاهش نقاط تا حداکثر ~۱۲۰ نقطه (الگوریتم RDP)
4. `analyzeGeometry()` روی مسیر مستقیم → تخمین نیاز به شارژ و ایستگاه‌های کاندید

### فاز ۲ — مسیر نهایی (در صورت نیاز)

5. اگر برنامه شارژ ایستگاه داشت → `fetchOsrmRoute(origin → via[] → destination)`
6. تحلیل مجدد هندسه روی مسیر نهایی (مستقیم یا عبوری از شارژر)

### چرا مسیر رفت ≠ برگشت؟

OSRM همیشه به **ترتیب مبدا → مقصد** مسیریابی می‌کند. با جابجایی مبدا/مقصد:
- هندسه مسیر متفاوت است
- ارتفاع سگمنت‌به‌سگمنت در **جهت حرکت** محاسبه می‌شود
- باد موافق/مخالف بر اساس **زاویه حرکت** هر سگمنت اعمال می‌شود

---

## ۳. منابع خارجی (زنده — بدون ذخیره)

| منبع | API | کاربرد | ذخیره در DB؟ |
|------|-----|--------|--------------|
| مسیر جاده | `router.project-osrm.org` | فاصله، زمان، polyline | خیر |
| ارتفاع | `api.opentopodata.org/v1/srtm30m` | مدل SRTM30m | خیر |
| آب‌وهوا | `api.open-meteo.com` | دما، باد، جهت باد | خیر |
| جستجوی آدرس (UI) | Nominatim OSM | فقط در مرورگر | خیر |

### ۳.۱ مسیر — OSRM

```
GET https://router.project-osrm.org/route/v1/driving/{lng},{lat};...;{lng},{lat}
    ?overview=full&geometries=geojson&steps=false
```

خروجی:
- `distance_m` — طول مسیر (متر)
- `duration_s` — زمان (ثانیه)
- `coordinates` — لیست `[lat, lng]` روی جاده

فایل: `cloud/app/Services/Navigator/EvNavigatorService.php` → `fetchOsrmRoute()`

### ۳.۲ ارتفاع — OpenTopoData

پس از نمونه‌برداری نقاط مسیر:

```
GET https://api.opentopodata.org/v1/srtm30m?locations=lat,lng|lat,lng|...
```

- هر batch حداکثر **۱۰۰ نقطه**
- بین batchها **۱.۱ ثانیه** تأخیر (محدودیت API)
- اگر API در دسترس نباشد: ارتفاع ثابت **۱۲۰۰ متر** (fallback) و پرچم `estimated: true`

فایل: `EvNavigatorService::fetchElevations()`

**پردازش ارتفاع در مصرف:**

| مرحله | توضیح |
|--------|--------|
| `capElevationDelta()` | تغییر ارتفاع هر سگمنت به حداکثر ±۳۰ متر (کاهش نویز DEM) |
| `gravitationalEnergyKwh()` | سربالایی = مصرف اضافه؛ سرازیری = بازیابی انرژی (بازیافت) |
| سقف سربالایی | حداکثر ۱۲٪ مصرف پایه همان سگمنت |

**نمودار ارتفاع در UI:** از `elevation_profile` در پاسخ API ساخته می‌شود (فقط در حافظه/JSON پاسخ).

### ۳.۳ آب‌وهوا — Open-Meteo

- نمونه در **۳ نقطه**: ابتدا، وسط، انتهای مسیر
- درون‌یابی خطی برای هر سگمنت
- میانگین دما برای ضریب دمای مسیر

فایل: `fetchWeatherAlongRoute()`, `fetchWeather()`

---

## ۴. مدل مصرف انرژی (یک منبع حقیقت)

```
مصرف سگمنت = مصرف_پایه_مسیر × فاصله + اصلاح_باد + اصلاح_گرانش
```

### ۴.۱ مصرف پایه خودرو

از جدول `ev_catalog_vehicles` (فیلد `range_real_max_km`):

```
consumption_base = (battery_kwh × 0.90) ÷ (range_real_max_km ÷ 100)
```

- **۹۰٪** = انرژی قابل استفاده از ۱۰۰٪ تا رزرو ۱۰٪
- برد WLTP فقط مرجع نمایشی است؛ در محاسبه استفاده نمی‌شود

فایل: `EvCatalogVehicle::resolvedConsumptionBase()`

### ۴.۲ ضرایب سطح مسیر

روی `consumption_base` (یک‌بار برای کل مسیر):

| عامل | شرط | ضریب تقریبی |
|------|------|-------------|
| سرعت | بالای ۱۰۰ km/h | +۰.۲٪ به ازای هر km/h اضافه |
| دما | زیر ۰°C | ×۱.۰۶ |
| دما | زیر ۸°C | ×۱.۰۴ |
| دما | بالای ۳۲°C | ×۱.۰۳ |

فایل: `EvVehicleCatalog::routeConsumption()`

### ۴.۳ اصلاح سطح سگمنت

| عامل | روش |
|------|-----|
| باد | headwind/tailwind نسبت به bearing سگمنت |
| شیب | انرژی پتانسیل جرم خودرو × بازیافت |

فایل: `EvNavigatorService::buildSegments()`

### ۴.۴ برد و رسیدن به مقصد

**هر دو از همان سگمنت‌ها:**

- `attachSocToSegments()` — کسر انرژی و SOC پس از هر سگمنت
- `range_km_on_start` — کیلومتر تا رسیدن به رزرو SOC روی **همان مسیر**
- `arrival_soc` — SOC در انتهای polyline

اگر `مسافت > برد` → `needs_charge = true` (ناسازگاری ریاضی وجود ندارد).

---

## ۵. ذخیره‌سازی داده — چه چیزی کجا است؟

### ۵.۱ دیتابیس MySQL (پایدار)

#### جدول `ev_catalog_vehicles`

کاتالوگ خودروهای برقی — **تنها منبع زنده مشخصات خودرو**.

| ستون | نوع | توضیح |
|------|-----|--------|
| `id` | bigint | کلید اصلی |
| `uuid` | uuid | شناسه یکتا |
| `slug` | string | شناسه سیستمی (`bmw_ix3`) |
| `name` | string | نام نمایشی فارسی/انگلیسی |
| `brand`, `model` | string | برند و مدل |
| `battery_kwh` | decimal | ظرفیت باتری قابل استفاده |
| `mass_kg` | int | وزن (گرانش) |
| `regen_efficiency` | decimal | بازیافت انرژی (۰–۱) |
| `cd`, `frontal_area_m2` | decimal | اختیاری — آیرودینامیک |
| `consumption_base` | decimal | محاسبه‌شده از برد واقعی |
| `range_wltp_km` | int | مرجع — فقط نمایش |
| `range_real_min_km` | int | برد واقعی عادی (از) |
| `range_real_max_km` | int | **مبنای محاسبه مصرف** |
| `range_worst_km` | int | بدترین حالت — مرجع |
| `consumption` | json | `{ "base": 12.1, "worst": 20 }` |
| `aliases` | json | نام‌های جایگزین برای تطبیق |
| `sort_order` | int | ترتیب در لیست |
| `is_active` | bool | فعال در مسیریاب |
| `deleted_at` | timestamp | soft delete |

Migrationها:
- `database/migrations/2026_06_13_100000_ev_catalog_vehicles.php`
- `database/migrations/2026_06_14_100000_ev_catalog_consumption_base.php`

مدیریت: پنل ادمین `/admin/ev-catalog`

#### جدول `vehicles` (خودروی کاربر)

| فیلد | کاربرد در مسیریاب |
|------|-------------------|
| `brand`, `model` | تطبیق با کاتالوگ (`EvVehicleCatalog::findByLabel`) |
| `battery_kwh` | override ظرفیت |
| `meta.consumption_kwh100` | override مصرف دستی کاربر |

#### جدول `stations` (+ chargers, connectors)

ایستگاه‌های شارژ SafirCharge — برای فیلتر در محدوده مسیر:

- bbox حدود ۰.۱۵ درجه از polyline
- فاصله حداکثر **۲۵ km** از نزدیک‌ترین نقطه مسیر
- فقط `status = active`

**نتیجه تحلیل مسیر در DB ذخیره نمی‌شود** (بدون تاریخچه trip).

---

### ۵.۲ فایل‌های seed (فقط نصب اولیه)

| فایل | نقش |
|------|-----|
| `database/seeders/data/ev_catalog_vehicles.php` | داده اولیه ۱۶+ مدل |
| `database/seeders/EvCatalogVehicleSeeder.php` | پر کردن DB اگر خالی باشد |
| `database/seeders/SyncEvCatalogFromSeedData.php` | همگام‌سازی/به‌روزرسانی از فایل seed |

```bash
php artisan db:seed --class=EvCatalogVehicleSeeder
php artisan db:seed --class=SyncEvCatalogFromSeedData
```

**Runtime از config یا فایل seed نمی‌خواند** — فقط از DB.

---

### ۵.۳ Config (تنظیمات — نه داده خودرو)

فایل: `cloud/config/safircharge.php` → بخش `ev_navigator`

| کلید | توضیح |
|------|--------|
| `api_url` | اگر پر باشد → پروکسی به سرور پیشرفته FastAPI |
| `timeout_seconds` | تایم‌اوت API خارجی |
| `popular_routes` | مسیرهای پیشنهادی UI (مبدا/مقصد ثابت) |

---

### ۵.۴ پاسخ API (موقت — فقط در JSON)

پس از `POST /app/api/navigator/analyze` ساختار اصلی:

```json
{
  "source": "lite",
  "route": {
    "distance_km": 438.4,
    "duration_min": 292,
    "direction": "مسیر به سمت جنوب",
    "mode": "direct | via_chargers",
    "polyline": [{ "lat": 35.68, "lng": 51.38 }, ...]
  },
  "summary": {
    "total_kwh": 57.2,
    "arrival_soc": 28.5,
    "range_km_on_start": 512,
    "consumption_per_100km": 13.1,
    "consumption_base_kwh100": 11.6,
    "consumption_multiplier": 1.04,
    "usable_kwh_on_start": 72,
    "reserve_soc": 10
  },
  "segments": [
    {
      "from_km": 0,
      "to_km": 12.4,
      "delta_h_m": -8.2,
      "elevation_m": 1180,
      "consumption_kwh": 1.62,
      "soc_before": 100,
      "soc_after": 98.1,
      "temp_c": 22
    }
  ],
  "elevation_profile": [
    { "km": 0, "elev_m": 1200 },
    { "km": 12.4, "elev_m": 1192 }
  ],
  "soc_profile": [
    { "km": 0, "soc": 100 },
    { "km": 438.4, "soc": 28.5 }
  ],
  "chargers": [...],
  "charge_plan": [...],
  "verdict": { "status": "ok | needs_charge | no_chargers", ... },
  "data_sources": [...]
}
```

این داده‌ها در سمت کلاینت (`ev-navigator.js`) برای نقشه و نمودار استفاده می‌شوند و **persist نمی‌شوند**.

---

## ۶. ساختار فایل‌های کد

```
cloud/
├── app/
│   ├── Http/Controllers/App/NavigatorController.php   # API + صفحه
│   ├── Http/Controllers/Admin/EvCatalogVehicleController.php
│   ├── Models/EvCatalogVehicle.php
│   ├── Services/Navigator/
│   │   ├── EvNavigatorService.php      # هسته مسیریاب
│   │   └── EvVehicleCatalog.php        # خواندن کاتالوگ DB
│   └── Support/Geo.php                 # haversine, bearing
├── config/safircharge.php              # popular_routes, api_url
├── database/
│   ├── migrations/...ev_catalog_vehicles...
│   └── seeders/data/ev_catalog_vehicles.php
├── docs/EV_NAVIGATOR.md                # همین سند
├── public/assets/mobile/ev-navigator.js
└── resources/views/app/navigator.blade.php
```

---

## ۷. API Endpoints

| Method | مسیر | کار |
|--------|------|-----|
| GET | `/app/navigator` | صفحه UI |
| POST | `/app/api/navigator/analyze` | تحلیل کامل |
| POST | `/app/api/navigator/preview` | پیش‌نمایش سریع مسیر |

ورودی نمونه `analyze`:

```json
{
  "origin": { "lat": 35.689, "lng": 51.389, "label": "تهران" },
  "destination": { "lat": 29.591, "lng": 52.583, "label": "شیراز" },
  "current_soc": 100,
  "target_soc": 10,
  "vehicle_preset": "bmw_ix3"
}
```

---

## ۸. نمونه‌برداری مسیر (RDP)

چرا نقاط کم می‌شوند؟
- OSRM هزاران نقطه برمی‌گرداند
- OpenTopoData محدودیت ۱۰۰ نقطه/درخواست دارد
- محاسبه سگمنت باید سریع باشد

الگوریتم: Ramer-Douglas-Peucker → اگر هنوز > ۱۲۰ نقطه → نمونه‌برداری یکنواخت

فایل: `smartSampleRoute()`, `rdp()`

---

## ۹. دیاگرام جریان ارتفاع و مصرف

```mermaid
flowchart TD
    A[OSRM polyline] --> B[smartSampleRoute ~120 نقطه]
    B --> C[OpenTopoData SRTM30m]
    B --> D[Open-Meteo × 3 نقطه]
    C --> E[elevations[]]
    D --> F[weather per segment]
    G[ev_catalog_vehicles DB] --> H[consumption_base]
    H --> I[routeConsumption: speed + temp]
    I --> J[buildSegments]
    E --> J
    F --> J
    J --> K[attachSocToSegments]
    K --> L[summary + elevation_profile]
    M[stations DB] --> N[stationsAlongRoute]
    K --> N
    N --> O[charge_plan + verdict]
```

---

## ۱۰. خلاصه — چه چیزی ذخیره می‌شود؟

| داده | ذخیره | محل |
|------|--------|-----|
| مشخصات خودرو (باتری، برد، مصرف) | ✅ بله | `ev_catalog_vehicles` |
| خودروی کاربر | ✅ بله | `vehicles` |
| ایستگاه‌های شارژ | ✅ بله | `stations` |
| polyline مسیر | ❌ خیر | فقط JSON پاسخ |
| ارتفاع نقاط | ❌ خیر | فقط JSON پاسخ |
| آب‌وهوا | ❌ خیر | فقط JSON پاسخ |
| سگمنت‌ها و SOC | ❌ خیر | فقط JSON پاسخ |
| تاریخچه سفر | ❌ خیر | پیاده‌سازی نشده |

---

## ۱۱. توسعه آینده (پیشنهادی)

- کش Redis برای elevation نقاط پرتکرار
- جدول `navigator_trips` برای ذخیره تاریخچه تحلیل
- سرور پیشرفته FastAPI (`EV_NAVIGATOR_API_URL`) به‌جای lite
- کالیبره مصرف از داده واقعی شارژ کاربران (telemetry)

---

*آخرین به‌روزرسانی: بر اساس کد `cloud/` — حالت lite مسیریاب برقی SafirCharge.*
