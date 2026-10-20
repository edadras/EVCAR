<?php

namespace App\Services\Notifications;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ارسال اعلان پوش با Firebase Cloud Messaging (HTTP v1).
 * با FCM_CREDENTIALS (مسیر فایل JSON حساب سرویس) فعال می‌شود؛ بدون آن فقط اعلان درون‌برنامه‌ای ثبت می‌شود.
 */
class FcmPushService
{
    public function enabled(): bool
    {
        return $this->credentials() !== null;
    }

    /** @param  array<string, scalar>  $data */
    public function sendToUser(User $user, string $title, string $body, array $data = []): int
    {
        if (! $this->enabled()) {
            return 0;
        }

        $sent = 0;
        foreach (DeviceToken::where('user_id', $user->id)->where('provider', 'fcm')->get() as $device) {
            $sent += $this->send($device, $title, $body, $data) ? 1 : 0;
        }

        return $sent;
    }

    /** @param  array<string, scalar>  $data */
    private function send(DeviceToken $device, string $title, string $body, array $data): bool
    {
        $creds = $this->credentials();
        $token = $this->accessToken($creds);
        if (! $token) {
            return false;
        }

        $response = Http::timeout(8)->withToken($token)->post(
            'https://fcm.googleapis.com/v1/projects/'.$creds['project_id'].'/messages:send',
            ['message' => [
                'token' => $device->token,
                'notification' => ['title' => $title, 'body' => $body],
                'data' => array_map('strval', $data),
                'android' => ['priority' => 'high', 'notification' => ['channel_id' => 'safircharge']],
                'apns' => ['payload' => ['aps' => ['sound' => 'default']]],
            ]],
        );

        if ($response->successful()) {
            return true;
        }

        // توکن باطل‌شده (حذف اپ یا خروج) پاک می‌شود.
        $status = $response->json('error.details.0.errorCode') ?? $response->json('error.status');
        if (in_array($status, ['UNREGISTERED', 'INVALID_ARGUMENT', 'NOT_FOUND'], true) || $response->status() === 404) {
            $device->delete();
        } else {
            Log::warning('FCM send failed', ['status' => $response->status(), 'body' => $response->body()]);
        }

        return false;
    }

    /** @return array{project_id:string,client_email:string,private_key:string,token_uri?:string}|null */
    private function credentials(): ?array
    {
        $path = (string) config('services.fcm.credentials', '');
        if ($path === '' || ! is_readable($path)) {
            return null;
        }
        $json = json_decode((string) file_get_contents($path), true);

        return is_array($json) && isset($json['project_id'], $json['client_email'], $json['private_key']) ? $json : null;
    }

    /** توکن OAuth2 با JWT امضاشدهٔ حساب سرویس (RS256)؛ ۵۰ دقیقه کش می‌شود. */
    private function accessToken(array $creds): ?string
    {
        return Cache::remember('fcm:access_token:'.md5($creds['client_email']), now()->addMinutes(50), function () use ($creds) {
            $now = time();
            $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
            $header = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims = $b64(json_encode([
                'iss' => $creds['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => $creds['token_uri'] ?? 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));
            if (! openssl_sign($header.'.'.$claims, $signature, $creds['private_key'], OPENSSL_ALGO_SHA256)) {
                return null;
            }
            $response = Http::asForm()->timeout(8)->post($creds['token_uri'] ?? 'https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $header.'.'.$claims.'.'.$b64($signature),
            ]);

            return $response->json('access_token');
        });
    }
}
