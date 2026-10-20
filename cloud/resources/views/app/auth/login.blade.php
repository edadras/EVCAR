<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>SafirCharge — ورود</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/mobile/app-mobile.css">
</head>
<body data-no-tab>
<div class="phone login-phone">
    <div class="login-top">
        <div class="logo-mark" data-ic="bolt"></div>
        <div class="logo-name">SafirCharge</div>
        <div class="logo-sub">شارژ هوشمند خودرو برقی</div>
    </div>

    <div class="login-sheet">
        <div class="row between" style="align-items:center;margin-bottom:4px">
            <h2 style="font-size:22px;margin:0">ورود / ثبت‌نام</h2>
            <span class="pill pill-blue" id="authBackendBadge">مرکز ابری</span>
        </div>

        @if($errors->any())
            <div class="offline-note mt12" style="border-color:var(--red);color:var(--red)">{{ $errors->first() }}</div>
        @endif

        {{-- مرحله ۱: شماره موبایل --}}
        <div id="stepPhone">
            <p class="muted fs13 mt8">شماره موبایل خود را وارد کنید. اگر ثبت‌نام کرده‌اید رمز می‌خواهیم؛ در غیر این صورت کد تأیید ارسال می‌شود.</p>
            <div class="field mt20">
                <label>شماره موبایل</label>
                <input class="input tnum" id="authPhone" inputmode="numeric" value="{{ old('phone') }}" dir="ltr" style="text-align:left" placeholder="۰۹۱۲۳۴۵۶۷۸۹" autocomplete="tel">
            </div>
            <p class="fs12 mt8" id="phoneMsg"></p>
            <button class="btn mt12" type="button" id="btnPhoneNext">ادامه</button>
            <div class="offline-note mt16">
                <span data-ic="shield"></span>
                <span>کد تأیید از <b>لبه ایستگاه</b> (WiFi) یا <b>مرکز ابری</b> بسته به اتصال شما ارسال می‌شود.</span>
            </div>
        </div>

        {{-- مرحله ۲: رمز عبور (کاربر موجود) --}}
        <div id="stepPassword" hidden>
            <button class="txt-back" type="button" id="backFromPwd"><span data-ic="back"></span> تغییر شماره</button>
            <h2 style="font-size:22px;margin-top:12px">رمز عبور</h2>
            <p class="muted fs13 mt8">رمز عبور حساب <b class="tnum" id="pwdPhoneLabel">—</b> را وارد کنید.</p>
            <div class="field mt20">
                <label>رمز عبور</label>
                <input class="input" id="authPassword" type="password" placeholder="رمز عبور" autocomplete="current-password">
            </div>
            <p class="fs12 mt8" id="pwdMsg"></p>
            <button class="btn mt12" type="button" id="btnPwdLogin">ورود</button>
        </div>

        {{-- مرحله ۳: OTP (ثبت‌نام) --}}
        <div id="stepOtp" hidden>
            <button class="txt-back" type="button" id="backFromOtp"><span data-ic="back"></span> تغییر شماره</button>
            <h2 style="font-size:22px;margin-top:12px">کد تأیید</h2>
            <p class="muted fs13 mt8">کد ۶ رقمی ارسال‌شده به <b class="tnum" id="otpPhoneLabel">—</b> را وارد کنید.</p>
            <input type="hidden" id="authOtpValue">
            <div class="otp mt20" id="otpCells">
                <div class="cell">_</div><div class="cell">_</div><div class="cell">_</div>
                <div class="cell">_</div><div class="cell">_</div><div class="cell">_</div>
            </div>
            <p class="fs11 muted mt10" id="otpTestHint" hidden></p>
            <div class="center-col mt16">
                <span class="fs13 muted">ارسال مجدد تا <b class="tnum" id="otpTimer" style="color:var(--accent)">۰۱:۰۰</b></span>
                <button type="button" class="btn btn-ghost mt8" id="btnOtpResend" style="height:40px;font-size:13px">ارسال مجدد کد</button>
            </div>
            <p class="fs12 mt8" id="otpMsg"></p>
            <button class="btn mt12" type="button" id="btnOtpVerify">تأیید کد</button>
        </div>

        {{-- مرحله ۴: تعیین رمز (ثبت‌نام) --}}
        <div id="stepRegister" hidden>
            <button class="txt-back" type="button" id="backFromReg"><span data-ic="back"></span> بازگشت</button>
            <h2 style="font-size:22px;margin-top:12px">تعیین رمز عبور</h2>
            <p class="muted fs13 mt8">برای <b class="tnum" id="regPhoneLabel">—</b> یک رمز عبور انتخاب کنید.</p>
            <div class="field mt20">
                <label>رمز عبور جدید</label>
                <input class="input" id="regPassword" type="password" placeholder="حداقل ۸ کاراکتر" autocomplete="new-password">
            </div>
            <div class="field mt16">
                <label>تکرار رمز عبور</label>
                <input class="input" id="regPasswordConfirm" type="password" placeholder="تکرار رمز" autocomplete="new-password">
            </div>
            <p class="fs12 mt8" id="regMsg"></p>
            <button class="btn mt12" type="button" id="btnRegister">ثبت‌نام و ورود</button>
        </div>

        <div class="center-col mt20">
            <span class="fs12 muted">با ورود، <a href="{{ route('app.terms') }}" style="color:var(--accent);font-weight:600;text-decoration:none">قوانین و مقررات</a> را می‌پذیرید.</span>
        </div>
    </div>
</div>

<style>
.login-phone{background:linear-gradient(165deg,var(--accent),color-mix(in srgb,var(--accent) 50%,var(--orange)))}
.login-top{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#fff;gap:6px}
.logo-mark{width:84px;height:84px;border-radius:26px;background:rgba(255,255,255,.16);display:grid;place-items:center;margin-bottom:10px;backdrop-filter:blur(6px)}
.logo-mark svg{width:44px;height:44px;fill:#fff;stroke:#fff}
.logo-name{font-size:30px;font-weight:800;letter-spacing:-.02em}
.logo-sub{font-size:14px;opacity:.9}
.login-sheet{background:var(--surface);border-radius:30px 30px 0 0;padding:28px 24px calc(34px + env(safe-area-inset-bottom,0));box-shadow:0 -10px 40px rgba(0,0,0,.18);flex:none}
.offline-note{display:flex;align-items:flex-start;gap:10px;background:var(--surface-2);border:1px solid var(--border-2);border-radius:14px;padding:13px;font-size:12.5px;color:var(--text-2);line-height:1.6}
.offline-note svg{width:20px;height:20px;color:var(--green);flex:none;margin-top:1px}
.txt-back{background:none;border:none;color:var(--accent);font-weight:600;font-size:13px;display:inline-flex;align-items:center;gap:4px;padding:0;cursor:pointer;font-family:inherit}
.txt-back svg{width:16px;height:16px}
.pill-blue{background:var(--accent-soft);color:var(--accent-strong)}
#authOtpValue{position:absolute;opacity:0;height:0;width:0;pointer-events:none}
</style>

<script>
window.SC_CONNECTIVITY = @json($scConnectivity ?? []);
window.SC_AUTH_API = {
    lookupCloud: @json(route('app.api.auth.lookup')),
    lookupEdge: '/api/v1/auth/lookup',
    otpRequestCloud: @json(route('app.api.auth.otp.request')),
    otpRequestEdge: '/api/v1/auth/otp/request',
    otpVerifyCloud: @json(route('app.api.auth.otp.verify')),
    otpVerifyEdge: '/api/v1/auth/otp/verify',
    loginCloud: @json(route('app.api.auth.login')),
    registerCloud: @json(route('app.api.auth.register')),
    homeUrl: @json(route('app.home')),
};
</script>
<script src="/assets/mobile/app-mobile.js"></script>
<script src="/assets/mobile/edge-router.js"></script>
<script src="/assets/mobile/auth-wizard.js"></script>
</body>
</html>
