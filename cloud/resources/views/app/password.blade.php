@extends('app.layouts.mobile', ['noTab' => true])

@section('title', 'SafirCharge — تغییر رمز عبور')

@section('phone')
<div class="appbar" style="padding-top:2px">
    <a href="{{ route('app.account') }}" class="ab-btn"><span data-ic="back"></span></a>
    <div style="flex:1"><div class="ab-title">تغییر رمز عبور</div></div>
</div>

<form method="POST" action="{{ route('app.password.update') }}">
@csrf
<div class="screen">
    @if(session('success'))
    <div class="note-box" style="margin-bottom:14px;border-color:color-mix(in srgb,var(--green) 22%,transparent);background:var(--green-soft)">
        <span data-ic="check"></span><span>{{ session('success') }}</span>
    </div>
    @endif
    @if($errors->any())
    <div class="note-box" style="margin-bottom:14px;border-color:color-mix(in srgb,var(--red) 22%,transparent);background:var(--red-soft)">
        <span data-ic="x"></span><span>{{ $errors->first() }}</span>
    </div>
    @endif

    <div class="center-col rise" style="margin:8px 0 20px">
        <div class="key-badge" data-ic="key"></div>
        <div class="fs13 muted" style="text-align:center;max-width:260px;line-height:1.7;margin-top:12px">رمز جدید باید حداقل ۸ کاراکتر و شامل حروف و عدد باشد.</div>
    </div>

    <div class="card card-p">
        <div class="field"><label>رمز عبور فعلی</label>
            <div class="pw-field"><input class="input" type="password" name="current_password" id="cur" placeholder="رمز فعلی" required><button type="button" class="pw-eye" data-t="cur"><span data-ic="eye"></span></button></div>
        </div>
        <div class="field"><label>رمز عبور جدید</label>
            <div class="pw-field"><input class="input" type="password" name="password" id="new" placeholder="رمز جدید" required minlength="8"><button type="button" class="pw-eye" data-t="new"><span data-ic="eye"></span></button></div>
            <div class="pw-meter"><i id="bar"></i></div>
            <div class="fs12 muted" id="strength">قدرت رمز: —</div>
        </div>
        <div class="field" style="margin-bottom:0"><label>تکرار رمز جدید</label>
            <div class="pw-field"><input class="input" type="password" name="password_confirmation" id="rep" placeholder="تکرار رمز جدید" required><button type="button" class="pw-eye" data-t="rep"><span data-ic="eye"></span></button></div>
            <div class="fs12" id="match" style="margin-top:6px"></div>
        </div>
    </div>

    <div class="note-box mt16">
        <span data-ic="shield"></span>
        <span>پس از تغییر رمز، از همهٔ دستگاه‌های دیگر خارج می‌شوید.</span>
    </div>
</div>

<div class="savebar">
    <button type="submit" class="btn"><span data-ic="check"></span> ذخیرهٔ رمز جدید</button>
</div>
</form>
@endsection

@push('styles')
<style>
.key-badge{width:80px;height:80px;border-radius:26px;background:var(--accent-soft);color:var(--accent-strong);display:grid;place-items:center}
.key-badge svg{width:38px;height:38px}
.pw-field{position:relative}
.pw-field .input{padding-inline-start:46px;width:100%;border:1.5px solid var(--border);background:var(--surface-2);border-radius:13px;height:50px;font-family:inherit;font-size:14px;color:var(--text)}
.pw-eye{position:absolute;inset-inline-start:6px;top:50%;transform:translateY(-50%);width:34px;height:34px;border:none;background:none;color:var(--muted);display:grid;place-items:center}
.pw-eye svg{width:19px;height:19px}
.pw-meter{height:5px;border-radius:5px;background:var(--surface-3);overflow:hidden;margin:10px 0 6px}
.pw-meter i{display:block;height:100%;width:0;border-radius:5px;background:var(--red);transition:all .2s}
.note-box{display:flex;align-items:flex-start;gap:10px;background:var(--surface-2);border:1px solid var(--border-2);border-radius:14px;padding:13px;font-size:12px;color:var(--text-2);line-height:1.7}
.note-box svg{width:19px;height:19px;color:var(--accent);flex:none;margin-top:1px}
.savebar{position:absolute;bottom:0;left:0;right:0;background:color-mix(in srgb,var(--surface) 94%,transparent);backdrop-filter:blur(14px);border-top:1px solid var(--border);padding:14px 20px calc(20px + env(safe-area-inset-bottom,0));z-index:20}
.savebar .btn svg{width:18px;height:18px}
</style>
@endpush

@push('scripts')
<script>
(function(){
  document.querySelectorAll('.pw-eye').forEach(function(b){
    b.addEventListener('click',function(){
      var inp=document.getElementById(b.getAttribute('data-t'));
      inp.type=inp.type==='password'?'text':'password';
    });
  });
  var newI=document.getElementById('new'),rep=document.getElementById('rep');
  newI.addEventListener('input',function(){
    var v=this.value,score=0;
    if(v.length>=8)score++;if(/[0-9]/.test(v))score++;if(/[a-zA-Z]/.test(v))score++;if(/[^a-zA-Z0-9]/.test(v))score++;
    var pct=[0,30,55,80,100][score],col=['var(--red)','var(--red)','var(--amber)','var(--green)','var(--green)'][score];
    var lbl=['—','ضعیف','متوسط','خوب','قوی'][score];
    var bar=document.getElementById('bar');bar.style.width=pct+'%';bar.style.background=col;
    document.getElementById('strength').textContent='قدرت رمز: '+lbl;
    checkMatch();
  });
  function checkMatch(){
    var m=document.getElementById('match');
    if(!rep.value){m.textContent='';return;}
    if(rep.value===newI.value){m.textContent='✓ رمزها مطابقت دارند';m.style.color='var(--green)';}
    else{m.textContent='✕ رمزها مطابقت ندارند';m.style.color='var(--red)';}
  }
  rep.addEventListener('input',checkMatch);
})();
</script>
@endpush
