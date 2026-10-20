@extends('app.layouts.mobile', ['noTab' => true])

@section('title', 'SafirCharge — طرح‌ها')

@section('phone')
@php
    use App\Support\Persian;
    $hero = $campaigns->sortByDesc('discount_percent')->first();
    $others = $hero ? $campaigns->where('id', '!=', $hero->id) : collect();
    $active = $campaigns->filter(fn ($c) => (float) ($c->bonus_amount ?? 0) > 0);
    $badgeFor = function ($c) {
        if ($c->discount_percent) {
            return Persian::digits((int) $c->discount_percent).'%';
        }
        if ($c->bonus_amount) {
            return Persian::money((int) round((float) $c->bonus_amount / 1000)).'K';
        }

        return '★';
    };
    $iconFor = function ($c) {
        return match ($c->type) {
            'loyalty' => ['ic' => 'coins', 'c' => 'ic-orange'],
            'referral' => ['ic' => 'gift', 'c' => 'ic-violet'],
            'seasonal' => ['ic' => 'bolt', 'c' => 'ic-blue'],
            'opening' => ['ic' => 'leaf', 'c' => 'ic-green'],
            default => ['ic' => 'gift', 'c' => 'ic-green'],
        };
    };
    $descFor = function ($c) {
        $parts = [];
        if ($c->discount_percent) {
            $parts[] = Persian::digits((int) $c->discount_percent).'٪ تخفیف';
        }
        if ($c->bonus_amount) {
            $parts[] = Persian::money((int) round((float) $c->bonus_amount / 10)).' تومان اعتبار';
        }
        if ($c->ends_at) {
            $parts[] = 'تا '.Persian::digits($c->ends_at->format('Y/m/d'));
        }

        return implode(' · ', $parts) ?: ($c->conditions['summary'] ?? 'طرح فعال SafirCharge');
    };
@endphp
<div class="appbar" style="padding-top:2px">
    <a href="{{ route('app.home') }}" class="ab-btn"><span data-ic="back"></span></a>
    <div style="flex:1"><div class="ab-title">طرح‌ها و پیشنهادها</div></div>
</div>

<div class="screen">
    <div class="card-lite fs13" style="margin-bottom:14px;line-height:1.8">
        تخفیف‌ها و اعتبارهای پاداش هنگام شارژ در ایستگاه‌های مشمول به‌صورت خودکار اعمال می‌شوند.
    </div>

    @if($hero)
    <div class="offer-hero rise">
        <div class="oh-badge">{{ $hero->discount_percent ? Persian::digits((int) $hero->discount_percent).'%' : '★' }}</div>
        <div class="oh-ic" data-ic="bolt"></div>
        <div class="oh-title">{{ $hero->name }}</div>
        <div class="oh-body">{{ $descFor($hero) }}</div>
        <a href="{{ route('app.map') }}" class="oh-cta" style="display:block;text-align:center;text-decoration:none">مشاهده ایستگاه‌ها</a>
    </div>
    @endif

    <div class="sec-title" style="margin-top:20px">طرح‌های دارای اعتبار</div>
    <div style="display:flex;flex-direction:column;gap:11px" id="active">
        @forelse($active as $c)
            @php $style = $iconFor($c); @endphp
            <div class="ocard">
                <div class="oc-ic {{ $style['c'] }}"><span data-ic="{{ $style['ic'] }}"></span></div>
                <div style="flex:1">
                    <div class="oc-t">{{ $c->name }}</div>
                    <div class="oc-d">{{ $descFor($c) }}</div>
                </div>
                <span class="oc-badge">{{ $badgeFor($c) }}</span>
            </div>
        @empty
            <div class="ocard" style="justify-content:center"><span class="muted fs13">طرح فعالی در سیستم ثبت نشده است.</span></div>
        @endforelse
    </div>

    <div class="sec-title" style="margin-top:20px">سایر پیشنهادها</div>
    <div style="display:flex;flex-direction:column;gap:11px" id="others">
        @forelse($others as $c)
            @php $style = $iconFor($c); @endphp
            <div class="ocard">
                <div class="oc-ic {{ $style['c'] }}"><span data-ic="{{ $style['ic'] }}"></span></div>
                <div style="flex:1">
                    <div class="oc-t">{{ $c->name }}</div>
                    <div class="oc-d">{{ $descFor($c) }}</div>
                </div>
                <span class="oc-badge muted fs12">خودکار</span>
            </div>
        @empty
            <div class="ocard" style="justify-content:center"><span class="muted fs13">پیشنهاد دیگری موجود نیست.</span></div>
        @endforelse
    </div>
</div>
@endsection

@push('styles')
<style>
.offer-hero{position:relative;overflow:hidden;border-radius:22px;padding:20px;color:#fff;background:linear-gradient(140deg,var(--accent),#7C3AED);box-shadow:0 16px 34px rgba(var(--accent-rgb),.34)}
.offer-hero::after{content:"";position:absolute;inset:0;background:radial-gradient(circle at 85% 0%,rgba(255,255,255,.24),transparent 48%)}
.oh-badge{position:absolute;top:18px;inset-inline-end:20px;font-size:30px;font-weight:800;z-index:1}
.oh-ic{width:48px;height:48px;border-radius:14px;background:rgba(255,255,255,.2);display:grid;place-items:center;position:relative;z-index:1}.oh-ic svg{width:25px;height:25px}
.oh-title{font-size:19px;font-weight:800;margin-top:14px;position:relative;z-index:1}
.oh-body{font-size:13px;opacity:.92;margin-top:7px;line-height:1.8;position:relative;z-index:1}
.oh-cta{margin-top:16px;width:100%;background:#fff;color:var(--accent-strong);font-family:inherit;font-weight:800;font-size:14px;border:none;padding:13px;border-radius:13px;position:relative;z-index:1;cursor:pointer}
.ocard{display:flex;align-items:center;gap:13px;padding:14px;border-radius:16px;background:var(--surface);border:1px solid var(--border);box-shadow:var(--shadow-sm)}
.ocard .oc-ic{width:46px;height:46px;border-radius:13px;display:grid;place-items:center;flex:none}.oc-ic svg{width:22px;height:22px}
.ocard .oc-t{font-weight:700;font-size:13.5px}.ocard .oc-d{font-size:11.5px;color:var(--muted);margin-top:3px;line-height:1.6}
.oc-badge{margin-inline-start:auto;font-weight:800;font-size:15px;color:var(--accent);flex:none}
</style>
@endpush
