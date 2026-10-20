<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateOcpi
{
    public function handle(Request $request, Closure $next): Response
    {
        $tokens = array_filter(array_map('trim', explode(',', (string) config('safircharge.ocpi.tokens', ''))));
        $header = (string) $request->header('Authorization', '');
        $token = str_starts_with($header, 'Token ') ? trim(substr($header, 6)) : '';

        // OCPI 2.2: توکن ممکن است Base64 شده باشد.
        $decoded = base64_decode($token, true);
        $candidates = array_filter([$token, $decoded !== false ? $decoded : null]);

        foreach ($tokens as $valid) {
            foreach ($candidates as $candidate) {
                if (hash_equals($valid, $candidate)) {
                    return $next($request);
                }
            }
        }

        return response()->json(['status_code' => 2001, 'status_message' => 'Invalid or missing token', 'timestamp' => now()->toIso8601ZuluString()], 401);
    }
}
