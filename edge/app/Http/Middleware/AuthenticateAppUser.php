<?php

namespace App\Http\Middleware;

use App\Models\CachedUser;
use App\Models\CustomerSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAppUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->resolveCloudUser($request)
            ?? $this->resolveCloudSyncUser($request)
            ?? $this->resolveSessionUser($request);

        if (! $user) {
            $message = $this->syncAuthAttempted($request)
                ? 'کاربر در ایستگاه ثبت نشده — چند دقیقه صبر کنید تا همگام‌سازی انجام شود.'
                : 'احراز هویت اپلیکیشن الزامی است.';

            return response()->json(['message' => $message], $this->syncAuthAttempted($request) ? 403 : 401);
        }

        $request->attributes->set('cached_user', $user);

        return $next($request);
    }

    private function resolveCloudUser(Request $request): ?CachedUser
    {
        $uuid = $request->header('X-SC-User-UUID');
        $token = $request->header('X-SC-Edge-Token');

        if (! $uuid || ! $token) {
            return null;
        }

        if (! $this->validCloudToken($uuid, $token)) {
            return null;
        }

        return CachedUser::where('cloud_user_uuid', $uuid)->first();
    }

    private function resolveCloudSyncUser(Request $request): ?CachedUser
    {
        if (! $this->syncAuthAttempted($request)) {
            return null;
        }

        $uuid = $request->header('X-SC-User-UUID');
        if (! $uuid) {
            return null;
        }

        return CachedUser::where('cloud_user_uuid', $uuid)->first();
    }

    private function syncAuthAttempted(Request $request): bool
    {
        $sync = $request->header('X-SC-Cloud-Sync');
        $expected = config('services.cloud.sync_token');

        return $sync && $expected && hash_equals($expected, $sync);
    }

    private function resolveSessionUser(Request $request): ?CachedUser
    {
        $bearer = $request->bearerToken();
        if (! $bearer) {
            return null;
        }

        $hash = hash('sha256', $bearer);
        $session = CustomerSession::where('token_hash', $hash)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        if (! $session) {
            return null;
        }

        $session->update(['last_used_at' => now()]);

        return CachedUser::where('uuid', $session->customer_uuid)->first();
    }

    private function validCloudToken(string $uuid, string $token): bool
    {
        $key = config('services.cloud.app_key') ?: config('app.key');
        $today = hash_hmac('sha256', $uuid.'|'.now()->format('Y-m-d'), $key);
        if (hash_equals($today, $token)) {
            return true;
        }
        $yesterday = hash_hmac('sha256', $uuid.'|'.now()->subDay()->format('Y-m-d'), $key);

        return hash_equals($yesterday, $token);
    }
}
