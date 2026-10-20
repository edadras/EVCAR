<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStationOperator
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->isPanelOperator()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'دسترسی اپراتور ایستگاه لازم است.'], 403);
            }

            return redirect()->route('panel.login');
        }

        return $next($request);
    }
}
