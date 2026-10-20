@extends('admin.layouts.app', ['page' => 'alerts', 'title' => 'هشدارهای عملیاتی', 'crumb' => 'مدیریت شبکه'])

@section('content')
@php use App\Support\Persian; @endphp
<div class="kpi-grid mt-8">
  <div class="mini-stat"><div class="ms-ic ic-orange" data-ic="bell"></div><div><div class="ms-v tnum">{{ Persian::num($counts['open']) }}</div><div class="ms-l">هشدار باز</div></div></div>
  <div class="mini-stat"><div class="ms-ic ic-red" data-ic="activity"></div><div><div class="ms-v tnum">{{ Persian::num($counts['critical']) }}</div><div class="ms-l">بحرانی</div></div></div>
  <div class="mini-stat"><div class="ms-ic ic-blue" data-ic="clock"></div><div><div class="ms-v tnum">{{ Persian::num($counts['today']) }}</div><div class="ms-l">ثبت‌شده امروز</div></div></div>
</div>

<div class="card mt-16">
  <div class="card-head">
    <div><h3>فهرست هشدارها</h3><div class="ch-sub">قطع شارژر، خطای کانکتور و قطع ارتباط کنترلر ایستگاه — خودکار باز و بسته می‌شوند</div></div>
    <div class="seg">
      @foreach(['open' => 'باز', 'resolved' => 'بسته‌شده', 'all' => 'همه'] as $key => $label)
        <a href="{{ route('admin.alerts.index', ['status' => $key]) }}" class="{{ $status === $key ? 'active' : '' }}" style="text-decoration:none;padding:6px 12px">{{ $label }}</a>
      @endforeach
    </div>
  </div>
  <table class="tbl">
    <thead><tr><th>شدت</th><th>موضوع</th><th>پیام</th><th>ایستگاه</th><th>تکرار</th><th>آخرین مشاهده</th><th></th></tr></thead>
    <tbody>
    @forelse($alerts as $alert)
      <tr>
        <td><span class="pill {{ $alert->severity === 'critical' ? 'pill-red' : ($alert->severity === 'warning' ? 'pill-amber' : 'pill-accent') }}">{{ ['critical' => 'بحرانی', 'warning' => 'هشدار', 'info' => 'اطلاع'][$alert->severity] ?? $alert->severity }}</span></td>
        <td class="tnum" dir="ltr">{{ $alert->subject_key }}</td>
        <td>{{ $alert->message }}</td>
        <td>{{ $alert->station?->name ?? '—' }}</td>
        <td class="tnum">{{ Persian::num($alert->occurrences) }}</td>
        <td class="tnum">{{ Persian::digits($alert->last_seen_at?->diffForHumans() ?? '—') }}</td>
        <td>
          @if($alert->status === 'open')
          <form method="POST" action="{{ route('admin.alerts.resolve', $alert) }}">@csrf<button class="btn btn-sm" type="submit">بستن</button></form>
          @else
          <span class="muted fs12">بسته شد</span>
          @endif
        </td>
      </tr>
    @empty
      <tr><td colspan="7" class="muted" style="text-align:center;padding:28px">هشداری وجود ندارد.</td></tr>
    @endforelse
    </tbody>
  </table>
  <div class="card-pad">{{ $alerts->links() }}</div>
</div>
@endsection
