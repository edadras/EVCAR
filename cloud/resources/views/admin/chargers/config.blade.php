@extends('admin.layouts.app', ['page' => 'stations', 'title' => 'تنظیمات دستگاه '.$charger->charge_point_id, 'crumb' => $charger->station->name])

@section('content')
<div class="flex between items-center mt-8" style="margin-bottom:18px">
  <a href="{{ route('admin.chargers.show', $charger) }}" class="btn btn-sm">بازگشت به شارژر</a>
  <div class="flex gap-8 items-center">
    <span class="fs12 muted" id="cfgAt">@if(!empty($charger->meta['ocpp_config_at']))آخرین خواندن: {{ \Carbon\Carbon::parse($charger->meta['ocpp_config_at'])->format('Y-m-d H:i') }}@else هنوز از دستگاه خوانده نشده @endif</span>
    <button type="button" class="btn btn-sm btn-primary" id="btnRefresh" @disabled(!$connected)>خواندن تنظیمات از دستگاه</button>
  </div>
</div>

@if(!$connected)
<div class="card" style="margin-bottom:16px"><div class="card-pad fs13">شارژر الان به سرور متصل نیست؛ فهرست زیر آخرین مقادیر خوانده‌شده است و ارسال تغییر ممکن نیست. دستگاه پس از هر راه‌اندازی تنظیماتش را خودکار گزارش می‌دهد.</div></div>
@endif

<div class="card">
  <div class="card-head"><div><h3>تنظیمات OCPP دستگاه</h3><div class="ch-sub">مقادیر واقعی روی دستگاه (GetConfiguration). هر ردیف جداگانه با ChangeConfiguration ارسال می‌شود.</div></div>
    <input class="finput" id="cfgSearch" placeholder="جستجوی کلید…" style="max-width:220px" dir="ltr"></div>
  <div class="card-pad" style="padding:0">
    <table class="tbl" id="cfgTbl">
      <thead><tr><th style="width:26%">کلید</th><th>توضیح</th><th style="width:30%">مقدار</th><th style="width:150px"></th></tr></thead>
      <tbody id="cfgBody"></tbody>
    </table>
    <div class="card-pad fs13 muted" id="cfgEmpty" style="display:none">فهرستی موجود نیست. وقتی شارژر آنلاین است «خواندن تنظیمات از دستگاه» را بزنید.</div>
  </div>
</div>

<div class="card mt-16">
  <div class="card-head"><div><h3>ارسال کلید دلخواه</h3><div class="ch-sub">برای کلیدهای اختصاصی سازنده که در فهرست بالا نیستند.</div></div></div>
  <div class="card-pad flex gap-8 items-center" style="flex-wrap:wrap">
    <input class="finput" id="customKey" placeholder="Key" dir="ltr" style="max-width:260px">
    <input class="finput" id="customVal" placeholder="Value" dir="ltr" style="max-width:320px">
    <button type="button" class="btn btn-sm" id="btnCustom" @disabled(!$connected)>ارسال</button>
    <span class="fs12" id="customMsg"></span>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function(){
  var rows = @json($rows);
  var connected = @json($connected);
  var updateUrl = @json(route('admin.chargers.config.update', $charger));
  var refreshUrl = @json(route('admin.chargers.config.refresh', $charger));
  function csrf(){var m=document.querySelector('meta[name=csrf-token]');return m?m.getAttribute('content'):'';}
  function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
  var body=document.getElementById('cfgBody');

  function input(r,i){
    var dis=(r.readonly||!connected)?' disabled':'';
    if(r.type==='bool'){
      var v=String(r.value).toLowerCase();
      return '<select class="fselect" data-i="'+i+'"'+dis+'><option value="true"'+(v==='true'?' selected':'')+'>true (فعال)</option><option value="false"'+(v==='false'?' selected':'')+'>false (غیرفعال)</option></select>';
    }
    if(r.type==='int'){
      return '<div class="flex gap-8 items-center"><input class="finput tnum" type="number" data-i="'+i+'" min="'+(r.min==null?0:r.min)+'" value="'+esc(r.value)+'" dir="ltr"'+dis+'>'+(r.unit?'<span class="fs12 muted">'+esc(r.unit)+'</span>':'')+'</div>';
    }
    return '<input class="finput" data-i="'+i+'" value="'+esc(r.value)+'" dir="ltr"'+dis+'>';
  }

  function render(){
    var q=(document.getElementById('cfgSearch').value||'').toLowerCase();
    var html='';
    rows.forEach(function(r,i){
      if(q && r.key.toLowerCase().indexOf(q)<0 && String(r.label||'').indexOf(q)<0) return;
      html+='<tr><td class="tnum" dir="ltr" style="text-align:right;word-break:break-all">'+esc(r.key)+(r.risky?' <span class="pill pill-amber" title="تغییر اشتباه ممکن است اتصال دستگاه را قطع کند">حساس</span>':'')+'</td>'
        +'<td class="fs12">'+(r.label?esc(r.label):'<span class="muted">اختصاصی سازنده</span>')+'</td>'
        +'<td>'+input(r,i)+'</td>'
        +'<td>'+(r.readonly?'<span class="fs12 muted">فقط‌خواندنی</span>':'<button type="button" class="btn btn-sm" data-send="'+i+'"'+(connected?'':' disabled')+'>ارسال</button>')+'<div class="fs12 mt-4" data-msg="'+i+'"></div></td></tr>';
    });
    body.innerHTML=html;
    document.getElementById('cfgEmpty').style.display=rows.length?'none':'block';
  }

  function send(key,value,msgEl,btn){
    msgEl.style.color='';msgEl.textContent='در حال ارسال…';if(btn)btn.disabled=true;
    return fetch(updateUrl,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf()},body:JSON.stringify({key:key,value:value})})
      .then(function(r){return r.json().then(function(j){return j;});})
      .then(function(j){
        var msg=j.message||(j.errors?Object.values(j.errors).flat().join(' '):'خطا');
        msgEl.textContent=(j.status?j.status+' — ':'')+msg;
        msgEl.style.color=j.ok?'var(--green,#16a34a)':'var(--red,#dc2626)';
        return j;
      })
      .catch(function(){msgEl.textContent='ارتباط با سرور برقرار نشد.';msgEl.style.color='var(--red,#dc2626)';})
      .finally(function(){if(btn)btn.disabled=false;});
  }

  body.addEventListener('click',function(e){
    var b=e.target.closest('[data-send]');if(!b)return;
    var i=+b.getAttribute('data-send'),r=rows[i];
    var el=body.querySelector('[data-i="'+i+'"]');var v=el?el.value:'';
    if(String(v)===String(r.value==null?'':r.value) && !confirm('مقدار تغییری نکرده؛ دوباره ارسال شود؟'))return;
    if(r.risky && !confirm('«'+r.key+'» تنظیم حساس است. مقدار اشتباه ممکن است ارتباط شارژر با سرور را قطع کند و فقط با مراجعهٔ حضوری قابل اصلاح باشد.\n\nمقدار جدید: '+v+'\n\nارسال شود؟'))return;
    send(r.key,v,body.querySelector('[data-msg="'+i+'"]'),b).then(function(j){if(j&&j.ok){r.value=v;}});
  });

  document.getElementById('cfgSearch').addEventListener('input',render);

  document.getElementById('btnCustom').addEventListener('click',function(){
    var k=document.getElementById('customKey').value.trim(),v=document.getElementById('customVal').value;
    if(!k){alert('کلید را وارد کنید.');return;}
    if(!confirm('ارسال '+k+' = '+v+' به دستگاه؟'))return;
    send(k,v,document.getElementById('customMsg'),this);
  });

  document.getElementById('btnRefresh').addEventListener('click',function(){
    var b=this,at=document.getElementById('cfgAt');b.disabled=true;at.textContent='در حال خواندن از دستگاه…';
    fetch(refreshUrl,{method:'POST',headers:{'Accept':'application/json','X-CSRF-TOKEN':csrf()}})
      .then(function(r){return r.json();})
      .then(function(j){
        if(j.ok){rows=j.rows;render();at.textContent='خوانده شد: '+j.at+' ('+rows.length+' کلید)';}
        else{at.textContent=j.message||'خطا در خواندن';}
      })
      .catch(function(){at.textContent='ارتباط با سرور برقرار نشد.';})
      .finally(function(){b.disabled=false;});
  });

  render();
})();
</script>
@endpush
