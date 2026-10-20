@if(!empty($auditRows) && count($auditRows))
<div class="sec-title mt24">سوابق تغییرات</div>
<div class="hist-card">
    @foreach($auditRows as $row)
    <div class="hist-row">
        <div class="hist-ic {{ $row['color'] }}" data-ic="{{ $row['icon'] }}"></div>
        <div class="hist-main">
            <div class="fw7 fs13">{{ $row['title'] }}</div>
            <div class="fs12 muted mt4" style="line-height:1.6">{{ $row['detail'] }}</div>
        </div>
        <span class="hist-time fs11 muted">{{ $row['time'] }}</span>
    </div>
    @endforeach
</div>
@endif
