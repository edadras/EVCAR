@extends('admin.layouts.app', ['page' => 'ev-catalog', 'title' => $vehicle ? 'ویرایش مدل خودرو' : 'افزودن مدل خودرو', 'crumb' => 'کاتالوگ خودروهای برقی'])

@section('content')
@php
    use App\Support\Persian;
    $c = $vehicle?->consumption ?? [];
    $baseConsumption = old('consumption_base', $vehicle?->consumption_base ?? $c['base'] ?? $c['mixed'] ?? '');
    $worstConsumption = old('consumption_worst', $c['worst'] ?? '');
    $aliasesText = old('aliases', $vehicle ? implode("\n", $vehicle->aliases ?? []) : '');
@endphp

<div class="flex between items-center mt-8" style="margin-bottom:18px">
    <a href="{{ route('admin.ev-catalog.index') }}" class="btn btn-sm">بازگشت به فهرست</a>
    @if($vehicle)
    <form method="POST" action="{{ route('admin.ev-catalog.destroy', $vehicle) }}" onsubmit="return confirm('این مدل حذف شود؟');">
        @csrf @method('DELETE')
        <button type="submit" class="btn btn-sm" style="color:var(--red)">حذف مدل</button>
    </form>
    @endif
</div>

<form method="POST" action="{{ $vehicle ? route('admin.ev-catalog.update', $vehicle) : route('admin.ev-catalog.store') }}">
    @csrf
    @if($vehicle) @method('PUT') @endif

    <div class="grid col-2-1">
        <div style="display:flex;flex-direction:column;gap:16px">
            <div class="card">
                <div class="card-head"><h3>مشخصات خودرو</h3></div>
                <div class="card-pad">
                    <div class="form-grid">
                        <div class="ffield col-span">
                            <label>نام نمایشی <span class="req">*</span></label>
                            <input class="finput" name="name" value="{{ old('name', $vehicle?->name) }}" placeholder="مثلاً ایویکو اسکایول ET5" required>
                        </div>
                        <div class="ffield">
                            <label>شناسه سیستمی (slug) <span class="req">*</span></label>
                            <input class="finput" name="slug" dir="ltr" style="text-align:left" value="{{ old('slug', $vehicle?->slug) }}" placeholder="skywell_et5" pattern="[a-z0-9_]+" {{ $vehicle ? 'readonly' : '' }} required>
                            <div class="fhint">فقط حروف کوچک انگلیسی، عدد و زیرخط</div>
                        </div>
                        <div class="ffield">
                            <label>برند</label>
                            <input class="finput" name="brand" value="{{ old('brand', $vehicle?->brand) }}" placeholder="Skywell">
                        </div>
                        <div class="ffield">
                            <label>مدل</label>
                            <input class="finput" name="model" value="{{ old('model', $vehicle?->model) }}" placeholder="ET5">
                        </div>
                        <div class="ffield">
                            <label>ظرفیت باتری (kWh) <span class="req">*</span></label>
                            <input class="finput tnum" name="battery_kwh" type="number" step="0.1" min="10" max="200" value="{{ old('battery_kwh', $vehicle?->battery_kwh ?? 60) }}" required>
                        </div>
                        <div class="ffield">
                            <label>وزن (kg) <span class="req">*</span></label>
                            <input class="finput tnum" name="mass_kg" type="number" min="800" max="3500" value="{{ old('mass_kg', $vehicle?->mass_kg ?? 1750) }}" required>
                        </div>
                        <div class="ffield">
                            <label>بازیافت انرژی (۰–۱)</label>
                            <input class="finput tnum" name="regen_efficiency" type="number" step="0.01" min="0.3" max="0.95" value="{{ old('regen_efficiency', $vehicle?->regen_efficiency ?? 0.65) }}" required>
                        </div>
                        <div class="ffield">
                            <label>ضریب درگ (Cd)</label>
                            <input class="finput tnum" name="cd" type="number" step="0.01" min="0.15" max="0.5" value="{{ old('cd', $vehicle?->cd) }}">
                        </div>
                        <div class="ffield">
                            <label>سطح جلو (m²)</label>
                            <input class="finput tnum" name="frontal_area_m2" type="number" step="0.01" min="1.5" max="4" value="{{ old('frontal_area_m2', $vehicle?->frontal_area_m2) }}">
                        </div>
                        <div class="ffield">
                            <label>ترتیب نمایش</label>
                            <input class="finput tnum" name="sort_order" type="number" min="0" value="{{ old('sort_order', $vehicle?->sort_order ?? 0) }}">
                        </div>
                        <div class="ffield">
                            <label class="toggle-row" style="margin:0;padding:12px 0">
                                <span>فعال در مسیریاب</span>
                                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $vehicle?->is_active ?? true)) style="width:18px;height:18px">
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                    <div>
                        <h3>مصرف و برد</h3>
                        <div class="ch-sub">مبنای مسیریاب: مصرف واقعی روزمره — نه WLTP</div>
                    </div>
                </div>
                <div class="card-pad">
                    <div class="form-grid">
                        <div class="ffield">
                            <label>مصرف پایه (kWh/100km)</label>
                            <input class="finput tnum" name="consumption_base" type="number" step="0.1" min="8" max="45"
                                value="{{ $baseConsumption }}" readonly style="opacity:.85">
                            <div class="fhint">خودکار از برد واقعی عادی محاسبه می‌شود</div>
                        </div>
                        <div class="ffield">
                            <label>مصرف بدترین حالت (مرجع)</label>
                            <input class="finput tnum" name="consumption_worst" type="number" step="0.1" min="8" max="50"
                                value="{{ $worstConsumption }}">
                            <div class="fhint">زمستان، سرعت بالا، HVAC — فقط نمایش</div>
                        </div>
                        <div class="ffield">
                            <label>برد WLTP (km)</label>
                            <input class="finput tnum" name="range_wltp_km" type="number" min="50" max="1200"
                                value="{{ old('range_wltp_km', $vehicle?->range_wltp_km) }}">
                        </div>
                        <div class="ffield">
                            <label>برد واقعی عادی — از (km)</label>
                            <input class="finput tnum" name="range_real_min_km" type="number" min="50" max="1200"
                                value="{{ old('range_real_min_km', $vehicle?->range_real_min_km) }}">
                        </div>
                        <div class="ffield">
                            <label>برد واقعی عادی — تا (km) <span class="req">*</span></label>
                            <input class="finput tnum" name="range_real_max_km" type="number" min="50" max="1200"
                                value="{{ old('range_real_max_km', $vehicle?->range_real_max_km) }}" required>
                            <div class="fhint">مبنای مسیریاب — مثلاً ۶۲۰ برای BMW iX3</div>
                        </div>
                        <div class="ffield">
                            <label>برد بدترین حالت (km)</label>
                            <input class="finput tnum" name="range_worst_km" type="number" min="50" max="1200"
                                value="{{ old('range_worst_km', $vehicle?->range_worst_km) }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h3>نام‌های جایگزین (تطبیق خودرو کاربر)</h3></div>
                <div class="card-pad">
                    <div class="ffield col-span">
                        <label>نام‌های مستعار</label>
                        <textarea class="finput" name="aliases" rows="4" placeholder="هر خط یک نام — مثلاً اسکایول ET5">{{ $aliasesText }}</textarea>
                        <div class="fhint">برای تطبیق خودکار با خودروهای ثبت‌شده در اپ</div>
                    </div>
                </div>
            </div>
        </div>

        <div style="align-self:start;position:sticky;top:84px">
            <div class="card">
                <div class="card-head"><h3>راهنما</h3></div>
                <div class="card-pad muted fs12" style="line-height:1.8">
                    <p>تغییرات بلافاصله در <strong>مسیریاب برقی</strong> و فرم ثبت خودرو اعمال می‌شود.</p>
                    <p class="mt-8">مسیریاب فقط از <strong>مصرف پایه</strong> استفاده می‌کند و سرعت، دما و شیب را روی آن اعمال می‌کند. برد و رسیدن به مقصد از همان مدل محاسبه می‌شود.</p>
                    <p class="mt-8">مدل‌های غیرفعال در مسیریاب نمایش داده نمی‌شوند.</p>
                </div>
                <div class="card-pad" style="border-top:1px solid var(--border-2)">
                    <button type="submit" class="btn btn-primary" style="width:100%">
                        {{ $vehicle ? 'ذخیره تغییرات' : 'ایجاد مدل' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection
