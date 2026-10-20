<?php

namespace App\Http\Controllers\App;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('app.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $phone = preg_replace('/\D+/', '', $data['phone']);
        if (str_starts_with($phone, '98')) {
            $phone = '0'.substr($phone, 2);
        }

        $user = User::where('role', UserRole::Customer)
            ->where(function ($q) use ($phone, $data) {
                $q->where('phone', 'like', '%'.substr($phone, -10))
                    ->orWhere('email', $data['phone']);
            })
            ->first();

        if (! $user || ! Auth::attempt(['email' => $user->email, 'password' => $data['password']], true)) {
            return back()->withErrors(['phone' => 'شماره موبایل یا رمز عبور اشتباه است.'])->onlyInput('phone');
        }

        $request->session()->regenerate();

        return redirect()->route('app.home');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('app.login');
    }
}
