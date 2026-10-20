@extends('admin.layouts.app', ['page' => 'tariffs', 'title' => 'کارت‌های RFID', 'crumb' => 'تعرفه و RFID'])

@section('content')
<div class="flex between items-center mt-8" style="margin-bottom:18px">
    <a href="{{ route('admin.rfid.create') }}" class="btn btn-primary btn-sm">+ صدور کارت</a>
</div>
<div class="card"><div class="card-pad" style="padding:0">
<table class="tbl">
<thead><tr><th>idTag</th><th>کاربر</th><th>برچسب</th><th>وضعیت</th><th>صدور</th></tr></thead>
<tbody>
@forelse($cards as $card)
<tr>
<td class="tnum">{{ $card->id_tag }}</td>
<td>{{ $card->user?->name ?? '—' }}</td>
<td>{{ $card->card_label ?? '—' }}</td>
<td>{{ $card->status }}</td>
<td class="tnum">{{ $card->issued_at?->format('Y/m/d') }}</td>
</tr>
@empty
<tr><td colspan="5" class="muted" style="text-align:center;padding:24px">کارتی ثبت نشده است.</td></tr>
@endforelse
</tbody></table></div></div>
<div class="mt-16">{{ $cards->links() }}</div>
@endsection
