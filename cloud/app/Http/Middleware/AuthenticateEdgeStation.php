<?php

namespace App\Http\Middleware;

use App\Models\EdgeStation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateEdgeStation
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if (! $token) {
            return response()->json(['message' => 'توکن ایستگاه ارسال نشده است.'], 401);
        }

        $edge = EdgeStation::findByApiToken($token);
        if (! $edge) {
            return response()->json(['message' => 'توکن ایستگاه نامعتبر است.'], 401);
        }

        $request->attributes->set('edge_station', $edge);

        return $next($request);
    }
}
