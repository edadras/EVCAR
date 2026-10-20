<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guest-only gate for a panel's login routes: a user already signed in to *this* panel is
 * sent to its dashboard; anyone signed in to a different panel still sees the login form
 * so they can switch accounts (e.g. customer → admin).
 */
class RedirectIfPanelUser
{
    public function handle(Request $request, Closure $next, string $panel): Response
    {
        $user = $request->user();

        if ($user instanceof User && $this->belongsTo($user, $panel)) {
            return redirect()->route(match ($panel) {
                'admin' => 'admin.dashboard',
                'partner' => 'partner.dashboard',
                default => 'app.home',
            });
        }

        return $next($request);
    }

    private function belongsTo(User $user, string $panel): bool
    {
        return match ($panel) {
            'admin' => $user->isStaff(),
            'partner' => $user->role === UserRole::Partner,
            'app' => $user->role === UserRole::Customer,
            default => false,
        };
    }
}
