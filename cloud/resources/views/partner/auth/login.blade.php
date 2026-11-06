<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QUAD EV — ورود شرکا</title>
    <link rel="icon" type="image/png" href="/assets/brand/quad-icon-256.png">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="@v('/assets/admin/app.css')">
    <link rel="stylesheet" href="@v('/assets/partner/login.css')">
</head>
<body>
<div class="login-wrap">
    <div class="login-brand">
        <div class="lb-top">
            <div><img class="brand-logo-lg" src="/assets/brand/quad-logo-white.png" alt="QUAD EV"><div style="color:rgba(255,255,255,.7);font-size:13px;margin-top:8px">QUAD EV · پنل شرکا و مالکان شارژر</div></div>
        </div>
        <div class="lb-mid">
            <h1 style="color:#fff;font-size:30px;line-height:1.5;font-weight:800">دستگاه‌های خود را<br>هوشمندانه مدیریت کنید</h1>
            <p style="color:rgba(255,255,255,.78);font-size:15px;line-height:2;margin-top:14px">وضعیت شارژرها، درآمد و تسویه — همه از دیتابیس زنده.</p>
        </div>
        <div class="lb-foot">© {{ now()->year }} QUAD EV</div>
    </div>
    <div class="login-form">
        <div class="lf-inner">
            <h2 style="font-size:24px">ورود به پنل شرکا</h2>
            <p class="muted" style="margin:6px 0 26px">ایمیل یا کد شریک (مثلاً PTR-01)</p>
            @if($errors->any())
                <div style="margin-bottom:16px;padding:12px;background:var(--red-soft);border-radius:12px;color:var(--red);font-size:13px">{{ $errors->first() }}</div>
            @endif
            <form method="POST" action="{{ route('partner.login') }}">
                @csrf
                <div class="ffield" style="margin-bottom:16px">
                    <label>نام کاربری / کد شریک</label>
                    <input class="finput" name="login" value="{{ old('login', 'partner@safircharge.ir') }}" required dir="ltr" style="text-align:right">
                </div>
                <div class="ffield" style="margin-bottom:22px">
                    <label>رمز عبور</label>
                    <input class="finput" name="password" type="password" required>
                </div>
                <label class="rmbr" style="margin-bottom:18px;display:flex"><input type="checkbox" name="remember" value="1" checked> مرا به خاطر بسپار</label>
                <button type="submit" class="btn btn-primary" style="height:50px;width:100%;border:none">ورود به پنل</button>
            </form>
            <div class="lf-help" style="margin-top:24px">حساب نمونه: <b dir="ltr">partner@safircharge.ir</b> / Partner@123456</div>
            <div class="lf-alt" style="margin-top:16px;text-align:center"><a href="/admin/login" style="color:var(--accent);font-weight:700">ورود پنل مدیریت</a></div>
        </div>
    </div>
</div>
</body>
</html>
