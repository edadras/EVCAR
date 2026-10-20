@extends('admin.layouts.app', ['page' => 'finance', 'title' => 'جزئیات تسویه'.($settlement ? ' '.$settlement->settlement_number : ''), 'crumb' => 'مدیریت مالی'])

@section('content')
@php use App\Support\Persian; @endphp
<div class="flex between items-center mt-8" style="margin-bottom:18px">
      <a href="/admin/finance" class="btn btn-sm"><span class="bic" data-ic="dots"></span> بازگشت به مالی</a>
      @if($settlement)
      <div class="flex gap-8">
        <button class="btn btn-sm" type="button"><span class="bic" data-ic="download"></span> دانلود فاکتور</button>
        <button class="btn btn-sm btn-primary" type="button"><span class="bic" data-ic="check"></span> تأیید و پرداخت تسویه</button>
      </div>
      @endif
    </div>

    @if(!$settlement)
      <div class="card card-pad"><div class="muted">تسویه‌ای ثبت نشده است. <a href="{{ route('admin.settlement.create') }}">ایجاد تسویهٔ جدید</a></div></div>
    @else
    @php
      $partner = $settlement->partner;
      $maint = (float) ($settlement->meta['maintenance_fee'] ?? 0);
      $grossToman = (int) round($settlement->gross_revenue / 10);
      $shareToman = (int) round($settlement->partner_share / 10);
      $feeToman = (int) round($settlement->platform_fee / 10);
      $maintToman = (int) round($maint / 10);
      $statusPill = match($settlement->status) {
        'paid' => 'pill-green',
        'approved' => 'pill-accent',
        default => 'pill-amber',
      };
      $statusLabel = match($settlement->status) {
        'paid' => 'پرداخت‌شده',
        'approved' => 'تأییدشده',
        'pending' => 'در انتظار پرداخت',
        default => $settlement->status,
      };
    @endphp
    <div class="grid col-2-1">
      <div style="display:flex;flex-direction:column;gap:16px">
        <div class="card card-pad">
          <div class="flex between items-center">
            <div class="flex items-center gap-16">
              <span class="uavatar" style="width:54px;height:54px;border-radius:15px;font-size:22px;background:#0E6BA8">{{ mb_substr($partner?->name ?? '؟', 0, 1) }}</span>
              <div>
                <div class="fw8" style="font-size:18px">{{ $partner?->name ?? '—' }}</div>
                <div class="muted fs13 mt-4">اشتراک {{ Persian::num((int) ($partner?->revenue_share_percent ?? 0)) }}٪ · {{ $settlement->period_start?->format('Y/m/d') }} – {{ $settlement->period_end?->format('Y/m/d') }}</div>
              </div>
            </div>
            <span class="pill {{ $statusPill }}" style="font-size:13px;padding:6px 14px"><span class="pdot"></span>{{ $statusLabel }}</span>
          </div>
        </div>

        <div class="card">
          <div class="card-head"><div><h3>ریز محاسبهٔ سهم</h3><div class="ch-sub">بر اساس {{ Persian::num($settlement->session_count) }} جلسهٔ شارژ</div></div></div>
          <div class="card-pad">
            <div class="calc-row"><span class="muted">درآمد ناخالص ایستگاه‌ها</span><b class="tnum">{{ Persian::num($grossToman) }}</b></div>
            <div class="calc-row"><span class="muted">سهم اپراتور</span><b class="tnum" style="color:var(--green)">{{ Persian::num($shareToman) }}</b></div>
            <div class="calc-row"><span class="muted">کارمزد سامانه</span><b class="tnum">−{{ Persian::num($feeToman) }}</b></div>
            @if($maintToman > 0)
            <div class="calc-row"><span class="muted">هزینهٔ نگهداری تجهیزات</span><b class="tnum">−{{ Persian::num($maintToman) }}</b></div>
            @endif
            <div class="calc-row total"><span>مبلغ خالص قابل پرداخت</span><b class="tnum">{{ Persian::num($shareToman) }}</b></div>
          </div>
        </div>

        <div class="card">
          <div class="card-head"><h3>سهم به تفکیک ایستگاه</h3></div>
          <div class="table-wrap">
            <table class="tbl">
              <thead><tr><th>ایستگاه</th><th>جلسات</th><th>انرژی</th><th>درآمد</th><th>سهم اپراتور</th></tr></thead>
              <tbody>
                @forelse($settlement->items as $item)
                  <tr>
                    <td class="cell-strong">{{ $item->station?->name ?? '—' }}</td>
                    <td class="tnum">{{ Persian::num($item->session_count ?? 0) }}</td>
                    <td class="tnum">{{ Persian::num((int) round($item->energy_kwh ?? 0)) }} kWh</td>
                    <td class="tnum">{{ Persian::num((int) round(($item->gross_revenue ?? 0) / 10)) }}</td>
                    <td class="tnum cell-strong" style="color:var(--green)">{{ Persian::num((int) round(($item->partner_share ?? 0) / 10)) }}</td>
                  </tr>
                @empty
                  <tr><td colspan="5" class="cell-muted">ریز ایستگاه ثبت نشده است.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div style="display:flex;flex-direction:column;gap:16px;align-self:start;position:sticky;top:84px">
        <div class="card card-pad">
          <div class="muted fs12">مبلغ قابل پرداخت</div>
          <div class="fs22 fw8 tnum mt-8" style="font-size:30px;color:var(--green)">{{ Persian::num($shareToman) }}</div>
          <div class="muted fs12">تومان</div>
          <hr class="sep">
          <div class="flex between mt-8" style="font-size:13px"><span class="muted">شناسهٔ تسویه</span><b class="mono">{{ $settlement->settlement_number }}</b></div>
          <div class="flex between mt-12" style="font-size:13px"><span class="muted">شمارهٔ شبا</span><b class="mono" dir="ltr">{{ $partner?->bank_iban ?? '—' }}</b></div>
          <div class="flex between mt-12" style="font-size:13px"><span class="muted">انرژی کل</span><b class="tnum">{{ Persian::num((int) round($settlement->energy_kwh)) }} kWh</b></div>
        </div>
      </div>
    </div>
    @endif
@endsection

@push('styles')
<style>
.bic{display:inline-grid;width:16px;height:16px}.bic svg{width:16px;height:16px}
.calc-row{display:flex;justify-content:space-between;align-items:center;font-size:13.5px;padding:9px 0;border-bottom:1px solid var(--border-2)}
.calc-row:last-child{border-bottom:none}
.calc-row.total{font-size:16px;font-weight:800;background:var(--accent-soft);margin:6px -8px 0;padding:13px 12px;border-radius:10px;color:var(--accent-strong)}
</style>
@endpush

@push('scripts')
<script>
(function(){
  document.querySelectorAll('[data-ic]').forEach(function(el){el.innerHTML=window.icon(el.getAttribute('data-ic'));});
})();
</script>
@endpush
