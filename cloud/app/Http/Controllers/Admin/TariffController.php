<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tariff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TariffController extends Controller
{
    public function create(): View
    {
        return view('admin.tariffs.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        if (empty($data['code'])) {
            $data['code'] = 'TRF-'.Str::upper(Str::random(5));
        }

        if ($request->boolean('is_default')) {
            Tariff::where('is_default', true)->update(['is_default' => false]);
        }

        Tariff::create([
            'uuid' => (string) Str::uuid(),
            'name' => $data['name'],
            'code' => $data['code'],
            'pricing_model' => $data['pricing_model'],
            'currency' => 'IRR',
            'price_per_kwh' => $data['price_per_kwh'] ?? 0,
            'price_per_minute' => $data['price_per_minute'] ?? 0,
            'session_fee' => $data['session_fee'] ?? 0,
            'member_discount_percent' => $data['member_discount_percent'] ?? 0,
            'is_default' => $request->boolean('is_default'),
            'status' => $data['status'],
            'valid_from' => now(),
        ]);

        return redirect()
            ->route('admin.tariffs.index')
            ->with('success', 'تعرفه با موفقیت ایجاد شد.');
    }

    public function edit(Tariff $tariff): View
    {
        $tariff->loadCount('chargingSessions');

        return view('admin.tariffs.create', ['tariff' => $tariff]);
    }

    public function update(Request $request, Tariff $tariff): RedirectResponse
    {
        $data = $this->validated($request, $tariff);

        if ($request->boolean('is_default')) {
            Tariff::where('is_default', true)->where('id', '!=', $tariff->id)->update(['is_default' => false]);
        }

        $tariff->update([
            'name' => $data['name'],
            'code' => $data['code'],
            'pricing_model' => $data['pricing_model'],
            'price_per_kwh' => $data['price_per_kwh'] ?? 0,
            'price_per_minute' => $data['price_per_minute'] ?? 0,
            'session_fee' => $data['session_fee'] ?? 0,
            'member_discount_percent' => $data['member_discount_percent'] ?? 0,
            'is_default' => $request->boolean('is_default'),
            'status' => $data['status'],
        ]);

        return redirect()
            ->route('admin.tariffs.index')
            ->with('success', 'تعرفه به‌روزرسانی شد.');
    }

    public function destroy(Tariff $tariff): RedirectResponse
    {
        if ($tariff->is_default) {
            return redirect()
                ->route('admin.tariffs.index')
                ->withErrors(['tariff' => 'تعرفهٔ پیش‌فرض قابل حذف نیست. ابتدا تعرفهٔ پیش‌فرض دیگری تعیین کنید.']);
        }

        $tariff->delete();

        return redirect()
            ->route('admin.tariffs.index')
            ->with('success', 'تعرفه حذف شد.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Tariff $tariff = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:32', 'unique:tariffs,code,'.($tariff?->id ?? 'NULL')],
            'pricing_model' => ['required', 'in:per_kwh,per_minute,mixed'],
            'price_per_kwh' => ['nullable', 'numeric', 'min:0'],
            'price_per_minute' => ['nullable', 'numeric', 'min:0'],
            'session_fee' => ['nullable', 'numeric', 'min:0'],
            'member_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_default' => ['nullable', 'boolean'],
            'status' => ['required', 'in:active,inactive,draft'],
        ]);
    }
}
