<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ورود — پنل ایستگاه SafirCharge</title>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/panel/panel.css">
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <h1>پنل ایستگاه</h1>
        <p class="muted fs13">ورود مسئول فنی / اپراتور — بدون وابستگی به مرکز ابری</p>
        @if($errors->any())
        <div class="alert alert-error">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ route('panel.login.submit') }}">
            @csrf
            <div class="field">
                <label>ایمیل</label>
                <input type="text" name="email" value="{{ old('email') }}" required autocomplete="username">
            </div>
            <div class="field">
                <label>رمز عبور</label>
                <input type="password" name="password" required autocomplete="current-password">
            </div>
            <label class="fs12 muted" style="display:flex;align-items:center;gap:8px;margin-bottom:16px">
                <input type="checkbox" name="remember" value="1"> مرا به خاطر بسپار
            </label>
            <button type="submit" class="btn btn-primary" style="width:100%">ورود به پنل</button>
        </form>
    </div>
</div>
</body>
</html>
