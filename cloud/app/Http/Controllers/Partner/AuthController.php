<?php

namespace App\Http\Controllers\Partner;

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
        return view('partner.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $login = $data['login'];
        $user = User::where('email', $login)
            ->orWhereHas('partner', fn ($q) => $q->where('code', $login))
            ->first();

        if (! $user || $user->role !== UserRole::Partner || ! Auth::attempt(['email' => $user->email, 'password' => $data['password']], $request->boolean('remember'))) {
            return back()->withErrors(['login' => 'اطلاعات ورود نامعتبر است.'])->onlyInput('login');
        }

        $request->session()->regenerate();

        return redirect()->route('partner.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('partner.login');
    }
}
