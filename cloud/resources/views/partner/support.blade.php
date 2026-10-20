@extends('partner.layouts.app', ['page' => 'support', 'title' => 'پشتیبانی', 'crumb' => 'پنل شرکا'])

@section('content')

<div class="card">
    <div class="card-pad" style="padding:0">
        <table class="tbl">
            <thead><tr><th>شماره</th><th>موضوع</th><th>ایستگاه</th><th>وضعیت</th></tr></thead>
            <tbody>
            @forelse($tickets as $t)
                <tr>
                    <td class="tnum">{{ $t->ticket_number }}</td>
                    <td>{{ $t->subject }}</td>
                    <td>{{ $t->station?->name }}</td>
                    <td><span class="pill">{{ $t->status }}</span></td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted" style="text-align:center;padding:24px">تیکتی نیست</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
