@extends('panel.layouts.app')

@section('title', 'جلسات شارژ')
@section('heading', 'جلسات شارژ محلی')

@section('content')
<div class="card">
    <div class="card-head"><h3>جلسات اخیر</h3><div class="ch-sub">شامل شارژ آفلاین مشتریان — پس از اتصال به ابر همگام می‌شود</div></div>
    <div class="card-pad" style="padding-top:0">
        <table class="table">
            <thead>
                <tr><th>وضعیت</th><th>کانکتور</th><th>شروع</th><th>پایان</th><th>انرژی</th><th>هزینه</th><th></th></tr>
            </thead>
            <tbody>
            @forelse($sessions as $s)
                @php
                    $conn = $s->connector;
                    $cp = $s->charger?->charge_point_id ?? '—';
                    $label = $cp.' · C'.($conn?->connector_number ?? '—');
                @endphp
                <tr data-uuid="{{ $s->uuid }}">
                    <td><span class="pill {{ $s->status === 'active' ? 'pill-green' : 'pill-accent' }}">{{ $s->status }}</span></td>
                    <td>{{ $label }}</td>
                    <td class="tnum">{{ $s->started_at?->format('Y/m/d H:i') ?? '—' }}</td>
                    <td class="tnum">{{ $s->stopped_at?->format('Y/m/d H:i') ?? '—' }}</td>
                    <td class="tnum">{{ \App\Support\Persian::num($s->energy_kwh) }} kWh</td>
                    <td class="tnum">{{ \App\Support\Persian::num((int) round($s->cost_amount / 10)) }} ت</td>
                    <td>
                        @if($s->status === 'active')
                        <button type="button" class="btn btn-sm stop-session" data-uuid="{{ $s->uuid }}">توقف</button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">جلسه‌ای ثبت نشده است.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function(){
  var stopTpl=@json(route('panel.api.sessions.stop', ['sessionUuid' => '__UUID__']));
  function csrf(){return document.querySelector('meta[name=csrf-token]').getAttribute('content');}
  document.querySelectorAll('.stop-session').forEach(function(btn){
    btn.addEventListener('click',function(){
      if(!confirm('جلسه شارژ متوقف شود؟'))return;
      var uuid=btn.getAttribute('data-uuid');
      btn.disabled=true;
      fetch(stopTpl.replace('__UUID__',uuid),{method:'POST',headers:{'Accept':'application/json','X-CSRF-TOKEN':csrf()}})
        .then(function(r){return r.json();})
        .then(function(j){if(j.ok)location.reload();else{alert(j.error||'خطا');btn.disabled=false;}})
        .catch(function(){alert('خطای شبکه');btn.disabled=false;});
    });
  });
})();
</script>
@endpush
