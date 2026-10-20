<?php

namespace App\Services\Auth;

use App\Models\CachedUser;
use App\Models\CustomerSession;
use App\Models\GsmMessageLog;
use App\Models\OtpRequest;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OtpService
{
    /** @return list<string> */
    public function testOtpCodes(): array
    {
        return array_values(array_filter(array_map(
            'trim',
            explode(',', (string) config('safircharge.auth.test_otp_codes', ''))
        )));
    }

    public function isTestOtpCode(string $code): bool
    {
        return ! app()->isProduction() && in_array($code, $this->testOtpCodes(), true);
    }

    /** @return array<string, mixed> */
    public function lookup(string $phone): array
    {
        $normalized = PhoneNormalizer::normalize($phone);
        $cached = CachedUser::where('phone', $normalized)->first();

        return [
            'registered' => $cached !== null,
            'has_password' => $cached !== null && ! empty($cached->cloud_user_uuid),
            'is_local_only' => (bool) ($cached?->is_local_only),
            'backend' => 'edge',
            'phone' => $normalized,
        ];
    }

    public function request(string $phone, string $purpose, ?string $ip = null, ?string $ua = null): array
    {
        $normalized = PhoneNormalizer::normalize($phone);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        if (config('app.debug') && $this->testOtpCodes() !== []) {
            $code = $this->testOtpCodes()[0];
        }

        $otp = OtpRequest::create([
            'phone' => $normalized,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'status' => 'sent',
            'sent_at' => now(),
            'expires_at' => now()->addMinutes(5),
            'ip_address' => $ip,
            'user_agent' => $ua,
        ]);

        GsmMessageLog::create([
            'uuid' => (string) Str::uuid(),
            'otp_request_id' => $otp->id,
            'phone' => $normalized,
            'message_body' => "کد SafirCharge: {$code}",
            'status' => config('services.gsm.driver') === 'log' ? 'sent' : 'queued',
            'queued_at' => now(),
            'sent_at' => config('services.gsm.driver') === 'log' ? now() : null,
            'meta' => ['driver' => config('services.gsm.driver')],
        ]);

        $response = [
            'message' => 'کد تأیید ارسال شد.',
            'phone' => $normalized,
            'purpose' => $purpose,
            'expires_in' => 300,
            'status' => 'sent',
            'backend' => 'edge',
        ];

        // کد هرگز در production به کلاینت برگردانده نمی‌شود.
        if (! app()->isProduction() && (config('app.debug') || $this->testOtpCodes() !== [] || config('services.gsm.driver') === 'log')) {
            $response['debug_code'] = $code;
            $response['test_codes_hint'] = $this->testOtpCodes();
        }

        return $response;
    }

    public function verify(string $phone, string $code, ?string $ip = null): array
    {
        $normalized = PhoneNormalizer::normalize($phone);
        $otp = null;

        if (! $this->isTestOtpCode($code)) {
            $otp = OtpRequest::where('phone', $normalized)
                ->where('status', 'sent')
                ->where('expires_at', '>', now())
                ->latest('id')
                ->first();

            if (! $otp || ! Hash::check($code, $otp->code_hash)) {
                if ($otp) {
                    $otp->increment('attempts');
                    if ($otp->attempts >= $otp->max_attempts) {
                        $otp->update(['status' => 'failed']);
                    }
                }
                throw new \RuntimeException('کد تأیید نامعتبر یا منقضی است.');
            }
        } else {
            $otp = OtpRequest::where('phone', $normalized)
                ->where('status', 'sent')
                ->latest('id')
                ->first();
        }

        $purpose = $otp?->purpose ?? 'register';

        if ($purpose === 'register') {
            return $this->verifyForRegister($normalized, $otp, $ip);
        }

        return $this->verifyForLogin($normalized, $otp, $ip);
    }

    /** @return array<string, mixed> */
    private function verifyForRegister(string $normalized, ?OtpRequest $otp, ?string $ip): array
    {
        $token = $otp?->uuid ?? (string) Str::uuid();
        $otp?->update([
            'status' => 'verified',
            'verified_at' => now(),
        ]);

        $key = config('services.cloud.app_key', config('app.key'));

        return [
            'message' => 'کد تأیید شد.',
            'purpose' => 'register',
            'registration_token' => $token,
            'registration_proof' => hash_hmac('sha256', $normalized.'|'.$token, $key),
            'phone' => $normalized,
            'backend' => 'edge',
        ];
    }

    /** @return array<string, mixed> */
    private function verifyForLogin(string $normalized, ?OtpRequest $otp, ?string $ip): array
    {
        $cached = CachedUser::where('phone', $normalized)->first();
        if (! $cached) {
            $cached = CachedUser::create([
                'uuid' => (string) Str::uuid(),
                'phone' => $normalized,
                'name' => 'کاربر '.$normalized,
                'status' => 'active',
                'is_local_only' => true,
                'pending_sync' => true,
            ]);
        }

        $plainToken = Str::random(64);
        CustomerSession::create([
            'customer_uuid' => $cached->uuid,
            'token_hash' => hash('sha256', $plainToken),
            'platform' => 'web',
            'ip_address' => $ip,
            'last_used_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);

        $otp?->update([
            'status' => 'verified',
            'verified_at' => now(),
            'customer_uuid' => $cached->uuid,
        ]);

        return [
            'message' => 'ورود موفق',
            'purpose' => 'login',
            'edge_session_token' => $plainToken,
            'customer_uuid' => $cached->uuid,
            'cloud_user_uuid' => $cached->cloud_user_uuid,
            'name' => $cached->name,
            'expires_at' => now()->addDays(7)->toIso8601String(),
            'backend' => 'edge',
        ];
    }
}
