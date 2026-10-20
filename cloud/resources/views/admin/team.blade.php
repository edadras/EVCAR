@extends('admin.layouts.app', ['page' => 'team', 'title' => 'کاربران مدیر و دسترسی', 'crumb' => 'سامانه و ارتباطات'])

@section('content')
@php use App\Support\Persian; @endphp
<div class="flex between items-center mt-8" style="margin-bottom:18px">
      <div class="tabs" id="tabs">
        <button class="active" data-tab="team">کاربران مدیر</button>
        <button data-tab="roles">نقش‌ها و دسترسی</button>
        <button data-tab="audit">گزارش فعالیت</button>
      </div>
      <button class="btn btn-sm btn-primary" id="addBtn"><span class="bic" data-ic="plus"></span> افزودن کاربر مدیر</button>
    </div>

    <div class="kpi-grid">
      <div class="mini-stat"><div class="ms-ic ic-blue" data-ic="shield"></div><div><div class="ms-v tnum">{{ Persian::num($staff->count()) }}</div><div class="ms-l">کاربر مدیر فعال</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-violet" data-ic="users"></div><div><div class="ms-v tnum">{{ Persian::num($staff->pluck('role')->unique()->count()) }}</div><div class="ms-l">نقش تعریف‌شده</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-green" data-ic="check"></div><div><div class="ms-v tnum">{{ Persian::num($staff->where('status', 'active')->count()) }}</div><div class="ms-l">فعال</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-orange" data-ic="eye"></div><div><div class="ms-v tnum">{{ Persian::num($staff->whereNotNull('last_login_at')->count()) }}</div><div class="ms-l">با سابقهٔ ورود</div></div></div>
    </div>

    <!-- کاربران مدیر -->
    <section data-pane="team">
      <div class="card mt-16">
        <div class="card-head">
          <div><h3>کاربران مدیر سامانه</h3><div class="ch-sub">کارکنانی که به پنل مدیریت دسترسی دارند</div></div>
          <div class="head-actions"><div class="seg" id="roleSeg"><button class="active">همه</button><button>مدیر کل</button><button>مالی</button><button>اپراتور</button></div></div>
        </div>
        <div class="table-wrap">
          <table class="tbl">
            <thead><tr><th>کاربر</th><th>نقش</th><th>دامنهٔ دسترسی</th><th>احراز دومرحله‌ای</th><th>آخرین ورود</th><th>وضعیت</th><th></th></tr></thead>
            <tbody id="teamBody">
              @foreach($staff as $member)
                @php
                  $rk = $member->role->value;
                  $rg = match ($member->role) {
                      \App\Enums\UserRole::SuperAdmin => 'admin',
                      \App\Enums\UserRole::FinancialManager => 'finance',
                      \App\Enums\UserRole::StationOperator, \App\Enums\UserRole::Support => 'operator',
                      default => 'other',
                  };
                  $colors = ['#0E6BA8','#16A34A','#F97316','#7C3AED','#0891B2','#DC2626','#D97706'];
                  $c = $colors[crc32($member->email ?? $member->name) % count($colors)];
                  $init = collect(explode(' ', $member->name))->map(fn($w) => mb_substr($w, 0, 1))->join('.');
                  $online = $member->status === 'active';
                @endphp
                <tr data-rk="{{ $rk }}" data-rg="{{ $rg }}">
                  <td><div class="cell-user"><span class="uavatar" style="background:{{ $c }}">{{ $init }}</span><span class="cell-strong">{{ $member->name }}</span></div></td>
                  <td><span class="pill pill-accent">{{ $member->role->label() }}</span></td>
                  <td class="cell-muted">{{ $member->email ?? $member->phone ?? '—' }}</td>
                  <td><span class="pill pill-green"><span class="pdot"></span>فعال</span></td>
                  <td class="cell-muted">{{ $member->last_login_at ? $member->last_login_at->diffForHumans() : '—' }}</td>
                  <td><span class="pill {{ $online ? 'pill-green' : 'pill' }}">@if($online)<span class="pdot"></span>@endif{{ $online ? 'آنلاین' : 'غیرفعال' }}</span></td>
                  <td><button class="btn btn-sm" type="button">ویرایش</button></td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- نقش‌ها و ماتریس دسترسی -->
    <section data-pane="roles" style="display:none">
      <div class="grid col-1-2 mt-16">
        <!-- لیست نقش‌ها -->
        <div class="card" style="align-self:start">
          <div class="card-head"><div><h3>نقش‌ها</h3></div><div class="head-actions"><button class="btn btn-sm" id="addRoleBtn"><span class="bic" data-ic="plus"></span> نقش</button></div></div>
          <div id="roleList"></div>
        </div>

        <!-- ماتریس دسترسی -->
        <div class="card">
          <div class="card-head">
            <div><h3>دسترسی نقش: <span id="curRole" style="color:var(--accent)">مدیر مالی</span></h3><div class="ch-sub">سطح دسترسی به هر بخش سامانه را تعیین کنید</div></div>
            <div class="head-actions"><button class="btn btn-sm btn-primary">ذخیرهٔ دسترسی‌ها</button></div>
          </div>
          <div class="card-pad" style="padding-top:8px">
            <div class="perm-head">
              <span>بخش سامانه</span>
              <span class="ph-levels"><span>بدون</span><span>مشاهده</span><span>ویرایش</span><span>کامل</span></span>
            </div>
            <div id="permRows"></div>
          </div>
        </div>
      </div>
    </section>

    <!-- گزارش فعالیت -->
    <section data-pane="audit" style="display:none">
      <div class="card mt-16">
        <div class="card-head"><div><h3>گزارش فعالیت کاربران مدیر</h3><div class="ch-sub">ثبت رخدادهای حساس برای حسابرسی</div></div>
          <div class="head-actions"><button class="btn btn-sm"><span class="bic" data-ic="download"></span> خروجی</button></div>
        </div>
        <div class="card-pad" style="padding-top:6px" id="auditList"></div>
      </div>
    </section>
@endsection

@push('styles')
<style>
.bic{display:inline-grid;width:16px;height:16px}.bic svg{width:16px;height:16px}
.mini-stat{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:16px;display:flex;align-items:center;gap:13px;box-shadow:var(--shadow-sm)}
.ms-ic{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;flex:none}.ms-ic svg{width:22px;height:22px}
.ms-v{font-size:22px;font-weight:800}.ms-l{font-size:12px;color:var(--muted)}

.role-item{display:flex;align-items:center;gap:12px;padding:13px 18px;border-bottom:1px solid var(--border-2);cursor:pointer;transition:background .12s}
.role-item:last-child{border-bottom:none}
.role-item:hover{background:var(--surface-2)}
.role-item.active{background:var(--accent-softer);box-shadow:inset 3px 0 0 var(--accent)}
.role-ic{width:38px;height:38px;border-radius:11px;display:grid;place-items:center;flex:none}.role-ic svg{width:19px;height:19px}
.role-n{font-weight:700;font-size:13.5px}.role-c{font-size:11.5px;color:var(--muted)}

.perm-head{display:flex;align-items:center;justify-content:space-between;padding:0 4px 10px;border-bottom:1px solid var(--border);font-size:11.5px;color:var(--muted);font-weight:700}
.ph-levels{display:grid;grid-template-columns:repeat(4,62px);gap:4px;text-align:center}
.perm-row{display:flex;align-items:center;justify-content:space-between;padding:11px 4px;border-bottom:1px solid var(--border-2)}
.perm-row:last-child{border-bottom:none}
.perm-name{display:flex;align-items:center;gap:10px;font-size:13px;font-weight:600}
.perm-name .pn-ic{width:32px;height:32px;border-radius:9px;background:var(--surface-3);color:var(--text-2);display:grid;place-items:center}.pn-ic svg{width:16px;height:16px}
.lvl-group{display:grid;grid-template-columns:repeat(4,62px);gap:4px}
.lvl{height:30px;border:1px solid var(--border);border-radius:8px;background:var(--surface);cursor:pointer;display:grid;place-items:center;font-size:11px;color:var(--muted);font-weight:700;transition:all .12s}
.lvl.on{background:var(--accent);border-color:var(--accent);color:#fff}
.lvl.on.lvl-none{background:var(--surface-3);border-color:var(--border);color:var(--text-2)}

.audit-row{display:flex;align-items:center;gap:13px;padding:12px 0;border-bottom:1px solid var(--border-2)}
.audit-row:last-child{border-bottom:none}
.audit-ic{width:36px;height:36px;border-radius:10px;display:grid;place-items:center;flex:none}.audit-ic svg{width:17px;height:17px}
.audit-time{margin-inline-start:auto;font-size:11.5px;color:var(--muted)}

.modal-overlay{position:fixed;inset:0;background:rgba(8,16,24,.55);backdrop-filter:blur(3px);display:none;align-items:center;justify-content:center;z-index:100;padding:20px}
.modal-overlay.open{display:flex}
.modal{background:var(--surface);border-radius:20px;box-shadow:var(--shadow-lg);width:100%;max-width:580px;max-height:90vh;overflow:auto;animation:rise .3s both}
.modal-head{display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--border-2)}
.modal-head h3{font-size:16px}.modal-head svg{width:18px;height:18px}
.modal-body{padding:22px}
.modal-foot{display:flex;justify-content:flex-end;gap:10px;padding:16px 22px;border-top:1px solid var(--border-2)}
.toggle-row{display:flex;align-items:center;justify-content:space-between;gap:16px;background:var(--surface-2);border:1px solid var(--border-2);border-radius:12px;padding:13px 15px;cursor:pointer}
.switch{width:44px;height:25px;border-radius:20px;background:var(--surface-3);position:relative;flex:none;transition:background .15s}
.switch i{position:absolute;top:3px;inset-inline-start:3px;width:19px;height:19px;border-radius:50%;background:#fff;box-shadow:var(--shadow-sm);transition:transform .16s}
.switch.on{background:var(--green)}
html[dir=rtl] .switch.on i{transform:translateX(-19px)}
</style>
@endpush

@push('scripts')
@verbatim
<!-- مودال افزودن کاربر مدیر -->
<div class="modal-overlay" id="modal">
  <div class="modal">
    <div class="modal-head">
      <h3>افزودن کاربر مدیر</h3>
      <button class="icon-btn" id="closeModal" style="width:34px;height:34px"><span data-ic="x"></span></button>
    </div>
    <div class="modal-body">
      <div class="form-grid">
        <div class="ffield"><label>نام و نام خانوادگی <span class="req">*</span></label><input class="finput" id="stName" placeholder="مثلاً نگار صادقی"></div>
        <div class="ffield"><label>شمارهٔ موبایل <span class="req">*</span></label><input class="finput tnum" dir="ltr" style="text-align:right" id="stPhone" placeholder="۰۹۱۲ ۰۰۰ ۰۰۰۰"></div>
        <div class="ffield"><label>ایمیل سازمانی</label><input class="finput" dir="ltr" style="text-align:right" id="stEmail" placeholder="name@safircharge.ir"></div>
        <div class="ffield"><label>نقش <span class="req">*</span></label><select class="fselect" id="stRole"><option value="super_admin">مدیر کل</option><option value="financial_manager">مدیر مالی</option><option value="station_operator" selected>اپراتور ایستگاه</option><option value="support">پشتیبانی</option><option value="corporate_manager">مدیر سازمان</option></select></div>
        <div class="ffield col-span"><label>دامنهٔ دسترسی (ایستگاه‌ها)</label><select class="fselect"><option>همهٔ ایستگاه‌ها</option><option>فقط تهران</option><option>ایستگاه‌های منتخب</option><option>ایستگاه‌های یک اپراتور</option></select></div>
      </div>
      <label class="toggle-row mt-8"><div><div class="fw7 fs13">الزام احراز هویت دومرحله‌ای</div><div class="fhint">ارسال کد یک‌بارمصرف هنگام ورود</div></div><span class="switch on" data-sw><i></i></span></label>
    </div>
    <div class="modal-foot">
      <button class="btn" id="cancelModal">انصراف</button>
      <button type="button" class="btn btn-primary" id="saveStaff"><span class="bic" data-ic="check"></span> ایجاد و ارسال دعوت</button>
    </div>
  </div>
</div>

<!-- مودال ساخت نقش جدید -->
<div class="modal-overlay" id="roleModal">
  <div class="modal" style="max-width:640px">
    <div class="modal-head">
      <h3>ساخت نقش جدید</h3>
      <button class="icon-btn" id="closeRole" style="width:34px;height:34px"><span data-ic="x"></span></button>
    </div>
    <div class="modal-body">
      <div class="form-grid">
        <div class="ffield"><label>نام نقش <span class="req">*</span></label><input class="finput" id="rmName" placeholder="مثلاً مدیر منطقه"></div>
        <div class="ffield"><label>کپی از نقش (اختیاری)</label><select class="fselect" id="rmBase"><option value="blank">شروع از صفر</option><option value="viewer">ناظر (فقط مشاهده)</option><option value="operator">اپراتور ایستگاه</option><option value="finance">مدیر مالی</option><option value="admin">مدیر کل</option></select></div>
        <div class="ffield col-span"><label>توضیح</label><input class="finput" id="rmDesc" placeholder="شرح کوتاهی از وظایف این نقش"></div>
      </div>
      <div class="sec-title" style="font-size:14px;margin:18px 0 10px">دسترسی اولیه به بخش‌ها</div>
      <div class="perm-head"><span>بخش سامانه</span><span class="ph-levels"><span>بدون</span><span>مشاهده</span><span>ویرایش</span><span>کامل</span></span></div>
      <div id="rmPerms"></div>
    </div>
    <div class="modal-foot">
      <button class="btn" id="cancelRole">انصراف</button>
      <button class="btn btn-primary"><span class="bic" data-ic="check"></span> ذخیرهٔ نقش</button>
    </div>
  </div>
</div>



<script>
(function(){
  document.querySelectorAll('[data-ic]').forEach(function(el){el.innerHTML=window.icon(el.getAttribute('data-ic'));});
  function init(s){return s.split(' ').map(function(w){return w[0];}).join('.');}

  var roles=[
    {n:'مدیر کل',c:'دسترسی کامل به همهٔ بخش‌ها',ic:'shield',col:'ic-blue',cnt:'۳'},
    {n:'مدیر مالی',c:'مالی، فاکتور، تسویه، شرکا',ic:'chart',col:'ic-green',cnt:'۶',active:true},
    {n:'اپراتور ایستگاه',c:'مدیریت و کنترل شارژرها',ic:'station',col:'ic-orange',cnt:'۴۸'},
    {n:'پشتیبانی',c:'خطاها، تیکت‌ها، کنترل از راه دور',ic:'headset',col:'ic-violet',cnt:'۲۲'},
    {n:'مدیر سازمان',c:'مدیریت ناوگان و کارت سازمان',ic:'building',col:'ic-blue',cnt:'۸۴'},
    {n:'ناظر (فقط مشاهده)',c:'دسترسی خواندنی به گزارش‌ها',ic:'eye',col:'ic-green',cnt:'۵'},
  ];
  document.getElementById('roleList').innerHTML=roles.map(function(r,i){
    return '<div class="role-item'+(r.active?' active':'')+'" data-i="'+i+'">'+
      '<div class="role-ic '+r.col+'">'+window.icon(r.ic)+'</div>'+
      '<div style="flex:1"><div class="role-n">'+r.n+'</div><div class="role-c">'+r.c+'</div></div>'+
      '<span class="tag">'+r.cnt+' کاربر</span></div>';
  }).join('');

  var modules=[
    {n:'داشبورد و مانیتورینگ',ic:'grid',lvl:1},
    {n:'ایستگاه‌ها و نقشه',ic:'map',lvl:1},
    {n:'جلسات شارژ',ic:'bolt',lvl:1},
    {n:'مالی و تسویه',ic:'chart',lvl:3},
    {n:'فاکتور شرکا',ic:'receipt',lvl:3},
    {n:'تعرفه‌ها و RFID',ic:'sliders',lvl:2},
    {n:'کاربران و سازمان‌ها',ic:'users',lvl:1},
    {n:'پشتیبانی و خطاها',ic:'headset',lvl:0},
    {n:'کاربران مدیر و دسترسی',ic:'shield',lvl:0},
  ];
  var lvlNames=['—','مشاهده','ویرایش','کامل'];
  function renderPerms(){
    document.getElementById('permRows').innerHTML=modules.map(function(m,mi){
      var cells='';for(var l=0;l<4;l++){cells+='<button class="lvl'+(m.lvl===l?' on':'')+(l===0?' lvl-none':'')+'" data-m="'+mi+'" data-l="'+l+'">'+(l===0?'✕':lvlNames[l])+'</button>';}
      return '<div class="perm-row"><div class="perm-name"><span class="pn-ic">'+window.icon(m.ic)+'</span>'+m.n+'</div><div class="lvl-group">'+cells+'</div></div>';
    }).join('');
    document.querySelectorAll('.lvl').forEach(function(b){
      b.addEventListener('click',function(){
        var mi=+b.getAttribute('data-m'),l=+b.getAttribute('data-l');modules[mi].lvl=l;renderPerms();
      });
    });
  }
  renderPerms();
  document.querySelectorAll('.role-item').forEach(function(it){
    it.addEventListener('click',function(){
      document.querySelectorAll('.role-item').forEach(function(x){x.classList.remove('active');});it.classList.add('active');
      document.getElementById('curRole').textContent=roles[+it.getAttribute('data-i')].n;
    });
  });

  var audit=@json($auditRows ?? []);
  document.getElementById('auditList').innerHTML=audit.length?audit.map(function(a){
    var color=a.color==='hist-warn'?'ic-orange':(a.color==='hist-ok'?'ic-green':'ic-blue');
    return '<div class="audit-row"><div class="audit-ic '+color+'">'+window.icon(a.icon)+'</div>'+
      '<div class="l-main"><div class="l-title">'+a.title+'</div><div class="l-sub">'+a.detail+'</div></div>'+
      '<span class="audit-time">'+a.time+'</span></div>';
  }).join(''):'<p class="muted fs13" style="padding:16px 0">هنوز رویدادی ثبت نشده است.</p>';

  document.querySelectorAll('#tabs button').forEach(function(b){
    b.addEventListener('click',function(){
      document.querySelectorAll('#tabs button').forEach(function(x){x.classList.remove('active');});b.classList.add('active');
      var t=b.getAttribute('data-tab');
      document.querySelectorAll('[data-pane]').forEach(function(s){s.style.display=s.getAttribute('data-pane')===t?'':'none';});
    });
  });
  document.querySelectorAll('#roleSeg button').forEach(function(b,idx){
    var keys=['all','admin','finance','operator'];
    b.addEventListener('click',function(){
      document.querySelectorAll('#roleSeg button').forEach(function(x){x.classList.remove('active');});b.classList.add('active');
      document.querySelectorAll('#teamBody tr').forEach(function(tr){
        var rg=tr.getAttribute('data-rg');tr.style.display=(idx===0||rg===keys[idx])?'':'none';
      });
    });
  });

  var modal=document.getElementById('modal');
  document.getElementById('addBtn').addEventListener('click',function(){modal.classList.add('open');});
  document.getElementById('closeModal').addEventListener('click',function(){modal.classList.remove('open');});
  document.getElementById('cancelModal').addEventListener('click',function(){modal.classList.remove('open');});
  modal.addEventListener('click',function(e){if(e.target===modal)modal.classList.remove('open');});
  document.querySelectorAll('[data-sw]').forEach(function(s){s.closest('.toggle-row').addEventListener('click',function(){s.classList.toggle('on');});});
  document.getElementById('saveStaff').addEventListener('click',function(){
    if(!window.AdminUI)return;
    var name=document.getElementById('stName').value.trim();
    var phone=document.getElementById('stPhone').value.trim();
    if(!name||!phone){alert('نام و موبایل الزامی است.');return;}
    window.AdminUI.postJson(window.AdminUI.apiBase()+'/api/staff',{
      name:name,phone:phone,email:document.getElementById('stEmail').value.trim()||null,
      role:document.getElementById('stRole').value
    }).then(function(res){alert(res.message||'ثبت شد');modal.classList.remove('open');location.reload();})
      .catch(function(e){alert((e&&e.message)||'خطا در ایجاد کاربر');});
  });

  // مودال ساخت نقش
  var presets={blank:[0,0,0,0,0,0,0,0,0],viewer:[1,1,1,1,1,1,1,0,0],operator:[2,3,3,0,0,1,1,2,0],finance:[1,1,1,3,3,2,1,0,0],admin:[3,3,3,3,3,3,3,3,3]};
  var rmLvls=presets.blank.slice();
  var rmMods=['داشبورد','ایستگاه‌ها','جلسات شارژ','مالی و تسویه','فاکتور شرکا','تعرفه و RFID','کاربران','پشتیبانی','کاربران مدیر'];
  function renderRM(){
    document.getElementById('rmPerms').innerHTML=rmMods.map(function(m,mi){
      var cells='';for(var l=0;l<4;l++){cells+='<button class="lvl'+(rmLvls[mi]===l?' on':'')+(l===0?' lvl-none':'')+'" data-m="'+mi+'" data-l="'+l+'">'+(l===0?'✕':lvlNames[l])+'</button>';}
      return '<div class="perm-row"><div class="perm-name">'+m+'</div><div class="lvl-group">'+cells+'</div></div>';
    }).join('');
    document.querySelectorAll('#rmPerms .lvl').forEach(function(b){b.addEventListener('click',function(){rmLvls[+b.getAttribute('data-m')]=+b.getAttribute('data-l');renderRM();});});
  }
  renderRM();
  document.getElementById('rmBase').addEventListener('change',function(){rmLvls=(presets[this.value]||presets.blank).slice();renderRM();});
  var roleModal=document.getElementById('roleModal');
  document.getElementById('addRoleBtn').addEventListener('click',function(){roleModal.classList.add('open');});
  document.getElementById('closeRole').addEventListener('click',function(){roleModal.classList.remove('open');});
  document.getElementById('cancelRole').addEventListener('click',function(){roleModal.classList.remove('open');});
  roleModal.addEventListener('click',function(e){if(e.target===roleModal)roleModal.classList.remove('open');});
})();
</script>
@endverbatim
@endpush
