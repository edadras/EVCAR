<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->canAccessAdmin()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'دسترسی غیرمجاز.'], 403);
            }

            return redirect()->route('admin.login');
        }

        return $next($request);
    }
}
