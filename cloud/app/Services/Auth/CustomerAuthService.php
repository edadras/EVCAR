<?php

namespace App\Services\Auth;

use App\Enums\UserRole;
use App\Models\OtpVerification;
use App\Models\User;
use App\Models\Wallet;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CustomerAuthService
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

    public function findByPhone(string $phone): ?User
    {
        $normalized = PhoneNormalizer::normalize($phone);
        if ($normalized === '') {
            return null;
        }

        return User::query()
            ->where('role', UserRole::Customer)
            ->where(function ($q) use ($normalized) {
                $q->where('phone', $normalized)
                    ->orWhere('phone', 'like', '%'.substr($normalized, -10));
            })
            ->first();
    }

    /** @return array<string, mixed> */
    public function lookup(string $phone): array
    {
        $user = $this->findByPhone($phone);

        return [
            'registered' => $user !== null,
            'has_password' => $user !== null && $user->password !== null,
            'backend' => 'cloud',
            'phone' => PhoneNormalizer::normalize($phone),
        ];
    }

    /** @return array<string, mixed> */
    public function requestOtp(string $phone, string $purpose, ?string $ip = null): array
    {
        $normalized = PhoneNormalizer::normalize($phone);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        if (config('app.debug') && $this->testOtpCodes() !== []) {
            $code = $this->testOtpCodes()[0];
        }

        OtpVerification::create([
            'uuid' => (string) Str::uuid(),
            'phone' => $normalized,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'channel' => 'sms',
            'sent_at' => now(),
            'expires_at' => now()->addMinutes(5),
            'ip_address' => $ip,
        ]);

        $response = [
            'message' => 'کد تأیید ارسال شد.',
            'phone' => $normalized,
            'purpose' => $purpose,
            'expires_in' => 300,
            'backend' => 'cloud',
        ];

        // کد هرگز در production به کلاینت برگردانده نمی‌شود.
        if (! app()->isProduction() && (config('app.debug') || $this->testOtpCodes() !== [] || config('services.gsm.driver') === 'log')) {
            $response['debug_code'] = $code;
            $response['test_codes_hint'] = $this->testOtpCodes();
        }

        return $response;
    }

    /** @return array<string, mixed> */
    public function verifyOtp(string $phone, string $code, string $purpose = 'register'): array
    {
        $normalized = PhoneNormalizer::normalize($phone);

        if ($this->isTestOtpCode($code)) {
            $token = (string) Str::uuid();
            Cache::put($this->registrationCacheKey($token), [
                'phone' => $normalized,
                'purpose' => $purpose,
                'verified_at' => now()->toIso8601String(),
                'source' => 'cloud',
            ], now()->addMinutes(15));

            return [
                'message' => 'کد تأیید شد.',
                'purpose' => $purpose,
                'registration_token' => $token,
                'phone' => $normalized,
                'backend' => 'cloud',
            ];
        }

        $otp = OtpVerification::query()
            ->where('phone', $normalized)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $otp || ! Hash::check($code, $otp->code_hash)) {
            if ($otp) {
                $otp->increment('attempts');
            }
            throw new \RuntimeException('کد تأیید نامعتبر یا منقضی است.');
        }

        $otp->update(['verified_at' => now()]);

        $token = (string) Str::uuid();
        Cache::put($this->registrationCacheKey($token), [
            'phone' => $normalized,
            'purpose' => $purpose,
            'otp_uuid' => $otp->uuid,
            'verified_at' => now()->toIso8601String(),
            'source' => 'cloud',
        ], now()->addMinutes(15));

        return [
            'message' => 'کد تأیید شد.',
            'purpose' => $purpose,
            'registration_token' => $token,
            'phone' => $normalized,
            'backend' => 'cloud',
        ];
    }

    /** @return array<string, mixed> */
    public function register(
        string $phone,
        string $password,
        string $registrationToken,
        ?string $name = null,
        ?string $registrationProof = null,
        bool $startWebSession = true,
    ): array {
        $normalized = PhoneNormalizer::normalize($phone);
        $payload = Cache::get($this->registrationCacheKey($registrationToken));

        if (! is_array($payload)) {
            $payload = $this->validateEdgeRegistration($normalized, $registrationToken, $registrationProof);
        }

        if (($payload['phone'] ?? '') !== $normalized) {
            throw new \RuntimeException('توکن ثبت‌نام نامعتبر یا منقضی است. دوباره کد تأیید بگیرید.');
        }

        if ($this->findByPhone($normalized)) {
            throw new \RuntimeException('این شماره قبلاً ثبت‌نام کرده است.');
        }

        $displayName = $name ?: 'کاربر '.substr($normalized, -4);
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => $displayName,
            'email' => 'm'.$normalized.'@safircharge.local',
            'phone' => $normalized,
            'password' => $password,
            'role' => UserRole::Customer,
            'status' => 'active',
            'phone_verified_at' => now(),
        ]);

        Wallet::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'balance' => 0,
            'bonus_balance' => 0,
            'status' => 'active',
        ]);

        Cache::forget($this->registrationCacheKey($registrationToken));

        if ($startWebSession) {
            Auth::login($user, true);
        }

        return [
            'message' => 'ثبت‌نام موفق',
            'user' => ['uuid' => $user->uuid, 'name' => $user->name],
            'user_id' => $user->id,
        ];
    }

    /** @return array<string, mixed> */
    public function login(string $phone, string $password): array
    {
        $user = $this->findByPhone($phone);

        if (! $user || ! Auth::attempt(['email' => $user->email, 'password' => $password], true)) {
            throw new \RuntimeException('شماره موبایل یا رمز عبور اشتباه است.');
        }

        $user->update(['last_login_at' => now()]);

        return [
            'message' => 'ورود موفق',
            'user' => ['uuid' => $user->uuid, 'name' => $user->name],
        ];
    }

    /** @return array<string, mixed> */
    private function validateEdgeRegistration(string $phone, string $token, ?string $proof): array
    {
        if ($proof === null || $proof === '') {
            throw new \RuntimeException('توکن ثبت‌نام نامعتبر یا منقضی است. دوباره کد تأیید بگیرید.');
        }

        $key = config('app.key');
        $expected = hash_hmac('sha256', $phone.'|'.$token, $key);
        if (! hash_equals($expected, $proof)) {
            throw new \RuntimeException('تأیید لبه معتبر نیست.');
        }

        return [
            'phone' => $phone,
            'purpose' => 'register',
            'source' => 'edge',
            'verified_at' => now()->toIso8601String(),
        ];
    }

    private function registrationCacheKey(string $token): string
    {
        return 'sc_reg_token:'.$token;
    }
}
