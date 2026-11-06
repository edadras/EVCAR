<?php

namespace App\Support;

/**
 * کلیدهای استاندارد ChangeConfiguration در OCPP 1.6 با نوع و توضیح فارسی.
 * کلیدهای اختصاصی سازنده که در این فهرست نیستند متن آزاد هستند.
 */
final class OcppConfigCatalog
{
    /** @var array<string, array{type:string, label:string, unit?:string, min?:int}> */
    public const KEYS = [
        'HeartbeatInterval' => ['type' => 'int', 'label' => 'فاصلهٔ Heartbeat (هم‌زمان‌سازی ساعت و تشخیص آنلاین بودن)', 'unit' => 'ثانیه', 'min' => 10],
        'MeterValueSampleInterval' => ['type' => 'int', 'label' => 'فاصلهٔ ارسال مقادیر کنتور در حین شارژ', 'unit' => 'ثانیه', 'min' => 0],
        'MeterValuesSampledData' => ['type' => 'csl', 'label' => 'کمیت‌های ارسالی در حین شارژ (انرژی، توان، ولتاژ، SoC…)'],
        'ClockAlignedDataInterval' => ['type' => 'int', 'label' => 'فاصلهٔ مقادیر کنتور هم‌تراز با ساعت (۰ = خاموش)', 'unit' => 'ثانیه', 'min' => 0],
        'MeterValuesAlignedData' => ['type' => 'csl', 'label' => 'کمیت‌های هم‌تراز با ساعت'],
        'StopTxnSampledData' => ['type' => 'csl', 'label' => 'کمیت‌های پیوست StopTransaction'],
        'StopTxnAlignedData' => ['type' => 'csl', 'label' => 'کمیت‌های هم‌تراز پیوست StopTransaction'],
        'ConnectionTimeOut' => ['type' => 'int', 'label' => 'مهلت وصل کردن کابل پس از شروع شارژ', 'unit' => 'ثانیه', 'min' => 0],
        'MinimumStatusDuration' => ['type' => 'int', 'label' => 'حداقل پایداری وضعیت قبل از گزارش', 'unit' => 'ثانیه', 'min' => 0],
        'WebSocketPingInterval' => ['type' => 'int', 'label' => 'فاصلهٔ Ping اتصال WebSocket (۰ = خاموش)', 'unit' => 'ثانیه', 'min' => 0],
        'ResetRetries' => ['type' => 'int', 'label' => 'تعداد تلاش ریست در صورت ناموفق بودن', 'min' => 0],
        'TransactionMessageAttempts' => ['type' => 'int', 'label' => 'تعداد تلاش ارسال پیام‌های تراکنش', 'min' => 0],
        'TransactionMessageRetryInterval' => ['type' => 'int', 'label' => 'فاصلهٔ تلاش مجدد پیام‌های تراکنش', 'unit' => 'ثانیه', 'min' => 0],
        'AuthorizeRemoteTxRequests' => ['type' => 'bool', 'label' => 'استعلام Authorize برای شروع از راه دور'],
        'LocalAuthorizeOffline' => ['type' => 'bool', 'label' => 'مجوز کارت‌های ذخیره‌شده در حالت آفلاین'],
        'LocalPreAuthorize' => ['type' => 'bool', 'label' => 'شروع فوری با کارت‌های ذخیره‌شده (بدون انتظار سرور)'],
        'AllowOfflineTxForUnknownId' => ['type' => 'bool', 'label' => 'اجازهٔ شارژ آفلاین برای کارت ناشناخته'],
        'AuthorizationCacheEnabled' => ['type' => 'bool', 'label' => 'کش مجوز کارت‌ها'],
        'LocalAuthListEnabled' => ['type' => 'bool', 'label' => 'فهرست محلی کارت‌ها فعال باشد'],
        'StopTransactionOnEVSideDisconnect' => ['type' => 'bool', 'label' => 'پایان شارژ با جدا شدن کابل از سمت خودرو'],
        'StopTransactionOnInvalidId' => ['type' => 'bool', 'label' => 'توقف شارژ اگر کارت بعداً نامعتبر شد'],
        'UnlockConnectorOnEVSideDisconnect' => ['type' => 'bool', 'label' => 'آزاد شدن قفل کانکتور با جدا شدن از خودرو'],
        'MaxEnergyOnInvalidId' => ['type' => 'int', 'label' => 'حداکثر انرژی برای کارت نامعتبر', 'unit' => 'Wh', 'min' => 0],
        'BlinkRepeat' => ['type' => 'int', 'label' => 'تعداد چشمک چراغ', 'min' => 0],
        'LightIntensity' => ['type' => 'int', 'label' => 'شدت نور', 'unit' => '%', 'min' => 0],
        'ConnectorPhaseRotation' => ['type' => 'csl', 'label' => 'ترتیب فاز کانکتورها'],
        'NumberOfConnectors' => ['type' => 'int', 'label' => 'تعداد کانکتورها'],
        'SupportedFeatureProfiles' => ['type' => 'csl', 'label' => 'پروفایل‌های OCPP پشتیبانی‌شده'],
        'GetConfigurationMaxKeys' => ['type' => 'int', 'label' => 'حداکثر کلید در هر GetConfiguration'],
        'LocalAuthListMaxLength' => ['type' => 'int', 'label' => 'ظرفیت فهرست محلی کارت‌ها'],
        'SendLocalListMaxLength' => ['type' => 'int', 'label' => 'حداکثر کارت در هر ارسال فهرست'],
        'ChargeProfileMaxStackLevel' => ['type' => 'int', 'label' => 'حداکثر سطح پروفایل شارژ'],
        'ChargingScheduleAllowedChargingRateUnit' => ['type' => 'csl', 'label' => 'واحدهای مجاز محدودیت توان (A/W)'],
        'ChargingScheduleMaxPeriods' => ['type' => 'int', 'label' => 'حداکثر دوره در برنامهٔ شارژ'],
        'MaxChargingProfilesInstalled' => ['type' => 'int', 'label' => 'حداکثر پروفایل شارژ نصب‌شده'],
        'ReserveConnectorZeroSupported' => ['type' => 'bool', 'label' => 'پشتیبانی رزرو کل شارژر'],
    ];

    /** کلیدهایی که تغییر اشتباهشان ارتباط دستگاه با سرور را قطع می‌کند. */
    public static function risky(string $key): bool
    {
        return (bool) preg_match('/url|server|host|domain|address|\bip\b|ip$|port|apn|ssid|wifi|password|authorizationkey|security|cpid|chargeboxid|chargepointid/i', $key);
    }

    /** @return array{type:string,label:?string,unit:?string,min:?int} */
    public static function describe(string $key, mixed $value = null): array
    {
        $known = self::KEYS[$key] ?? null;
        $type = $known['type'] ?? (in_array(strtolower((string) $value), ['true', 'false'], true) ? 'bool' : 'text');

        return ['type' => $type, 'label' => $known['label'] ?? null, 'unit' => $known['unit'] ?? null, 'min' => $known['min'] ?? null];
    }
}
