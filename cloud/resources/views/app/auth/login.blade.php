<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>QUAD EV — ورود</title>
    <link rel="icon" type="image/png" href="/assets/brand/quad-icon-256.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="@v('/assets/mobile/app-mobile.css')">
    <link rel="stylesheet" href="@v('/assets/mobile/app-fx.css')">
    <link rel="preload" as="image" href="/assets/app-img/login-hero.webp">
</head>
<body data-no-tab>
<div class="phone login-phone">
    <div class="login-top">
        <div class="login-bg" aria-hidden="true">
            <img class="fx-kenburns" src="/assets/app-img/login-hero.webp" alt="">
            <div class="login-particles"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
        </div>
        <img class="logo-img" src="/assets/brand/quad-logo-white.png" alt="QUAD EV">
        <div class="logo-sub">شارژ هوشمند خودرو برقی</div>
        <div class="login-feats">
            <span><i data-ic="map"></i>نقشهٔ ایستگاه‌ها</span>
            <span><i data-ic="qr"></i>شارژ با QR</span>
            <span><i data-ic="nav"></i>مسیریاب برقی</span>
        </div>
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
            <div class="otp mt20" id="otpCells">
                <input type="text" id="authOtpValue" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" maxlength="6" aria-label="کد تأیید">
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
            <a href="{{ route('download') }}" class="get-app" id="getApp"><span data-ic="download"></span> دانلود اپ اندروید و نصب روی آیفون</a>
        </div>
    </div>
</div>

<style>
.login-phone{background:#071a2c;position:relative}
.login-bg{position:absolute;top:0;left:0;right:0;bottom:-34px;overflow:hidden;z-index:-1}
.login-bg img{width:100%;height:100%;object-fit:cover;object-position:50% 78%}
.login-bg::after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(5,20,38,.6) 0%,rgba(5,20,38,.15) 45%,rgba(5,20,38,0) 70%,rgba(5,20,38,.25) 100%)}
.login-particles{position:absolute;inset:0;z-index:1;pointer-events:none}
.login-particles i{position:absolute;bottom:-10px;width:6px;height:6px;border-radius:50%;background:#7cc8ff;box-shadow:0 0 10px 2px rgba(124,200,255,.8);opacity:0}
.login-particles i:nth-child(even){background:#ffb36b;box-shadow:0 0 10px 2px rgba(255,160,80,.8)}
.login-particles i:nth-child(1){left:12%}.login-particles i:nth-child(2){left:26%;width:4px;height:4px}.login-particles i:nth-child(3){left:41%}.login-particles i:nth-child(4){left:55%;width:4px;height:4px}
.login-particles i:nth-child(5){left:68%}.login-particles i:nth-child(6){left:79%;width:5px;height:5px}.login-particles i:nth-child(7){left:88%;width:3px;height:3px}.login-particles i:nth-child(8){left:34%;width:3px;height:3px}
.login-top{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;padding:12% 20px 20px;color:#fff;gap:6px;position:relative;z-index:1;isolation:isolate;text-shadow:0 2px 12px rgba(0,0,0,.35);min-height:300px}
.login-sheet{position:relative;z-index:2}
.login-feats{display:flex;gap:8px;flex-wrap:wrap;justify-content:center;margin-top:14px}
.login-feats span{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;padding:7px 12px;border-radius:30px;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.22);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px)}
.login-feats i{display:inline-grid;width:15px;height:15px}.login-feats svg{width:15px;height:15px}
@media (prefers-reduced-motion: no-preference){
  .logo-img{animation:fx-logo-in .9s cubic-bezier(.2,.7,.3,1) both, fx-float 6.5s ease-in-out .9s infinite}
  .logo-sub{animation:fx-up .7s .25s cubic-bezier(.2,.7,.3,1) both}
  .login-feats span{animation:fx-up .6s cubic-bezier(.2,.7,.3,1) both}
  .login-feats span:nth-child(1){animation-delay:.4s}.login-feats span:nth-child(2){animation-delay:.5s}.login-feats span:nth-child(3){animation-delay:.6s}
  .login-sheet{animation:fx-sheet-up .7s .15s cubic-bezier(.2,.8,.25,1) both}
  #stepPhone,#stepPassword,#stepOtp,#stepRegister{animation:fx-step .4s cubic-bezier(.2,.7,.3,1) both}
  .login-particles i{animation:fx-rise 7s linear infinite}
  .login-particles i:nth-child(2){animation-delay:1.2s;animation-duration:9s}.login-particles i:nth-child(3){animation-delay:2.6s}.login-particles i:nth-child(4){animation-delay:.6s;animation-duration:8s}
  .login-particles i:nth-child(5){animation-delay:3.4s}.login-particles i:nth-child(6){animation-delay:1.8s;animation-duration:10s}.login-particles i:nth-child(7){animation-delay:4.2s}.login-particles i:nth-child(8){animation-delay:5s;animation-duration:9s}
  .otp .cell.filled{animation:fx-pop .3s cubic-bezier(.3,1.6,.5,1)}
}
@keyframes fx-logo-in{from{opacity:0;transform:scale(.85) translateY(10px);filter:blur(6px)}}
@keyframes fx-up{from{opacity:0;transform:translateY(12px)}}
@keyframes fx-sheet-up{from{transform:translateY(100%)}}
@keyframes fx-step{from{opacity:0;transform:translateX(-18px)}}
@keyframes fx-rise{0%{transform:translateY(0);opacity:0}10%{opacity:.9}80%{opacity:.6}100%{transform:translateY(-75vh);opacity:0}}
.logo-mark{width:84px;height:84px;border-radius:26px;background:rgba(255,255,255,.16);display:grid;place-items:center;margin-bottom:10px;backdrop-filter:blur(6px)}
.logo-mark svg{width:44px;height:44px;fill:#fff;stroke:#fff}
.logo-img{display:block;width:min(220px,60vw);height:auto;margin-bottom:10px}
.logo-sub{font-size:14px;opacity:.9}
.login-sheet{background:var(--surface);border-radius:30px 30px 0 0;padding:28px 24px calc(34px + env(safe-area-inset-bottom,0));box-shadow:0 -10px 40px rgba(0,0,0,.18);flex:none}
.offline-note{display:flex;align-items:flex-start;gap:10px;background:var(--surface-2);border:1px solid var(--border-2);border-radius:14px;padding:13px;font-size:12.5px;color:var(--text-2);line-height:1.6}
.offline-note svg{width:20px;height:20px;color:var(--green);flex:none;margin-top:1px}
.txt-back{background:none;border:none;color:var(--accent);font-weight:600;font-size:13px;display:inline-flex;align-items:center;gap:4px;padding:0;cursor:pointer;font-family:inherit}
.txt-back svg{width:16px;height:16px}
.pill-blue{background:var(--accent-soft);color:var(--accent-strong)}
.get-app{display:inline-flex;align-items:center;gap:6px;margin-top:12px;padding:8px 14px;border-radius:30px;background:var(--accent-soft);color:var(--accent-strong);font-size:12.5px;font-weight:700;text-decoration:none}
.get-app svg{width:15px;height:15px}
@media (display-mode: standalone){.get-app{display:none}}
#otpCells{position:relative}
#authOtpValue{position:absolute;inset:0;width:100%;height:100%;opacity:0;font-size:16px;border:0;background:transparent;color:transparent;caret-color:transparent;z-index:2}
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
<script src="@v('/assets/mobile/app-mobile.js')"></script>
<script src="@v('/assets/mobile/app-fx.js')"></script>
<script src="@v('/assets/mobile/edge-router.js')"></script>
<script src="@v('/assets/mobile/auth-wizard.js')"></script>
</body>
</html>
