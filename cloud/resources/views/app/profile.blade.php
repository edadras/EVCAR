@extends('app.layouts.mobile', ['noTab' => true])

@section('title', 'SafirCharge — ویرایش پروفایل')

@section('phone')
@php
use App\Support\Persian;
$parts = preg_split('/\s+/u', trim($user->name), -1, PREG_SPLIT_NO_EMPTY);
$firstName = $parts[0] ?? '';
$lastName = implode(' ', array_slice($parts, 1)) ?: '';
$nationalId = $user->meta['national_id'] ?? '';
$city = $user->meta['city'] ?? 'تهران';
@endphp
<div class="appbar" style="padding-top:2px">
    <a href="{{ route('app.account') }}" class="ab-btn"><span data-ic="back"></span></a>
    <div style="flex:1"><div class="ab-title">ویرایش پروفایل</div></div>
</div>

<form method="POST" action="{{ route('app.profile.update') }}">
@csrf
<div class="screen">
    @if(session('success'))
    <div class="note-box mb12" style="border-color:var(--green);color:var(--green)">{{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div class="note-box mb12" style="border-color:var(--red);color:var(--red)">{{ $errors->first() }}</div>
    @endif

    <div class="center-col rise" style="margin:8px 0 22px">
        <div class="av-wrap">
            <div class="pf-av-lg">{{ $initials }}</div>
        </div>
        <div class="fw8 mt12" style="font-size:17px">{{ $user->name }}</div>
        <div class="fs12 muted">{{ $memberSince }}</div>
    </div>

    <div class="card card-p">
        <div class="field"><label>نام</label><input class="input" name="first_name" value="{{ old('first_name', $firstName) }}" required></div>
        <div class="field"><label>نام خانوادگی</label><input class="input" name="last_name" value="{{ old('last_name', $lastName) }}"></div>
        <div class="field">
            <label>شماره موبایل</label>
            <div class="locked">
                <input class="input tnum" dir="ltr" style="text-align:right" value="{{ $phoneFormatted }}" readonly>
                @if($user->phone_verified_at)
                <span class="lock-badge"><span data-ic="check"></span>تأییدشده</span>
                @endif
            </div>
        </div>
        <div class="field"><label>کد ملی</label><input class="input tnum" name="national_id" dir="ltr" style="text-align:right" value="{{ old('national_id', $nationalId ? Persian::digits($nationalId) : '') }}"></div>
        <div class="field"><label>ایمیل</label><input class="input" name="email" dir="ltr" style="text-align:right" value="{{ old('email', $user->email ?? '') }}" placeholder="name@email.com"></div>
        <div class="field" style="margin-bottom:0"><label>شهر</label>
            <select class="input" name="city" style="appearance:none">
                @foreach(['تهران', 'کرج', 'اصفهان', 'مشهد', 'شیراز', 'تبریز'] as $c)
                    <option value="{{ $c }}" @selected(old('city', $city) === $c)>{{ $c }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="note-box mt16">
        <span data-ic="shield"></span>
        <span>شماره موبایل پایهٔ احراز هویت شماست و برای تغییر آن باید با پشتیبانی تماس بگیرید.</span>
    </div>
</div>

<div class="savebar">
    <button type="submit" class="btn"><span data-ic="check"></span> ذخیرهٔ تغییرات</button>
</div>
</form>
@endsection

@push('styles')
<style>
.pf-av-lg{width:88px;height:88px;border-radius:26px;background:linear-gradient(135deg,var(--accent),var(--orange));color:#fff;display:grid;place-items:center;font-weight:800;font-size:28px}
.note-box{display:flex;align-items:flex-start;gap:10px;padding:13px;border-radius:14px;background:var(--surface-2);border:1px solid var(--border-2);font-size:12.5px;color:var(--text-2);line-height:1.7}
.note-box svg{width:18px;height:18px;color:var(--accent);flex:none;margin-top:2px}
.locked{position:relative}
.lock-badge{position:absolute;inset-inline-start:12px;top:50%;transform:translateY(-50%);display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:700;color:var(--green)}
.lock-badge svg{width:14px;height:14px}
.savebar{position:absolute;bottom:0;left:0;right:0;padding:12px 18px calc(16px + env(safe-area-inset-bottom,0));background:color-mix(in srgb,var(--surface) 94%,transparent);backdrop-filter:blur(14px);border-top:1px solid var(--border);z-index:20}
.savebar .btn{width:100%;height:52px}
</style>
@endpush
