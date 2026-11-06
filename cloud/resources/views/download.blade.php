@php
    use App\Support\Persian;
    $ic = [
        'android' => '<path fill="currentColor" stroke="none" d="M17.6 9.48l1.84-3.18c.16-.31.04-.69-.26-.85a.64.64 0 0 0-.83.22l-1.88 3.24a11.43 11.43 0 0 0-8.94 0L5.65 5.67a.64.64 0 0 0-.87-.2c-.28.18-.37.54-.22.83L6.4 9.48A10.81 10.81 0 0 0 1 18h22a10.81 10.81 0 0 0-5.4-8.52zM7 15.25a1.25 1.25 0 1 1 0-2.5 1.25 1.25 0 0 1 0 2.5zm10 0a1.25 1.25 0 1 1 0-2.5 1.25 1.25 0 0 1 0 2.5z"/>',
        'apple' => '<path fill="currentColor" stroke="none" d="M16.37 12.6c-.02-2.3 1.88-3.4 1.96-3.46-1.07-1.56-2.73-1.77-3.32-1.8-1.41-.14-2.76.83-3.48.83-.72 0-1.82-.81-3-.79-1.54.02-2.97.9-3.76 2.28-1.6 2.78-.41 6.9 1.15 9.16.76 1.1 1.67 2.34 2.86 2.3 1.15-.05 1.58-.74 2.97-.74 1.38 0 1.77.74 2.98.72 1.23-.02 2.01-1.12 2.76-2.23.87-1.28 1.23-2.52 1.25-2.58-.03-.01-2.4-.92-2.42-3.65zM14.1 5.86c.63-.77 1.06-1.83.94-2.89-.91.04-2.01.6-2.66 1.37-.58.67-1.09 1.76-.95 2.79 1.01.08 2.04-.51 2.67-1.27z"/>',
        'bolt' => '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'phone' => '<rect x="6" y="2" width="12" height="20" rx="3"/><path d="M11 18h2"/>',
    ];
    $svg = fn ($k) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$ic[$k].'</svg>';
    $main = $builds['arm64'] ?? null;
    $alt = $builds['armv7'] ?? null;
@endphp
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>دانلود اپ QUAD EV — اندروید و آیفون</title>
    <meta name="description" content="دانلود مستقیم اپلیکیشن QUAD EV برای اندروید و راهنمای نصب وب‌اپ روی آیفون.">
    <meta name="theme-color" content="#061629">
    <meta property="og:title" content="دانلود اپ QUAD EV">
    <meta property="og:image" content="{{ url('/assets/landing/app-icon.png') }}">
    <link rel="icon" type="image/png" href="/assets/brand/quad-icon-256.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="@v('/assets/landing/landing.css')">
</head>
<body data-platform="{{ $platform }}">

<header class="nav" id="nav">
    <div class="wrap">
        <a href="/" class="nav-logo"><img src="/assets/brand/quad-logo-white.png" alt="QUAD EV"></a>
        <nav class="nav-links">
            <a href="/">صفحهٔ اصلی</a>
            <a href="#android">نصب اندروید</a>
            <a href="#ios">آیفون</a>
        </nav>
        <div class="nav-cta">
            <a href="/app/login" class="btn btn-primary">{!! $svg('bolt') !!} ورود به وب‌اپ</a>
        </div>
    </div>
</header>

<section class="dl-hero">
    <div id="sparks"></div>
    <div class="wrap split">
        <div class="rv rv-r">
            <div class="dl-app">
                <img src="/assets/landing/app-icon.png" alt="آیکون اپ QUAD EV">
                <div>
                    <h1>اپلیکیشن QUAD EV</h1>
                    <p>شارژ هوشمند خودروی برقی؛ نقشه، اسکن QR، کیف پول و مسیریاب.</p>
                </div>
            </div>
            <div class="dl-meta">
                <span>نسخهٔ {{ Persian::digits($version) }}</span>
                @if ($main)<span>حجم {{ $main['size'] }} مگابایت</span><span>به‌روزرسانی {{ $main['date'] }}</span>@endif
                <span>اندروید ۷ و بالاتر</span>
            </div>
            <div class="store-row">
                @if ($main)
                    <a href="{{ $main['url'] }}" class="store-badge android" id="dlAndroid" download>{!! $svg('android') !!}<span class="sb-t"><small>دانلود مستقیم</small><b>اپ اندروید</b></span></a>
                @endif
                <a href="#ios" class="store-badge ios">{!! $svg('apple') !!}<span class="sb-t"><small>نصب از سافاری</small><b>آیفون (iOS)</b></span></a>
            </div>
            @if ($alt)
                <span class="dl-alt">اپ روی گوشی شما نصب نشد؟ <a href="{{ $alt['url'] }}" download>نسخهٔ گوشی‌های قدیمی‌تر ({{ $alt['size'] }} مگابایت)</a></span>
            @endif
            <div class="dl-hint" id="dlHint">{!! $svg('check') !!}<span>دانلود شروع شد. پس از پایان، فایل را از اعلان‌ها باز کنید و «نصب» را بزنید.</span></div>
            <div class="dl-qr">
                <img src="/assets/landing/download-qr.png" alt="کد QR صفحهٔ دانلود">
                <div><b>با گوشی اسکن کنید</b><small>دوربین گوشی را روی این کد بگیرید تا صفحهٔ دانلود روی گوشی باز شود.</small></div>
            </div>
        </div>
        <div class="phones rv rv-z" id="phones">
            <div class="halo"></div>
            <div class="phone p3"><img src="/assets/landing/app-wallet.webp" alt="" loading="lazy"></div>
            <div class="phone p2"><img src="/assets/landing/app-charger.webp" alt="" loading="lazy"></div>
            <div class="phone p1"><img src="/assets/landing/app-home.webp" alt="پیشخان اپ QUAD EV"></div>
        </div>
    </div>
</section>

<section class="sec">
    <div class="wrap">
        <div class="sec-head rv">
            <span class="kicker">راهنمای نصب</span>
            <h2>در کمتر از یک دقیقه نصب کنید</h2>
            <p>اپ اندروید مستقیم از سرور QUAD دانلود می‌شود؛ روی آیفون، وب‌اپ را به صفحهٔ اصلی اضافه کنید.</p>
        </div>
        <div class="dl-guides">
            <div class="dl-card and rv" id="android">
                <h3><i>{!! $svg('android') !!}</i> اندروید</h3>
                <p>فایل نصبی (APK) رسمی QUAD EV، امضاشده و بدون تبلیغات.</p>
                <ol class="dl-steps">
                    <li><div>دکمهٔ «دانلود اپ اندروید» را بزنید<small>دانلود از سرور QUAD و بدون نیاز به گوگل‌پلی.</small></div></li>
                    <li><div>فایل دانلودشده را باز کنید<small>اگر پیام «نصب از منابع ناشناس» آمد، در تنظیمات به مرورگر اجازهٔ نصب بدهید.</small></div></li>
                    <li><div>«نصب» را بزنید و وارد شوید<small>با همان شمارهٔ موبایل وب‌اپ؛ کیف پول و سوابق شما منتقل می‌شود.</small></div></li>
                </ol>
                @if ($main)
                    <a href="{{ $main['url'] }}" class="store-badge android" download>{!! $svg('android') !!}<span class="sb-t"><small>نسخهٔ {{ Persian::digits($version) }} · {{ $main['size'] }} مگابایت</small><b>دانلود اپ اندروید</b></span></a>
                @endif
            </div>
            <div class="dl-card ios rv" id="ios">
                <h3><i>{!! $svg('apple') !!}</i> آیفون و آیپد</h3>
                <p>نسخهٔ App Store به‌زودی منتشر می‌شود. تا آن زمان وب‌اپ QUAD EV دقیقاً مثل یک اپ نصب و اجرا می‌شود.</p>
                <ol class="dl-steps">
                    <li><div>این صفحه را در <b>Safari</b> باز کنید<small>نصب روی صفحهٔ اصلی فقط از سافاری ممکن است.</small></div></li>
                    <li><div>وارد وب‌اپ شوید و دکمهٔ اشتراک‌گذاری را بزنید<small>آیکون مربع با فلش رو به بالا، پایین صفحه.</small></div></li>
                    <li><div>گزینهٔ «Add to Home Screen» را انتخاب کنید<small>آیکون QUAD EV روی صفحهٔ اصلی گوشی اضافه می‌شود.</small></div></li>
                </ol>
                <a href="/app/login" class="store-badge light ios">{!! $svg('apple') !!}<span class="sb-t"><small>اجرا در سافاری</small><b>باز کردن وب‌اپ</b></span></a>
            </div>
        </div>
    </div>
</section>

<footer>
    <div class="wrap">
        <div class="foot-bot"><span>© {{ Persian::dateTime(now(), 'yyyy') }} QUAD EV — همهٔ حقوق محفوظ است.</span><span><a href="/">بازگشت به صفحهٔ اصلی</a></span></div>
    </div>
</footer>

<script src="@v('/assets/landing/landing.js')"></script>
<script>
(function () {
    var b = document.getElementById('dlAndroid'), h = document.getElementById('dlHint');
    if (b && h) b.addEventListener('click', function () { h.classList.add('show'); });
    if (document.body.dataset.platform === 'ios' && location.hash === '') {
        var ios = document.getElementById('ios');
        if (ios) setTimeout(function () { ios.scrollIntoView({behavior: 'smooth', block: 'start'}); }, 900);
    }
})();
</script>
</body>
</html>
