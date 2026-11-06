@extends('admin.layouts.app', ['page' => 'external-stations', 'title' => $operator ? 'ویرایش اپراتور' : 'افزودن اپراتور', 'crumb' => 'اپراتورهای شارژ'])

@section('content')
<div class="flex between items-center mt-8" style="margin-bottom:18px">
    <a href="{{ route('admin.ev-operators.index') }}" class="btn btn-sm">بازگشت به اپراتورها</a>
    @if($operator)
    <form method="POST" action="{{ route('admin.ev-operators.destroy', $operator) }}" onsubmit="return confirm('اپراتور و همهٔ ایستگاه‌هایش حذف شوند؟');">
        @csrf @method('DELETE')
        <button type="submit" class="btn btn-sm" style="color:var(--red)">حذف اپراتور</button>
    </form>
    @endif
</div>

<form method="POST" enctype="multipart/form-data" action="{{ $operator ? route('admin.ev-operators.update', $operator) : route('admin.ev-operators.store') }}">
    @csrf
    @if($operator) @method('PUT') @endif

    <div class="grid col-2-1">
        <div class="card">
            <div class="card-head"><h3>مشخصات اپراتور</h3></div>
            <div class="card-pad">
                <div class="form-grid">
                    <div class="ffield">
                        <label>نام اپراتور / برند <span class="req">*</span></label>
                        <input class="finput" name="name" value="{{ old('name', $operator?->name) }}" placeholder="مثلاً مپنا" required>
                    </div>
                    <div class="ffield">
                        <label>شناسه (slug) <span class="req">*</span></label>
                        <input class="finput" name="slug" dir="ltr" style="text-align:left" value="{{ old('slug', $operator?->slug) }}" pattern="[a-z0-9_\-]+" placeholder="mapna" {{ $operator ? 'readonly' : 'required' }}>
                    </div>
                    <div class="ffield">
                        <label>شبکه شارژ</label>
                        <input class="finput" name="network" value="{{ old('network', $operator?->network) }}" placeholder="مثلاً شارینت">
                    </div>
                    <div class="ffield">
                        <label>نام اپلیکیشن</label>
                        <input class="finput" name="app_name" value="{{ old('app_name', $operator?->app_name) }}">
                    </div>
                    <div class="ffield">
                        <label>وب‌سایت</label>
                        <input class="finput" name="website" dir="ltr" value="{{ old('website', $operator?->website) }}" placeholder="https://…">
                    </div>
                    <div class="ffield">
                        <label>تلفن پشتیبانی</label>
                        <input class="finput" name="phone" dir="ltr" value="{{ old('phone', $operator?->phone) }}">
                    </div>
                    <div class="ffield">
                        <label>رنگ برند</label>
                        <input class="finput" name="color" type="color" value="{{ old('color', $operator?->color ?? '#64748b') }}" style="height:42px">
                    </div>
                    <div class="ffield">
                        <label>ترتیب نمایش</label>
                        <input class="finput tnum" name="sort_order" type="number" min="0" value="{{ old('sort_order', $operator?->sort_order ?? 0) }}">
                    </div>
                    <div class="ffield col-span">
                        <label>توضیحات</label>
                        <textarea class="finput" name="description" rows="3" maxlength="1000">{{ old('description', $operator?->description) }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div style="align-self:start;position:sticky;top:84px">
            <div class="card">
                <div class="card-head"><h3>لوگو</h3></div>
                <div class="card-pad">
                    <div style="display:grid;place-items:center;margin-bottom:12px">
                        <img id="logoPrev" src="{{ $operator?->logoUrl() ?? '/assets/operators/generic.png' }}" alt="" style="width:96px;height:96px;object-fit:contain;border-radius:50%;background:#fff;border:2px solid var(--border-2)">
                    </div>
                    <input class="finput" type="file" name="logo" accept="image/png,image/jpeg,image/webp" id="logoIn">
                    <div class="fhint">PNG/JPG/WebP، حداکثر ۱ مگابایت — مربعی و با پس‌زمینهٔ شفاف بهتر دیده می‌شود</div>
                    <label class="toggle-row" style="margin:12px 0 0;padding:8px 0">
                        <span>نمایش ایستگاه‌ها روی نقشه</span>
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $operator?->is_active ?? true)) style="width:18px;height:18px">
                    </label>
                </div>
                <div class="card-pad" style="border-top:1px solid var(--border-2)">
                    <button type="submit" class="btn btn-primary" style="width:100%">{{ $operator ? 'ذخیره تغییرات' : 'ایجاد اپراتور' }}</button>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.getElementById('logoIn').addEventListener('change', function (e) {
    var f = e.target.files && e.target.files[0];
    if (f) document.getElementById('logoPrev').src = URL.createObjectURL(f);
});
</script>
@endpush
