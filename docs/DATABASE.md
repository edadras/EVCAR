# SafirCharge — ساختار پایگاه داده

## اصول طراحی

- همهٔ entityهای اصلی **`uuid`** یکتا دارند (سازگاری Edge ↔ Cloud و API موبایل).
- **Cloud** = منبع حقیقت business؛ **Edge** = اجرای لوکال + کش + outbox سینک.
- **احراز مشتری:** OTP روی Edge با **GSM** (`otp_requests` + `gsm_message_logs`).
- **API موبایل (Android/iOS):** از **Cloud** با Sanctum (`personal_access_tokens` + `mobile_devices`).
- مشتری پس از تأیید OTP در ایستگاه، در `cached_users` ثبت و به Cloud سینک می‌شود؛ اپ موبایل توکن را از Cloud می‌گیرد.

---

## Cloud (`safircharge_cloud`)

### احراز هویت و API موبایل

| جدول | نقش |
|------|-----|
| `users` | همه نقش‌ها: customer, partner, admin, operator, ... |
| `personal_access_tokens` | Sanctum — وب‌اپ، اندروید، iOS |
| `mobile_devices` | ثبت دستگاه و push token |
| `otp_verifications` | OTP آنلاین (پشتیبان؛ اصلی روی Edge/GSM) |

### شرکا و سازمان

| جدول | نقش |
|------|-----|
| `partners` | مالکان/شرکای شارژر |
| `partner_contracts` | درصد سهم درآمد |
| `organizations` | حساب سازمانی |
| `organization_members` | رانندگان سازمان |

### شبکه شارژ

| جدول | نقش |
|------|-----|
| `stations` | ایستگاه‌ها |
| `chargers` | شارژر (OCPP charge_point_id) |
| `connectors` | کانکتورها |
| `edge_stations` | ثبت Edge + توکن API + heartbeat |

### مالی

| جدول | نقش |
|------|-----|
| `tariffs` / `tariff_rules` / `station_tariffs` | موتور تعرفه |
| `wallets` / `wallet_transactions` | کیف پول و دفترکل |
| `payments` | پرداخت درگاه/کیف پول |
| `invoices` / `invoice_items` | فاکتور |
| `settlements` / `settlement_items` | تسویه شرکا |

### دسترسی

| جدول | نقش |
|------|-----|
| `rfid_cards` | کارت RFID |
| `qr_tokens` | QR کانکتور |
| `vehicles` | خودرو مشتری |

### عملیات

| جدول | نقش |
|------|-----|
| `charging_sessions` | جلسات (از Edge سینک) |
| `session_meter_values` | نمونه meter |
| `reservations` | رزرو |
| `anpr_events` | پلاک‌خوانی (از Edge) |

### پشتیبانی و سامانه

| جدول | نقش |
|------|-----|
| `support_tickets` / `ticket_messages` | تیکت |
| `campaigns` | طرح/تخفیف |
| `user_notifications` | اعلان |
| `inventory_items` / `inventory_movements` | انبار |
| `repair_orders` | تعمیرات |
| `sync_inbox` / `sync_cursors` | دریافت از Edge |
| `audit_logs` / `system_settings` | ممیزی و تنظیمات |

---

## Edge (`safircharge_edge`)

### هویت ایستگاه و GSM

| جدول | نقش |
|------|-----|
| `station_identity` | شناسه Edge و اتصال Cloud |
| `gsm_modem_config` | تنظیمات مودم |
| `otp_requests` | درخواست OTP محلی |
| `gsm_message_logs` | لاگ پیامک ارسالی |
| `customer_sessions` | نشست مشتری روی WiFi محلی |

### کش (Pull از Cloud)

| جدول | نقش |
|------|-----|
| `cached_users` | کاربران (+ `is_local_only` قبل از سینک) |
| `cached_wallets` | موجودی کش‌شده |
| `cached_rfid_cards` | RFID |
| `cached_tariffs` | تعرفه |
| `cached_qr_tokens` | QR |
| `cached_vehicles` | خودرو |

### دستگاه و عملیات لوکال

| جدول | نقش |
|------|-----|
| `chargers` / `connectors` | OCPP محلی |
| `charging_sessions` | جلسه (منبع زمان وقوع) |
| `session_meter_values` | meter |
| `reservations` | رزرو محلی |
| `pending_transactions` | تراکنش در انتظار سینک |
| `anpr_detections` | تشخیص پلاک (Pelak lite) |

### سینک

| جدول | نقش |
|------|-----|
| `sync_outbox` | رویدادهای Push به Cloud |
| `sync_inbox` | رویدادهای Pull از Cloud |
| `sync_cursors` | موقعیت سینک |
| `personal_access_tokens` | توکن اپراتور/سرویس محلی |

### پرسنل ایستگاه

| جدول | نقش |
|------|-----|
| `users` | اپراتور محلی (role=operator) |

---

## جریان OTP مشتری (GSM)

```
مشتری → WiFi ایستگاه → Edge API
  → otp_requests (کد تولید)
  → gsm_message_logs (ارسال از مودم)
  → تأیید → cached_users (is_local_only=1)
  → sync_outbox (user.created)
  → Cloud sync_inbox → users
  → اپ موبایل → Cloud API + personal_access_tokens
```

---

## نقش‌های `users.role` (Cloud)

`super_admin` · `financial_manager` · `station_operator` · `support` · `corporate_manager` · `partner` · `customer`
