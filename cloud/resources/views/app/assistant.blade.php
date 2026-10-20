@extends('app.layouts.mobile', ['noTab' => true])

@section('title', 'SafirCharge — دستیار هوشمند')

@section('phone')
<div class="appbar chat-bar" style="padding-top:2px">
    <a href="{{ route('app.support') }}" class="ab-btn"><span data-ic="back"></span></a>
    <div class="chat-agent">
        <span class="ca-av ai-av">✦</span>
        <div>
            <div class="fw7 fs13">دستیار هوشمند SafirCharge</div>
            <div class="fs12" style="color:var(--green)"><span class="onl"></span><span id="aiState">{{ $initial['ai_enabled'] ? 'پاسخ فوری ۲۴ ساعته' : 'پاسخ از پرسش‌های متداول' }}</span></div>
        </div>
    </div>
    <button type="button" class="ab-btn" id="newChat" title="گفتگوی تازه"><span data-ic="plus"></span></button>
</div>

<div class="chat-scroll" id="chat"></div>

<div class="ai-bar" id="escalateBar">
    <button type="button" id="escalateBtn"><span data-ic="headset"></span> گفتگو با کارشناس انسانی</button>
</div>
<form class="chat-input" id="aiForm" autocomplete="off">
    <input id="msgInput" maxlength="2000" placeholder="سؤال خود را بپرسید…" required>
    <button type="submit" class="ci-send" id="sendBtn"><span data-ic="nav"></span></button>
</form>
@endsection

@push('styles')
<style>
.chat-bar{background:var(--surface);border-bottom:1px solid var(--border);gap:10px}
.chat-agent{flex:1;display:flex;align-items:center;gap:10px}
.ca-av{width:40px;height:40px;border-radius:12px;display:grid;place-items:center;flex:none}
.ai-av{background:linear-gradient(140deg,var(--accent),var(--violet));color:#fff;font-size:20px}
.onl{display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--green);margin-inline-end:5px}
.chat-scroll{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:10px;background:var(--bg)}
.msg{display:flex;flex-direction:column;max-width:84%}
.msg .bub{padding:11px 14px;font-size:13.5px;line-height:1.85;border-radius:18px;white-space:pre-wrap;word-break:break-word}
.msg .time{font-size:10px;color:var(--muted);margin-top:3px}
.msg .tools{font-size:10.5px;color:var(--muted);margin-top:3px}
.msg.bot{align-self:flex-start;align-items:flex-start}
.msg.bot .bub{background:var(--surface);border:1px solid var(--border);border-bottom-right-radius:6px;color:var(--text)}
.msg.me{align-self:flex-end;align-items:flex-end}
.msg.me .bub{background:var(--accent);color:#fff;border-bottom-left-radius:6px}
.msg.sys .bub{background:var(--green-soft);color:var(--green);font-weight:600}
.typing .bub{display:flex;gap:5px}.typing i{width:7px;height:7px;border-radius:50%;background:var(--muted);animation:tp 1s infinite}
.typing i:nth-child(2){animation-delay:.15s}.typing i:nth-child(3){animation-delay:.3s}
@keyframes tp{0%,100%{opacity:.3}50%{opacity:1}}
.quick{display:flex;flex-wrap:wrap;gap:8px;margin-top:4px}
.quick button{border:1px solid var(--accent);background:var(--accent-soft);color:var(--accent-strong);font-family:inherit;font-size:12.5px;font-weight:600;padding:8px 13px;border-radius:20px;cursor:pointer}
.ai-bar{flex:none;padding:8px 14px 0;background:var(--surface);border-top:1px solid var(--border)}
.ai-bar button{width:100%;height:38px;border-radius:12px;border:1px dashed var(--border);background:var(--surface-2);color:var(--text-2);font-family:inherit;font-weight:700;font-size:12.5px;display:flex;align-items:center;justify-content:center;gap:6px;cursor:pointer}
.ai-bar svg{width:16px;height:16px}
.chat-input{flex:none;display:flex;align-items:center;gap:9px;padding:10px 14px calc(14px + env(safe-area-inset-bottom,0));background:var(--surface)}
.chat-input input{flex:1;height:46px;border:1.5px solid var(--border);background:var(--surface-2);border-radius:23px;padding:0 16px;font-family:inherit;font-size:14px;color:var(--text);outline:none}
.chat-input input:focus{border-color:var(--accent)}
.ci-send{width:46px;height:46px;border-radius:50%;border:none;background:var(--accent);color:#fff;display:grid;place-items:center;flex:none;cursor:pointer}.ci-send svg{width:20px;height:20px;transform:scaleX(-1)}
.ci-send:disabled{opacity:.5}
</style>
@endpush

@push('scripts')
<script>
(function(){
  var state=@json($initial);
  var urls={send:@json(route('app.api.assistant.send')),escalate:@json(route('app.api.assistant.escalate')),reset:@json(route('app.api.assistant.reset')),ticket:@json(route('app.ticket'))};
  var csrf=document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  var chat=document.getElementById('chat'),input=document.getElementById('msgInput'),form=document.getElementById('aiForm'),send=document.getElementById('sendBtn');
  var busy=false;
  function fa(s){return String(s).replace(/[0-9]/g,function(d){return '۰۱۲۳۴۵۶۷۸۹'[d];});}
  function esc(s){var d=document.createElement('div');d.textContent=s;return d.innerHTML;}
  var toolNames={get_account_summary:'حساب شما',get_recent_sessions:'سوابق شارژ',get_wallet_transactions:'تراکنش‌ها',find_stations:'ایستگاه‌ها',get_tariffs:'تعرفه',list_my_tickets:'تیکت‌ها',create_support_ticket:'ثبت تیکت'};
  function render(){
    var html='';
    if(!state.messages.length){
      html+='<div class="msg bot"><div class="bub">سلام! من دستیار هوشمند SafirCharge هستم ✦\nدربارهٔ شارژ، کیف پول، ایستگاه‌ها یا هر مشکلی بپرسید؛ اطلاعات حساب شما را هم بررسی می‌کنم.</div></div>';
      html+='<div class="quick">'+state.suggestions.map(function(s){return '<button type="button">'+esc(s)+'</button>';}).join('')+'</div>';
    }
    state.messages.forEach(function(m){
      var tools=(m.tools||[]).map(function(t){return toolNames[t]||t;});
      html+='<div class="msg '+(m.role==='user'?'me':'bot')+'"><div class="bub">'+esc(m.text)+'</div>'+
        (tools.length?'<span class="tools">🔎 بررسی '+tools.join('، ')+'</span>':'')+
        (m.time?'<span class="time">'+fa(m.time)+'</span>':'')+'</div>';
    });
    if(state.ticket){
      html+='<div class="msg sys"><div class="bub">درخواست شما با شمارهٔ '+esc(state.ticket.number)+' برای کارشناس ثبت شد. <a href="'+urls.ticket+'?id='+state.ticket.id+'">پیگیری</a></div></div>';
    }
    if(busy)html+='<div class="msg bot typing"><div class="bub"><i></i><i></i><i></i></div></div>';
    chat.innerHTML=html;
    chat.querySelectorAll('.quick button').forEach(function(b){b.addEventListener('click',function(){input.value=b.textContent;submit();});});
    document.getElementById('escalateBar').style.display=state.ticket?'none':'';
    chat.scrollTop=chat.scrollHeight;
  }
  function post(url,body){
    return fetch(url,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(body||{})})
      .then(function(r){return r.json().then(function(d){if(!r.ok)throw new Error(d.message||'خطا در ارتباط');return d;});});
  }
  function submit(){
    var text=input.value.trim();
    if(!text||busy)return;
    input.value='';busy=true;send.disabled=true;
    state.messages.push({role:'user',text:text,tools:[]});render();
    post(urls.send,{message:text,conversation:state.conversation}).then(function(d){state=d;})
      .catch(function(e){state.messages.push({role:'assistant',text:e.message,tools:[]});})
      .finally(function(){busy=false;send.disabled=false;render();input.focus();});
  }
  form.addEventListener('submit',function(e){e.preventDefault();submit();});
  document.getElementById('escalateBtn').addEventListener('click',function(){
    if(!state.messages.length){alert('ابتدا مشکل خود را بنویسید تا با متن گفتگو برای کارشناس ثبت شود.');input.focus();return;}
    if(!confirm('درخواست شما با متن همین گفتگو برای کارشناس پشتیبانی ثبت شود؟'))return;
    post(urls.escalate,{conversation:state.conversation}).then(function(d){state=d;render();}).catch(function(e){alert(e.message);});
  });
  document.getElementById('newChat').addEventListener('click',function(){
    post(urls.reset).then(function(d){state=d;render();});
  });
  render();
})();
</script>
@endpush
