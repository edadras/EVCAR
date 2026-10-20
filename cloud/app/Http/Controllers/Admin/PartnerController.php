<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PartnerController extends Controller
{
    public function create(): View
    {
        return view('admin.partners.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:32', 'unique:partners,code'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'city' => ['nullable', 'string', 'max:64'],
            'province' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:500'],
            'bank_iban' => ['nullable', 'string', 'max:34'],
            'national_id' => ['nullable', 'string', 'max:20'],
            'revenue_share_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'platform_fee_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'settlement_cycle' => ['nullable', 'in:weekly,monthly,quarterly'],
        ]);

        if (empty($data['code'])) {
            $data['code'] = 'PTR-'.Str::upper(Str::random(4));
        }

        Partner::create([
            'uuid' => (string) Str::uuid(),
            'name' => $data['name'],
            'code' => $data['code'],
            'contact_phone' => $data['contact_phone'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,
            'city' => $data['city'] ?? null,
            'province' => $data['province'] ?? null,
            'address' => $data['address'] ?? null,
            'bank_iban' => $data['bank_iban'] ?? null,
            'national_id' => $data['national_id'] ?? null,
            'revenue_share_percent' => $data['revenue_share_percent'],
            'platform_fee_percent' => $data['platform_fee_percent'] ?? 10,
            'settlement_cycle' => $data['settlement_cycle'] ?? 'monthly',
            'status' => 'active',
        ]);

        return redirect()
            ->route('admin.partners.index')
            ->with('success', 'شریک با موفقیت ثبت شد.');
    }
}
