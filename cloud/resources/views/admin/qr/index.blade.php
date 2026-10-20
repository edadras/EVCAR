@extends('admin.layouts.app', ['page' => 'tariffs', 'title' => 'کدهای QR', 'crumb' => 'QR'])

@section('content')
<div class="flex between items-center mt-8" style="margin-bottom:18px">
    <a href="{{ route('admin.qr.create') }}" class="btn btn-primary btn-sm">+ QR جدید</a>
</div>
<div class="card"><div class="card-pad" style="padding:0">
<table class="tbl">
<thead><tr><th>توکن</th><th>ایستگاه</th><th>لینک</th><th>آفلاین</th><th>وضعیت</th></tr></thead>
<tbody>
@forelse($tokens as $t)
<tr>
<td class="tnum" style="font-size:12px">{{ $t->token }}</td>
<td>{{ $t->station?->name }}</td>
<td style="font-size:12px;max-width:200px;overflow:hidden">{{ $t->deep_link }}</td>
<td>{{ $t->offline_capable ? 'بله' : 'خیر' }}</td>
<td>{{ $t->status }}</td>
</tr>
@empty
<tr><td colspan="5" class="muted" style="text-align:center;padding:24px">QR ثبت نشده است.</td></tr>
@endforelse
</tbody></table></div></div>
<div class="mt-16">{{ $tokens->links() }}</div>
@endsection
