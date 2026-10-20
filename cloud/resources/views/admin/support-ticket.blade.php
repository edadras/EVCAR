@extends('admin.layouts.app', ['page' => 'support', 'title' => 'تیکت '.$ticket->ticket_number, 'crumb' => 'مرکز پشتیبانی'])

@section('content')
@php use App\Support\Persian; @endphp
<div class="flex between items-center mt-8" style="margin-bottom:16px">
    <div>
        <a href="{{ route('admin.support') }}" class="btn btn-sm">→ بازگشت به تیکت‌ها</a>
        <span class="pill" style="margin-inline-start:8px">{{ $labels['sources'][$ticket->source] ?? $ticket->source }}</span>
        <span class="muted fs12" style="margin-inline-start:8px">ایجاد {{ Persian::digits($ticket->created_at?->locale('fa')->diffForHumans() ?? '') }}
            @if($ticket->first_response_at) · اولین پاسخ پس از {{ Persian::digits($ticket->created_at->diffInMinutes($ticket->first_response_at, true) >= 1 ? (int) $ticket->created_at->diffInMinutes($ticket->first_response_at, true) : 0) }} دقیقه @else · <b style="color:var(--orange)">هنوز پاسخ داده نشده</b>@endif
        </span>
    </div>
</div>

<div class="grid col-2-1">
    <div style="display:flex;flex-direction:column;gap:16px">
        <div class="card">
            <div class="card-head"><div><h3>{{ $ticket->subject }}</h3><div class="ch-sub">{{ $labels['categories'][$ticket->category] ?? $ticket->category }}@if($ticket->station) · {{ $ticket->station->name }}@endif</div></div></div>
            <div class="card-pad thread">
                @forelse($ticket->messages->sortBy('id') as $m)
                    <div class="tm {{ $m->is_internal ? 'internal' : ($m->is_staff_reply ? 'staff' : 'cust') }}">
                        <div class="tm-head">
                            <b>{{ $m->is_internal ? '🔒 یادداشت داخلی · ' : '' }}{{ $m->user?->name ?? '—' }}</b>
                            <span class="muted fs12">{{ Persian::digits($m->created_at?->format('Y/m/d H:i') ?? '') }}</span>
                        </div>
                        <div class="tm-body">{{ $m->message }}</div>
                    </div>
                @empty
                    <div class="tm cust"><div class="tm-body">{{ $ticket->body }}</div></div>
                @endforelse
            </div>
        </div>

        <form class="card" method="POST" action="{{ route('admin.support.reply', $ticket) }}" id="replyForm">@csrf
            <div class="card-head">
                <h3>پاسخ به مشتری</h3>
                <div class="head-actions" style="gap:8px">
                    <select class="fselect" id="cannedSel" style="max-width:220px">
                        <option value="">پاسخ‌های آماده…</option>
                        @foreach($canned as $c)<option value="{{ $c['text'] }}">{{ $c['title'] }}</option>@endforeach
                    </select>
                    <button type="button" class="btn btn-sm" id="draftBtn" title="{{ $aiEnabled ? 'پیش‌نویس با هوش مصنوعی از روی گفتگو و اطلاعات حساب مشتری' : 'کلید Claude API تنظیم نشده؛ فقط قالب ساده' }}">✦ پیش‌نویس هوشمند</button>
                </div>
            </div>
            <div class="card-pad">
                <textarea class="finput" name="message" id="replyText" rows="6" required placeholder="متن پاسخ…" style="width:100%;resize:vertical;min-height:130px"></textarea>
                <div class="flex between items-center mt-8" style="flex-wrap:wrap;gap:10px">
                    <label class="fs13" style="display:flex;align-items:center;gap:6px"><input type="checkbox" name="internal" value="1" id="internalChk"> یادداشت داخلی (مشتری نمی‌بیند)</label>
                    <div class="flex gap-8 items-center">
                        <select class="fselect" name="status" style="max-width:200px">
                            <option value="">وضعیت: بدون تغییر</option>
                            @foreach($labels['statuses'] as $k => $l)<option value="{{ $k }}">وضعیت پس از ارسال: {{ $l }}</option>@endforeach
                        </select>
                        <button class="btn btn-primary" type="submit" id="sendReply">ارسال پاسخ</button>
                    </div>
                </div>
            </div>
        </form>

        @if($conversation)
        <div class="card">
            <div class="card-head"><h3>✦ گفتگوی مشتری با دستیار هوشمند</h3><a class="btn btn-sm" href="{{ route('admin.support.ai.show', $conversation->uuid) }}">مشاهدهٔ کامل</a></div>
            <div class="card-pad muted fs13">این تیکت از گفتگو با دستیار هوشمند ایجاد شده؛ متن کامل گفتگو در اولین پیام تیکت پیوست است.</div>
        </div>
        @endif
    </div>

    <div style="display:flex;flex-direction:column;gap:16px">
        <form class="card" method="POST" action="{{ route('admin.support.update', $ticket) }}">@csrf
            <div class="card-head"><h3>مشخصات تیکت</h3></div>
            <div class="card-pad" style="display:flex;flex-direction:column;gap:10px">
                <div class="ffield"><label>وضعیت</label><select class="fselect" name="status">@foreach($labels['statuses'] as $k => $l)<option value="{{ $k }}" @selected($ticket->status === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="ffield"><label>اولویت</label><select class="fselect" name="priority">@foreach($labels['priorities'] as $k => $l)<option value="{{ $k }}" @selected($ticket->priority === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="ffield"><label>دسته</label><select class="fselect" name="category">@foreach($labels['categories'] as $k => $l)<option value="{{ $k }}" @selected($ticket->category === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="ffield"><label>کارشناس مسئول</label><select class="fselect" name="assigned_to"><option value="">تعیین نشده</option>@foreach($staff as $s)<option value="{{ $s->id }}" @selected($ticket->assigned_to === $s->id)>{{ $s->name }} ({{ $s->role?->label() }})</option>@endforeach</select></div>
                <button class="btn" type="submit">ذخیرهٔ تغییرات</button>
            </div>
        </form>

        @if($customer)
        <div class="card">
            <div class="card-head"><h3>پروندهٔ مشتری</h3></div>
            <div class="card-pad">
                <div class="fw7 fs16">{{ $customer->name }}</div>
                <div class="muted fs13 tnum">{{ Persian::digits($customer->phone ?? '') }}</div>
                <div class="grid g-2 mt-16">
                    <div class="mini"><div class="muted fs12">موجودی</div><div class="fw7 tnum">{{ Persian::money((int) round(($customer->wallet?->balance ?? 0) / 10)) }} ت</div></div>
                    <div class="mini"><div class="muted fs12">جلسات شارژ</div><div class="fw7 tnum">{{ Persian::digits($customer->chargingSessions()->count()) }}</div></div>
                </div>
                @foreach($customer->vehicles as $v)
                    <div class="fs13 mt-8">🚗 {{ $v->brand }} {{ $v->model }} · <span class="tnum">{{ Persian::digits("{$v->plate_two} {$v->plate_letter} {$v->plate_three} | {$v->plate_province}") }}</span></div>
                @endforeach
                <div class="section-title mt-16" style="margin-bottom:6px">آخرین جلسات</div>
                @forelse($sessions as $s)
                    <div class="sess"><span>{{ $s->station?->name ?? '—' }}<span class="muted fs12"> · {{ Persian::digits($s->started_at?->format('m/d H:i') ?? '') }}</span></span>
                        <span class="tnum fs12">{{ Persian::kwh($s->energy_kwh) }} kWh · {{ Persian::money((int) round($s->cost_amount / 10)) }} ت @if($s->status !== 'stopped' && $s->status !== 'completed')<span class="pill pill-amber">{{ $s->status }}</span>@endif</span></div>
                @empty
                    <div class="muted fs13">جلسه‌ای ندارد.</div>
                @endforelse
                @if($otherTickets->isNotEmpty())
                    <div class="section-title mt-16" style="margin-bottom:6px">تیکت‌های دیگر</div>
                    @foreach($otherTickets as $o)
                        <div class="fs13"><a href="{{ route('admin.support.ticket', $o) }}">{{ $o->ticket_number }}</a> · {{ \Illuminate\Support\Str::limit($o->subject, 30) }} <span class="muted fs12">({{ $labels['statuses'][$o->status] ?? $o->status }})</span></div>
                    @endforeach
                @endif
            </div>
        </div>

        <form class="card" method="POST" action="{{ route('admin.support.credit', $ticket) }}" onsubmit="return confirm('مبلغ به کیف پول مشتری اضافه شود؟')">@csrf
            <div class="card-head"><div><h3>استرداد / اعتبار جبرانی</h3><div class="ch-sub">به کیف پول مشتری اضافه و در تیکت و ممیزی ثبت می‌شود</div></div></div>
            <div class="card-pad" style="display:flex;flex-direction:column;gap:10px">
                <div class="grid g-2">
                    <div class="ffield"><label>مبلغ (تومان)</label><input class="finput tnum" name="amount" type="number" min="1000" max="5000000" required></div>
                    <div class="ffield"><label>نوع</label><select class="fselect" name="type"><option value="refund">استرداد</option><option value="compensation">اعتبار جبرانی</option></select></div>
                </div>
                <div class="ffield"><label>علت</label><input class="finput" name="reason" maxlength="200" required placeholder="مثلاً کسر دوبارهٔ هزینهٔ جلسه"></div>
                <button class="btn btn-orange" type="submit">ثبت و واریز</button>
            </div>
        </form>
        @endif
    </div>
</div>
@endsection

@push('styles')
<style>
.grid.col-2-1>*{min-width:0}
.thread{display:flex;flex-direction:column;gap:12px;max-height:560px;overflow:auto}
.tm{border-radius:14px;padding:12px 14px;max-width:88%}
.tm.cust{background:var(--surface-2);border:1px solid var(--border);align-self:flex-start}
.tm.staff{background:var(--accent-soft);align-self:flex-end}
.tm.internal{background:var(--amber-soft,#FBF1DD);border:1px dashed var(--amber,#D97706);align-self:flex-end}
.tm-head{display:flex;gap:10px;justify-content:space-between;margin-bottom:6px;font-size:13px}
.tm-body{white-space:pre-wrap;line-height:1.9;font-size:13.5px}
.mini{background:var(--surface-2);border-radius:12px;padding:10px}
.sess{display:flex;justify-content:space-between;gap:8px;padding:6px 0;border-bottom:1px solid var(--border-2);font-size:13px}
</style>
@endpush

@push('scripts')
<script>
(function(){
  var text=document.getElementById('replyText');
  document.getElementById('cannedSel').addEventListener('change',function(){if(this.value){text.value=this.value;text.focus();this.value='';}});
  document.getElementById('internalChk').addEventListener('change',function(){document.getElementById('sendReply').textContent=this.checked?'ثبت یادداشت داخلی':'ارسال پاسخ';});
  var btn=document.getElementById('draftBtn');
  btn.addEventListener('click',function(){
    btn.disabled=true;var old=btn.textContent;btn.textContent='در حال نوشتن…';
    fetch(@json(route('admin.support.draft', $ticket)),{method:'POST',headers:{'Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}})
      .then(function(r){return r.json();}).then(function(d){text.value=d.text||'';text.focus();})
      .catch(function(){alert('دریافت پیش‌نویس ناموفق بود.');})
      .finally(function(){btn.disabled=false;btn.textContent=old;});
  });
})();
</script>
@endpush
