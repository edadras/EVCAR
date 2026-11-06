<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EvOperator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EvOperatorController extends Controller
{
    private const UPLOAD_DIR = 'uploads/operators';

    public function index(): View
    {
        return view('admin.external-stations.operators', [
            'operators' => EvOperator::withCount([
                'stations',
                'stations as active_stations_count' => fn ($q) => $q->where('is_active', true),
            ])->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.external-stations.operator-form', ['operator' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['logo_path'] = $this->storeLogo($request, $data['slug']);
        EvOperator::create($data);

        return redirect()->route('admin.ev-operators.index')->with('success', 'اپراتور اضافه شد.');
    }

    public function edit(EvOperator $evOperator): View
    {
        return view('admin.external-stations.operator-form', ['operator' => $evOperator]);
    }

    public function update(Request $request, EvOperator $evOperator): RedirectResponse
    {
        $data = $this->validated($request, $evOperator);
        if ($logo = $this->storeLogo($request, $evOperator->slug)) {
            $this->deleteUploadedLogo($evOperator->logo_path);
            $data['logo_path'] = $logo;
        }
        unset($data['slug']);
        $evOperator->update($data);

        return redirect()->route('admin.ev-operators.index')->with('success', 'اپراتور به‌روزرسانی شد.');
    }

    public function destroy(EvOperator $evOperator): RedirectResponse
    {
        $this->deleteUploadedLogo($evOperator->logo_path);
        $evOperator->delete();

        return redirect()->route('admin.ev-operators.index')->with('success', 'اپراتور و ایستگاه‌هایش حذف شدند.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?EvOperator $operator = null): array
    {
        $data = $request->validate([
            'slug' => [
                Rule::requiredIf(! $operator), 'nullable', 'string', 'max:64', 'regex:/^[a-z0-9_-]+$/',
                Rule::unique('ev_operators', 'slug')->ignore($operator?->id),
            ],
            'name' => ['required', 'string', 'max:120'],
            'network' => ['nullable', 'string', 'max:120'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'website' => ['nullable', 'url', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'app_name' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
        ]);
        unset($data['logo']);
        $data['slug'] = Str::lower((string) ($data['slug'] ?? ''));
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function storeLogo(Request $request, string $slug): ?string
    {
        if (! $request->hasFile('logo')) {
            return null;
        }
        $dir = public_path(self::UPLOAD_DIR);
        File::ensureDirectoryExists($dir);
        $file = $request->file('logo');
        $name = $slug.'-'.now()->format('YmdHis').'.'.$file->extension();
        $file->move($dir, $name);

        return '/'.self::UPLOAD_DIR.'/'.$name;
    }

    private function deleteUploadedLogo(?string $path): void
    {
        if ($path && str_starts_with($path, '/'.self::UPLOAD_DIR.'/')) {
            File::delete(public_path(ltrim($path, '/')));
        }
    }
}
