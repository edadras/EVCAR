<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Support\Persian;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserController extends Controller
{
    public function create(): View
    {
        return view('admin.users.create', [
            'organizations' => Organization::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'phone' => Persian::toAsciiDigits(trim((string) $request->input('phone', ''))),
            'name' => trim((string) $request->input('name', '')),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'organization_id' => ['nullable', 'exists:organizations,id'],
            'initial_balance' => ['nullable', 'numeric', 'min:0'],
        ]);

        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'password' => $data['password'],
            'role' => UserRole::Customer,
            'status' => 'active',
            'organization_id' => $data['organization_id'] ?? null,
            'email_verified_at' => $data['email'] ? now() : null,
            'phone_verified_at' => now(),
        ]);

        $balance = (float) ($data['initial_balance'] ?? 0);
        if ($balance > 0) {
            $wallet = Wallet::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'balance' => $balance * 10,
                'currency' => 'IRR',
                'status' => 'active',
            ]);
            WalletTransaction::create([
                'uuid' => (string) Str::uuid(),
                'wallet_id' => $wallet->id,
                'type' => 'credit',
                'amount' => $balance * 10,
                'balance_after' => $balance * 10,
                'description' => 'موجودی اولیه هنگام ثبت کاربر',
                'occurred_at' => now(),
            ]);
        }

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'کاربر با موفقیت ایجاد شد.');
    }
}
