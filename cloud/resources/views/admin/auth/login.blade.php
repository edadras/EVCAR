<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SafirCharge — ورود مدیریت</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/admin/app.css">
    <link rel="stylesheet" href="/assets/admin/login.css">
</head>
<body>
@php use App\Support\Persian; @endphp
<div class="login-wrap">
  <div class="login-brand">
    <div class="lb-top">
      <div class="brand-mark" style="width:46px;height:46px;border-radius:14px"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 3 14h7l-1 8 10-12h-7l1-8z"/></svg></div>
      <div><div style="font-weight:800;font-size:20px;color:#fff">SafirCharge</div><div style="color:rgba(255,255,255,.7);font-size:13px">سامانهٔ مدیریت شارژ خودرو برقی</div></div>
    </div>
    <div class="lb-mid">
      <h1 style="color:#fff;font-size:30px;line-height:1.5;font-weight:800">مرکز فرماندهی<br>شبکهٔ شارژ شما</h1>
      <p style="color:rgba(255,255,255,.78);font-size:15px;line-height:2;margin-top:14px">پایش زندهٔ شارژرها، مدیریت مالی و تسویه، تعرفه، کاربران و دسترسی — همه در یک پلتفرم.</p>
      <div class="lb-stats">
        <div><div class="lbs-v">{{ Persian::digits($chargers_total ?? 0) }}</div><div class="lbs-l">شارژر شبکه</div></div>
        <div><div class="lbs-v">{{ Persian::digits($cities_count ?? 0) }} شهر</div><div class="lbs-l">پوشش</div></div>
        <div><div class="lbs-v">{{ Persian::digits($charger_uptime ?? 0) }}٪</div><div class="lbs-l">آپ‌تایم</div></div>
      </div>
    </div>
    <div class="lb-foot">© {{ now()->year }} SafirCharge — همهٔ حقوق محفوظ است</div>
  </div>

  <div class="login-form">
    <div class="lf-inner">
      <h2 style="font-size:24px">ورود به پنل مدیریت</h2>
      <p class="muted" style="margin:6px 0 26px">با حساب کاربری مدیر سامانه وارد شوید.</p>

      @if($errors->any())
        <div style="margin-bottom:16px;padding:12px;border-radius:12px;background:var(--red-soft);color:var(--red);font-size:13px">{{ $errors->first() }}</div>
      @endif

      <form method="POST" action="{{ route('admin.login') }}">
        @csrf
        <div class="ffield" style="margin-bottom:16px"><label>نام کاربری / ایمیل سازمانی</label><input class="finput" name="email" dir="ltr" style="text-align:right" value="{{ old('email', 'admin@safircharge.ir') }}" required></div>
        <div class="ffield" style="margin-bottom:10px"><label>رمز عبور</label><input class="finput" name="password" type="password" required></div>
        <div class="flex between items-center" style="margin-bottom:22px">
          <label class="rmbr"><input type="checkbox" name="remember" value="1"> مرا به خاطر بسپار</label>
          <a href="#" class="fs13" style="color:var(--accent);font-weight:600">فراموشی رمز</a>
        </div>
        <button type="submit" class="btn btn-primary" style="height:50px;width:100%;font-size:15px">ورود به پنل مدیریت</button>
      </form>

      <div class="lf-div"><span>یا</span></div>
      <button type="button" class="btn" style="height:48px;width:100%" disabled title="فاز بعدی">ورود با رمز یک‌بارمصرف (OTP)</button>

      <div class="lf-help">دسترسی به این پنل ویژهٔ کارکنان مجاز است و با احراز هویت دومرحله‌ای محافظت می‌شود.</div>
      <div class="lf-alt">شریک یا مالک شارژر هستید؟ <a href="/partner/login" style="color:var(--accent);font-weight:700">ورود به پنل شرکا</a></div>
    </div>
  </div>
</div>
</body>
</html>
