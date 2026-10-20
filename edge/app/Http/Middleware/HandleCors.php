<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleCors
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->header('Origin');
        $allowed = config('cors.allowed_origins', ['*']);
        $allowOrigin = ($origin && (in_array('*', $allowed, true) || in_array($origin, $allowed, true)))
            ? ($allowed === ['*'] ? '*' : $origin)
            : ($allowed[0] ?? '*');

        if ($request->isMethod('OPTIONS')) {
            return response('', 204)
                ->header('Access-Control-Allow-Origin', $allowOrigin)
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Accept, X-Requested-With, X-SC-User-UUID, X-SC-Edge-Token, X-SC-Cloud-Sync, Authorization')
                ->header('Access-Control-Max-Age', '86400');
        }

        $response = $next($request);

        $response->headers->set('Access-Control-Allow-Origin', $allowOrigin);
        $response->headers->set('Vary', 'Origin');

        return $response;
    }
}
