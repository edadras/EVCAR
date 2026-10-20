<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable', 'string'],
        ]);

        foreach ($data['settings'] as $id => $value) {
            $setting = SystemSetting::find($id);
            if (! $setting) {
                continue;
            }
            $setting->update(['value' => ['raw' => $value]]);
        }

        return back()->with('success', 'تنظیمات ذخیره شد.');
    }

    public function updateSync(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sync_interval_seconds' => ['required', 'integer', 'min:5', 'max:3600'],
            'batch_size' => ['required', 'integer', 'min:10', 'max:5000'],
            'retry_enabled' => ['nullable', 'boolean'],
            'offline_queue_enabled' => ['nullable', 'boolean'],
        ]);

        SystemSetting::updateOrCreate(
            ['group' => 'sync', 'key' => 'engine'],
            ['value' => [
                'sync_interval_seconds' => $data['sync_interval_seconds'],
                'batch_size' => $data['batch_size'],
                'retry_enabled' => $request->boolean('retry_enabled'),
                'offline_queue_enabled' => $request->boolean('offline_queue_enabled'),
            ], 'is_public' => false]
        );

        return redirect()
            ->route('admin.sync.settings')
            ->with('success', 'تنظیمات سینک ذخیره شد.');
    }
}
