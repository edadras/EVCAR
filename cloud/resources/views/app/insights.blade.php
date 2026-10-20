@extends('app.layouts.mobile', ['tab' => 'account', 'noTab' => true])

@section('title', 'SafirCharge — تحلیل هزینه‌ها')

@section('phone')
@php
    use App\Support\Persian;
    $t = $ins['totals'];
    $maxCost = max(1, collect($ins['months'])->max('cost'));
@endphp
<div class="appbar" style="padding-top:2px">
    <a href="{{ route('app.history') }}" class="ab-btn"><span data-ic="back"></span></a>
    <div style="flex:1"><div class="ab-title">تحلیل هزینه و مصرف</div><div class="fs12 muted">۶ ماه اخیر</div></div>
</div>

<div class="screen" style="padding-bottom:40px">
    <div class="in-hero rise">
        <div class="fs12" style="opacity:.85">هزینهٔ کل شارژ</div>
        <div class="in-v tnum">{{ Persian::money($t['cost']) }} <small>تومان</small></div>
        @if($ins['month_change_pct'] !== null)
        <div class="fs12">این ماه {{ $ins['month_change_pct'] > 0 ? Persian::digits($ins['month_change_pct']).'٪ بیشتر' : Persian::digits(abs($ins['month_change_pct'])).'٪ کمتر' }} از ماه قبل</div>
        @endif
    </div>

    <div class="sec-title">هزینهٔ ماهانه</div>
    <div class="card card-p">
        <div class="in-bars">
            @foreach($ins['months'] as $m)
            <div class="in-col">
                <span class="in-amt tnum">{{ $m['cost'] ? Persian::money(round($m['cost'] / 1000)).'k' : '' }}</span>
                <i style="height:{{ max(3, round(100 * $m['cost'] / $maxCost)) }}%" class="{{ $loop->last ? 'cur' : '' }}"></i>
                <span class="in-lbl">{{ $m['label'] }}</span>
            </div>
            @endforeach
        </div>
    </div>

    <div class="in-grid">
        <div><span>جلسات</span><b class="tnum">{{ Persian::digits($t['sessions']) }}</b></div>
        <div><span>انرژی</span><b class="tnum">{{ Persian::kwh($t['energy']) }} kWh</b></div>
        <div><span>میانگین هر kWh</span><b class="tnum">{{ $t['avg_per_kwh'] ? Persian::money($t['avg_per_kwh']).' ت' : '—' }}</b></div>
        <div><span>میانگین مدت</span><b class="tnum">{{ $t['avg_minutes'] ? Persian::digits($t['avg_minutes']).' دقیقه' : '—' }}</b></div>
        <div><span>شارژ شبانه</span><b class="tnum">{{ Persian::digits($t['night_share']) }}٪</b></div>
        <div><span>مسافت تقریبی</span><b class="tnum">{{ Persian::money($t['km']) }} km</b></div>
    </div>

    <div class="eco-card rise">
        <div class="fw7">💚 صرفه‌جویی نسبت به خودروی بنزینی</div>
        <div class="row between mt4"><span class="fs13">هزینهٔ سوخت معادل کمتر</span><b class="tnum">{{ Persian::money($t['fuel_saving']) }} ت</b></div>
        <div class="row between mt4"><span class="fs13">کاهش انتشار CO₂</span><b class="tnum">{{ Persian::money($t['co2_kg']) }} kg</b></div>
        <div class="fs12 mt4" style="opacity:.8">برآورد بر اساس ۶٫۵ کیلومتر به‌ازای هر kWh و مصرف ۸ لیتر بنزین در ۱۰۰ کیلومتر.</div>
    </div>

    @if($ins['top_station'])
    <a class="card card-p row between" style="margin-top:12px;text-decoration:none;color:inherit;align-items:center" href="{{ route('app.charger', ['station' => $ins['top_station']['code']]) }}">
        <span><span class="fs12 muted" style="display:block">ایستگاه پرتکرار شما</span><b>{{ $ins['top_station']['name'] }}</b></span>
        <span class="pill pill-green">{{ Persian::digits($ins['top_station']['sessions']) }} جلسه</span>
    </a>
    @endif

    <div class="tip-card"><span data-ic="leaf"></span><span class="fs13">{{ $ins['tip'] }}</span></div>
</div>
@endsection

@push('styles')
<style>
.in-hero{border-radius:20px;padding:20px;color:#fff;background:linear-gradient(130deg,var(--accent),color-mix(in srgb,var(--accent) 55%,var(--violet,#7c3aed)))}
.in-v{font-size:30px;font-weight:800;margin:4px 0}.in-v small{font-size:13px;opacity:.85}
.in-bars{display:flex;align-items:flex-end;gap:10px;height:170px}
.in-col{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%;gap:5px}
.in-col i{display:block;width:100%;max-width:34px;border-radius:9px 9px 4px 4px;background:color-mix(in srgb,var(--accent) 35%,var(--surface-2))}
.in-col i.cur{background:var(--accent)}
.in-amt{font-size:10px;color:var(--muted)}.in-lbl{font-size:10.5px;color:var(--text-2);white-space:nowrap}
.in-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:14px}
.in-grid>div{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:12px}
.in-grid span{display:block;font-size:11.5px;color:var(--muted)}.in-grid b{font-size:15px}
.eco-card{margin-top:14px;padding:16px;border-radius:18px;background:var(--green-soft);color:var(--green)}
.eco-card b{color:var(--text)}
.tip-card{display:flex;gap:10px;align-items:flex-start;margin-top:12px;padding:14px;border-radius:16px;background:var(--surface);border:1px dashed var(--border)}
.tip-card svg{width:20px;height:20px;color:var(--green);flex:none}
</style>
@endpush
